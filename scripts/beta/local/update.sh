#!/bin/bash
# =============================================================================
# Component Update Script
# Usage: ./update.sh [component] --version=x.x.x
#        ./update.sh all  (updates all components to configured versions)
#
# Examples:
#   ./update.sh php --version=8.4.18
#   ./update.sh frankenphp --version=1.12.0
#   ./update.sh all
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Default Versions (override with environment variables or --version flag)
# =============================================================================
DEFAULT_FRANKENPHP_VERSION="${FRANKENPHP_VERSION:-1.11.1}"
DEFAULT_PHP_VERSION="${PHP_VERSION:-8.4.17}"
DEFAULT_NODE_VERSION="${NODE_VERSION:-20.18.1}"
DEFAULT_COMPOSER_VERSION="${COMPOSER_VERSION:-2.8.4}"
DEFAULT_REDIS_VERSION="${REDIS_VERSION:-7.4.2}"

# =============================================================================
# Update Functions
# =============================================================================

update_component() {
    local component="$1"
    local version="$2"

    case "${component}" in
        frankenphp)
            log "Updating FrankenPHP to ${version}..."
            bash "${SCRIPT_DIR}/frankenphp.sh" --version="${version}" --force
            ;;
        php)
            log "Updating PHP CLI to ${version}..."
            bash "${SCRIPT_DIR}/php.sh" --version="${version}" --force
            ;;
        node|nodejs)
            log "Updating Node.js to ${version}..."
            bash "${SCRIPT_DIR}/nodejs.sh" --version="${version}" --force
            ;;
        composer)
            log "Updating Composer to ${version}..."
            bash "${SCRIPT_DIR}/composer.sh" --version="${version}" --force
            ;;
        redis)
            log "Updating Redis to ${version}..."
            bash "${SCRIPT_DIR}/redis.sh" --version="${version}" --force --configure
            ;;
        vito-service)
            log "Updating Vito Root Service to ${version}..."
            bash "${SCRIPT_DIR}/vito-service.sh" --version="${version}" --force
            ;;
        *)
            log_error "Unknown component: ${component}"
            log_error "Valid components: frankenphp, php, node, composer, redis, vito-service"
            return 1
            ;;
    esac
}

update_all() {
    log "Updating all components..."
    echo ""

    update_component "frankenphp" "${DEFAULT_FRANKENPHP_VERSION}"
    update_component "php" "${DEFAULT_PHP_VERSION}"
    update_component "node" "${DEFAULT_NODE_VERSION}"
    update_component "composer" "${DEFAULT_COMPOSER_VERSION}"
    update_component "redis" "${DEFAULT_REDIS_VERSION}"

    echo ""
    log_success "All components updated"
}

restart_services() {
    log "Restarting services..."
    bash "${SCRIPT_DIR}/systemd.sh" --restart
}

show_usage() {
    echo "Usage: $0 <component> --version=x.x.x"
    echo "       $0 all"
    echo ""
    echo "Components:"
    echo "  frankenphp    - FrankenPHP web server"
    echo "  php           - PHP CLI"
    echo "  node          - Node.js"
    echo "  composer      - PHP Composer"
    echo "  redis         - Redis server"
    echo "  vito-service  - Vito root service"
    echo "  all           - Update all components to configured versions"
    echo ""
    echo "Options:"
    echo "  --version=x.x.x  - Specify version to install"
    echo "  --restart        - Restart services after update"
    echo ""
    echo "Examples:"
    echo "  $0 php --version=8.4.18"
    echo "  $0 frankenphp --version=1.12.0 --restart"
    echo "  $0 vito-service --version=v1.0.0"
    echo "  $0 all --restart"
    echo ""
    echo "Environment variables for 'all' command:"
    echo "  FRANKENPHP_VERSION  (default: ${DEFAULT_FRANKENPHP_VERSION})"
    echo "  PHP_VERSION         (default: ${DEFAULT_PHP_VERSION})"
    echo "  NODE_VERSION        (default: ${DEFAULT_NODE_VERSION})"
    echo "  COMPOSER_VERSION    (default: ${DEFAULT_COMPOSER_VERSION})"
    echo "  REDIS_VERSION       (default: ${DEFAULT_REDIS_VERSION})"
    echo ""
}

# =============================================================================
# Main
# =============================================================================
main() {
    local component=""
    local version=""
    local do_restart="N"

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
            --restart)
                do_restart="Y"
                shift
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

    if [[ "${component}" == "all" ]]; then
        update_all
    else
        if [[ -z "${version}" ]]; then
            log_error "Version is required for single component update"
            log_error "Usage: $0 ${component} --version=x.x.x"
            exit 1
        fi
        update_component "${component}" "${version}"
    fi

    if [[ "${do_restart}" == "Y" ]]; then
        restart_services
    fi
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
