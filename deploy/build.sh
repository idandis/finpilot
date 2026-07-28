#!/usr/bin/env bash
set -euo pipefail

# Builds a ready-to-upload package for a shared host with no SSH access.
#
# The copy is built OUTSIDE this repo (in $FINPILOT_DEPLOY_DIR, default
# ~/finpilot-deploy-builds). Building in-place was tried and caused two
# real problems: npm installing into the wrong node_modules because the
# copy was nested inside this project's own directory tree, and the
# running `npm run dev` file watcher reacting to the copy as if it were
# source changes (full-reload storms). Building outside avoids both.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_ROOT="${FINPILOT_DEPLOY_DIR:-$HOME/finpilot-deploy-builds}"

# --- Node version guard -------------------------------------------------
# This project's Vite/rolldown toolchain requires Node >=20.19 or >=22.12.
# If nvm is available with a compatible version installed, switch to it
# automatically; otherwise fail with a clear message instead of producing
# a broken build.
node_ok() {
    local v major minor
    v="$(node -v 2>/dev/null | sed 's/^v//')" || return 1
    major="${v%%.*}"
    [ "$major" -ge 22 ] && return 0
    if [ "$major" -eq 20 ]; then
        minor="$(echo "$v" | cut -d. -f2)"
        [ "$minor" -ge 19 ] && return 0
    fi
    return 1
}

if ! node_ok; then
    export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
    if [ -s "$NVM_DIR/nvm.sh" ]; then
        # shellcheck disable=SC1091
        . "$NVM_DIR/nvm.sh"
        nvm use 22 >/dev/null 2>&1 || nvm use 20.20.2 >/dev/null 2>&1 || true
    fi
fi

if ! node_ok; then
    echo "error: Node $(node -v 2>/dev/null || echo 'not found') is too old for this project (needs >=20.19 or >=22.12)." >&2
    echo "Install/activate a newer Node (e.g. 'nvm install 22 && nvm use 22') and re-run this script." >&2
    exit 1
fi
echo "==> Using $(node -v) / npm $(npm -v)"

cd "$ROOT_DIR"

STAMP="$(date +%Y%m%d-%H%M%S)"
PKG_NAME="finpilot-$STAMP"
PKG_DIR="$BUILD_ROOT/$PKG_NAME"

echo "==> Copying project to $PKG_DIR"
mkdir -p "$PKG_DIR"
rsync -a --exclude-from="deploy/rsync-exclude.txt" ./ "$PKG_DIR/"

echo "==> Installing PHP production dependencies"
(cd "$PKG_DIR" && composer install --no-dev --optimize-autoloader)

echo "==> Installing JS dependencies and building assets"
(cd "$PKG_DIR" && npm ci && npm run build)

echo "==> Removing frontend build inputs no longer needed at runtime (assets are already compiled into public/build)"
rm -rf "$PKG_DIR/node_modules" "$PKG_DIR/resources/js" "$PKG_DIR/resources/css"
rm -f "$PKG_DIR/package.json" "$PKG_DIR/package-lock.json" "$PKG_DIR/vite.config.ts" "$PKG_DIR/tsconfig.json"

echo "==> Removing the Vite dev-server marker if it slipped in"
rm -f "$PKG_DIR/public/hot"

echo "==> Creating zip archive"
(cd "$BUILD_ROOT" && zip -rq "$PKG_NAME.zip" "$PKG_NAME")

echo
echo "Done. This repo's own vendor/ and node_modules/ were left untouched."
echo "  Folder: $PKG_DIR"
echo "  Zip:    $BUILD_ROOT/$PKG_NAME.zip"
echo
echo "Next: upload the zip to the server and follow the deploy checklist"
echo "(production .env, document root -> public/, migrations, cron)."
