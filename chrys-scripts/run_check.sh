#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

ENV_FILE="$PROJECT_ROOT/private/chrysalis-slack.env"

if [[ -f "$ENV_FILE" ]]; then
  source "$ENV_FILE"
else
  echo "INFO: Slack env not found, continuing without it: $ENV_FILE"
fi

bash "$SCRIPT_DIR/check_classvals.sh"
bash "$SCRIPT_DIR/check_hydration_prompt.sh"