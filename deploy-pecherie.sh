#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$SCRIPT_DIR"
EXPECTED_REPO_ROOT="/home/charizai/projects/chrysalis"

case "$REPO_ROOT" in
  "$EXPECTED_REPO_ROOT")
    ;;
  /mnt/*)
    echo "Refusing to deploy from a Windows-mounted path:"
    echo "  $REPO_ROOT"
    echo
    echo "Open the WSL-native repo instead:"
    echo "  $EXPECTED_REPO_ROOT"
    exit 1
    ;;
  *)
    echo "Refusing to deploy from an unexpected location:"
    echo "  $REPO_ROOT"
    echo
    echo "Expected:"
    echo "  $EXPECTED_REPO_ROOT"
    exit 1
    ;;
esac

REMOTE_USER="sxnzlfun"
REMOTE_HOST="pecherie"

SRC_PUBLIC="$REPO_ROOT/public_html/pecherie/"
DEST_PUBLIC="/home/sxnzlfun/public_html/pecherie/"

SRC_PRIVATE="$REPO_ROOT/private/"
DEST_PRIVATE="/home/sxnzlfun/private/"

SRC_WORKFLOWS="$REPO_ROOT/.github/workflows/"
DEST_WORKFLOWS="/home/sxnzlfun/.github/workflows/"

COMMON_EXCLUDES=(
  --exclude '.git/'
  --exclude '.github/'
  --exclude '.idea/'
  --exclude '*.bak'
  --exclude 'error_log'
  --exclude '.DS_Store'
  --exclude '*.env'
  --exclude 'chrysalis-slack.env'
  --exclude 'pecherie_config.php'
)

PUBLIC_FLAGS=(-rlvtz --delete -e ssh)
PRIVATE_FLAGS=(-rlvtz -e ssh)

mode="${1:-dry-run}"

if [[ "$mode" == "dry-run" ]]; then
  PUBLIC_FLAGS+=(-n)
  PRIVATE_FLAGS+=(-n)
  echo "Running DRY RUN deploy..."
elif [[ "$mode" == "live" ]]; then
  echo "Running LIVE deploy..."
else
  echo "Usage: ./deploy-pecherie.sh [dry-run|live]"
  exit 1
fi

echo
echo "Running from: $REPO_ROOT"

echo
echo "Syncing public_html/pecherie..."
echo "DEBUG → ${REMOTE_USER}@${REMOTE_HOST}"
rsync "${PUBLIC_FLAGS[@]}" \
  "${COMMON_EXCLUDES[@]}" \
  "$SRC_PUBLIC" \
  "${REMOTE_USER}@${REMOTE_HOST}:${DEST_PUBLIC}"

echo
echo "Syncing private..."
rsync "${PRIVATE_FLAGS[@]}" \
  "${COMMON_EXCLUDES[@]}" \
  "$SRC_PRIVATE" \
  "${REMOTE_USER}@${REMOTE_HOST}:${DEST_PRIVATE}"

echo
echo "Syncing .github/workflows..."
ssh "${REMOTE_USER}@${REMOTE_HOST}" 'mkdir -p ~/.github/workflows'
rsync "${PRIVATE_FLAGS[@]}" \
  "$SRC_WORKFLOWS" \
  "${REMOTE_USER}@${REMOTE_HOST}:${DEST_WORKFLOWS}"

echo
echo "Done."