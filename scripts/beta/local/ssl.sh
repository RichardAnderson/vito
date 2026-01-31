#!/bin/bash
# =============================================================================
# SSL/TLS Configuration with Let's Encrypt
# Usage: ./ssl.sh --domain=example.com --email=admin@example.com --port=3000
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Installation Functions
# =============================================================================

install_ssl_prerequisites() {
    log "Installing nginx and certbot for SSL..."
    apt-get install -y nginx certbot python3-certbot-nginx

    # Stop nginx temporarily (we'll configure it)
    systemctl stop nginx || true
}

configure_nginx_acme() {
    local domain="$1"

    log "Configuring nginx for Let's Encrypt challenges..."

    cat > /etc/nginx/sites-available/vito-acme <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${domain};

    # Let's Encrypt ACME challenge only
    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    # Return 404 for everything else - nginx only handles ACME
    location / {
        return 404;
    }
}
EOF

    # Enable site
    ln -sf /etc/nginx/sites-available/vito-acme /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default

    # Test nginx configuration
    if ! nginx -t 2>&1; then
        log_error "Nginx configuration test failed"
        return 1
    fi

    systemctl enable nginx
    systemctl start nginx
}

open_acme_port() {
    log "Opening port 80 for ACME challenges..."

    # Ensure ufw is configured
    if ! ufw status | grep -q "Status: active"; then
        ufw default deny incoming
        ufw default allow outgoing
        ufw allow ssh
        ufw --force enable
    fi

    # Open port 80 for Let's Encrypt
    ufw allow 80/tcp comment 'Let'\''s Encrypt ACME'
}

obtain_ssl_certificate() {
    local domain="$1"
    local email="$2"

    log "Obtaining SSL certificate from Let's Encrypt..."

    # Request certificate (non-interactive)
    set +e
    certbot certonly \
        --nginx \
        --non-interactive \
        --agree-tos \
        --email "${email}" \
        --domain "${domain}"
    local certbot_exit=$?
    set -e

    if [[ $certbot_exit -ne 0 ]]; then
        log_error "Certbot failed with exit code ${certbot_exit}"
        log_error "Common causes: DNS not pointing to this server, port 80 blocked, rate limited"
        return 1
    fi

    # Verify certificate exists
    if [[ ! -f "/etc/letsencrypt/live/${domain}/fullchain.pem" ]]; then
        log_error "Failed to obtain SSL certificate - certificate file not found"
        return 1
    fi

    # Create renewal hook to restart FrankenPHP
    mkdir -p /etc/letsencrypt/renewal-hooks/post
    cat > /etc/letsencrypt/renewal-hooks/post/restart-vito.sh <<EOF
#!/bin/bash
systemctl restart vito-php
EOF
    chmod +x /etc/letsencrypt/renewal-hooks/post/restart-vito.sh

    log_success "SSL certificate obtained successfully"
}

configure_frankenphp_ssl() {
    local domain="$1"
    local port="$2"

    log "Configuring FrankenPHP with SSL..."

    mkdir -p "${VITO_LOCAL}/etc"
    chown vito:vito "${VITO_LOCAL}/etc"

    cat > "${VITO_LOCAL}/etc/Caddyfile" <<EOF
{
    frankenphp
    # Disable automatic HTTPS (we use certbot certs)
    auto_https off
}

https://${domain}:${port} {
    tls /etc/letsencrypt/live/${domain}/fullchain.pem /etc/letsencrypt/live/${domain}/privkey.pem
    root * ${VITO_APP}/public
    encode zstd gzip
    php_server
}
EOF

    chown vito:vito "${VITO_LOCAL}/etc/Caddyfile"
}

grant_certificate_access() {
    local domain="$1"

    log "Granting vito user access to SSL certificates..."

    # Create a dedicated group for certificate access
    local cert_group="acme-certs"
    groupadd -f "${cert_group}"
    usermod -aG "${cert_group}" vito

    # Set group ownership on Let's Encrypt directories
    chgrp -R "${cert_group}" /etc/letsencrypt/live /etc/letsencrypt/archive
    chmod -R g+rx /etc/letsencrypt/live /etc/letsencrypt/archive
}

# =============================================================================
# Main
# =============================================================================
main() {
    local domain=""
    local email=""
    local port="3000"

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --domain=*)
                domain="${1#*=}"
                shift
                ;;
            --domain)
                domain="$2"
                shift 2
                ;;
            --email=*)
                email="${1#*=}"
                shift
                ;;
            --email)
                email="$2"
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
            *)
                shift
                ;;
        esac
    done

    # Validate required parameters
    if [[ -z "${domain}" ]]; then
        log_error "Domain is required: --domain=example.com"
        exit 1
    fi

    if [[ -z "${email}" ]]; then
        log_error "Email is required: --email=admin@example.com"
        exit 1
    fi

    # Check if domain is valid for SSL
    if ! is_valid_ssl_domain "${domain}"; then
        log_error "Domain '${domain}' is not valid for SSL (cannot be localhost or IP address)"
        exit 1
    fi

    log "SSL installer (domain: ${domain}, port: ${port})"

    install_ssl_prerequisites
    configure_nginx_acme "${domain}"
    open_acme_port
    obtain_ssl_certificate "${domain}" "${email}"
    configure_frankenphp_ssl "${domain}" "${port}"
    grant_certificate_access "${domain}"

    log_success "SSL configuration complete"
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
