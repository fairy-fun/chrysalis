#!/usr/bin/env bash
cd "$(dirname "$0")"

set -euo pipefail

REMOTE_USER="sxnzlfun"
REMOTE_HOST="pecherie"

SRC_PUBLIC="./public_html/pecherie/"
DEST_PUBLIC="/home/sxnzlfun/public_html/pecherie/"

SRC_PRIVATE="./private/"
DEST_PRIVATE="/home/sxnzlfun/private/"

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
echo "Running from: $(pwd)"
echo "Done."