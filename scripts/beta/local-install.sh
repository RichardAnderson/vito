#!/bin/bash
set -e

echo "
 __      ___ _        _____             _
 \ \    / (_) |      |  __ \           | |
  \ \  / / _| |_ ___ | |  | | ___ _ __ | | ___  _   _
   \ \/ / | | __/ _ \| |  | |/ _ \ '_ \| |/ _ \| | | |
    \  /  | | || (_) | |__| |  __/ |_) | | (_) | |_| |
     \/   |_|\__\___/|_____/ \___| .__/|_|\___/ \__, |
                                 | |             __/ |
                                 |_|            |___/

          Self-Contained Local Installation
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
echo "MINIMUM REQUIREMENTS:"
echo "  - 2GB RAM (recommended)"
echo "  - 2 vCPUs (recommended)"
echo "  - This method uses additional server resources compared to other installers"
echo ""
echo "For production systems, please use:"
echo "  - Standard installer: https://vitodeploy.com/introduction/installation.html"
echo "  - Docker installer:   https://vitodeploy.com/introduction/installation.html#docker"
echo ""

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
# Configuration - Version Definitions
# =============================================================================
export VITO_REPO="${VITO_REPO:-https://github.com/RichardAnderson/vito}"
export VITO_BRANCH="${VITO_BRANCH:-feat/local-install-additions}"
export VITO_LOCAL_REPO="${VITO_LOCAL_REPO:-RichardAnderson/vito-local}"

# Component versions - change these to update components
export FRANKENPHP_VERSION="${FRANKENPHP_VERSION:-1.11.1}"
export PHP_VERSION="${PHP_VERSION:-8.4.17}"
export NODE_VERSION="${NODE_VERSION:-20.18.1}"
export REDIS_VERSION="${REDIS_VERSION:-7.4.2}"
export COMPOSER_VERSION="${COMPOSER_VERSION:-2.8.4}"

# Defaults
DEFAULT_VITO_PORT=3000
DEFAULT_VITO_DOMAIN="localhost"

# Directories
export VITO_HOME="/home/vito"
export VITO_LOCAL="${VITO_HOME}/.local"
export VITO_BIN="${VITO_LOCAL}/bin"
export VITO_DATA="${VITO_LOCAL}/data"
export VITO_LOGS="${VITO_LOCAL}/logs"
export VITO_APP="${VITO_HOME}/www"
export VITO_VERSIONS="${VITO_LOCAL}/versions"

# Scripts directory (set after we clone the repo)
SCRIPTS_DIR=""

# =============================================================================
# Minimal Helper Functions (before common.sh is available)
# =============================================================================
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
}

# Download with retry logic (needed before repo clone)
download() {
    local url="$1"
    local dest="$2"
    local retries=3
    local delay=5

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

# Cleanup temporary files
cleanup() {
    log "Cleaning up temporary files..."
    rm -f /tmp/vito-*.tar.gz /tmp/php-cli.tar.gz /tmp/node.tar.xz /tmp/redis.tar.gz 2>/dev/null || true
    rm -rf /tmp/redis-* /tmp/scripts /tmp/systemd /tmp/install.sh /tmp/uninstall.sh 2>/dev/null || true
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

is_valid_ssl_domain() {
    local domain="$1"

    [[ "${domain}" == "localhost" ]] && return 1
    [[ "${domain}" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] && return 1
    [[ "${domain}" == *:* ]] && return 1

    return 0
}

# =============================================================================
# Architecture Detection (needed before repo clone)
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
# User Setup (Stage 1 - Before repo clone)
# =============================================================================
setup_vito_user() {
    log "Setting up vito user..."

    if ! id "vito" &>/dev/null; then
        # Create password hash and pass via chpasswd to avoid process list exposure
        useradd -m -s /bin/bash vito
        echo "vito:${V_PASSWORD}" | chpasswd

        cat > /etc/sudoers.d/vito <<EOF
# Vito user can run any command without password
vito ALL=(ALL) NOPASSWD: ALL
EOF
        chmod 440 /etc/sudoers.d/vito
    fi

    # Create directory structure
    mkdir -p "${VITO_BIN}" "${VITO_DATA}" "${VITO_LOGS}" "${VITO_VERSIONS}"
    mkdir -p "${VITO_HOME}/.ssh"
    chown -R vito:vito "${VITO_HOME}"

    # Generate SSH keys for vito user (only if not exists)
    if [[ ! -f "${VITO_HOME}/.ssh/id_rsa" ]]; then
        su - vito -c "ssh-keygen -t rsa -N '' -f ~/.ssh/id_rsa" <<<y 2>/dev/null || true
    fi
}

# =============================================================================
# Repository Clone (Stage 2)
# =============================================================================
clone_vito_repository() {
    log "Cloning Vito repository from ${VITO_REPO} (branch: ${VITO_BRANCH})..."

    rm -rf "${VITO_APP}"
    git config --local core.fileMode false 2>/dev/null || git config --global core.fileMode false
    git clone -b "${VITO_BRANCH}" "${VITO_REPO}.git" "${VITO_APP}"

    cd "${VITO_APP}" || { log_error "Failed to cd to ${VITO_APP}"; return 1; }

    # Only checkout latest tag for release branches
    if [[ "${VITO_BRANCH}" =~ ^[0-9]+\.x$ ]] || [[ "${VITO_BRANCH}" == "main" ]] || [[ "${VITO_BRANCH}" == "master" ]]; then
        local latest_tag
        latest_tag=$(git tag -l --merged "${VITO_BRANCH}" --sort=-v:refname | head -n 1)
        if [[ -n "${latest_tag}" ]]; then
            log "Checking out tag ${latest_tag}..."
            git checkout "${latest_tag}"
        fi
    else
        log "Staying on branch ${VITO_BRANCH} (feature branch)"
    fi

    # Set permissions
    find "${VITO_APP}" -type d -exec chmod 755 {} \;
    find "${VITO_APP}" -type f -exec chmod 644 {} \;
    git config core.fileMode false

    # Make scripts executable
    chmod +x "${VITO_APP}/scripts/beta/local/"*.sh 2>/dev/null || true

    # Set scripts directory
    SCRIPTS_DIR="${VITO_APP}/scripts/beta/local"
    chown -R vito:vito "${VITO_HOME}"
}

# =============================================================================
# Component Installation (Stage 3 - Using modular scripts)
# =============================================================================
install_components() {
    log "Installing components using modular scripts..."

    # Export variables needed by component scripts
    export VITO_HOME VITO_LOCAL VITO_BIN VITO_DATA VITO_LOGS VITO_APP VITO_VERSIONS

    local force_flag=""
    if [[ "${REBUILD_DEPS}" == "Y" ]]; then
        force_flag="--force"
    fi

    # Install each component with version parameter
    log "Installing FrankenPHP..."
    bash "${SCRIPTS_DIR}/frankenphp.sh" --version="${FRANKENPHP_VERSION}" ${force_flag}

    log "Installing PHP CLI..."
    bash "${SCRIPTS_DIR}/php.sh" --version="${PHP_VERSION}" ${force_flag}

    log "Installing Node.js..."
    bash "${SCRIPTS_DIR}/nodejs.sh" --version="${NODE_VERSION}" ${force_flag}

    log "Installing Composer..."
    bash "${SCRIPTS_DIR}/composer.sh" --version="${COMPOSER_VERSION}" ${force_flag}

    log "Installing Redis..."
    bash "${SCRIPTS_DIR}/redis.sh" --version="${REDIS_VERSION}" --configure ${force_flag}

    log "Installing Vito Root Service..."
    bash "${SCRIPTS_DIR}/vito-service.sh" --repo="${VITO_LOCAL_REPO}"
}

# =============================================================================
# SSL Setup (Stage 4 - Optional)
# =============================================================================
setup_ssl() {
    if [[ "${ENABLE_SSL}" != "Y" ]]; then
        return 0
    fi

    log "Setting up SSL..."
    bash "${SCRIPTS_DIR}/ssl.sh" \
        --domain="${VITO_DOMAIN}" \
        --email="${V_ADMIN_EMAIL}" \
        --port="${VITO_PORT}"
}

# =============================================================================
# Firewall Setup (Stage 5)
# =============================================================================
setup_firewall() {
    log "Configuring firewall..."

    local ssl_flag=""
    if [[ "${ENABLE_SSL}" == "Y" ]]; then
        ssl_flag="--ssl"
    fi

    bash "${SCRIPTS_DIR}/firewall.sh" --port="${VITO_PORT}" ${ssl_flag}
}

# =============================================================================
# Application Setup (Stage 6)
# =============================================================================
setup_application() {
    log "Setting up Vito application..."

    local ssl_flag=""
    if [[ "${ENABLE_SSL}" == "Y" ]]; then
        ssl_flag="--ssl"
    fi

    # Pass password via environment variable to avoid process list exposure
    VITO_ADMIN_PASSWORD="${V_ADMIN_PASSWORD}" bash "${SCRIPTS_DIR}/app.sh" \
        --url="${VITO_APP_URL}" \
        --email="${V_ADMIN_EMAIL}" \
        --password-env=VITO_ADMIN_PASSWORD \
        --domain="${VITO_DOMAIN}" \
        --port="${VITO_PORT}" \
        --create-server \
        ${ssl_flag}
}

# =============================================================================
# Services Setup (Stage 7)
# =============================================================================
setup_services() {
    log "Configuring and starting services..."

    local ssl_flag=""
    if [[ "${ENABLE_SSL}" == "Y" ]]; then
        ssl_flag="--ssl"
    fi

    bash "${SCRIPTS_DIR}/systemd.sh" --port="${VITO_PORT}" ${ssl_flag}
    bash "${SCRIPTS_DIR}/systemd.sh" --start
}

# =============================================================================
# Save Credentials Securely
# =============================================================================
save_credentials() {
    local creds_file="${VITO_HOME}/.vito-credentials"

    log "Saving credentials securely..."

    # Use umask to create file with restricted permissions from the start
    (
        umask 077
        cat > "${creds_file}" <<EOF
# Vito Installation Credentials
# Generated: $(date)
# DELETE THIS FILE AFTER NOTING THE CREDENTIALS

SSH_USER=vito
SSH_PASSWORD=${V_PASSWORD}
ADMIN_EMAIL=${V_ADMIN_EMAIL}
ADMIN_PASSWORD=${V_ADMIN_PASSWORD}
APP_URL=${VITO_APP_URL}
SSL_ENABLED=${ENABLE_SSL}

# Component Versions
FRANKENPHP_VERSION=${FRANKENPHP_VERSION}
PHP_VERSION=${PHP_VERSION}
NODE_VERSION=${NODE_VERSION}
COMPOSER_VERSION=${COMPOSER_VERSION}
REDIS_VERSION=${REDIS_VERSION}
EOF
    )
    chown root:root "${creds_file}"

    echo "Credentials saved to: ${creds_file} (root access only)"
    echo "Please save these credentials and delete the file."
}

# =============================================================================
# Acquire Lock (prevent concurrent runs)
# =============================================================================
LOCK_FILE="/var/lock/vito-install.lock"
exec 200>"${LOCK_FILE}"
if ! flock -n 200; then
    log_error "Another installation is already in progress"
    exit 1
fi

# =============================================================================
# Setup cleanup trap
# =============================================================================
trap cleanup EXIT

# =============================================================================
# Detect architecture early
# =============================================================================
detect_architecture

# =============================================================================
# Input Collection
# =============================================================================
echo "Please provide the following configuration values."
echo "Press Enter to accept the default value shown in brackets."
echo ""

# Generate defaults
DEFAULT_V_PASSWORD=$(openssl rand -base64 12)
DEFAULT_V_ADMIN_EMAIL="test@test.com"
DEFAULT_V_ADMIN_PASSWORD="password"

# SSH Password for vito user
if [[ -z "${V_PASSWORD}" ]]; then
    printf "SSH password for vito user [auto-generated]: "
    read -r V_PASSWORD </dev/tty
    export V_PASSWORD=${V_PASSWORD:-$DEFAULT_V_PASSWORD}
fi
echo "  SSH Password: [set]"

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

# Port
while true; do
    if [[ -z "${VITO_PORT}" ]]; then
        printf "Port (must be >= 1024 for non-root) [%s]: " "${DEFAULT_VITO_PORT}"
        read -r VITO_PORT </dev/tty
        VITO_PORT=${VITO_PORT:-$DEFAULT_VITO_PORT}
    fi
    if validate_port "${VITO_PORT}"; then
        export VITO_PORT
        break
    fi
    unset VITO_PORT
done
echo "  Port: ${VITO_PORT}"

# SSL - only ask if domain is valid for SSL
if is_valid_ssl_domain "${VITO_DOMAIN}"; then
    if [[ -z "${ENABLE_SSL}" ]]; then
        printf "Enable SSL with Let's Encrypt? (y/N) [N]: "
        read -r ENABLE_SSL </dev/tty
        ENABLE_SSL=${ENABLE_SSL:-N}
    fi
    if [[ "${ENABLE_SSL}" =~ ^[Yy]$ ]]; then
        export ENABLE_SSL="Y"
        export VITO_APP_URL="https://${VITO_DOMAIN}:${VITO_PORT}"
        echo "  SSL: Enabled"
    else
        export ENABLE_SSL="N"
        export VITO_APP_URL="http://${VITO_DOMAIN}:${VITO_PORT}"
        echo "  SSL: Disabled"
    fi
else
    export ENABLE_SSL="N"
    export VITO_APP_URL="http://${VITO_DOMAIN}:${VITO_PORT}"
fi
echo "  App URL: ${VITO_APP_URL}"

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
    printf "Admin password [auto-generated]: "
    read -r V_ADMIN_PASSWORD </dev/tty
    export V_ADMIN_PASSWORD=${V_ADMIN_PASSWORD:-$DEFAULT_V_ADMIN_PASSWORD}
fi
echo "  Admin Password: [set]"

# Rebuild dependencies
if [[ -z "${REBUILD_DEPS}" ]]; then
    printf "Rebuild all dependencies? (y/N) [N]: "
    read -r REBUILD_DEPS </dev/tty
    REBUILD_DEPS=${REBUILD_DEPS:-N}
fi
if [[ "${REBUILD_DEPS}" =~ ^[Yy]$ ]]; then
    export REBUILD_DEPS="Y"
    echo "  Rebuild Dependencies: Yes"
else
    export REBUILD_DEPS="N"
    echo "  Rebuild Dependencies: No (will skip already installed)"
fi

echo ""
echo "Component Versions:"
echo "  FrankenPHP: ${FRANKENPHP_VERSION}"
echo "  PHP CLI:    ${PHP_VERSION}"
echo "  Node.js:    ${NODE_VERSION}"
echo "  Composer:   ${COMPOSER_VERSION}"
echo "  Redis:      ${REDIS_VERSION}"
echo ""

# =============================================================================
# Main Installation
# =============================================================================
log "Installing minimal system prerequisites..."
apt-get update
apt-get install -y curl tar xz-utils git unzip build-essential ufw

# Stage 1: Setup vito user
setup_vito_user

# Stage 2: Clone repository (to get the modular scripts)
clone_vito_repository

# Stage 3: Install components using modular scripts
install_components

# Stage 4: SSL setup (if enabled)
setup_ssl

# Stage 5: Firewall configuration
setup_firewall

# Stage 6: Application setup
setup_application

# Stage 7: Services setup
setup_services

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
echo "  SSH User:       vito"
echo "  SSH Password:   ${V_PASSWORD}"
echo "  Admin Email:    ${V_ADMIN_EMAIL}"
echo "  Admin Password: ${V_ADMIN_PASSWORD}"
echo ""
echo "Component Versions Installed:"
echo "  FrankenPHP: ${FRANKENPHP_VERSION}"
echo "  PHP CLI:    ${PHP_VERSION}"
echo "  Node.js:    ${NODE_VERSION}"
echo "  Composer:   ${COMPOSER_VERSION}"
echo "  Redis:      ${REDIS_VERSION}"
echo ""
echo "Services:"
echo "  systemctl status vito-redis"
echo "  systemctl status vito-php"
echo "  systemctl status vito-worker"
if [[ "${ENABLE_SSL}" == "Y" ]]; then
    echo "  systemctl status nginx"
fi
echo ""
echo "Firewall Status:"
if [[ "${ENABLE_SSL}" == "Y" ]]; then
    ufw status | grep -E "^${VITO_PORT}|^22|^80"
else
    ufw status | grep -E "^${VITO_PORT}|^22"
fi
echo ""
if [[ "${ENABLE_SSL}" == "Y" ]]; then
    echo "SSL Certificate:"
    echo "  Certificate: /etc/letsencrypt/live/${VITO_DOMAIN}/fullchain.pem"
    echo "  Private Key: /etc/letsencrypt/live/${VITO_DOMAIN}/privkey.pem"
    echo "  Test renewal: certbot renew --dry-run"
    echo ""
fi
echo "Installation paths:"
echo "  App:      ${VITO_APP}"
echo "  Binaries: ${VITO_BIN}"
echo "  Logs:     ${VITO_LOGS}"
echo "  Data:     ${VITO_DATA}"
echo "  Scripts:  ${SCRIPTS_DIR}"
echo ""
echo "Local Server:"
echo "  A local server entry has been created in Vito."
echo "  You can manage it from the Vito dashboard."
echo ""
echo "To update components, modify the version environment variables and re-run:"
echo "  FRANKENPHP_VERSION=x.x.x PHP_VERSION=x.x.x ./local-install.sh"
echo ""

# Save credentials securely
save_credentials
echo ""
