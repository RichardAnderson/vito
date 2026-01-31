#!/bin/bash
# =============================================================================
# Systemd Services Configuration
# Usage: ./systemd.sh --port=3000 [--ssl]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Service Creation Functions
# =============================================================================

create_redis_service() {
    log "Creating Redis systemd service..."

    cat > /etc/systemd/system/vito-redis.service <<EOF
[Unit]
Description=Vito Redis Server
After=network.target

[Service]
Type=simple
User=vito
Group=vito
ExecStart=${VITO_BIN}/redis-server ${VITO_DATA}/redis.conf
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
}

create_php_service() {
    local port="$1"
    local enable_ssl="$2"

    log "Creating FrankenPHP systemd service..."

    local frankenphp_cmd
    if [[ "${enable_ssl}" == "Y" ]]; then
        frankenphp_cmd="${VITO_BIN}/frankenphp run --config ${VITO_LOCAL}/etc/Caddyfile"
    else
        frankenphp_cmd="${VITO_BIN}/frankenphp php-server --root ${VITO_APP}/public --listen 0.0.0.0:${port}"
    fi

    cat > /etc/systemd/system/vito-php.service <<EOF
[Unit]
Description=Vito FrankenPHP Server
After=network.target vito-redis.service
Requires=vito-redis.service

[Service]
Type=simple
User=vito
Group=vito
WorkingDirectory=${VITO_APP}
ExecStart=${frankenphp_cmd}
Restart=always
RestartSec=5
Environment=PATH=${VITO_BIN}:${VITO_LOCAL}/node/bin:/usr/local/bin:/usr/bin:/bin

[Install]
WantedBy=multi-user.target
EOF
}

create_worker_service() {
    log "Creating Horizon worker systemd service..."

    cat > /etc/systemd/system/vito-worker.service <<EOF
[Unit]
Description=Vito Horizon Worker
After=network.target vito-redis.service vito-php.service
Requires=vito-redis.service

[Service]
Type=simple
User=vito
Group=vito
WorkingDirectory=${VITO_APP}
ExecStart=${VITO_BIN}/php ${VITO_APP}/artisan horizon
Restart=always
RestartSec=5
Environment=PATH=${VITO_BIN}:${VITO_LOCAL}/node/bin:/usr/local/bin:/usr/bin:/bin

[Install]
WantedBy=multi-user.target
EOF
}

# =============================================================================
# Service Management Functions
# =============================================================================

reload_and_enable_services() {
    log "Reloading systemd and enabling services..."

    systemctl daemon-reload
    systemctl enable vito-redis vito-php vito-worker
}

start_services() {
    log "Starting services..."

    systemctl start vito-redis
    wait_for_service vito-redis

    systemctl start vito-php
    wait_for_service vito-php

    systemctl start vito-worker
    wait_for_service vito-worker

    log_success "All services started"
}

stop_services() {
    log "Stopping services..."

    stop_service_if_running vito-worker
    stop_service_if_running vito-php
    stop_service_if_running vito-redis

    log "All services stopped"
}

restart_services() {
    log "Restarting services..."

    systemctl restart vito-redis
    wait_for_service vito-redis

    systemctl restart vito-php
    wait_for_service vito-php

    systemctl restart vito-worker
    wait_for_service vito-worker

    log_success "All services restarted"
}

# =============================================================================
# Main
# =============================================================================
main() {
    local port="3000"
    local enable_ssl="N"
    local action="configure"

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
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
            --start)
                action="start"
                shift
                ;;
            --stop)
                action="stop"
                shift
                ;;
            --restart)
                action="restart"
                shift
                ;;
            *)
                shift
                ;;
        esac
    done

    case "${action}" in
        configure)
            log "Systemd configuration (port: ${port}, ssl: ${enable_ssl})"

            # Fix ownership before creating services
            chown -R vito:vito "${VITO_HOME}"

            create_redis_service
            create_php_service "${port}" "${enable_ssl}"
            create_worker_service
            reload_and_enable_services
            ;;
        start)
            start_services
            ;;
        stop)
            stop_services
            ;;
        restart)
            restart_services
            ;;
    esac
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
