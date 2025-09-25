#!/usr/bin/env bash
set -euo pipefail

log() {
  printf '[build-plugin-zip] %s\n' "$1" >&2
}

usage() {
  cat <<'USAGE' >&2
Usage: tools/build-plugin-zip.sh [--slug <slug>] [--version <version>] [--zip-name <name>] [--out-dir <path>]

Creates a distributable WordPress plugin zip by staging the project into an intermediate
folder and compressing it. Exclusions are driven by the optional .distignore file in the
repository root in addition to a set of sensible defaults.

Options:
  --slug <slug>         Plugin directory name inside the archive (default: fp-esperienze)
  --version <version>   Version string used when generating the zip name if --zip-name is not supplied
  --zip-name <name>     Explicit zip filename (with or without .zip extension)
  --out-dir <path>      Directory (relative or absolute) where the build artefacts are written (default: dist)
  -h, --help            Show this help message and exit
USAGE
}

SLUG="fp-esperienze"
VERSION=""
ZIP_NAME=""
OUT_DIR="dist"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --slug)
      [[ $# -ge 2 ]] || { usage; exit 1; }
      SLUG="$2"
      shift 2
      ;;
    --version)
      [[ $# -ge 2 ]] || { usage; exit 1; }
      VERSION="$2"
      shift 2
      ;;
    --zip-name)
      [[ $# -ge 2 ]] || { usage; exit 1; }
      ZIP_NAME="$2"
      shift 2
      ;;
    --out-dir)
      [[ $# -ge 2 ]] || { usage; exit 1; }
      OUT_DIR="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      usage
      exit 1
      ;;
  esac
done

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if ! command -v zip >/dev/null 2>&1; then
  log "zip command not found. Install zip before packaging."
  exit 1
fi

if [[ ! -d "${ROOT_DIR}/vendor" ]]; then
  log "vendor directory missing. Run composer install before packaging."
  exit 1
fi

if [[ -z "$ZIP_NAME" ]]; then
  if [[ -n "$VERSION" ]]; then
    ZIP_NAME="${SLUG}-v${VERSION}.zip"
  else
    ZIP_NAME="${SLUG}.zip"
  fi
else
  [[ "$ZIP_NAME" == *.zip ]] || ZIP_NAME="${ZIP_NAME}.zip"
fi

if [[ "$OUT_DIR" != /* ]]; then
  OUT_DIR="${ROOT_DIR}/${OUT_DIR}"
fi

STAGE_DIR="${OUT_DIR}/${SLUG}"
ZIP_PATH="${OUT_DIR}/${ZIP_NAME}"

log "Staging plugin into ${STAGE_DIR}"
rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"
mkdir -p "$OUT_DIR"

RSYNC_ARGS=(
  -a
  --delete
  --prune-empty-dirs
  --exclude=.git
  --exclude=.github
  --exclude=.gitignore
  --exclude=.gitattributes
  --exclude=.DS_Store
  --exclude=.idea
  --exclude=.vscode
  --exclude=build
  --exclude=dist
  --exclude=.distignore
)

if [[ -f "${ROOT_DIR}/.distignore" ]]; then
  RSYNC_ARGS+=("--exclude-from=${ROOT_DIR}/.distignore")
fi

rsync "${RSYNC_ARGS[@]}" "${ROOT_DIR}/" "$STAGE_DIR/"

log "Creating archive ${ZIP_PATH}"
rm -f "$ZIP_PATH"
(
  cd "$OUT_DIR"
  zip -rq "$ZIP_NAME" "$SLUG"
)

log "Created package at ${ZIP_PATH}"
printf '%s\n' "$ZIP_PATH"
