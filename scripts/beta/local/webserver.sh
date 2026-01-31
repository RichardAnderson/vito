#!/bin/bash
# =============================================================================
# Web Server Installation and Configuration (Nginx or Caddy)
# Usage: ./webserver.sh <command> [options]
# Commands:
#   install    - Install web server (nginx or caddy)
#   configure  - Configure reverse proxy vhost
#   ssl        - Obtain SSL certificate (nginx+certbot only, caddy auto-handles)
#   status     - Check web server status
#   reload     - Reload web server configuration
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
NGINX_VHOST_TEMPLATE="${SCRIPT_DIR}/nginx-vhost.conf"
CADDY_VHOST_TEMPLATE="${SCRIPT_DIR}/caddy-vhost.conf"
NGINX_SITES_AVAILABLE="/etc/nginx/sites-available"
NGINX_SITES_ENABLED="/etc/nginx/sites-enabled"
CADDY_CONFIG_DIR="/etc/caddy"

# =============================================================================
# Installation Functions
# =============================================================================
install_nginx() {
    log "Installing Nginx..."

    apt-get update
    apt-get install -y nginx certbot python3-certbot-nginx

    # Remove default site
    rm -f "${NGINX_SITES_ENABLED}/default"

    # Enable and start nginx
    systemctl enable nginx
    systemctl start nginx

    log_success "Nginx installed successfully"
}

install_caddy() {
    log "Installing Caddy..."

    # Install Caddy via official repository
    apt-get update
    apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl

    # Add Caddy GPG key and repository
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list

    apt-get update
    apt-get install -y caddy

    # Enable and start caddy
    systemctl enable caddy
    systemctl start caddy

    log_success "Caddy installed successfully"
}

install_webserver() {
    local server_type="${1:-nginx}"

    case "${server_type}" in
        nginx)
            install_nginx
            ;;
        caddy)
            install_caddy
            ;;
        *)
            log_error "Unknown web server type: ${server_type}"
            log_error "Supported types: nginx, caddy"
            return 1
            ;;
    esac
}

# =============================================================================
# Configuration Functions
# =============================================================================
configure_nginx() {
    local domain="${1:-localhost}"
    local vhost_name="${2:-vito}"

    log "Configuring Nginx reverse proxy for ${domain}..."

    if [[ ! -f "${NGINX_VHOST_TEMPLATE}" ]]; then
        log_error "Nginx vhost template not found: ${NGINX_VHOST_TEMPLATE}"
        return 1
    fi

    # Create vhost from template
    sed "s/DOMAIN/${domain}/g" "${NGINX_VHOST_TEMPLATE}" > "${NGINX_SITES_AVAILABLE}/${vhost_name}"

    # Enable site
    ln -sf "${NGINX_SITES_AVAILABLE}/${vhost_name}" "${NGINX_SITES_ENABLED}/${vhost_name}"

    # Test configuration
    if ! nginx -t 2>&1; then
        log_error "Nginx configuration test failed"
        return 1
    fi

    # Reload nginx
    systemctl reload nginx

    log_success "Nginx configured for ${domain}"
}

configure_caddy() {
    local domain="${1:-localhost}"

    log "Configuring Caddy reverse proxy for ${domain}..."

    if [[ ! -f "${CADDY_VHOST_TEMPLATE}" ]]; then
        log_error "Caddy vhost template not found: ${CADDY_VHOST_TEMPLATE}"
        return 1
    fi

    # For localhost, use http:// prefix to disable automatic HTTPS
    local caddy_domain="${domain}"
    if [[ "${domain}" == "localhost" ]]; then
        caddy_domain="http://localhost"
    fi

    # Create Caddyfile from template
    mkdir -p "${CADDY_CONFIG_DIR}"
    sed "s/DOMAIN/${caddy_domain}/g" "${CADDY_VHOST_TEMPLATE}" > "${CADDY_CONFIG_DIR}/Caddyfile"

    # Test configuration
    if ! caddy validate --config "${CADDY_CONFIG_DIR}/Caddyfile" 2>&1; then
        log_error "Caddy configuration validation failed"
        return 1
    fi

    # Reload caddy
    systemctl reload caddy

    log_success "Caddy configured for ${domain}"
}

configure_webserver() {
    local server_type="${1:-nginx}"
    local domain="${2:-localhost}"

    case "${server_type}" in
        nginx)
            configure_nginx "${domain}"
            ;;
        caddy)
            configure_caddy "${domain}"
            ;;
        *)
            log_error "Unknown web server type: ${server_type}"
            return 1
            ;;
    esac
}

# =============================================================================
# SSL Functions
# =============================================================================
obtain_ssl_nginx() {
    local domain="${1}"
    local email="${2}"

    # Check if domain is valid for SSL
    if ! is_valid_ssl_domain "${domain}"; then
        log "Skipping SSL for ${domain} (localhost or IP address)"
        return 0
    fi

    log "Obtaining SSL certificate for ${domain} via certbot..."

    # Run certbot
    if certbot --nginx -d "${domain}" --non-interactive --agree-tos -m "${email}"; then
        log_success "SSL certificate obtained for ${domain}"

        # Create renewal hook to notify about certificate renewal
        mkdir -p /etc/letsencrypt/renewal-hooks/post
        cat > /etc/letsencrypt/renewal-hooks/post/reload-nginx.sh <<'EOF'
#!/bin/bash
systemctl reload nginx
EOF
        chmod +x /etc/letsencrypt/renewal-hooks/post/reload-nginx.sh

        return 0
    else
        log_error "Failed to obtain SSL certificate"
        log_error "Common causes: DNS not pointing to this server, port 80 blocked"
        return 1
    fi
}

obtain_ssl() {
    local server_type="${1:-nginx}"
    local domain="${2}"
    local email="${3}"

    case "${server_type}" in
        nginx)
            obtain_ssl_nginx "${domain}" "${email}"
            ;;
        caddy)
            # Caddy handles SSL automatically
            if is_valid_ssl_domain "${domain}"; then
                log "Caddy will automatically obtain SSL for ${domain}"
            else
                log "SSL not applicable for ${domain}"
            fi
            ;;
        *)
            log_error "Unknown web server type: ${server_type}"
            return 1
            ;;
    esac
}

# =============================================================================
# Status Functions
# =============================================================================
webserver_status() {
    local server_type="${1:-nginx}"

    case "${server_type}" in
        nginx)
            systemctl status nginx --no-pager
            ;;
        caddy)
            systemctl status caddy --no-pager
            ;;
        *)
            log_error "Unknown web server type: ${server_type}"
            return 1
            ;;
    esac
}

webserver_reload() {
    local server_type="${1:-nginx}"

    case "${server_type}" in
        nginx)
            systemctl reload nginx
            log_success "Nginx reloaded"
            ;;
        caddy)
            systemctl reload caddy
            log_success "Caddy reloaded"
            ;;
        *)
            log_error "Unknown web server type: ${server_type}"
            return 1
            ;;
    esac
}

# =============================================================================
# Firewall Functions
# =============================================================================
configure_firewall() {
    log "Configuring firewall for web traffic..."

    # Ensure ufw is installed and configured
    if ! command -v ufw &>/dev/null; then
        apt-get install -y ufw
    fi

    # Enable ufw if not already
    if ! ufw status | grep -q "Status: active"; then
        ufw default deny incoming
        ufw default allow outgoing
        ufw allow ssh
        ufw --force enable
    fi

    # Open HTTP and HTTPS ports
    ufw allow 80/tcp comment 'HTTP'
    ufw allow 443/tcp comment 'HTTPS'

    log_success "Firewall configured for ports 80 and 443"
}

# =============================================================================
# Main
# =============================================================================
main() {
    local command="${1:-}"
    shift || true

    case "${command}" in
        install)
            local server_type="nginx"
            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --type=*) server_type="${1#*=}"; shift ;;
                    nginx|caddy) server_type="$1"; shift ;;
                    *) shift ;;
                esac
            done
            install_webserver "${server_type}"
            ;;
        configure)
            local server_type="nginx" domain="localhost"
            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --type=*) server_type="${1#*=}"; shift ;;
                    --domain=*) domain="${1#*=}"; shift ;;
                    *) shift ;;
                esac
            done
            configure_webserver "${server_type}" "${domain}"
            ;;
        ssl)
            local server_type="nginx" domain="" email=""
            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --type=*) server_type="${1#*=}"; shift ;;
                    --domain=*) domain="${1#*=}"; shift ;;
                    --email=*) email="${1#*=}"; shift ;;
                    *) shift ;;
                esac
            done
            if [[ -z "${domain}" ]]; then
                log_error "Domain is required: --domain=example.com"
                exit 1
            fi
            if [[ -z "${email}" && "${server_type}" == "nginx" ]]; then
                log_error "Email is required for certbot: --email=admin@example.com"
                exit 1
            fi
            obtain_ssl "${server_type}" "${domain}" "${email}"
            ;;
        firewall)
            configure_firewall
            ;;
        status)
            local server_type="nginx"
            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --type=*) server_type="${1#*=}"; shift ;;
                    nginx|caddy) server_type="$1"; shift ;;
                    *) shift ;;
                esac
            done
            webserver_status "${server_type}"
            ;;
        reload)
            local server_type="nginx"
            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --type=*) server_type="${1#*=}"; shift ;;
                    nginx|caddy) server_type="$1"; shift ;;
                    *) shift ;;
                esac
            done
            webserver_reload "${server_type}"
            ;;
        *)
            echo "Usage: $0 <command> [options]"
            echo ""
            echo "Commands:"
            echo "  install [--type=nginx|caddy]     Install web server"
            echo "  configure                        Configure reverse proxy"
            echo "    --type=nginx|caddy             Web server type"
            echo "    --domain=DOMAIN                Domain name"
            echo "  ssl                              Obtain SSL certificate"
            echo "    --type=nginx|caddy             Web server type"
            echo "    --domain=DOMAIN                Domain name"
            echo "    --email=EMAIL                  Email for certbot (nginx only)"
            echo "  firewall                         Configure firewall (open 80/443)"
            echo "  status [--type=nginx|caddy]      Check web server status"
            echo "  reload [--type=nginx|caddy]      Reload web server"
            exit 1
            ;;
    esac
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
