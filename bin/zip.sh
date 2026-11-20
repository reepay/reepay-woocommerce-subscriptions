#!/usr/bin/env bash
#
# Build the release zip: build/reepay-subscriptions-for-woocommerce.zip
#
# 1. Copy the plugin into build/<slug>/, skipping everything in .distignore.
# 2. Run `composer install --no-dev --optimize-autoloader` IN THAT COPY, so the
#    shipped vendor/ has no PHPUnit/dev packages and a consistent autoloader,
#    while the working folder's vendor/ (needed by `npm run phpunit`) is
#    left untouched.
# 3. Zip build/<slug>/ so the archive unpacks to a single <slug>/ folder.

set -euo pipefail

SLUG="reepay-subscriptions-for-woocommerce"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT/build"
STAGE_DIR="$BUILD_DIR/$SLUG"
ZIP_FILE="$BUILD_DIR/$SLUG.zip"

BLUE='\033[1;34m'; GREEN='\033[1;32m'; RED='\033[1;31m'; RESET='\033[0m'
status()  { echo -e "\n${BLUE}$1${RESET}"; }
success() { echo -e "\n${GREEN}$1${RESET}\n"; }
error()   { echo -e "\n${RED}$1${RESET}\n" >&2; exit 1; }

for cmd in rsync composer zip; do
	command -v "$cmd" >/dev/null 2>&1 || error "Required command not found: $cmd"
done

cd "$ROOT"

# Refuse to ship mismatched version numbers.
status "Checking version numbers..."
HEADER_VERSION=$(grep -m1 -E '^\s*\*\s*Version:' "$SLUG.php" | sed -E 's/.*Version:\s*//' | tr -d '[:space:]')
CLASS_VERSION=$(grep -m1 -E 'public static \$version' "$SLUG.php" | sed -E "s/.*'([^']+)'.*/\1/")
README_VERSION=$(grep -m1 -i -E '^Stable tag:' readme.txt | sed -E 's/.*:\s*//I' | tr -d '[:space:]')
echo "  plugin header:   $HEADER_VERSION"
echo "  \$version:        $CLASS_VERSION"
echo "  readme.txt tag:  $README_VERSION"
if [ "$HEADER_VERSION" != "$CLASS_VERSION" ] || [ "$HEADER_VERSION" != "$README_VERSION" ]; then
	error "Version mismatch - update the plugin header, \$version and readme.txt Stable tag to the same value."
fi

status "Preparing $STAGE_DIR ..."
rm -rf "$BUILD_DIR"
mkdir -p "$STAGE_DIR"

status "Copying files (excluding .distignore entries)..."
rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$STAGE_DIR/"

status "Installing production Composer autoloader (no dev packages)..."
composer install \
	--working-dir="$STAGE_DIR" \
	--no-dev \
	--optimize-autoloader \
	--no-interaction \
	--no-progress

status "Creating archive..."
( cd "$BUILD_DIR" && zip -r -q "$SLUG.zip" "$SLUG" )

FILE_COUNT=$(unzip -Z1 "$ZIP_FILE" | grep -vc '/$')
SIZE=$(du -h "$ZIP_FILE" | cut -f1)
success "Done: build/$SLUG.zip (v$HEADER_VERSION, $FILE_COUNT files, $SIZE)"
