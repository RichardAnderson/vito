#!/bin/bash
# =============================================================================
# Node.js Installer
# Usage: ./nodejs.sh --version=20.18.1 [--force]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
require_supported_architecture
COMPONENT_NAME="node"
DEFAULT_VERSION="20.18.1"

# =============================================================================
# Installation Function
# =============================================================================
install_nodejs() {
    local version="$1"
    local force="${2:-N}"

    ensure_directories

    if needs_install "${COMPONENT_NAME}" "${version}" "${VITO_LOCAL}/node" "${force}"; then
        log "Installing Node.js ${version}..."

        local url="https://nodejs.org/dist/v${version}/node-v${version}-linux-${NODE_ARCH}.tar.xz"
        local tmp_file="/tmp/node-${version}.tar.xz"

        # Clean previous installation
        rm -rf "${VITO_LOCAL}/node"

        download "${url}" "${tmp_file}"
        tar -xJf "${tmp_file}" -C "${VITO_LOCAL}"
        mv "${VITO_LOCAL}/node-v${version}-linux-${NODE_ARCH}" "${VITO_LOCAL}/node"
        rm -f "${tmp_file}"

        # Create symlinks for node binaries
        ln -sf "${VITO_LOCAL}/node/bin/node" "${VITO_BIN}/node"
        ln -sf "${VITO_LOCAL}/node/bin/npm" "${VITO_BIN}/npm"
        ln -sf "${VITO_LOCAL}/node/bin/npx" "${VITO_BIN}/npx"

        mark_installed "${COMPONENT_NAME}" "${version}"
    else
        log_skip "Node.js ${version} already installed"
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    parse_installer_args "$@"

    local version="${VERSION:-$DEFAULT_VERSION}"

    log "Node.js installer (version: ${version}, force: ${FORCE_REBUILD})"
    install_nodejs "${version}" "${FORCE_REBUILD}"
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
