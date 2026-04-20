<?php
declare(strict_types=1);

function fw_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fw_assert_starts_with(string $prefix, string $value, string $message): void
{
    fw_assert(strncmp($value, $prefix, strlen($prefix)) === 0, $message);
}