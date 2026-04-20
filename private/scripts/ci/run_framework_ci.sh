#!/usr/bin/env bash
set -euo pipefail

php private/scripts/ci/check_forbidden_primitive_calls.php
php private/scripts/ci/check_procedure_registration_paths.php
php private/scripts/ci/check_directive_registry_drift.php