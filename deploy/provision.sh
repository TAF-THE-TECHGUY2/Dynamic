#!/usr/bin/env bash
#
# One-time provisioning for a fresh Ubuntu 24.04 EC2 instance.
#
# From the AWS console (EC2 -> Connect -> Session Manager), no SSH required:
#
#   curl -fsSL https://raw.githubusercontent.com/TAF-THE-TECHGUY2/Dynamic/main/deploy/provision.sh -o /tmp/provision.sh
#   sudo bash /tmp/provision.sh
#
# Or over SSH:
#
#   scp deploy/provision.sh ubuntu@<host>:~
#   ssh ubuntu@<host> 'sudo bash provision.sh'
#
# Safe to re-run: every step checks before it acts.
#
# What it does NOT do: write .env, or run the first deploy. Those come after,
# because .env holds secrets that should never be in a repo or a shell history.

set -euo pipefail

APP_USER="${APP_USER:-www-data}"
APP_DIR="${APP_DIR:-/var/www/dynamic}"
REPO="${REPO:-https://github.com/TAF-THE-TECHGUY2/Dynamic.git}"
DB_NAME="${DB_NAME:-dynamic_solutions}"
DB_USER="${DB_USER:-dynamic}"
PHP_VERSION="8.3"

log() { printf '\n\033[1;31m==>\033[0m %s\n' "$1"; }

if [[ $EUID -ne 0 ]]; then
    echo "Run with sudo: sudo bash provision.sh" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Swap
#
# A t3.small has 2GB. Headless Chromium peaks well over 500MB while rendering a
# PDF, and it will be doing that alongside PHP-FPM and Postgres. Without swap
# the kernel kills the queue worker mid-render and the only symptom is invoices
# that never arrive.
# ---------------------------------------------------------------------------
if ! swapon --show | grep -q '/swapfile'; then
    log "Creating a 2GB swap file"
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    sysctl -w vm.swappiness=10
    echo 'vm.swappiness=10' > /etc/sysctl.d/99-swappiness.conf
else
    log "Swap already configured, skipping"
fi

log "Updating apt"
export DEBIAN_FRONTEND=noninteractive
# Stops needrestart opening its service-restart dialog and stalling the run.
export NEEDRESTART_MODE=a
apt-get update -qq
apt-get upgrade -y -qq

# ---------------------------------------------------------------------------
# PHP, Postgres, nginx
#
# Ubuntu 24.04 ships PHP 8.3 in main, so no third-party PPA is needed.
# bcmath is not optional here — App\Support\Dec does every cent of the money
# arithmetic with it.
# ---------------------------------------------------------------------------
log "Installing PHP ${PHP_VERSION}, PostgreSQL 16, nginx"
apt-get install -y -qq \
    nginx \
    postgresql postgresql-contrib \
    php${PHP_VERSION}-fpm php${PHP_VERSION}-cli \
    php${PHP_VERSION}-pgsql php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-gd php${PHP_VERSION}-intl \
    git unzip curl acl certbot python3-certbot-nginx

# Debian's packaging installs the module but does not always enable it for every
# SAPI, so enable each explicitly. phpenmod is a no-op when it is already on.
log "Enabling PHP extensions"
for mod in bcmath mbstring gd intl zip curl xml pgsql; do
    phpenmod -v "${PHP_VERSION}" "$mod" 2>/dev/null || true
done
systemctl restart "php${PHP_VERSION}-fpm" 2>/dev/null || true

log "Verifying the extensions the app actually needs"
MISSING=()
for ext in pdo_pgsql bcmath mbstring gd intl zip; do
    if php -m | grep -qi "^${ext}$"; then
        echo "  ok   ${ext}"
    else
        echo "  MISSING ${ext}"
        MISSING+=("$ext")
    fi
done

if (( ${#MISSING[@]} > 0 )); then
    # One retry with the packages named directly. bcmath in particular is not
    # optional: App\Support\Dec does every cent of the money arithmetic with it,
    # so a server without it would compute wrong totals rather than fail loudly.
    log "Installing the missing extensions directly: ${MISSING[*]}"

    for ext in "${MISSING[@]}"; do
        pkg="php${PHP_VERSION}-${ext#pdo_}"
        apt-get install -y "$pkg" || echo "  could not install ${pkg}" >&2
        phpenmod -v "${PHP_VERSION}" "${ext#pdo_}" 2>/dev/null || true
    done

    systemctl restart "php${PHP_VERSION}-fpm" 2>/dev/null || true

    STILL_MISSING=()
    for ext in "${MISSING[@]}"; do
        php -m | grep -qi "^${ext}$" || STILL_MISSING+=("$ext")
    done

    if (( ${#STILL_MISSING[@]} > 0 )); then
        cat >&2 <<FAIL

Cannot continue: PHP is missing ${STILL_MISSING[*]}.

Diagnose with:
    dpkg -l | grep -E 'php${PHP_VERSION}-(bcmath|mbstring|gd|intl|zip|pgsql)'
    apt-cache policy php${PHP_VERSION}-bcmath
    php -i | grep -i 'Loaded Configuration|Scan this dir'

If the package is genuinely unavailable, the universe repository is probably
not enabled:
    sudo add-apt-repository -y universe && sudo apt-get update

FAIL
        exit 1
    fi
fi

# ---------------------------------------------------------------------------
# Node and Composer
# ---------------------------------------------------------------------------
if ! command -v node >/dev/null 2>&1; then
    log "Installing Node 22"
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y -qq nodejs
else
    log "Node already present: $(node -v)"
fi

if ! command -v composer >/dev/null 2>&1; then
    log "Installing Composer"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
else
    log "Composer already present: $(composer --version)"
fi

# ---------------------------------------------------------------------------
# Chromium for the PDFs
#
# The single most common failure on this stack. Snap-packaged Chromium is
# confined and cannot read the temporary file Browsershot renders from, so the
# puppeteer-managed build is used instead.
# ---------------------------------------------------------------------------
log "Installing headless Chromium and its libraries"
apt-get install -y -qq \
    libnss3 libatk-bridge2.0-0 libatk1.0-0 libcups2 libdrm2 libxkbcommon0 \
    libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libpango-1.0-0 \
    libcairo2 libasound2t64 libatspi2.0-0 fonts-liberation

export PUPPETEER_CACHE_DIR=/opt/puppeteer
mkdir -p "$PUPPETEER_CACHE_DIR"
npx --yes puppeteer@latest browsers install chrome >/tmp/chrome-install.log 2>&1 || {
    cat /tmp/chrome-install.log >&2
    exit 1
}

CHROME_PATH="$(find /opt/puppeteer -type f -name chrome -perm -u+x | head -1)"
if [[ -z "$CHROME_PATH" ]]; then
    echo "Chrome installed but the binary could not be located under /opt/puppeteer" >&2
    exit 1
fi
chmod -R a+rx /opt/puppeteer
log "Chrome installed at: ${CHROME_PATH}"
echo "$CHROME_PATH" > /etc/dynamic-chrome-path

# ---------------------------------------------------------------------------
# Database
# ---------------------------------------------------------------------------
log "Creating the database and role"
DB_PASS_FILE=/etc/dynamic-db-password

if [[ ! -f "$DB_PASS_FILE" ]]; then
    openssl rand -base64 32 | tr -d '/+=' | head -c 32 > "$DB_PASS_FILE"
    chmod 600 "$DB_PASS_FILE"
fi
DB_PASS="$(cat "$DB_PASS_FILE")"

sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 \
    || sudo -u postgres psql -qc "CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASS}';"
sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 \
    || sudo -u postgres createdb -O "${DB_USER}" "${DB_NAME}"

log "Database password written to ${DB_PASS_FILE} (root only)"

# ---------------------------------------------------------------------------
# Application directory
# ---------------------------------------------------------------------------
if [[ ! -d "${APP_DIR}/.git" ]]; then
    log "Cloning ${REPO}"
    mkdir -p "$(dirname "$APP_DIR")"
    git clone "$REPO" "$APP_DIR"
else
    log "Repository already cloned at ${APP_DIR}"
fi

git config --global --add safe.directory "$APP_DIR"
chown -R "${APP_USER}:${APP_USER}" "$APP_DIR"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} \; 2>/dev/null || true

# www-data's home is /var/www and is not writable by it, so npm and composer
# have nowhere to put their caches and the deploy fails on a permissions error
# that reads like something else entirely.
log "Giving ${APP_USER} writable cache directories"
mkdir -p /var/www/.npm /var/www/.composer /var/www/.cache
chown -R "${APP_USER}:${APP_USER}" /var/www/.npm /var/www/.composer /var/www/.cache

# Both npm ci (puppeteer's postinstall) and Browsershot look here. Without it
# every deploy re-downloads ~150MB of Chrome into a directory nothing can read.
if ! grep -q PUPPETEER_CACHE_DIR /etc/environment; then
    echo 'PUPPETEER_CACHE_DIR=/opt/puppeteer' >> /etc/environment
fi

# ---------------------------------------------------------------------------
# nginx
# ---------------------------------------------------------------------------
log "Configuring nginx"
sed -e "s|__APP_DIR__|${APP_DIR}|g" -e "s|__PHP_VERSION__|${PHP_VERSION}|g" \
    "${APP_DIR}/deploy/nginx.conf" > /etc/nginx/sites-available/dynamic
ln -sf /etc/nginx/sites-available/dynamic /etc/nginx/sites-enabled/dynamic
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

# ---------------------------------------------------------------------------
# Queue worker and scheduler
#
# systemd rather than Supervisor: it is already running, and Restart=always
# gives the same guarantee with one less moving part.
# ---------------------------------------------------------------------------
log "Installing the queue worker service"
sed -e "s|__APP_DIR__|${APP_DIR}|g" -e "s|__APP_USER__|${APP_USER}|g" \
    "${APP_DIR}/deploy/dynamic-queue.service" > /etc/systemd/system/dynamic-queue.service
systemctl daemon-reload
systemctl enable dynamic-queue

log "Installing the scheduler cron entry"
cat > /etc/cron.d/dynamic-scheduler <<CRON
# Drives documents:expire (hourly) and the queue housekeeping.
* * * * * ${APP_USER} cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/dynamic-scheduler

# ---------------------------------------------------------------------------
# Firewall
# ---------------------------------------------------------------------------
log "Configuring ufw"
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null

cat <<SUMMARY

$(printf '\033[1;31m%s\033[0m' '=== Provisioning complete ===')

Next, on the server:

  1. Write the environment file:
         sudo -u ${APP_USER} cp ${APP_DIR}/.env.example ${APP_DIR}/.env
         sudo -u ${APP_USER} nano ${APP_DIR}/.env

     Database password : $(cat "$DB_PASS_FILE")
     Chrome path       : ${CHROME_PATH}

     Every value reading TODO is printed on customer documents. Fill them in.

  2. Deploy:
         cd ${APP_DIR} && sudo -u ${APP_USER} bash deploy/deploy.sh --first-run

  3. TLS, once DNS points here:
         sudo certbot --nginx -d your-domain.co.za -d www.your-domain.co.za

SUMMARY
