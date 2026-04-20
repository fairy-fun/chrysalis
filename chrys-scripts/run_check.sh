#!/usr/bin/env bash
set -euo pipefail

source /home/sxnzlfun/private/chrysalis-slack.env
bash /home/sxnzlfun/chrys-scripts/check_classvals.sh
bash /home/sxnzlfun/chrys-scripts/check_hydration_prompt.sh