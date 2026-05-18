<?php
declare(strict_types=1);

function fw_write_workflow_context(
    array $context,
    string $path,
    mixed $value
): array {

    $segments = explode('.', $path);

    if (count($segments) === 0) {
        return $context;
    }

    $ref =& $context;

    foreach ($segments as $segment) {

        if (!is_array($ref)) {
            $ref = [];
        }

        if (!array_key_exists($segment, $ref)) {
            $ref[$segment] = [];
        }

        $ref =& $ref[$segment];
    }

    $ref = $value;

    return $context;
}