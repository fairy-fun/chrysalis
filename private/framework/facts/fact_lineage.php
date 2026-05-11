<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Fact Lineage Utilities
|--------------------------------------------------------------------------
|
| Read-side lineage traversal + write-side lineage validation helpers.
|
| Doctrine:
|
| - facts are immutable
| - supersession defines lineage
| - canonicality is projection-side
| - lineage traversal is historical/read-side
| - lineage validation is ingress/write-side
|
*/

require_once __DIR__ . '/fact_governance.php';

/*
|--------------------------------------------------------------------------
| Internal Helpers
|--------------------------------------------------------------------------
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

    return $row === false
        ? null
        : $row;
}

/*
|--------------------------------------------------------------------------
| Read-Side Lineage Traversal
|--------------------------------------------------------------------------
*/

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

        $currentId = isset(
            $fact['supersedes_linked_fact_id']
        )
            ? (int) $fact['supersedes_linked_fact_id']
            : null;

        if ($currentId < 1) {
            $currentId = null;
        }
    }

    return $results;
}

function fact_lineage_descendants(
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

    $currentId = $linkedFactId;

    while (true) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$table}
            WHERE supersedes_linked_fact_id = :id
            ORDER BY linked_fact_id ASC
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $currentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            break;
        }

        $results[] = $row;

        $currentId = (int) $row['linked_fact_id'];
    }

    return $results;
}

function fact_lineage_chain(
    PDO $pdo,
    int $linkedFactId,
    bool $eventScoped = false
): array {
    $ancestors = fact_lineage_ancestors(
        $pdo,
        $linkedFactId,
        $eventScoped
    );

    return array_reverse($ancestors);
}

/*
|--------------------------------------------------------------------------
| Write-Side Validation
|--------------------------------------------------------------------------
*/

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

    /*
    |--------------------------------------------------------------------------
    | Scope Integrity
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Cycle Detection
    |--------------------------------------------------------------------------
    */

    $visited = [];

    $currentId = $supersedesLinkedFactId;

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

        $currentId = isset(
            $fact['supersedes_linked_fact_id']
        )
            ? (int) $fact['supersedes_linked_fact_id']
            : null;

        if ($currentId < 1) {
            $currentId = null;
        }
    }
}