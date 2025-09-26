#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

MAIN_FILE="$(grep -Rl --include="*.php" -m1 "^\\s*\\*\\s*Plugin Name:" "$ROOT_DIR" || true)"
if [[ -z "$MAIN_FILE" ]]; then
  echo "Errore: file principale del plugin non trovato." >&2
  exit 1
fi

PLUGIN_DIR="$(dirname "$MAIN_FILE")"
PLUGIN_BASENAME="$(basename "$PLUGIN_DIR")"

VERSION="$(grep -E "^[[:space:]]*\\*+[[:space:]]*Version:" "$MAIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]*//')"
if [[ -z "$VERSION" ]]; then
  VERSION="0.0.0"
fi

SLUG="$(echo "$PLUGIN_BASENAME" | tr '[:upper:]' '[:lower:]')"

DIST_DIR="$ROOT_DIR/dist"
mkdir -p "$DIST_DIR"

ZIP_NAME="${SLUG}-v${VERSION}.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"

EXCLUDES=(
  "${PLUGIN_BASENAME}/.git/*"
  "${PLUGIN_BASENAME}/.github/*"
  "${PLUGIN_BASENAME}/node_modules/*"
  "${PLUGIN_BASENAME}/vendor/*"
  "${PLUGIN_BASENAME}/tests/*"
  "${PLUGIN_BASENAME}/docs/*"
  "${PLUGIN_BASENAME}/dist/*"
  "${PLUGIN_BASENAME}/.vscode/*"
  "${PLUGIN_BASENAME}/.idea/*"
  "${PLUGIN_BASENAME}/composer.lock"
  "${PLUGIN_BASENAME}/package-lock.json"
  "${PLUGIN_BASENAME}/coverage/*"
)

cd "$(dirname "$PLUGIN_DIR")"

rm -f "$ZIP_PATH"
zip -r "$ZIP_PATH" "$PLUGIN_BASENAME" -x "${EXCLUDES[@]}"

echo "OK: creato $ZIP_PATH"
