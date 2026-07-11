#!/usr/bin/env bash
# NeNe Clear — Tier A release ZIP build (#286: allowlist + Packagist vendor + SHA-256 sidecar).
#
# Usage:  bash tools/build-release.sh
# Output: build/release/nene-clear-<version>.zip + .sha256 sidecar
#
# Design (follows nene-invoice tools/build-release.sh):
# - The working tree's vendor/ is never touched. The distributed vendor is
#   resolved fresh in a staging directory from composer.json + composer.lock
#   (hideyukimori/nene2 comes from Packagist as a tagged dist — no path repo,
#   no symlinks). A single leftover symlink fails the build.
# - Files ship via an allowlist (structurally prevents dev-only files,
#   secrets, and caches from leaking into the artifact).
# - The archive keeps the `nene-clear/` top-level directory expected by the
#   origin `targets` manifest and the release CI verify step.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

BUILD_DIR="${RELEASE_BUILD_DIR:-$ROOT/build/release}"
STAGE="$BUILD_DIR/staging/nene-clear"
VERSION="$(tr -d ' \n\r' < "$ROOT/VERSION" 2>/dev/null || echo 0.0.0-dev)"
ZIP_PATH="${RELEASE_ZIP:-$BUILD_DIR/nene-clear-${VERSION}.zip}"

echo "==> NeNe Clear release build (${VERSION})"

# ----------------------------------------
# 1. Frontend build (Vite → public_html/assets/)
# ----------------------------------------
echo "==> Build the admin UI into public_html/assets/"
(
  cd frontend
  npm ci
  npm run build
)

# ----------------------------------------
# 2. Stage application files (allowlist)
# ----------------------------------------
echo "==> Stage release tree (allowlist)"
rm -rf "$BUILD_DIR/staging"
mkdir -p "$STAGE" "$STAGE/var" "$STAGE/tools"

cp -r "$ROOT/src" "$STAGE/src"
cp -r "$ROOT/lang" "$STAGE/lang"
mkdir -p "$STAGE/database"
cp -r "$ROOT/database/migrations" "$STAGE/database/migrations"
if [[ -d "$ROOT/database/schema" ]]; then
  cp -r "$ROOT/database/schema" "$STAGE/database/schema"
fi
# public_html contains the built SPA (assets/), install.php, index.php,
# .htaccess — copy after the frontend build.
cp -r "$ROOT/public_html" "$STAGE/public_html"
cp "$ROOT/phinx.php" "$STAGE/phinx.php"
cp "$ROOT/composer.json" "$STAGE/composer.json"
cp "$ROOT/composer.lock" "$STAGE/composer.lock"
cp "$ROOT/VERSION" "$STAGE/VERSION"
cp "$ROOT/README.md" "$STAGE/README.md"
cp "$ROOT/LICENSE" "$STAGE/LICENSE"
cp "$ROOT/.env.example" "$STAGE/.env.example"
# Production ops tools only (no build/dev tooling ships).
cp "$ROOT/tools/create-admin.php" "$STAGE/tools/create-admin.php"
cp "$ROOT/tools/sweep-demo.php" "$STAGE/tools/sweep-demo.php"
cp "$ROOT/tools/seed-demo.php" "$STAGE/tools/seed-demo.php"
touch "$STAGE/var/.gitkeep"

# ----------------------------------------
# 3. Production vendor resolved in staging (Packagist dist, no symlinks)
# ----------------------------------------
echo "==> composer install (production, staging, from lock)"
(
  cd "$STAGE"
  composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader --quiet
)
NENE2_RESOLVED="$(cd "$STAGE" && composer show hideyukimori/nene2 2>/dev/null | awk '/^versions/ {print $NF}')"
echo "    nene2 resolved: ${NENE2_RESOLVED}"

# ----------------------------------------
# 4. Pre-ship inspection — required artifacts, zero symlinks, real nene2 dist
# ----------------------------------------
required_paths=(
  public_html/index.php
  public_html/install.php
  public_html/.htaccess
  public_html/assets/index.html
  vendor/autoload.php
  vendor/robmorgan/phinx/src/Phinx/Migration/Manager.php
  vendor/hideyukimori/nene2/src/Demo/MinimalDemoErrorPageRenderer.php
)
for path in "${required_paths[@]}"; do
  if [[ ! -e "$STAGE/$path" ]]; then
    echo "error: missing required release artifact: $path" >&2
    exit 1
  fi
done

if [[ "$(find "$STAGE" -type l | wc -l)" -ne 0 ]]; then
  echo "error: symlinks found in the release staging tree:" >&2
  find "$STAGE" -type l >&2
  exit 1
fi

# ----------------------------------------
# 5. ZIP (nene-clear/ top-level) + SHA-256 sidecar
# ----------------------------------------
echo "==> Create ZIP: ${ZIP_PATH}"
mkdir -p "$(dirname "$ZIP_PATH")"
rm -f "$ZIP_PATH" "$ZIP_PATH.sha256"
if command -v zip >/dev/null 2>&1; then
  ( cd "$BUILD_DIR/staging" && zip -rq "$ZIP_PATH" nene-clear -x "*.DS_Store" -x "__MACOSX/*" )
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

( cd "$(dirname "$ZIP_PATH")" && sha256sum "$(basename "$ZIP_PATH")" > "$(basename "$ZIP_PATH").sha256" )
SHA256="$(cut -d' ' -f1 "$ZIP_PATH.sha256")"

echo "==> Done"
echo "    file:    ${ZIP_PATH} ($(du -h "$ZIP_PATH" | cut -f1))"
echo "    version: ${VERSION}"
echo "    nene2:   ${NENE2_RESOLVED}"
echo "    sha256:  ${SHA256}"
echo "    sidecar: ${ZIP_PATH}.sha256"
echo ""
echo "Next: upload the ZIP to nene-origin.dev and author a targets manifest"
echo "(latest.json) with this artifact_url + artifact_sha256 (see Slice 3)."
