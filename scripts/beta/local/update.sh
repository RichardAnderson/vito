#!/bin/bash
# =============================================================================
# Vito Update Script (Docker-based Installation)
# Usage: ./update.sh [component]
#
# Components:
#   docker       - Pull latest Docker image and restart container
#   vito-service - Update vito-root-service binary
#   all          - Update all components
#
# Examples:
#   ./update.sh docker
#   ./update.sh vito-service --version=v1.0.0
#   ./update.sh all
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
VITO_DATA_DIR="${VITO_DATA_DIR:-/opt/vito}"
COMPOSE_FILE="${COMPOSE_FILE:-${VITO_DATA_DIR}/docker-compose.local.yml}"

# =============================================================================
# Update Functions
# =============================================================================

update_docker() {
    log "Updating Vito Docker image..."

    # Check if compose file exists
    if [[ ! -f "${COMPOSE_FILE}" ]]; then
        log_error "Compose file not found: ${COMPOSE_FILE}"
        return 1
    fi

    # Pull latest image
    log "Pulling latest image..."
    docker compose -f "${COMPOSE_FILE}" pull

    # Restart container with new image
    log "Restarting container..."
    docker compose -f "${COMPOSE_FILE}" up -d

    # Wait for healthy
    log "Waiting for container to be healthy..."
    bash "${SCRIPT_DIR}/docker.sh" wait 120

    log_success "Docker container updated"
}

update_vito_service() {
    local version="${1:-latest}"

    log "Updating vito-root-service to ${version}..."
    bash "${SCRIPT_DIR}/vito-service.sh" --version="${version}" --force

    log_success "Vito root service updated"
}

update_all() {
    log "Updating all components..."
    echo ""

    update_docker
    update_vito_service "latest"

    echo ""
    log_success "All components updated"
}

show_versions() {
    echo "Current Versions:"
    echo ""

    # Docker image
    if docker inspect vito &>/dev/null; then
        local image_id
        image_id=$(docker inspect --format='{{.Image}}' vito 2>/dev/null | cut -c8-19)
        echo "  Docker Image: vitodeploy/vito (${image_id})"
    else
        echo "  Docker Image: (container not running)"
    fi

    # Vito root service
    if [[ -f "${VITO_VERSIONS}/vito-root-service.version" ]]; then
        echo "  Vito Service: $(cat "${VITO_VERSIONS}/vito-root-service.version")"
    else
        echo "  Vito Service: (unknown)"
    fi

    echo ""
}

show_usage() {
    echo "Usage: $0 <component> [options]"
    echo ""
    echo "Components:"
    echo "  docker       - Pull latest Docker image and restart container"
    echo "  vito-service - Update vito-root-service binary"
    echo "  all          - Update all components"
    echo "  versions     - Show current versions"
    echo ""
    echo "Options:"
    echo "  --version=x.x.x  - Specify version for vito-service"
    echo ""
    echo "Examples:"
    echo "  $0 docker                          # Pull latest image"
    echo "  $0 vito-service --version=v1.0.0   # Update to specific version"
    echo "  $0 all                             # Update everything"
    echo "  $0 versions                        # Show current versions"
    echo ""
}

# =============================================================================
# Main
# =============================================================================
main() {
    local component=""
    local version="latest"

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --version=*)
                version="${1#*=}"
                shift
                ;;
            --version)
                version="$2"
                shift 2
                ;;
            --help|-h)
                show_usage
                exit 0
                ;;
            *)
                if [[ -z "${component}" ]]; then
                    component="$1"
                fi
                shift
                ;;
        esac
    done

    if [[ -z "${component}" ]]; then
        show_usage
        exit 1
    fi

    case "${component}" in
        docker)
            update_docker
            ;;
        vito-service)
            update_vito_service "${version}"
            ;;
        all)
            update_all
            ;;
        versions)
            show_versions
            ;;
        *)
            log_error "Unknown component: ${component}"
            log_error "Valid components: docker, vito-service, all"
            exit 1
            ;;
    esac
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
