#!/bin/bash
# =============================================================================
# Build Vito Docker Image from Source
# Usage: ./build.sh [options]
#
# This script clones the Vito repository and builds the Docker image locally.
# Useful for development or when the DockerHub image is not up to date.
#
# Options:
#   --repo=URL       Git repository URL (default: https://github.com/vitodeploy/vito)
#   --branch=NAME    Branch to build from (default: 3.x)
#   --tag=NAME       Docker image tag (default: vitodeploy/vito:local)
#   --no-cache       Build without Docker cache
#   --clean          Remove source after building
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "${SCRIPT_DIR}/common.sh" ]]; then
    source "${SCRIPT_DIR}/common.sh"
else
    # Minimal logging if common.sh not available
    log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"; }
    log_error() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2; }
    log_success() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1"; }
fi

# =============================================================================
# Configuration
# =============================================================================
DEFAULT_REPO="https://github.com/vitodeploy/vito"
DEFAULT_BRANCH="3.x"
DEFAULT_TAG="vitodeploy/vito:local"
BUILD_DIR="/opt/vito/source"

# =============================================================================
# Build Functions
# =============================================================================

clone_repository() {
    local repo="$1"
    local branch="$2"
    local dest="$3"

    log "Cloning repository: ${repo} (branch: ${branch})..."

    # Remove existing source if present
    if [[ -d "${dest}" ]]; then
        log "Removing existing source directory..."
        rm -rf "${dest}"
    fi

    mkdir -p "$(dirname "${dest}")"

    git clone --depth 1 --branch "${branch}" "${repo}.git" "${dest}"

    log_success "Repository cloned to ${dest}"
}

build_image() {
    local source_dir="$1"
    local tag="$2"
    local no_cache="$3"

    log "Building Docker image: ${tag}..."

    local build_args=("-t" "${tag}")

    if [[ "${no_cache}" == "Y" ]]; then
        build_args+=("--no-cache")
    fi

    # Check for Dockerfile in docker/ directory
    local dockerfile_path="${source_dir}/docker/Dockerfile"
    if [[ ! -f "${dockerfile_path}" ]]; then
        log_error "Dockerfile not found at ${dockerfile_path}"
        return 1
    fi

    # Build from repo root with Dockerfile in docker/ directory
    cd "${source_dir}"
    docker build "${build_args[@]}" -f "${dockerfile_path}" .

    log_success "Docker image built: ${tag}"
}

cleanup_source() {
    local source_dir="$1"

    log "Cleaning up source directory..."
    rm -rf "${source_dir}"
    log_success "Source directory removed"
}

show_usage() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Build Vito Docker image from source."
    echo ""
    echo "Options:"
    echo "  --repo=URL       Git repository URL"
    echo "                   Default: ${DEFAULT_REPO}"
    echo "  --branch=NAME    Branch to build from"
    echo "                   Default: ${DEFAULT_BRANCH}"
    echo "  --tag=NAME       Docker image tag"
    echo "                   Default: ${DEFAULT_TAG}"
    echo "  --no-cache       Build without Docker cache"
    echo "  --clean          Remove source directory after building"
    echo "  --help           Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0"
    echo "  $0 --branch=main --tag=vitodeploy/vito:dev"
    echo "  $0 --repo=https://github.com/myuser/vito --branch=my-feature"
    echo ""
}

# =============================================================================
# Main
# =============================================================================
main() {
    local repo="${DEFAULT_REPO}"
    local branch="${DEFAULT_BRANCH}"
    local tag="${DEFAULT_TAG}"
    local no_cache="N"
    local clean="N"

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --repo=*)
                repo="${1#*=}"
                shift
                ;;
            --branch=*)
                branch="${1#*=}"
                shift
                ;;
            --tag=*)
                tag="${1#*=}"
                shift
                ;;
            --no-cache)
                no_cache="Y"
                shift
                ;;
            --clean)
                clean="Y"
                shift
                ;;
            --help|-h)
                show_usage
                exit 0
                ;;
            *)
                shift
                ;;
        esac
    done

    echo ""
    echo "Build Configuration:"
    echo "  Repository: ${repo}"
    echo "  Branch:     ${branch}"
    echo "  Tag:        ${tag}"
    echo "  No Cache:   ${no_cache}"
    echo "  Clean:      ${clean}"
    echo ""

    # Check Docker is available
    if ! command -v docker &>/dev/null; then
        log_error "Docker is not installed"
        exit 1
    fi

    if ! docker info &>/dev/null; then
        log_error "Docker daemon is not running"
        exit 1
    fi

    # Check git is available
    if ! command -v git &>/dev/null; then
        log_error "Git is not installed"
        exit 1
    fi

    # Clone repository
    clone_repository "${repo}" "${branch}" "${BUILD_DIR}"

    # Build image
    build_image "${BUILD_DIR}" "${tag}" "${no_cache}"

    # Cleanup if requested
    if [[ "${clean}" == "Y" ]]; then
        cleanup_source "${BUILD_DIR}"
    fi

    echo ""
    log_success "Build complete!"
    echo ""
    echo "Image: ${tag}"
    echo ""
    echo "To use this image with local-install.sh:"
    echo "  DOCKER_IMAGE=${tag} ./local-install.sh"
    echo ""
    echo "Or with --build-local flag:"
    echo "  ./local-install.sh --build-local --branch=${branch}"
    echo ""
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
