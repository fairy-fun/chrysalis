<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . '/private/chrysalis-slack.env';
$endpoint = 'https://antheapeche.com/pecherie/chill-api/admin/check_classvals_code_rules.php';

$slackWebhook = null;

if (is_file($envFile)) {
    $env = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($env !== false) {
        foreach ($env as $line) {
            if (strpos($line, 'SLACK_WEBHOOK_URL=') === 0) {
                $slackWebhook = trim(
                    substr($line, strlen('SLACK_WEBHOOK_URL=')),
                    " \t\n\r\0\x0B'\""
                );
                break;
            }
        }
    }

    if (!$slackWebhook) {
        fwrite(STDERR, "WARN: SLACK_WEBHOOK_URL not found in env file: {$envFile}\n");
    }
} else {
    fwrite(STDERR, "INFO: Slack env not found, continuing without Slack: {$envFile}\n");
}

function httpGet(string $url): array
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
    ];
}

function postJson(string $url, array $payload): array
{
    $ch = curl_init($url);
    $json = json_encode($payload);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
    ];
}

function sendSlack(?string $webhook, string $text): void
{
    if (!$webhook) {
        return;
    }

    postJson($webhook, ['text' => $text]);
}

function safeJsonDecode(?string $json): ?array
{
    if (!is_string($json) || trim($json) === '') {
        return null;
    }

    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return null;
    }

    return $data;
}

function extractDriftSummary(?array $data): array
{
    $checked = null;
    $drifted = null;
    $driftedTables = [];
    $missingCount = 0;
    $invalidCount = 0;

    if (!$data) {
        return [
            'checked' => null,
            'drifted' => null,
            'tables' => [],
            'missing_count' => 0,
            'invalid_count' => 0,
        ];
    }

    if (isset($data['summary']) && is_array($data['summary'])) {
        if (isset($data['summary']['checked']) && is_numeric($data['summary']['checked'])) {
            $checked = (int) $data['summary']['checked'];
        }

        if (isset($data['summary']['drifted']) && is_numeric($data['summary']['drifted'])) {
            $drifted = (int) $data['summary']['drifted'];
        }
    }

    if (isset($data['tables']) && is_array($data['tables'])) {
        foreach ($data['tables'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $table = $row['table'] ?? null;
            $ok = $row['ok'] ?? null;
            $status = $row['status'] ?? null;

            if ($ok === false && is_string($table) && $table !== '') {
                $driftedTables[] = $table;
            }

            if ($status === 'missing') {
                $missingCount++;
            } elseif ($status === 'invalid') {
                $invalidCount++;
            }
        }
    }

    $driftedTables = array_values(array_unique($driftedTables));

    if ($drifted === null) {
        $drifted = count($driftedTables);
    }

    return [
        'checked' => $checked,
        'drifted' => $drifted,
        'tables' => $driftedTables,
        'missing_count' => $missingCount,
        'invalid_count' => $invalidCount,
    ];
}

function buildDriftSlackMessage(array $summary): string
{
    $checked = $summary['checked'];
    $drifted = $summary['drifted'];
    $tables = $summary['tables'];
    $missingCount = $summary['missing_count'] ?? 0;
    $invalidCount = $summary['invalid_count'] ?? 0;

    $sample = array_slice($tables, 0, 5);
    $sampleText = !empty($sample) ? implode(', ', $sample) : 'None listed';

    $lines = [];
    $lines[] = '⚠️ Trigger Drift Detected';
    $lines[] = '• Tables checked: ' . ($checked !== null ? $checked : 'Unknown');
    $lines[] = '• Drifted tables: ' . ($drifted !== null ? $drifted : 'Unknown');
    $lines[] = '• Missing trigger sets: ' . $missingCount;
    $lines[] = '• Invalid tables: ' . $invalidCount;
    $lines[] = '• Sample affected: ' . $sampleText;

    if (count($tables) > 5) {
        $lines[] = '• More affected: +' . (count($tables) - 5);
    }

    if ($drifted !== null && $drifted > 0 && $missingCount > 0) {
        $lines[] = '• Note: triggers appear absent in this environment';
    }

    return implode("\n", $lines);
}

$check = httpGet($endpoint);
$status = $check['http_code'];

if ($check['error'] !== '') {
    sendSlack($slackWebhook, "🚨 Chrysalis trigger check request failed\n• cURL error: {$check['error']}");
    echo "Request error";
    if ($slackWebhook) {
        echo ", Slack sent";
    }
    echo "\n";
    exit(1);
}

if ($status === 200) {
    echo "No drift\n";
    exit(0);
}

if ($status === 409) {
    $data = safeJsonDecode($check['response']);
    $summary = extractDriftSummary($data);
    $message = buildDriftSlackMessage($summary);

    if ($data === null) {
        $message .= "\n• Note: response JSON missing or malformed";
    }

    sendSlack($slackWebhook, $message);
    echo "Drift detected";
    if ($slackWebhook) {
        echo ", Slack sent";
    }
    echo "\n";
    exit(0);
}

$message = "🚨 Chrysalis trigger check failed\n• HTTP: {$status}";

$data = safeJsonDecode($check['response']);

if (is_array($data)) {
    if (isset($data['status']) && is_string($data['status']) && $data['status'] !== '') {
        $message .= "\n• Status: " . $data['status'];
    }

    if (isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
        $message .= "\n• Message: " . $data['message'];
    }
}

sendSlack($slackWebhook, $message);

echo "Error {$status}";
if ($slackWebhook) {
    echo ", Slack sent";
}
echo "\n";

exit(1);