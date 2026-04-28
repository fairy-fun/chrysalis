<?php

declare(strict_types=1);

// Variables in scope from index.php: $pdo, $id, $toolInput

$sql   = trim((string) ($toolInput['sql'] ?? ''));
$limit = min((int) ($toolInput['limit'] ?? 100), 500);

if ($sql === '') {
    jsonrpcError($id, -32602, 'sql is required');
    exit;
}

// Enforce read-only: reject anything that isn't a SELECT
$firstWord = strtoupper(strtok(ltrim($sql), " \t\n\r"));
if ($firstWord !== 'SELECT') {
    jsonrpcError($id, -32602, 'Only SELECT statements are permitted');
    exit;
}

// Inject LIMIT if not already present
if (!preg_match('/\bLIMIT\b/i', $sql)) {
    $sql .= ' LIMIT ' . $limit;
}

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    jsonrpcError($id, -32603, 'Query failed: ' . $e->getMessage());
    exit;
}

jsonrpcSuccess($id, [
    'content' => [
        [
            'type' => 'text',
            'text' => json_encode([
                'row_count' => count($rows),
                'rows'      => $rows,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ],
    ],
]);