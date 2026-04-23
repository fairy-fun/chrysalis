<?php

declare(strict_types=1);

function run_all_invariants(PDO $pdo, array $registry): void
{
    foreach ($registry as $id => $definition) {
        if (!isset($definition['runner']) || !is_callable($definition['runner'])) {
            throw new RuntimeException('Invalid invariant runner for: ' . $id);
        }

        $runner = $definition['runner'];
        $runner($pdo);
    }
}

function run_named_invariants(PDO $pdo, array $registry, array $ids): void
{
    foreach ($ids as $id) {
        if (!isset($registry[$id])) {
            throw new RuntimeException('Unknown invariant: ' . $id);
        }

        $runner = $registry[$id]['runner'];

        if (!is_callable($runner)) {
            throw new RuntimeException('Invalid invariant runner for: ' . $id);
        }

        $runner($pdo);
    }
}
