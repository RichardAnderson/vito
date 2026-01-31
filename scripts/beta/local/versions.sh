#!/bin/bash
# =============================================================================
# Version Check and Display Utility
# Usage: ./versions.sh [--check] [--json]
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Version Display Functions
# =============================================================================

show_versions() {
    local format="${1:-text}"

    echo ""
    echo "Installed Component Versions"
    echo "============================="
    echo ""

    local components=("frankenphp" "php" "node" "composer" "redis")

    for component in "${components[@]}"; do
        local version
        version=$(get_installed_version "${component}")

        if [[ -n "${version}" ]]; then
            printf "  %-12s %s\n" "${component}:" "${version}"
        else
            printf "  %-12s %s\n" "${component}:" "(not installed)"
        fi
    done

    echo ""
    echo "Version files stored in: ${VITO_VERSIONS}"
    echo ""
}

show_versions_json() {
    echo "{"
    echo "  \"versions\": {"

    local components=("frankenphp" "php" "node" "composer" "redis")
    local count=${#components[@]}
    local i=0

    for component in "${components[@]}"; do
        local version
        version=$(get_installed_version "${component}")
        ((i++))

        if [[ $i -lt $count ]]; then
            echo "    \"${component}\": \"${version:-null}\","
        else
            echo "    \"${component}\": \"${version:-null}\""
        fi
    done

    echo "  },"
    echo "  \"versions_path\": \"${VITO_VERSIONS}\""
    echo "}"
}

check_for_updates() {
    echo ""
    echo "Version Comparison"
    echo "=================="
    echo ""

    # Define target versions (these would typically come from environment or config)
    local target_frankenphp="${FRANKENPHP_VERSION:-1.11.1}"
    local target_php="${PHP_VERSION:-8.4.17}"
    local target_node="${NODE_VERSION:-20.18.1}"
    local target_composer="${COMPOSER_VERSION:-2.8.4}"
    local target_redis="${REDIS_VERSION:-7.4.2}"

    check_component "frankenphp" "${target_frankenphp}"
    check_component "php" "${target_php}"
    check_component "node" "${target_node}"
    check_component "composer" "${target_composer}"
    check_component "redis" "${target_redis}"

    echo ""
}

check_component() {
    local name="$1"
    local target="$2"

    local installed
    installed=$(get_installed_version "${name}")

    if [[ -z "${installed}" ]]; then
        printf "  %-12s %-15s -> %-15s  %s\n" "${name}:" "(not installed)" "${target}" "[INSTALL NEEDED]"
    elif [[ "${installed}" != "${target}" ]]; then
        printf "  %-12s %-15s -> %-15s  %s\n" "${name}:" "${installed}" "${target}" "[UPDATE NEEDED]"
    else
        printf "  %-12s %-15s                    %s\n" "${name}:" "${installed}" "[OK]"
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    local action="show"
    local format="text"

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --check)
                action="check"
                shift
                ;;
            --json)
                format="json"
                shift
                ;;
            *)
                shift
                ;;
        esac
    done

    case "${action}" in
        show)
            if [[ "${format}" == "json" ]]; then
                show_versions_json
            else
                show_versions
            fi
            ;;
        check)
            check_for_updates
            ;;
    esac
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
