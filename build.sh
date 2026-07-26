#!/bin/bash

set -euo pipefail

PLUGIN_SLUG="wpadverts-armember"
PLUGIN_FILE="$PLUGIN_SLUG.php"

if [[ ! -f "$PLUGIN_FILE" ]]; then
  echo "Run this script from the plugin repository root."
  exit 1
fi

VERSION="$(
  sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$PLUGIN_FILE" | head -n 1
)"

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Could not determine a semantic version from $PLUGIN_FILE."
  exit 1
fi

OUTPUT_PATH="$PWD/${PLUGIN_SLUG}-${VERSION}.zip"
STAGING_DIR="$(mktemp -d)"
PACKAGE_DIR="$STAGING_DIR/$PLUGIN_SLUG"

cleanup() {
  rm -rf "$STAGING_DIR"
}

trap cleanup EXIT
mkdir -p "$PACKAGE_DIR"

rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude '.DS_Store' \
  --exclude '.editorconfig' \
  --exclude '.gitattributes' \
  --exclude '.gitignore' \
  --exclude '*.zip' \
  --exclude 'AGENTS.md' \
  --exclude 'CONTRIBUTING.md' \
  --exclude 'build.sh' \
  --exclude 'composer.json' \
  --exclude 'release.sh' \
  ./ "$PACKAGE_DIR/"

(
  cd "$STAGING_DIR"
  zip -rq "$OUTPUT_PATH" "$PLUGIN_SLUG"
)

unzip -tq "$OUTPUT_PATH"
echo "Created $OUTPUT_PATH"
