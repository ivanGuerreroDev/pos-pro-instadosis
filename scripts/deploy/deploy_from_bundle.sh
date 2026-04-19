#!/usr/bin/env bash
set -euo pipefail

# Deploy Laravel app from a prebuilt bundle uploaded by CI.
# This flow does not require git on the server.

APP_DIR="${APP_DIR:-/usr/share/nginx/html/pos-pro-instadosis}"
RELEASES_DIR="${RELEASES_DIR:-/opt/pos-pro-instadosis/releases}"
BUNDLE_PATH="${BUNDLE_PATH:-}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-https://pos.instadosis.com}"
STRICT_HEALTHCHECK="${STRICT_HEALTHCHECK:-false}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

if [[ -z "$BUNDLE_PATH" ]]; then
  echo "[ERROR] BUNDLE_PATH is required" >&2
  exit 1
fi

if [[ ! -f "$BUNDLE_PATH" ]]; then
  echo "[ERROR] Bundle not found: $BUNDLE_PATH" >&2
  exit 1
fi

TS="$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="$RELEASES_DIR/$TS"
TMP_EXTRACT="$RELEASE_DIR/src"

sudo mkdir -p "$TMP_EXTRACT"
sudo tar -xzf "$BUNDLE_PATH" -C "$TMP_EXTRACT"

if [[ ! -f "$TMP_EXTRACT/artisan" ]]; then
  echo "[ERROR] Invalid bundle: artisan file not found" >&2
  exit 1
fi

sudo mkdir -p "$APP_DIR"

# Preserve runtime data and server environment configuration.
if [[ -f "$APP_DIR/.env" && ! -f "$TMP_EXTRACT/.env" ]]; then
  sudo cp "$APP_DIR/.env" "$TMP_EXTRACT/.env"
fi
if [[ -d "$APP_DIR/storage" && ! -d "$TMP_EXTRACT/storage" ]]; then
  sudo mkdir -p "$TMP_EXTRACT/storage"
fi
if [[ -d "$APP_DIR/bootstrap/cache" && ! -d "$TMP_EXTRACT/bootstrap/cache" ]]; then
  sudo mkdir -p "$TMP_EXTRACT/bootstrap/cache"
fi

sudo rsync -a --delete \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='node_modules' \
  "$TMP_EXTRACT/" "$APP_DIR/"

# Ensure runtime paths are writable before composer/artisan hooks run.
# Use php-fpm group for runtime write access (Amazon Linux default: apache).
FPM_GROUP="$(sudo sed -n 's/^[[:space:]]*group[[:space:]]*=[[:space:]]*//p' /etc/php-fpm.d/www.conf | head -n1)"
if [[ -z "$FPM_GROUP" ]]; then
  FPM_GROUP="apache"
fi

sudo chown -R ec2-user:ec2-user "$APP_DIR"
sudo mkdir -p "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/storage/logs"
sudo touch "$APP_DIR/storage/logs/laravel.log"
sudo chown -R ec2-user:"$FPM_GROUP" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
sudo chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod g+s {} \;

cd "$APP_DIR"

$COMPOSER_BIN install --no-interaction --no-dev --prefer-dist --optimize-autoloader

$PHP_BIN artisan migrate --force
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache || echo "[WARN] route:cache failed; continuing deploy"
$PHP_BIN artisan view:cache

sudo systemctl reload php-fpm
sudo systemctl reload nginx

if command -v curl >/dev/null 2>&1 && [[ -n "$HEALTHCHECK_URL" ]]; then
  if ! curl -fsS "$HEALTHCHECK_URL" >/dev/null; then
    if [[ "$STRICT_HEALTHCHECK" == "true" ]]; then
      echo "[ERROR] Healthcheck failed: $HEALTHCHECK_URL" >&2
      exit 1
    fi
    echo "[WARN] Healthcheck failed, continuing: $HEALTHCHECK_URL"
  fi
fi

echo "[OK] Deploy completed from bundle: $BUNDLE_PATH"
