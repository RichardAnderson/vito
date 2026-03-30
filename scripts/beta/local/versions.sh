#!/bin/bash
# =============================================================================
# Version Check and Display Utility (Docker-based Installation)
# Usage: ./versions.sh [--json]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
CONTAINER_NAME="${CONTAINER_NAME:-vito}"

# =============================================================================
# Version Display Functions
# =============================================================================

get_docker_image_version() {
    if docker inspect "${CONTAINER_NAME}" &>/dev/null; then
        local image
        image=$(docker inspect --format='{{.Config.Image}}' "${CONTAINER_NAME}" 2>/dev/null)
        local image_id
        image_id=$(docker inspect --format='{{.Image}}' "${CONTAINER_NAME}" 2>/dev/null | cut -c8-19)
        echo "${image} (${image_id})"
    else
        echo ""
    fi
}

get_container_status() {
    if docker inspect "${CONTAINER_NAME}" &>/dev/null; then
        docker inspect --format='{{.State.Status}}' "${CONTAINER_NAME}" 2>/dev/null
    else
        echo "not found"
    fi
}

get_container_health() {
    if docker inspect "${CONTAINER_NAME}" &>/dev/null; then
        docker inspect --format='{{.State.Health.Status}}' "${CONTAINER_NAME}" 2>/dev/null || echo "no healthcheck"
    else
        echo ""
    fi
}

show_versions() {
    echo ""
    echo "Vito Installation Status"
    echo "========================="
    echo ""

    # Docker container
    local docker_image
    docker_image=$(get_docker_image_version)
    local container_status
    container_status=$(get_container_status)
    local container_health
    container_health=$(get_container_health)

    if [[ -n "${docker_image}" ]]; then
        printf "  %-18s %s\n" "Docker Image:" "${docker_image}"
        printf "  %-18s %s\n" "Container Status:" "${container_status}"
        printf "  %-18s %s\n" "Container Health:" "${container_health}"
    else
        printf "  %-18s %s\n" "Docker Container:" "(not running)"
    fi

    echo ""

    # Vito root service
    local vito_service_version
    vito_service_version=$(get_installed_version "vito-root-service")
    if [[ -n "${vito_service_version}" ]]; then
        printf "  %-18s %s\n" "Vito Root Service:" "${vito_service_version}"
    else
        printf "  %-18s %s\n" "Vito Root Service:" "(not installed)"
    fi

    # Socket status
    if [[ -S "/run/vito-root.sock" ]]; then
        printf "  %-18s %s\n" "Root Socket:" "active"
    else
        printf "  %-18s %s\n" "Root Socket:" "not found"
    fi

    echo ""

    # Web server
    if systemctl is-active --quiet nginx 2>/dev/null; then
        printf "  %-18s %s\n" "Web Server:" "nginx (active)"
    elif systemctl is-active --quiet caddy 2>/dev/null; then
        printf "  %-18s %s\n" "Web Server:" "caddy (active)"
    else
        printf "  %-18s %s\n" "Web Server:" "(not detected)"
    fi

    echo ""
}

show_versions_json() {
    local docker_image
    docker_image=$(get_docker_image_version)
    local container_status
    container_status=$(get_container_status)
    local container_health
    container_health=$(get_container_health)
    local vito_service_version
    vito_service_version=$(get_installed_version "vito-root-service")
    local socket_active="false"
    [[ -S "/run/vito-root.sock" ]] && socket_active="true"

    local webserver="null"
    if systemctl is-active --quiet nginx 2>/dev/null; then
        webserver="nginx"
    elif systemctl is-active --quiet caddy 2>/dev/null; then
        webserver="caddy"
    fi

    cat <<EOF
{
  "docker": {
    "image": "${docker_image:-null}",
    "status": "${container_status}",
    "health": "${container_health:-null}"
  },
  "vito_root_service": {
    "version": "${vito_service_version:-null}",
    "socket_active": ${socket_active}
  },
  "webserver": "${webserver}"
}
EOF
}

# =============================================================================
# Main
# =============================================================================
main() {
    local format="text"

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --json)
                format="json"
                shift
                ;;
            --help|-h)
                echo "Usage: $0 [--json]"
                echo ""
                echo "Options:"
                echo "  --json    Output in JSON format"
                exit 0
                ;;
            *)
                shift
                ;;
        esac
    done

    if [[ "${format}" == "json" ]]; then
        show_versions_json
    else
        show_versions
    fi
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
