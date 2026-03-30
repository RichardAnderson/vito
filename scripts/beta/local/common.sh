#!/bin/bash
# =============================================================================
# Common functions and utilities for Vito local installation scripts
# Source this file at the top of each installer script
# =============================================================================

# Prevent double-sourcing
if [[ -n "${VITO_COMMON_LOADED}" ]]; then
    return 0
fi
export VITO_COMMON_LOADED=1

# =============================================================================
# Directory Configuration (can be overridden before sourcing)
# =============================================================================
export VITO_HOME="${VITO_HOME:-/home/vito}"
export VITO_LOCAL="${VITO_LOCAL:-${VITO_HOME}/.local}"
export VITO_BIN="${VITO_BIN:-${VITO_LOCAL}/bin}"
export VITO_DATA="${VITO_DATA:-${VITO_LOCAL}/data}"
export VITO_LOGS="${VITO_LOGS:-${VITO_LOCAL}/logs}"
export VITO_APP="${VITO_APP:-${VITO_HOME}/www}"
export VITO_VERSIONS="${VITO_VERSIONS:-${VITO_LOCAL}/versions}"

# =============================================================================
# Architecture Detection
# =============================================================================
detect_architecture() {
    local arch
    arch=$(uname -m)
    case "$arch" in
        x86_64)
            export ARCH_SUFFIX="amd64"
            export NODE_ARCH="x64"
            export FRANKENPHP_ARCH="x86_64"
            ;;
        aarch64|arm64)
            export ARCH_SUFFIX="arm64"
            export NODE_ARCH="arm64"
            export FRANKENPHP_ARCH="aarch64"
            ;;
        *)
            echo "Unsupported architecture: $arch" >&2
            return 1
            ;;
    esac
}

# Auto-detect on source if not already set
if [[ -z "${ARCH_SUFFIX}" ]]; then
    if ! detect_architecture; then
        echo "FATAL: Unsupported architecture. Cannot continue." >&2
        # If sourced, we can't exit, but we set a flag
        export VITO_ARCH_UNSUPPORTED=1
    fi
fi

# =============================================================================
# Logging Functions
# =============================================================================
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
}

log_success() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1"
}

log_skip() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SKIP: $1"
}

# =============================================================================
# Download Function with Retry Logic
# =============================================================================
download() {
    local url="$1"
    local dest="$2"
    local retries="${3:-3}"
    local delay="${4:-5}"

    log "Downloading: ${url}"
    for ((i=1; i<=retries; i++)); do
        if curl -fsSL "${url}" -o "${dest}"; then
            return 0
        fi
        if [[ $i -lt $retries ]]; then
            log "Download failed, attempt $i/$retries. Retrying in ${delay}s..."
            sleep $delay
        fi
    done
    log_error "Failed to download ${url} after ${retries} attempts"
    return 1
}

# =============================================================================
# Version Management Functions
# =============================================================================

# Get the currently installed version of a component
get_installed_version() {
    local name="$1"
    local version_file="${VITO_VERSIONS}/${name}.version"

    if [[ -f "${version_file}" ]]; then
        cat "${version_file}"
    else
        echo ""
    fi
}

# Check if a component needs to be installed/rebuilt
# Returns 0 (true) if install needed, 1 (false) if already installed
needs_install() {
    local name="$1"
    local version="$2"
    local check_path="$3"
    local force_rebuild="${4:-N}"

    # Always rebuild if requested
    if [[ "${force_rebuild}" == "Y" ]]; then
        log "  -> ${name}: rebuild requested"
        return 0
    fi

    # Check if binary/directory exists
    if [[ ! -e "${check_path}" ]]; then
        log "  -> ${name}: not found at ${check_path}"
        return 0
    fi

    # Check version file
    local installed_version
    installed_version=$(get_installed_version "${name}")

    if [[ -z "${installed_version}" ]]; then
        log "  -> ${name}: no version file found"
        return 0
    fi

    # Compare versions
    if [[ "${installed_version}" != "${version}" ]]; then
        log "  -> ${name}: version mismatch (installed: ${installed_version}, want: ${version})"
        return 0
    fi

    # Already installed at correct version
    return 1
}

# Mark a component as installed with a specific version
mark_installed() {
    local name="$1"
    local version="$2"
    mkdir -p "${VITO_VERSIONS}"
    echo "${version}" > "${VITO_VERSIONS}/${name}.version"
    log_success "${name} ${version} installed"
}

# =============================================================================
# Service Management Functions
# =============================================================================

# Wait for a systemd service to become active
wait_for_service() {
    local service="$1"
    local max_wait="${2:-30}"
    local waited=0

    log "Waiting for ${service} to start..."
    while ! systemctl is-active --quiet "${service}"; do
        sleep 1
        ((waited++))
        if [[ $waited -ge $max_wait ]]; then
            log_error "${service} failed to start within ${max_wait}s"
            systemctl status "${service}" --no-pager || true
            return 1
        fi
    done
    log "${service} is running"
}

# Safely stop a service if it's running
stop_service_if_running() {
    local service="$1"
    if systemctl is-active --quiet "${service}" 2>/dev/null; then
        log "Stopping ${service}..."
        systemctl stop "${service}" || true
    fi
}

# =============================================================================
# Directory Setup Functions
# =============================================================================

# Ensure all required directories exist
ensure_directories() {
    mkdir -p "${VITO_BIN}" "${VITO_DATA}" "${VITO_LOGS}" "${VITO_VERSIONS}"
}

# =============================================================================
# Validation Functions
# =============================================================================

validate_domain() {
    local domain="$1"

    if [[ "${domain}" =~ ^https?:// ]]; then
        log_error "Domain should not include http:// or https://"
        return 1
    fi

    if [[ -z "${domain}" ]]; then
        log_error "Domain cannot be empty"
        return 1
    fi

    return 0
}

validate_email() {
    local email="$1"

    if [[ ! "${email}" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]]; then
        log_error "Invalid email format: ${email}"
        return 1
    fi

    return 0
}

validate_port() {
    local port="$1"

    if ! [[ "${port}" =~ ^[0-9]+$ ]]; then
        log_error "Port must be a number"
        return 1
    fi

    if [[ "${port}" -lt 1024 ]]; then
        log_error "Port must be >= 1024 to run as non-root user"
        return 1
    fi

    if [[ "${port}" -gt 65535 ]]; then
        log_error "Port must be <= 65535"
        return 1
    fi

    return 0
}

# Check if domain is valid for SSL (not localhost or IP address)
is_valid_ssl_domain() {
    local domain="$1"

    # Reject localhost
    if [[ "${domain}" == "localhost" ]]; then
        return 1
    fi

    # Reject IP addresses (IPv4)
    if [[ "${domain}" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        return 1
    fi

    # Reject IPv6 addresses (contains colons)
    if [[ "${domain}" == *:* ]]; then
        return 1
    fi

    return 0
}

# =============================================================================
# Script Initialization
# =============================================================================

# Parse common arguments used by installer scripts
# Usage: parse_installer_args "$@"
# Sets: VERSION, FORCE_REBUILD
parse_installer_args() {
    VERSION=""
    FORCE_REBUILD="N"

    while [[ $# -gt 0 ]]; do
        case "$1" in
            --version=*)
                VERSION="${1#*=}"
                shift
                ;;
            --version)
                VERSION="$2"
                shift 2
                ;;
            --force|-f)
                FORCE_REBUILD="Y"
                shift
                ;;
            *)
                shift
                ;;
        esac
    done
}

# Check if architecture is supported (call after sourcing)
require_supported_architecture() {
    if [[ "${VITO_ARCH_UNSUPPORTED}" == "1" ]]; then
        log_error "Cannot proceed: unsupported architecture"
        exit 1
    fi
}
