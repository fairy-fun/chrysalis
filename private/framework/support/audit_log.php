<?php
declare(strict_types=1);

function fw_audit_log(string $event, array $context = []): void
{
    error_log('[fw_audit] ' . json_encode([
        'event' => $event,
        'context' => $context,
        'ts' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES));
}