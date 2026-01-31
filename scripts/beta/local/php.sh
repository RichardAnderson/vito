#!/bin/bash
# =============================================================================
# PHP CLI Installer (Static Build)
# Usage: ./php.sh --version=8.4.17 [--force]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
require_supported_architecture
COMPONENT_NAME="php"
DEFAULT_VERSION="8.4.17"

# =============================================================================
# Installation Function
# =============================================================================
install_php() {
    local version="$1"
    local force="${2:-N}"

    ensure_directories

    if needs_install "${COMPONENT_NAME}" "${version}" "${VITO_BIN}/php" "${force}"; then
        log "Installing PHP CLI ${version}..."

        # Using 'bulk' build which includes intl, redis, and other required extensions
        local url="https://dl.static-php.dev/static-php-cli/bulk/php-${version}-cli-linux-${FRANKENPHP_ARCH}.tar.gz"
        local tmp_file="/tmp/php-cli-${version}.tar.gz"

        download "${url}" "${tmp_file}"
        tar -xzf "${tmp_file}" -C "${VITO_BIN}"
        rm -f "${tmp_file}"
        chmod +x "${VITO_BIN}/php"

        mark_installed "${COMPONENT_NAME}" "${version}"
    else
        log_skip "PHP CLI ${version} already installed"
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    parse_installer_args "$@"

    local version="${VERSION:-$DEFAULT_VERSION}"

    log "PHP CLI installer (version: ${version}, force: ${FORCE_REBUILD})"
    install_php "${version}" "${FORCE_REBUILD}"
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
