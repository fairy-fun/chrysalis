#!/usr/bin/env bash
set -euo pipefail

: "${API_URL:?API_URL is required}"
: "${API_KEY:?API_KEY is required}"

export CHARACTER_ID="${1:-CI_CHAR_EXPR_1}"
export DOMAIN_ID="${2:-1}"

response="$(
  curl -sS -X POST "$API_URL" \
    -H "Content-Type: application/json" \
    -H "X-API-Key: $API_KEY" \
    -d "{
      \"operation\": \"resolveCharacterExpressionOutput\",
      \"character_id\": \"$CHARACTER_ID\",
      \"domain_id\": $DOMAIN_ID
    }"
)"

echo "$response"

php -r '
$json = stream_get_contents(STDIN);
$data = json_decode($json, true);

if (!is_array($data)) {
    fwrite(STDERR, "FAIL: Response is not valid JSON\n");
    exit(1);
}

if (($data["status"] ?? null) !== "ok") {
    fwrite(STDERR, "FAIL: status is not ok\n");
    exit(1);
}

if (($data["character_id"] ?? null) !== getenv("CHARACTER_ID")) {
    fwrite(STDERR, "FAIL: character_id mismatch\n");
    exit(1);
}

if (($data["domain_id"] ?? null) !== getenv("DOMAIN_ID")) {
    fwrite(STDERR, "FAIL: domain_id was not returned as string\n");
    exit(1);
}

if (!array_key_exists("output", $data) || !is_array($data["output"])) {
    fwrite(STDERR, "FAIL: output missing or not object/array\n");
    exit(1);
}

echo "OK: resolveCharacterExpressionOutput API smoke test passed\n";
' <<< "$response"