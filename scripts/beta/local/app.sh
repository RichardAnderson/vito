#!/bin/bash
# =============================================================================
# Vito Application Setup
# Usage: ./app.sh --url=http://localhost:3000 --email=admin@example.com \
#                 --password=secret
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Application Setup Functions
# =============================================================================

setup_user_environment() {
    log "Setting up user environment..."

    # Add local bin to PATH for vito user (only if not already added)
    if ! grep -q "# Vito local binaries" "${VITO_HOME}/.bashrc" 2>/dev/null; then
        cat >> "${VITO_HOME}/.bashrc" <<EOF

# Vito local binaries
export PATH="${VITO_BIN}:\${PATH}"
export PATH="${VITO_LOCAL}/node/bin:\${PATH}"
EOF
    fi
}

install_dependencies() {
    log "Installing Composer dependencies..."

    chown -R vito:vito "${VITO_HOME}"
    su - vito -c "cd ${VITO_APP} && PATH=${VITO_BIN}:${VITO_LOCAL}/node/bin:\$PATH COMPOSER_ALLOW_SUPERUSER=1 ${VITO_BIN}/composer install --no-dev --optimize-autoloader"
}

configure_environment() {
    local app_url="$1"

    log "Configuring application environment..."

    cp "${VITO_APP}/.env.prod" "${VITO_APP}/.env"
    sed -i "s|^APP_URL=.*|APP_URL=${app_url}|" "${VITO_APP}/.env"
}

initialize_database() {
    log "Initializing database..."

    touch "${VITO_APP}/storage/database.sqlite"
    chown -R vito:vito "${VITO_APP}"

    su - vito -c "${VITO_BIN}/php ${VITO_APP}/artisan key:generate"
    su - vito -c "${VITO_BIN}/php ${VITO_APP}/artisan storage:link"
    su - vito -c "${VITO_BIN}/php ${VITO_APP}/artisan migrate --force"
}

create_admin_user() {
    local email="$1"
    local password="$2"

    log "Creating admin user..."

    # Create a temporary script to avoid password exposure in process list
    local tmp_script
    tmp_script=$(mktemp)
    chmod 700 "${tmp_script}"
    chown vito:vito "${tmp_script}"

    cat > "${tmp_script}" <<EOFSCRIPT
#!/bin/bash
${VITO_BIN}/php ${VITO_APP}/artisan user:create administrator '${email}' '${password}'
EOFSCRIPT

    su - vito -c "bash ${tmp_script}"
    local exit_code=$?

    # Securely remove the temp script
    rm -f "${tmp_script}"

    return ${exit_code}
}

generate_ssh_keys() {
    log "Generating SSH keys for the application..."

    openssl genpkey -algorithm RSA -out "${VITO_APP}/storage/ssh-private.pem"
    chmod 600 "${VITO_APP}/storage/ssh-private.pem"
    ssh-keygen -y -f "${VITO_APP}/storage/ssh-private.pem" > "${VITO_APP}/storage/ssh-public.key"
    chown vito:vito "${VITO_APP}/storage/ssh-private.pem" "${VITO_APP}/storage/ssh-public.key"
}

optimize_application() {
    log "Optimizing application..."

    su - vito -c "${VITO_BIN}/php ${VITO_APP}/artisan optimize"
}

setup_cron() {
    log "Setting up cron jobs..."

    # Remove existing vito schedule entry and add fresh one (prevents duplicates)
    (crontab -u vito -l 2>/dev/null | grep -v "artisan schedule:run" || true; echo "* * * * * ${VITO_BIN}/php ${VITO_APP}/artisan schedule:run >> /dev/null 2>&1") | crontab -u vito -
}

create_local_server() {
    local domain="$1"
    local port="$2"
    local enable_ssl="$3"

    log "Creating local server entry in Vito..."

    # Detect the server's primary IP address
    local server_ip
    server_ip=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}' || hostname -I | awk '{print $1}')

    if [[ -z "${server_ip}" ]]; then
        log_error "Could not detect server IP address, skipping local server creation"
        return 0
    fi

    # Build the list of open ports
    local ports="22,${port}"
    if [[ "${enable_ssl}" == "Y" ]]; then
        ports="${ports},80"
    fi

    # Determine if nginx is installed
    local nginx_installed="N"
    if [[ "${enable_ssl}" == "Y" ]]; then
        nginx_installed="Y"
    fi

    # Create the local server (user ID 1 is the admin we just created)
    su - vito -c "${VITO_BIN}/php ${VITO_APP}/artisan servers:create-local '${server_ip}' \
        --ports='${ports}' \
        --nginx='${nginx_installed}' \
        --name='localhost' \
        --user=1 \
        --domain='${domain}' \
        --web-port='${port}' \
        --ssl='${enable_ssl}'"

    log "Local server 'localhost' created with IP ${server_ip}"
}

# =============================================================================
# Main
# =============================================================================
main() {
    local app_url=""
    local admin_email=""
    local admin_password=""
    local password_env=""
    local domain="localhost"
    local port="3000"
    local enable_ssl="N"
    local create_server="N"

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --url=*)
                app_url="${1#*=}"
                shift
                ;;
            --url)
                app_url="$2"
                shift 2
                ;;
            --email=*)
                admin_email="${1#*=}"
                shift
                ;;
            --email)
                admin_email="$2"
                shift 2
                ;;
            --password=*)
                admin_password="${1#*=}"
                shift
                ;;
            --password)
                admin_password="$2"
                shift 2
                ;;
            --password-env=*)
                password_env="${1#*=}"
                shift
                ;;
            --password-env)
                password_env="$2"
                shift 2
                ;;
            --domain=*)
                domain="${1#*=}"
                shift
                ;;
            --domain)
                domain="$2"
                shift 2
                ;;
            --port=*)
                port="${1#*=}"
                shift
                ;;
            --port)
                port="$2"
                shift 2
                ;;
            --ssl)
                enable_ssl="Y"
                shift
                ;;
            --create-server)
                create_server="Y"
                shift
                ;;
            *)
                shift
                ;;
        esac
    done

    # If password-env is specified, read password from that environment variable
    if [[ -n "${password_env}" ]]; then
        admin_password="${!password_env}"
    fi

    # Validate required parameters
    if [[ -z "${app_url}" ]]; then
        log_error "App URL is required: --url=http://localhost:3000"
        exit 1
    fi

    if [[ -z "${admin_email}" ]]; then
        log_error "Admin email is required: --email=admin@example.com"
        exit 1
    fi

    if [[ -z "${admin_password}" ]]; then
        log_error "Admin password is required: --password=secret"
        exit 1
    fi

    log "Application setup"

    setup_user_environment
    install_dependencies
    configure_environment "${app_url}"
    initialize_database
    create_admin_user "${admin_email}" "${admin_password}"
    generate_ssh_keys
    optimize_application
    setup_cron

    if [[ "${create_server}" == "Y" ]]; then
        create_local_server "${domain}" "${port}" "${enable_ssl}"
    fi

    log_success "Application setup complete"
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
