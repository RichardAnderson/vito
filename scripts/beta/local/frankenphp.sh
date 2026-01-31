#!/bin/bash
# =============================================================================
# FrankenPHP Installer
# Usage: ./frankenphp.sh --version=1.11.1 [--force]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
require_supported_architecture
COMPONENT_NAME="frankenphp"
DEFAULT_VERSION="1.11.1"

# =============================================================================
# Installation Function
# =============================================================================
install_frankenphp() {
    local version="$1"
    local force="${2:-N}"

    ensure_directories

    if needs_install "${COMPONENT_NAME}" "${version}" "${VITO_BIN}/frankenphp" "${force}"; then
        log "Installing FrankenPHP ${version}..."

        local url="https://github.com/php/frankenphp/releases/download/v${version}/frankenphp-linux-${FRANKENPHP_ARCH}"
        local tmp_file="/tmp/frankenphp-${version}"

        download "${url}" "${tmp_file}"
        mv "${tmp_file}" "${VITO_BIN}/frankenphp"
        chmod +x "${VITO_BIN}/frankenphp"

        mark_installed "${COMPONENT_NAME}" "${version}"
    else
        log_skip "FrankenPHP ${version} already installed"
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    parse_installer_args "$@"

    local version="${VERSION:-$DEFAULT_VERSION}"

    log "FrankenPHP installer (version: ${version}, force: ${FORCE_REBUILD})"
    install_frankenphp "${version}" "${FORCE_REBUILD}"
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
