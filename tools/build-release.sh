#!/usr/bin/env bash
# Build a Tier A release ZIP for NeNe Clear — vendor bundled (--no-dev), the
# React admin UI built into public_html/assets/, and the web installer included.
# The artifact is what a human places on nene-origin.dev (see the origin `targets`
# manifest); GitHub is not the source (docs/roadmap Phase 3, NENE_ORIGIN_URL).
#
# NOTE: this runs `composer install --no-dev` in the repo, so the working tree's
# vendor/ loses dev tools afterwards. Run `composer install` to restore them.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

NENE2_DIR="${NENE2_DIR:-$(dirname "$ROOT")/NENE2}"
BUILD_DIR="${RELEASE_BUILD_DIR:-$ROOT/build/release}"
STAGING_DIR="$BUILD_DIR/staging/nene-clear"
VERSION="$(tr -d ' \n\r' < "$ROOT/VERSION" 2>/dev/null || echo 0.0.0-dev)"
ZIP_PATH="${RELEASE_ZIP:-$BUILD_DIR/nene-clear-${VERSION}.zip}"

echo "==> NeNe Clear release build (${VERSION})"

if [[ ! -f "$NENE2_DIR/composer.json" ]]; then
  echo "error: NENE2 not found at ${NENE2_DIR}. Clone it as a sibling or set NENE2_DIR." >&2
  exit 1
fi

echo "==> Install PHP dependencies (production, no-dev)"
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader

echo "==> Build the admin UI into public_html/assets/"
(
  cd frontend
  npm ci
  npm run build
)

required_paths=(
  public_html/index.php
  public_html/install.php
  public_html/.htaccess
  public_html/assets/index.html
  vendor/autoload.php
  vendor/robmorgan/phinx/src/Phinx/Migration/Manager.php
)
for path in "${required_paths[@]}"; do
  if [[ ! -e "$ROOT/$path" ]]; then
    echo "error: missing required release artifact: $path" >&2
    exit 1
  fi
done

echo "==> Stage release tree"
rm -rf "$BUILD_DIR/staging"
mkdir -p "$STAGING_DIR" "$STAGING_DIR/var"

rsync -a \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.cursor/' \
  --exclude='.claude/' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='.phpunit.cache/' \
  --exclude='.phpunit.result.cache' \
  --exclude='.phpstan.cache/' \
  --exclude='.php-cs-fixer.cache' \
  --exclude='build/' \
  --exclude='docs/' \
  --exclude='frontend/' \
  --exclude='node_modules/' \
  --exclude='tests/' \
  --exclude='var/*' \
  --exclude='database/*.sqlite3' \
  --exclude='docker-compose.yml' \
  --exclude='phpstan.neon' \
  --exclude='phpunit.xml' \
  --exclude='.php-cs-fixer.php' \
  "$ROOT/" "$STAGING_DIR/"

echo "==> Dereference path-dependency symlinks in staging vendor"
# vendor/vendor is a self-referential symlink that zip would follow infinitely.
rm -f "$STAGING_DIR/vendor/vendor"
# vendor/hideyukimori/nene2 is a Composer path-repo symlink → copy real content
# (src/ + composer.json are the only runtime-required pieces).
NENE2_VENDOR_LINK="$STAGING_DIR/vendor/hideyukimori/nene2"
if [[ -L "$NENE2_VENDOR_LINK" ]]; then
  rm "$NENE2_VENDOR_LINK"
  mkdir -p "$NENE2_VENDOR_LINK"
  rsync -a "$NENE2_DIR/src/" "$NENE2_VENDOR_LINK/src/"
  cp "$NENE2_DIR/composer.json" "$NENE2_VENDOR_LINK/composer.json"
fi

echo "==> Create ZIP: ${ZIP_PATH}"
mkdir -p "$(dirname "$ZIP_PATH")"
rm -f "$ZIP_PATH"
if command -v zip >/dev/null 2>&1; then
  ( cd "$BUILD_DIR/staging" && zip -rq "$ZIP_PATH" nene-clear )
else
  php -r '
    $zip = new ZipArchive();
    if ($zip->open(getenv("ZIP_PATH"), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "error: unable to create zip archive\n");
        exit(1);
    }
    $base = getenv("STAGING_PARENT") . "/nene-clear";
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($it as $file) {
        $local = "nene-clear/" . substr($file->getPathname(), strlen($base) + 1);
        if ($file->isDir()) {
            $zip->addEmptyDir(rtrim(str_replace("\\", "/", $local), "/"));
        } else {
            $zip->addFile($file->getPathname(), str_replace("\\", "/", $local));
        }
    }
    $zip->close();
  ' ZIP_PATH="$ZIP_PATH" STAGING_PARENT="$BUILD_DIR/staging"
fi

SHA256="$(sha256sum "$ZIP_PATH" | cut -d' ' -f1)"
echo "==> Done"
echo "    file:    ${ZIP_PATH} ($(du -h "$ZIP_PATH" | cut -f1))"
echo "    version: ${VERSION}"
echo "    sha256:  ${SHA256}"
echo ""
echo "Next: upload the ZIP to nene-origin.dev and author a targets manifest"
echo "(latest.json) with this artifact_url + artifact_sha256 (see Slice 3)."
