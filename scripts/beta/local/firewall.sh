#!/bin/bash
# =============================================================================
# Firewall Configuration (UFW)
# Usage: ./firewall.sh --port=3000 [--ssl]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration Function
# =============================================================================
configure_firewall() {
    local port="$1"
    local enable_ssl="$2"

    log "Configuring firewall..."

    # Enable ufw if not already enabled
    if ! ufw status | grep -q "Status: active"; then
        ufw default deny incoming
        ufw default allow outgoing
    fi

    # Allow SSH (idempotent - ufw handles duplicates)
    ufw allow ssh

    # Allow Vito port
    ufw allow "${port}/tcp" comment 'Vito Web'

    # Port 80 for SSL ACME challenges
    if [[ "${enable_ssl}" == "Y" ]]; then
        ufw allow 80/tcp comment 'LetsEncrypt ACME'
    fi

    # Enable firewall
    ufw --force enable

    log "Firewall status:"
    ufw status verbose
}

# =============================================================================
# Main
# =============================================================================
main() {
    local port="3000"
    local enable_ssl="N"

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
            *)
                shift
                ;;
        esac
    done

    log "Firewall configuration (port: ${port}, ssl: ${enable_ssl})"
    configure_firewall "${port}" "${enable_ssl}"
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
