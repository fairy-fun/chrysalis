<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Fact Lineage Utilities
|--------------------------------------------------------------------------
|
| Lineage doctrine:
|
| - facts are immutable
| - supersession defines lineage
| - lineage is linear, not branching
| - only a current lineage head may be superseded
| - canonicality remains projection-side
| - governance remains policy-side
|
*/

/*
* See private/docs/facts/fact_lineage.md for lineage invariants and atomic supersession contract.
 *
 */


function fact_lineage_table(bool $eventScoped): string
{
    return $eventScoped
        ? 'entity_linked_facts_event'
        : 'entity_linked_facts_global';
}

function fetch_linked_fact(
    PDO $pdo,
    string $table,
    int $linkedFactId
): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM {$table}
        WHERE linked_fact_id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $linkedFactId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function fact_lineage_successor(
    PDO $pdo,
    int $linkedFactId,
    bool $eventScoped = false
): ?array {
    if ($linkedFactId < 1) {
        throw new InvalidArgumentException(
            'linkedFactId must be positive'
        );
    }

    $table = fact_lineage_table($eventScoped);

    $stmt = $pdo->prepare("
        SELECT *
        FROM {$table}
        WHERE supersedes_linked_fact_id = :id
        LIMIT 2
    ");

    $stmt->execute([
        'id' => $linkedFactId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw new RuntimeException(
            'Fact lineage fork detected'
        );
    }

    return $rows[0] ?? null;
}

function fact_lineage_ancestors(
    PDO $pdo,
    int $linkedFactId,
    bool $eventScoped = false
): array {
    if ($linkedFactId < 1) {
        throw new InvalidArgumentException(
            'linkedFactId must be positive'
        );
    }

    $table = fact_lineage_table($eventScoped);

    $results = [];
    $visited = [];
    $currentId = $linkedFactId;

    while ($currentId !== null) {
        if (isset($visited[$currentId])) {
            throw new RuntimeException(
                'Fact lineage cycle detected'
            );
        }

        $visited[$currentId] = true;

        $fact = fetch_linked_fact(
            $pdo,
            $table,
            $currentId
        );

        if ($fact === null) {
            break;
        }

        $results[] = $fact;

        $nextId = $fact['supersedes_linked_fact_id'] ?? null;
        $currentId = $nextId === null ? null : (int) $nextId;

        if ($currentId < 1) {
            $currentId = null;
        }
    }

    return $results;
}

function fact_lineage_ancestry_chain(
    PDO $pdo,
    int $linkedFactId,
    bool $eventScoped = false
): array {
    return array_reverse(
        fact_lineage_ancestors(
            $pdo,
            $linkedFactId,
            $eventScoped
        )
    );
}

function fact_lineage_forward_path(
    PDO $pdo,
    int $linkedFactId,
    bool $eventScoped = false
): array {
    if ($linkedFactId < 1) {
        throw new InvalidArgumentException(
            'linkedFactId must be positive'
        );
    }

    $results = [];
    $visited = [];
    $currentId = $linkedFactId;

    while (true) {
        if (isset($visited[$currentId])) {
            throw new RuntimeException(
                'Fact lineage cycle detected'
            );
        }

        $visited[$currentId] = true;

        $successor = fact_lineage_successor(
            $pdo,
            $currentId,
            $eventScoped
        );

        if ($successor === null) {
            break;
        }

        $results[] = $successor;
        $currentId = (int) $successor['linked_fact_id'];
    }

    return $results;
}

function fact_lineage_root(
    PDO $pdo,
    int $linkedFactId,
    bool $eventScoped = false
): ?array {
    $chain = fact_lineage_ancestry_chain(
        $pdo,
        $linkedFactId,
        $eventScoped
    );

    return $chain[0] ?? null;
}

function fact_lineage_head(
    PDO $pdo,
    int $linkedFactId,
    bool $eventScoped = false
): ?array {
    if ($linkedFactId < 1) {
        throw new InvalidArgumentException(
            'linkedFactId must be positive'
        );
    }

    $table = fact_lineage_table($eventScoped);

    $current = fetch_linked_fact(
        $pdo,
        $table,
        $linkedFactId
    );

    if ($current === null) {
        return null;
    }

    $forwardPath = fact_lineage_forward_path(
        $pdo,
        $linkedFactId,
        $eventScoped
    );

    if ($forwardPath === []) {
        return $current;
    }

    return $forwardPath[count($forwardPath) - 1];
}

function fact_lineage_full(
    PDO $pdo,
    int $linkedFactId,
    bool $eventScoped = false
): array {
    $backward = fact_lineage_ancestry_chain(
        $pdo,
        $linkedFactId,
        $eventScoped
    );

    if ($backward === []) {
        return [];
    }

    $forward = fact_lineage_forward_path(
        $pdo,
        $linkedFactId,
        $eventScoped
    );

    return array_merge(
        $backward,
        $forward
    );
}

function assert_valid_fact_supersession(
    PDO $pdo,
    int $supersedesLinkedFactId,
    array $candidateFact,
    bool $eventScoped = false
): void {
    if ($supersedesLinkedFactId < 1) {
        throw new InvalidArgumentException(
            'supersedesLinkedFactId must be positive'
        );
    }

    $table = fact_lineage_table($eventScoped);

    $previous = fetch_linked_fact(
        $pdo,
        $table,
        $supersedesLinkedFactId
    );

    if ($previous === null) {
        throw new RuntimeException(
            'Superseded fact does not exist'
        );
    }

    $existingSuccessor = fact_lineage_successor(
        $pdo,
        $supersedesLinkedFactId,
        $eventScoped
    );

    if ($existingSuccessor !== null) {
        throw new RuntimeException(
            'Superseded fact is not a lineage head'
        );
    }

    $requiredFields = [
        'subject_entity_id',
        'fact_type_id',
    ];

    if ($eventScoped) {
        $requiredFields[] = 'context_entity_id';
    }

    foreach ($requiredFields as $field) {
        if (
            ($candidateFact[$field] ?? null)
            !==
            ($previous[$field] ?? null)
        ) {
            throw new RuntimeException(
                'Supersession scope mismatch: ' . $field
            );
        }
    }

    fact_lineage_ancestors(
        $pdo,
        $supersedesLinkedFactId,
        $eventScoped
    );
}