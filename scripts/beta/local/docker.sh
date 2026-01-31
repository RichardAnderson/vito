#!/bin/bash
# =============================================================================
# Docker Container Management for Vito
# Usage: ./docker.sh <command> [options]
# Commands:
#   check      - Check if Docker is installed
#   pull       - Pull the vitodeploy/vito image
#   generate   - Generate docker-compose.local.yml
#   start      - Start the container
#   stop       - Stop the container
#   status     - Check container status
#   wait       - Wait for container to be healthy
# =============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# =============================================================================
# Configuration
# =============================================================================
DOCKER_IMAGE="${DOCKER_IMAGE:-vitodeploy/vito:latest}"
CONTAINER_NAME="${CONTAINER_NAME:-vito}"
COMPOSE_FILE="${COMPOSE_FILE:-/opt/vito/docker-compose.local.yml}"
VITO_DATA_DIR="${VITO_DATA_DIR:-/opt/vito}"

# =============================================================================
# Docker Check Functions
# =============================================================================
check_docker_installed() {
    if ! command -v docker &>/dev/null; then
        log_error "Docker is not installed"
        log_error "Please install Docker first: https://docs.docker.com/engine/install/"
        return 1
    fi

    # Check if docker daemon is running
    if ! docker info &>/dev/null; then
        log_error "Docker daemon is not running"
        log_error "Please start Docker: sudo systemctl start docker"
        return 1
    fi

    # Check if docker compose is available (v2)
    if ! docker compose version &>/dev/null; then
        log_error "Docker Compose v2 is not available"
        log_error "Please install Docker Compose v2: https://docs.docker.com/compose/install/"
        return 1
    fi

    log_success "Docker is installed and running"
    docker --version
    docker compose version
    return 0
}

# =============================================================================
# Image Management
# =============================================================================
pull_image() {
    local image="${1:-${DOCKER_IMAGE}}"

    log "Pulling Docker image: ${image}..."
    if docker pull "${image}"; then
        log_success "Image pulled successfully: ${image}"
        return 0
    else
        log_error "Failed to pull image: ${image}"
        return 1
    fi
}

# =============================================================================
# Compose File Generation
# =============================================================================
generate_compose() {
    local admin_name="${1:-Admin}"
    local admin_email="${2:-admin@example.com}"
    local admin_password="${3:-password}"
    local app_key="${4}"
    local app_url="${5:-http://localhost}"
    local output_file="${6:-${COMPOSE_FILE}}"

    # Generate APP_KEY if not provided
    if [[ -z "${app_key}" ]]; then
        app_key="base64:$(openssl rand -base64 32)"
    fi

    log "Generating docker-compose.local.yml..."

    # Ensure directory exists
    mkdir -p "$(dirname "${output_file}")"

    cat > "${output_file}" <<EOF
services:
  vito:
    image: ${DOCKER_IMAGE}
    container_name: ${CONTAINER_NAME}
    environment:
      NAME: "${admin_name}"
      EMAIL: "${admin_email}"
      PASSWORD: "${admin_password}"
      APP_KEY: "${app_key}"
      APP_URL: "${app_url}"
    volumes:
      - vito-storage:/var/www/html/storage
      - /run/vito-root.sock:/run/vito-root.sock
    ports:
      - "127.0.0.1:8080:80"
    extra_hosts:
      - "host.docker.internal:host-gateway"
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/up"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

volumes:
  vito-storage:
    driver: local
EOF

    chmod 600 "${output_file}"
    log_success "Compose file generated: ${output_file}"
}

# =============================================================================
# Container Management
# =============================================================================
start_container() {
    local compose_file="${1:-${COMPOSE_FILE}}"

    if [[ ! -f "${compose_file}" ]]; then
        log_error "Compose file not found: ${compose_file}"
        return 1
    fi

    log "Starting Vito container..."
    docker compose -f "${compose_file}" up -d

    log_success "Container started"
}

stop_container() {
    local compose_file="${1:-${COMPOSE_FILE}}"

    if [[ ! -f "${compose_file}" ]]; then
        log_error "Compose file not found: ${compose_file}"
        return 1
    fi

    log "Stopping Vito container..."
    docker compose -f "${compose_file}" down

    log_success "Container stopped"
}

container_status() {
    local compose_file="${1:-${COMPOSE_FILE}}"

    if [[ ! -f "${compose_file}" ]]; then
        log "Compose file not found: ${compose_file}"
        return 1
    fi

    docker compose -f "${compose_file}" ps
}

wait_for_healthy() {
    local max_wait="${1:-120}"
    local waited=0

    log "Waiting for container to be healthy (max ${max_wait}s)..."

    while [[ $waited -lt $max_wait ]]; do
        local health
        health=$(docker inspect --format='{{.State.Health.Status}}' "${CONTAINER_NAME}" 2>/dev/null || echo "not_found")

        case "${health}" in
            healthy)
                log_success "Container is healthy"
                return 0
                ;;
            unhealthy)
                log_error "Container is unhealthy"
                docker logs "${CONTAINER_NAME}" --tail 50
                return 1
                ;;
            starting)
                # Still starting, continue waiting
                ;;
            not_found)
                log_error "Container not found: ${CONTAINER_NAME}"
                return 1
                ;;
        esac

        sleep 2
        ((waited+=2))
        printf "."
    done

    echo ""
    log_error "Timeout waiting for container to be healthy"
    docker logs "${CONTAINER_NAME}" --tail 50
    return 1
}

# =============================================================================
# Local Server Creation
# =============================================================================
create_local_server() {
    local domain="${VITO_DOMAIN:-localhost}"
    local ssl_enabled="${ENABLE_SSL:-N}"
    local webserver="${WEBSERVER:-nginx}"

    log "Creating local server entry in Vito..."

    # Get the server's main public IP address
    local host_ip
    host_ip=$(ip route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}')

    # Fallback: try hostname -I
    if [[ -z "${host_ip}" ]]; then
        host_ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    fi

    if [[ -z "${host_ip}" ]]; then
        log "Could not determine server IP address"
        return 1
    fi

    log "Using server IP: ${host_ip}"

    # Build nginx flag
    local nginx_flag="N"
    if [[ "${webserver}" == "nginx" ]]; then
        nginx_flag="Y"
    fi

    # Execute artisan command inside the container to create local server
    if docker exec "${CONTAINER_NAME}" php artisan servers:create-local \
        "${host_ip}" \
        --name="localhost" \
        --domain="${domain}" \
        --ports="22,80,443" \
        --nginx="${nginx_flag}" \
        --ssl="${ssl_enabled}"; then
        log_success "Local server created"
        return 0
    else
        log "Local server may already exist or command failed"
        return 0
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    local command="${1:-}"
    shift || true

    case "${command}" in
        check)
            check_docker_installed
            ;;
        pull)
            pull_image "$@"
            ;;
        generate)
            # Parse named arguments
            local name="" email="" password="" key="" url="" output=""
            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --name=*) name="${1#*=}"; shift ;;
                    --email=*) email="${1#*=}"; shift ;;
                    --password=*) password="${1#*=}"; shift ;;
                    --key=*) key="${1#*=}"; shift ;;
                    --url=*) url="${1#*=}"; shift ;;
                    --output=*) output="${1#*=}"; shift ;;
                    *) shift ;;
                esac
            done
            generate_compose "${name}" "${email}" "${password}" "${key}" "${url}" "${output}"
            ;;
        start)
            start_container "$@"
            ;;
        stop)
            stop_container "$@"
            ;;
        status)
            container_status "$@"
            ;;
        wait)
            wait_for_healthy "$@"
            ;;
        create-server)
            create_local_server
            ;;
        *)
            echo "Usage: $0 <command> [options]"
            echo ""
            echo "Commands:"
            echo "  check              Check if Docker is installed"
            echo "  pull [image]       Pull the Docker image"
            echo "  generate           Generate docker-compose.local.yml"
            echo "    --name=NAME      Admin name"
            echo "    --email=EMAIL    Admin email"
            echo "    --password=PASS  Admin password"
            echo "    --key=KEY        APP_KEY (auto-generated if not provided)"
            echo "    --url=URL        APP_URL"
            echo "    --output=FILE    Output file path"
            echo "  start [file]       Start the container"
            echo "  stop [file]        Stop the container"
            echo "  status [file]      Show container status"
            echo "  wait [seconds]     Wait for container to be healthy"
            echo "  create-server      Create local server entry in Vito"
            exit 1
            ;;
    esac
}

# Run if executed directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
