#!/bin/bash
# =============================================================================
# Redis Installer (Build from Source)
# Usage: ./redis.sh --version=7.4.2 [--force]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
require_supported_architecture
COMPONENT_NAME="redis"
DEFAULT_VERSION="7.4.2"

# =============================================================================
# Installation Function
# =============================================================================
install_redis() {
    local version="$1"
    local force="${2:-N}"

    ensure_directories

    if needs_install "${COMPONENT_NAME}" "${version}" "${VITO_LOCAL}/redis" "${force}"; then
        log "Installing Redis ${version}..."

        local url="https://github.com/redis/redis/archive/refs/tags/${version}.tar.gz"
        local tmp_file="/tmp/redis-${version}.tar.gz"
        local build_dir="/tmp/redis-${version}"

        # Setup cleanup trap for this function
        cleanup_redis_build() {
            log "Cleaning up Redis build artifacts..."
            rm -rf "${tmp_file}" "${build_dir}" 2>/dev/null || true
        }
        trap cleanup_redis_build EXIT

        # Clean previous build artifacts
        rm -rf "${VITO_LOCAL}/redis" "${build_dir}"

        download "${url}" "${tmp_file}"
        tar -xzf "${tmp_file}" -C /tmp

        cd "${build_dir}" || { log_error "Failed to cd to ${build_dir}"; return 1; }

        log "Building Redis (output logged to ${VITO_LOGS}/redis-build.log)..."
        mkdir -p "${VITO_LOGS}"

        if ! make -j"$(nproc)" PREFIX="${VITO_LOCAL}/redis" install >> "${VITO_LOGS}/redis-build.log" 2>&1; then
            log_error "Redis build failed. Check ${VITO_LOGS}/redis-build.log for details"
            # Cleanup happens via trap
            return 1
        fi

        cd /

        # Cleanup build artifacts (also happens via trap, but explicit is good)
        rm -rf "${tmp_file}" "${build_dir}"
        trap - EXIT

        # Create symlinks for redis binaries
        ln -sf "${VITO_LOCAL}/redis/bin/redis-server" "${VITO_BIN}/redis-server"
        ln -sf "${VITO_LOCAL}/redis/bin/redis-cli" "${VITO_BIN}/redis-cli"

        mark_installed "${COMPONENT_NAME}" "${version}"
    else
        log_skip "Redis ${version} already installed"
    fi
}

# =============================================================================
# Configuration Function
# =============================================================================
configure_redis() {
    log "Configuring Redis..."

    ensure_directories

    cat > "${VITO_DATA}/redis.conf" <<EOF
bind 127.0.0.1
port 6379
daemonize no
dir ${VITO_DATA}
logfile ${VITO_LOGS}/redis.log
pidfile ${VITO_DATA}/redis.pid
EOF

    log "Redis configuration written to ${VITO_DATA}/redis.conf"
}

# =============================================================================
# Main
# =============================================================================
main() {
    parse_installer_args "$@"

    local version="${VERSION:-$DEFAULT_VERSION}"
    local do_configure="N"

    # Parse additional arguments
    for arg in "$@"; do
        case "$arg" in
            --configure)
                do_configure="Y"
                ;;
        esac
    done

    log "Redis installer (version: ${version}, force: ${FORCE_REBUILD})"
    install_redis "${version}" "${FORCE_REBUILD}"

    if [[ "${do_configure}" == "Y" ]]; then
        configure_redis
    fi
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
