#!/bin/bash
# =============================================================================
# Composer Installer
# Usage: ./composer.sh --version=2.8.4 [--force]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
COMPONENT_NAME="composer"
DEFAULT_VERSION="2.8.4"

# =============================================================================
# Installation Function
# =============================================================================
install_composer() {
    local version="$1"
    local force="${2:-N}"

    ensure_directories

    if needs_install "${COMPONENT_NAME}" "${version}" "${VITO_BIN}/composer" "${force}"; then
        log "Installing Composer ${version}..."

        local url="https://getcomposer.org/download/${version}/composer.phar"

        download "${url}" "${VITO_BIN}/composer"
        chmod +x "${VITO_BIN}/composer"

        mark_installed "${COMPONENT_NAME}" "${version}"
    else
        log_skip "Composer ${version} already installed"
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    parse_installer_args "$@"

    local version="${VERSION:-$DEFAULT_VERSION}"

    log "Composer installer (version: ${version}, force: ${FORCE_REBUILD})"
    install_composer "${version}" "${FORCE_REBUILD}"
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
