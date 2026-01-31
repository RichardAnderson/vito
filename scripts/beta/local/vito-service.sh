#!/bin/bash
# =============================================================================
# Vito Root Service Installer
# Usage: ./vito-service.sh --repo=RichardAnderson/vito-local [--version=latest] [--force]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
require_supported_architecture
COMPONENT_NAME="vito-root-service"
DEFAULT_REPO="RichardAnderson/vito-local"
DEFAULT_VERSION="latest"

# =============================================================================
# Installation Function
# =============================================================================
install_vito_service() {
    local repo="$1"
    local version="$2"
    local force="${3:-N}"

    local install_dir="/usr/local/bin"
    local systemd_dir="/etc/systemd/system"
    local binary_name="vito-root-service"
    local tmp_file="/tmp/vito-local-service.tar.gz"
    local tmp_dir="/tmp/vito-local-service"

    # For version tracking, we use the repo + version as identifier
    local version_id="${repo}:${version}"

    if needs_install "${COMPONENT_NAME}" "${version_id}" "${install_dir}/${binary_name}" "${force}"; then
        log "Installing vito-root-service from ${repo} (version: ${version})..."

        # Determine download URL based on version
        local release_url
        if [[ "${version}" == "latest" ]]; then
            release_url="https://github.com/${repo}/releases/latest/download/vito-root-service-linux-${ARCH_SUFFIX}.tar.gz"
        else
            release_url="https://github.com/${repo}/releases/download/${version}/vito-root-service-linux-${ARCH_SUFFIX}.tar.gz"
        fi

        # Cleanup function for this installation
        cleanup_vito_service() {
            rm -rf "${tmp_file}" "${tmp_dir}" 2>/dev/null || true
        }
        trap cleanup_vito_service EXIT

        # Download and extract
        download "${release_url}" "${tmp_file}"
        rm -rf "${tmp_dir}"
        mkdir -p "${tmp_dir}"
        tar -xzf "${tmp_file}" -C "${tmp_dir}"

        # Stop existing service if running
        stop_service_if_running "vito-root.socket"
        stop_service_if_running "vito-root.service"

        # Install binary
        log "Installing ${binary_name} to ${install_dir}..."
        install -m 0755 "${tmp_dir}/${binary_name}" "${install_dir}/${binary_name}"

        # Install systemd units
        log "Installing systemd units..."
        install -m 0644 "${tmp_dir}/systemd/vito-root.socket" "${systemd_dir}/"
        install -m 0644 "${tmp_dir}/systemd/vito-root.service" "${systemd_dir}/"

        # Reload and enable
        systemctl daemon-reload
        systemctl enable vito-root.socket
        systemctl start vito-root.socket

        # Cleanup
        cleanup_vito_service
        trap - EXIT

        mark_installed "${COMPONENT_NAME}" "${version_id}"
    else
        log_skip "vito-root-service already installed at ${version}"
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    local repo="${DEFAULT_REPO}"
    local version="${DEFAULT_VERSION}"
    local force="N"

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --repo=*)
                repo="${1#*=}"
                shift
                ;;
            --repo)
                repo="$2"
                shift 2
                ;;
            --version=*)
                version="${1#*=}"
                shift
                ;;
            --version)
                version="$2"
                shift 2
                ;;
            --force|-f)
                force="Y"
                shift
                ;;
            *)
                shift
                ;;
        esac
    done

    log "Vito root service installer (repo: ${repo}, version: ${version}, force: ${force})"
    install_vito_service "${repo}" "${version}" "${force}"
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
