#!/bin/bash

set -euo pipefail

VERSION="${1:-}"

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Usage: ./release.sh X.Y.Z"
  exit 1
fi

TAG="v$VERSION"
NOTES_FILE="release-notes/$VERSION.md"

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Working tree must be clean before release."
  exit 1
fi

if git rev-parse "$TAG" >/dev/null 2>&1; then
  echo "Tag $TAG already exists."
  exit 1
fi

if [[ ! -f "$NOTES_FILE" ]]; then
  echo "Missing $NOTES_FILE."
  exit 1
fi

for heading in "## New Features" "## Improvements" "## Bug Fixes"; do
  grep -Fq "$heading" "$NOTES_FILE" || {
    echo "$NOTES_FILE is missing: $heading"
    exit 1
  }
done

HEADER_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' wpadverts-armember.php | head -n 1)"
CONSTANT_VERSION="$(sed -n "s/^define('WPAAG_VERSION', '\\(.*\\)');$/\\1/p" wpadverts-armember.php | head -n 1)"
STABLE_VERSION="$(sed -n 's/^Stable tag: //p' readme.txt | head -n 1)"

if [[ "$HEADER_VERSION" != "$VERSION" || "$CONSTANT_VERSION" != "$VERSION" || "$STABLE_VERSION" != "$VERSION" ]]; then
  echo "Version mismatch: header=$HEADER_VERSION constant=$CONSTANT_VERSION stable=$STABLE_VERSION expected=$VERSION"
  exit 1
fi

./build.sh
git tag -a "$TAG" -m "Release $VERSION"
git push origin main
git push origin "$TAG"

echo "Pushed $TAG. The release workflow will publish the packaged ZIP."
