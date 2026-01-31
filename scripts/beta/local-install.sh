#!/bin/bash
set -e

# =============================================================================
# Parse Command Line Arguments (early, before banner)
# =============================================================================
BUILD_LOCAL="N"
VITO_REPO="${VITO_REPO:-https://github.com/vitodeploy/vito}"
VITO_BRANCH="${VITO_BRANCH:-3.x}"
DOCKER_IMAGE="${DOCKER_IMAGE:-vitodeploy/vito:latest}"
SHOW_HELP="N"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --build-local)
            BUILD_LOCAL="Y"
            DOCKER_IMAGE="vitodeploy/vito:local"
            shift
            ;;
        --repo=*)
            VITO_REPO="${1#*=}"
            shift
            ;;
        --branch=*)
            VITO_BRANCH="${1#*=}"
            shift
            ;;
        --image=*)
            DOCKER_IMAGE="${1#*=}"
            shift
            ;;
        --help|-h)
            SHOW_HELP="Y"
            shift
            ;;
        *)
            shift
            ;;
    esac
done

if [[ "${SHOW_HELP}" == "Y" ]]; then
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  --build-local       Build Docker image from source instead of pulling from DockerHub"
    echo "  --repo=URL          Git repository URL (default: https://github.com/vitodeploy/vito)"
    echo "  --branch=NAME       Branch to build from (default: 3.x)"
    echo "  --image=NAME        Docker image to use (default: vitodeploy/vito:latest)"
    echo "  --help              Show this help message"
    echo ""
    echo "Environment Variables:"
    echo "  VITO_ACCEPT_BETA=yes    Skip beta warning confirmation"
    echo "  VITO_DOMAIN=domain      Pre-set domain name"
    echo "  V_ADMIN_EMAIL=email     Pre-set admin email"
    echo "  V_ADMIN_PASSWORD=pass   Pre-set admin password"
    echo "  WEBSERVER=nginx|caddy   Pre-set web server choice"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Standard install from DockerHub"
    echo "  $0 --build-local                      # Build from source (3.x branch)"
    echo "  $0 --build-local --branch=main        # Build from main branch"
    echo "  $0 --image=myregistry/vito:custom     # Use custom image"
    echo ""
    exit 0
fi

echo "
 __      ___ _        _____             _
 \ \    / (_) |      |  __ \           | |
  \ \  / / _| |_ ___ | |  | | ___ _ __ | | ___  _   _
   \ \/ / | | __/ _ \| |  | |/ _ \ '_ \| |/ _ \| | | |
    \  /  | | || (_) | |__| |  __/ |_) | | (_) | |_| |
     \/   |_|\__\___/|_____/ \___| .__/|_|\___/ \__, |
                                 | |             __/ |
                                 |_|            |___/

          Local Installation (Docker + Reverse Proxy)
"

# =============================================================================
# Beta Warning
# =============================================================================
echo "========================================"
echo "       BETA INSTALLATION METHOD"
echo "========================================"
echo ""
echo "WARNING: The local installation method is currently in BETA."
echo "  - This install approach is in active development"
echo "  - May contain bugs or incomplete features"
echo "  - Configuration format may change between versions"
echo "  - Not recommended for production systems"
echo ""
echo "REQUIREMENTS:"
echo "  - Docker installed and running"
echo "  - Ports 80 and 443 available"
echo "  - 2GB RAM minimum (recommended)"
echo ""
echo "For production systems, please use:"
echo "  - Standard installer: https://vitodeploy.com/introduction/installation.html"
echo "  - Docker installer:   https://vitodeploy.com/introduction/installation.html#docker"
echo ""

if [[ "${BUILD_LOCAL}" == "Y" ]]; then
    echo "BUILD MODE: Building Docker image from source"
    echo "  Repository: ${VITO_REPO}"
    echo "  Branch:     ${VITO_BRANCH}"
    echo ""
fi

# Allow skipping confirmation via environment variable (for automated testing)
if [[ "${VITO_ACCEPT_BETA:-}" != "yes" ]]; then
    printf "Do you accept these risks and wish to continue? (yes/no): "
    read -r ACCEPT_BETA </dev/tty
    if [[ "${ACCEPT_BETA}" != "yes" ]]; then
        echo ""
        echo "Installation cancelled."
        exit 0
    fi
fi
echo ""

# =============================================================================
# Configuration
# =============================================================================
export VITO_LOCAL_REPO="${VITO_LOCAL_REPO:-RichardAnderson/vito-local}"
export VITO_DATA_DIR="${VITO_DATA_DIR:-/opt/vito}"
export COMPOSE_FILE="${VITO_DATA_DIR}/docker-compose.local.yml"
export DOCKER_IMAGE

# Scripts download configuration
SCRIPTS_REPO="${VITO_REPO}"
SCRIPTS_BRANCH="${VITO_BRANCH}"
SCRIPTS_DIR="${VITO_DATA_DIR}/scripts/beta/local"

# Defaults
DEFAULT_VITO_DOMAIN="localhost"
DEFAULT_V_ADMIN_EMAIL="admin@example.com"
DEFAULT_V_ADMIN_PASSWORD="password"
DEFAULT_WEBSERVER="nginx"

# =============================================================================
# Minimal Helper Functions
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

is_valid_ssl_domain() {
    local domain="$1"

    [[ "${domain}" == "localhost" ]] && return 1
    [[ "${domain}" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] && return 1
    [[ "${domain}" == *:* ]] && return 1

    return 0
}

# =============================================================================
# Architecture Detection
# =============================================================================
detect_architecture() {
    local arch
    arch=$(uname -m)
    case "$arch" in
        x86_64)
            export ARCH_SUFFIX="amd64"
            ;;
        aarch64|arm64)
            export ARCH_SUFFIX="arm64"
            ;;
        *)
            log_error "Unsupported architecture: $arch"
            exit 1
            ;;
    esac
}

# =============================================================================
# Docker Check
# =============================================================================
check_docker() {
    log "Checking Docker installation..."

    if ! command -v docker &>/dev/null; then
        log_error "Docker is not installed"
        echo ""
        echo "Please install Docker first:"
        echo "  https://docs.docker.com/engine/install/"
        echo ""
        echo "Quick install for Ubuntu:"
        echo "  curl -fsSL https://get.docker.com | sh"
        echo ""
        exit 1
    fi

    if ! docker info &>/dev/null; then
        log_error "Docker daemon is not running"
        echo ""
        echo "Please start Docker:"
        echo "  sudo systemctl start docker"
        echo ""
        exit 1
    fi

    if ! docker compose version &>/dev/null; then
        log_error "Docker Compose v2 is not available"
        echo ""
        echo "Please install Docker Compose v2:"
        echo "  https://docs.docker.com/compose/install/"
        echo ""
        exit 1
    fi

    log_success "Docker is installed and running"
}

# =============================================================================
# Download Scripts from GitHub
# =============================================================================
download_scripts() {
    log "Downloading installation scripts..."

    # Construct raw GitHub URL
    # Handle both github.com URLs and raw URLs
    local base_url
    if [[ "${SCRIPTS_REPO}" == *"github.com"* ]]; then
        # Convert github.com URL to raw.githubusercontent.com
        local repo_path
        repo_path=$(echo "${SCRIPTS_REPO}" | sed 's|https://github.com/||' | sed 's|\.git$||')
        base_url="https://raw.githubusercontent.com/${repo_path}/refs/heads/${SCRIPTS_BRANCH}/scripts/beta/local"
    else
        base_url="${SCRIPTS_REPO}/scripts/beta/local"
    fi

    mkdir -p "${SCRIPTS_DIR}"

    # List of scripts to download
    local scripts=(
        "common.sh"
        "docker.sh"
        "webserver.sh"
        "vito-service.sh"
        "build.sh"
        "update.sh"
        "versions.sh"
        "nginx-vhost.conf"
        "caddy-vhost.conf"
    )

    for script in "${scripts[@]}"; do
        log "  Downloading ${script}..."
        if ! curl -fsSL "${base_url}/${script}" -o "${SCRIPTS_DIR}/${script}"; then
            log_error "Failed to download ${script}"
            exit 1
        fi
    done

    # Make shell scripts executable
    chmod +x "${SCRIPTS_DIR}"/*.sh

    log_success "Scripts downloaded to ${SCRIPTS_DIR}"
}

# =============================================================================
# Locate or Download Scripts
# =============================================================================
setup_scripts() {
    # First, check if scripts exist relative to this script (local development)
    local script_path
    script_path="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

    if [[ -d "${script_path}/local" && -f "${script_path}/local/docker.sh" ]]; then
        SCRIPTS_DIR="${script_path}/local"
        log "Using local scripts from: ${SCRIPTS_DIR}"
    elif [[ -d "${VITO_DATA_DIR}/scripts/beta/local" && -f "${VITO_DATA_DIR}/scripts/beta/local/docker.sh" ]]; then
        SCRIPTS_DIR="${VITO_DATA_DIR}/scripts/beta/local"
        log "Using cached scripts from: ${SCRIPTS_DIR}"
    else
        # Download scripts from GitHub
        download_scripts
    fi

    # Make scripts executable
    chmod +x "${SCRIPTS_DIR}"/*.sh 2>/dev/null || true
}

# =============================================================================
# Vito User Setup
# =============================================================================
setup_vito_user() {
    log "Setting up vito user..."

    # Generate a random password for the vito user
    VITO_USER_PASSWORD="${VITO_USER_PASSWORD:-$(openssl rand -base64 12)}"

    if ! id "vito" &>/dev/null; then
        # Create vito user
        useradd -m -s /bin/bash vito
        echo "vito:${VITO_USER_PASSWORD}" | chpasswd

        # Setup passwordless sudo
        cat > /etc/sudoers.d/vito <<EOF
# Vito user can run any command without password
vito ALL=(ALL) NOPASSWD: ALL
EOF
        chmod 440 /etc/sudoers.d/vito

        log_success "Created vito user with sudo access"
    else
        log "Vito user already exists"
    fi
}

# =============================================================================
# Build Docker Image from Source
# =============================================================================
build_local_image() {
    log "Building Docker image from source..."

    bash "${SCRIPTS_DIR}/build.sh" \
        --repo="${VITO_REPO}" \
        --branch="${VITO_BRANCH}" \
        --tag="${DOCKER_IMAGE}" \
        --clean

    log_success "Docker image built: ${DOCKER_IMAGE}"
}

# =============================================================================
# Input Collection
# =============================================================================
collect_configuration() {
    echo "Please provide the following configuration values."
    echo "Press Enter to accept the default value shown in brackets."
    echo ""

    # Domain
    while true; do
        if [[ -z "${VITO_DOMAIN}" ]]; then
            printf "Domain (without http/https) [%s]: " "${DEFAULT_VITO_DOMAIN}"
            read -r VITO_DOMAIN </dev/tty
            VITO_DOMAIN=${VITO_DOMAIN:-$DEFAULT_VITO_DOMAIN}
        fi
        if validate_domain "${VITO_DOMAIN}"; then
            export VITO_DOMAIN
            break
        fi
        unset VITO_DOMAIN
    done
    echo "  Domain: ${VITO_DOMAIN}"

    # Admin email
    while true; do
        if [[ -z "${V_ADMIN_EMAIL}" ]]; then
            printf "Admin email address [%s]: " "${DEFAULT_V_ADMIN_EMAIL}"
            read -r V_ADMIN_EMAIL </dev/tty
            V_ADMIN_EMAIL=${V_ADMIN_EMAIL:-$DEFAULT_V_ADMIN_EMAIL}
        fi
        if validate_email "${V_ADMIN_EMAIL}"; then
            export V_ADMIN_EMAIL
            break
        fi
        unset V_ADMIN_EMAIL
    done
    echo "  Admin Email: ${V_ADMIN_EMAIL}"

    # Admin password
    if [[ -z "${V_ADMIN_PASSWORD}" ]]; then
        printf "Admin password [%s]: " "${DEFAULT_V_ADMIN_PASSWORD}"
        read -r V_ADMIN_PASSWORD </dev/tty
        export V_ADMIN_PASSWORD=${V_ADMIN_PASSWORD:-$DEFAULT_V_ADMIN_PASSWORD}
    fi
    echo "  Admin Password: [set]"

    # Web server choice
    if [[ -z "${WEBSERVER}" ]]; then
        echo ""
        echo "Choose your web server for reverse proxy:"
        echo "  1) nginx (default) - Uses certbot for SSL"
        echo "  2) caddy - Automatic SSL handling"
        printf "Web server [1]: "
        read -r WEBSERVER_CHOICE </dev/tty
        case "${WEBSERVER_CHOICE}" in
            2|caddy)
                WEBSERVER="caddy"
                ;;
            *)
                WEBSERVER="nginx"
                ;;
        esac
    fi
    export WEBSERVER
    echo "  Web Server: ${WEBSERVER}"

    # Determine APP_URL based on domain
    if is_valid_ssl_domain "${VITO_DOMAIN}"; then
        export VITO_APP_URL="https://${VITO_DOMAIN}"
        export ENABLE_SSL="Y"
    else
        export VITO_APP_URL="http://${VITO_DOMAIN}"
        export ENABLE_SSL="N"
    fi
    echo "  App URL: ${VITO_APP_URL}"
    echo ""
}

# =============================================================================
# Installation Functions
# =============================================================================
install_vito_root_service() {
    log "Installing vito-root-service..."
    bash "${SCRIPTS_DIR}/vito-service.sh" --repo="${VITO_LOCAL_REPO}"
}

install_webserver() {
    log "Installing ${WEBSERVER}..."
    bash "${SCRIPTS_DIR}/webserver.sh" install --type="${WEBSERVER}"
}

configure_webserver() {
    log "Configuring ${WEBSERVER} as reverse proxy..."
    bash "${SCRIPTS_DIR}/webserver.sh" configure --type="${WEBSERVER}" --domain="${VITO_DOMAIN}"
}

configure_firewall() {
    log "Configuring firewall..."
    bash "${SCRIPTS_DIR}/webserver.sh" firewall
}

pull_docker_image() {
    log "Pulling Vito Docker image: ${DOCKER_IMAGE}..."
    bash "${SCRIPTS_DIR}/docker.sh" pull "${DOCKER_IMAGE}"
}

generate_docker_compose() {
    log "Generating docker-compose configuration..."

    # Generate APP_KEY
    APP_KEY="base64:$(openssl rand -base64 32)"

    # Export DOCKER_IMAGE so docker.sh can use it
    export DOCKER_IMAGE

    bash "${SCRIPTS_DIR}/docker.sh" generate \
        --name="Admin" \
        --email="${V_ADMIN_EMAIL}" \
        --password="${V_ADMIN_PASSWORD}" \
        --key="${APP_KEY}" \
        --url="${VITO_APP_URL}" \
        --output="${COMPOSE_FILE}"
}

start_docker_container() {
    log "Starting Vito container..."
    bash "${SCRIPTS_DIR}/docker.sh" start "${COMPOSE_FILE}"
}

wait_for_container() {
    log "Waiting for container to be healthy..."
    bash "${SCRIPTS_DIR}/docker.sh" wait 120
}

obtain_ssl_certificate() {
    if [[ "${ENABLE_SSL}" != "Y" ]]; then
        log "Skipping SSL (localhost or IP address)"
        return 0
    fi

    log "Obtaining SSL certificate..."
    bash "${SCRIPTS_DIR}/webserver.sh" ssl \
        --type="${WEBSERVER}" \
        --domain="${VITO_DOMAIN}" \
        --email="${V_ADMIN_EMAIL}"
}

create_local_server() {
    log "Creating local server entry in Vito..."
    bash "${SCRIPTS_DIR}/docker.sh" create-server || true
}

# =============================================================================
# Acquire Lock (prevent concurrent runs)
# =============================================================================
LOCK_FILE="/var/lock/vito-install.lock"
mkdir -p "$(dirname "${LOCK_FILE}")"
exec 200>"${LOCK_FILE}"
if ! flock -n 200; then
    log_error "Another installation is already in progress"
    exit 1
fi

# =============================================================================
# Main Installation
# =============================================================================

# Detect architecture
detect_architecture

# Check Docker is installed
check_docker

# Setup scripts (download if needed)
setup_scripts

# Collect configuration
collect_configuration

echo "========================================"
echo "    Starting Installation"
echo "========================================"
echo ""

# Install prerequisites
log "Installing system prerequisites..."
apt-get update
apt-get install -y curl openssl git

# Step 1: Setup vito user
setup_vito_user

# Step 2: Install vito-root-service
install_vito_root_service

# Step 3: Install web server
install_webserver

# Step 4: Get Docker image (build or pull)
if [[ "${BUILD_LOCAL}" == "Y" ]]; then
    build_local_image
else
    pull_docker_image
fi

# Step 5: Generate docker-compose.yml
generate_docker_compose

# Step 6: Configure web server as reverse proxy
configure_webserver

# Step 7: Configure firewall
configure_firewall

# Step 8: Start container
start_docker_container

# Step 9: Wait for container to be healthy
wait_for_container

# Step 10: Obtain SSL certificate (if applicable)
obtain_ssl_certificate

# Step 11: Create local server entry
create_local_server

# =============================================================================
# Final Summary
# =============================================================================
echo ""
echo "========================================"
echo "    Installation Complete!"
echo "========================================"
echo ""
echo "You can access Vito at: ${VITO_APP_URL}"
echo ""
echo "Credentials:"
echo "  Admin Email:    ${V_ADMIN_EMAIL}"
echo "  Admin Password: ${V_ADMIN_PASSWORD}"
echo ""
echo "Services:"
echo "  Web Server:     systemctl status ${WEBSERVER}"
echo "  Docker:         docker compose -f ${COMPOSE_FILE} ps"
echo "  Root Service:   systemctl status vito-root.socket"
echo ""
echo "Container Logs:"
echo "  docker compose -f ${COMPOSE_FILE} logs -f"
echo ""
echo "Useful Commands:"
echo "  Stop:    docker compose -f ${COMPOSE_FILE} down"
echo "  Start:   docker compose -f ${COMPOSE_FILE} up -d"
echo "  Restart: docker compose -f ${COMPOSE_FILE} restart"
echo ""

if [[ "${ENABLE_SSL}" == "Y" ]]; then
    echo "SSL Certificate:"
    if [[ "${WEBSERVER}" == "nginx" ]]; then
        echo "  Location: /etc/letsencrypt/live/${VITO_DOMAIN}/"
        echo "  Renewal:  certbot renew --dry-run"
    else
        echo "  Caddy manages SSL automatically"
    fi
    echo ""
fi

if [[ "${BUILD_LOCAL}" == "Y" ]]; then
    echo "Build Info:"
    echo "  Image:  ${DOCKER_IMAGE}"
    echo "  Source: ${VITO_REPO} (${VITO_BRANCH})"
    echo ""
fi

echo "Installation paths:"
echo "  Compose File: ${COMPOSE_FILE}"
echo "  Scripts:      ${SCRIPTS_DIR}"
echo "  Socket:       /run/vito-root.sock"
echo ""
echo "Local Server:"
echo "  A local server entry has been created in Vito."
echo "  You can manage it from the Vito dashboard."
echo ""

# Save credentials securely
CREDS_FILE="${VITO_DATA_DIR}/.credentials"
(
    umask 077
    cat > "${CREDS_FILE}" <<EOF
# Vito Installation Credentials
# Generated: $(date)
# DELETE THIS FILE AFTER NOTING THE CREDENTIALS

ADMIN_EMAIL=${V_ADMIN_EMAIL}
ADMIN_PASSWORD=${V_ADMIN_PASSWORD}
APP_URL=${VITO_APP_URL}
WEBSERVER=${WEBSERVER}
DOCKER_IMAGE=${DOCKER_IMAGE}
BUILD_LOCAL=${BUILD_LOCAL}
EOF
)
chmod 600 "${CREDS_FILE}"
echo "Credentials saved to: ${CREDS_FILE} (root access only)"
echo "Please save these credentials and delete the file."
echo ""
