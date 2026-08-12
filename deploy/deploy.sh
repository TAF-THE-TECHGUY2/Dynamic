#!/usr/bin/env bash
#
# Deploy Dynamic Solutions.
#
#   cd /var/www/dynamic && sudo -u www-data bash deploy/deploy.sh
#
# First time on a new server, after .env is filled in:
#
#   sudo -u www-data bash deploy/deploy.sh --first-run
#
# --first-run additionally generates the app key and seeds the grades, settings
# and owner account. It never seeds demo data.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FIRST_RUN=false

for arg in "$@"; do
    [[ "$arg" == "--first-run" ]] && FIRST_RUN=true
done

cd "$APP_DIR"

log() { printf '\n\033[1;31m==>\033[0m %s\n' "$1"; }

# `sudo -u www-data` leaves HOME pointing at the invoking user's home unless
# -H is passed, and www-data cannot write /home/ubuntu. Composer and npm both
# fail on their cache directories, and the deploy dies before vendor/ exists —
# which then surfaces as a 500 from a missing autoload.php, nowhere near the
# actual cause. Set them explicitly rather than depending on how we were called.
if [[ "$(id -un)" == "www-data" ]]; then
    export HOME=/var/www
    export COMPOSER_HOME=/var/www/.composer
    export npm_config_cache=/var/www/.npm
fi

if [[ ! -f .env ]]; then
    echo "No .env. Copy .env.example and fill it in before deploying." >&2
    exit 1
fi

log "Pulling"
git pull --ff-only origin "$(git rev-parse --abbrev-ref HEAD)"

log "Installing PHP dependencies"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if $FIRST_RUN && ! grep -qE '^APP_KEY=base64:' .env; then
    log "Generating the application key"
    php artisan key:generate --force
fi

# ---------------------------------------------------------------------------
# Front end
#
# The build is not optional and not only about the browser: the PDF renderer
# reads the compiled stylesheet off disk and inlines it, because Browsershot
# cannot fetch assets over HTTP. Skip this and every invoice comes out as
# unstyled HTML. See App\Support\InlineAssets.
# ---------------------------------------------------------------------------
log "Building assets"

# Reuse the Chrome provision.sh installed rather than pulling another copy.
export PUPPETEER_CACHE_DIR="${PUPPETEER_CACHE_DIR:-/opt/puppeteer}"
export PUPPETEER_SKIP_DOWNLOAD=true

npm ci --no-audit --no-fund
npm run build

if [[ ! -f public/build/manifest.json && ! -f public/build/.vite/manifest.json ]]; then
    echo "Build finished but no Vite manifest was produced — aborting." >&2
    exit 1
fi

# A stale hot file makes Blade point at a Vite dev server that is not running:
# the site loads unbundled TypeScript over a dead port and looks broken.
rm -f public/hot

log "Migrating"
php artisan migrate --force

if $FIRST_RUN; then
    log "Seeding grades, settings and the owner account"
    php artisan db:seed --force
fi

log "Caching configuration, routes and views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

log "Linking storage"
php artisan storage:link 2>/dev/null || true

log "Restarting the queue worker"
php artisan queue:restart
sudo systemctl restart dynamic-queue 2>/dev/null || \
    echo "  (could not restart dynamic-queue — run: sudo systemctl restart dynamic-queue)"

log "Checking the PDF renderer"
if php artisan tinker --execute='
    $d = App\Models\Document::query()->with("lines")->first();
    if (! $d) { echo "no documents yet — skipped\n"; exit; }
    $bytes = App\Jobs\RenderDocumentPdf::render($d);
    echo str_starts_with($bytes, "%PDF-")
        ? "ok — ".strlen($bytes)." bytes\n"
        : "NOT A PDF\n";
' 2>&1 | tail -2; then
    :
else
    echo "  PDF check failed — see DEPLOY.md, 'Chromium'." >&2
fi

log "Deployed: $(git rev-parse --short HEAD) on $(git rev-parse --abbrev-ref HEAD)"
