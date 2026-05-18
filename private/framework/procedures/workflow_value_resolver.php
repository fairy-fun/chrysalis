<?php
declare(strict_types=1);

/**
 * IMPORTANT:
 * This resolver is intentionally NOT aware of:
 * - entities
 * - persistence contracts
 * - database schema
 * - projections
 *
 * It must remain a pure structural accessor only.
 */

function fw_resolve_workflow_value(
    mixed $value,
    array $input = [],
    array $context = []
): mixed {

    if (!is_string($value)) {
        return $value;
    }

    if (!str_starts_with($value, '$')) {
        return $value;
    }

    $path = substr($value, 1);

    if ($path === '') {
        return null;
    }

    $segments = explode('.', $path);

    $root = array_shift($segments);

    $source = match ($root) {
        'input' => $input,
        'context' => $context,
        'row' => $context['row'] ?? [],
        default => null,
    };

    if (!is_array($source)) {
        return null;
    }

    $current = $source;

    foreach ($segments as $segment) {

        if (!is_array($current)) {
            return null;
        }

        if (!array_key_exists($segment, $current)) {
            return null;
        }

        $current = $current[$segment];
    }

    return $current;
}