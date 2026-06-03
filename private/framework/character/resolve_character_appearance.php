<?php

declare(strict_types=1);

function resolve_character_appearance(PDO $pdo, string $characterId): array
{
    $characterId = normalize_required_character_id($characterId);

    $rows = fetch_materialized_character_appearance_rows($pdo, $characterId);

    if ($rows !== []) {
        return $rows;
    }

    return derive_character_appearance_rows_from_source($pdo, $characterId);
}

function rebuild_character_appearance_resolved(PDO $pdo, ?string $characterId = null): array
{
    $characterId = normalize_optional_character_id($characterId);
    $sourceRows = derive_character_appearance_rows_from_source($pdo, $characterId);

    $pdo->beginTransaction();

    try {
        if ($characterId === null) {
            $pdo->exec('DELETE FROM v_character_appearance_resolved');
        } else {
            $delete = $pdo->prepare(
                <<<'SQL'
DELETE FROM v_character_appearance_resolved
WHERE character_id = :character_id
SQL
            );

            $delete->execute([
                ':character_id' => $characterId,
            ]);
        }

        if ($sourceRows !== []) {
            $insert = $pdo->prepare(
                <<<'SQL'
INSERT INTO v_character_appearance_resolved (
    character_id,
    attribute_id,
    attribute_type_id,
    value_classval_id,
    tag_id
)
VALUES (
    :character_id,
    :attribute_id,
    :attribute_type_id,
    :value_classval_id,
    :tag_id
)
SQL
            );

            foreach ($sourceRows as $row) {
                $insert->execute([
                    ':character_id' => $row['character_id'],
                    ':attribute_id' => $row['attribute_id'],
                    ':attribute_type_id' => $row['attribute_type_id'],
                    ':value_classval_id' => $row['value_classval_id'],
                    ':tag_id' => $row['tag_id'],
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $resolvedCharacterIds = [];

    foreach ($sourceRows as $row) {
        $resolvedCharacterIds[$row['character_id']] = $row['character_id'];
    }

    return [
        'scope' => $characterId === null ? 'all' : 'character',
        'character_id' => $characterId,
        'rebuilt_row_count' => count($sourceRows),
        'resolved_character_ids' => array_values($resolvedCharacterIds),
    ];
}

function fetch_materialized_character_appearance_rows(PDO $pdo, string $characterId): array
{
    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT
    r.character_id,
    r.attribute_id,
    r.attribute_type_id,
    r.value_classval_id,
    r.tag_id,
    at.code AS tag_code,
    at.label AS tag_label,
    cv.code AS value_classval_code,
    cv.label AS value_classval_label
FROM v_character_appearance_resolved r
INNER JOIN appearance_tags at
    ON at.id = r.tag_id
LEFT JOIN classvals cv
    ON cv.id = r.value_classval_id
WHERE r.character_id = :character_id
ORDER BY r.attribute_id ASC, r.tag_id ASC
SQL
    );

    $stmt->execute([
        ':character_id' => $characterId,
    ]);

    return normalize_character_appearance_rows($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function derive_character_appearance_rows_from_source(PDO $pdo, ?string $characterId = null): array
{
    $whereSql = '';
    $params = [];

    if ($characterId !== null) {
        $whereSql = 'WHERE cp.character_id = :character_id';
        $params[':character_id'] = $characterId;
    }

    $stmt = $pdo->prepare(
        sprintf(
            <<<'SQL'
SELECT
    cp.character_id,
    cpa.attribute_id,
    cpa.attribute_type_id,
    cpa.value_classval_id,
    cpat.tag_id,
    at.code AS tag_code,
    at.label AS tag_label,
    cv.code AS value_classval_code,
    cv.label AS value_classval_label
FROM character_profiles cp
INNER JOIN character_profile_attributes cpa
    ON cpa.profile_id = cp.profile_id
INNER JOIN character_profile_attribute_tags cpat
    ON cpat.attribute_id = cpa.attribute_id
INNER JOIN appearance_tags at
    ON at.id = cpat.tag_id
LEFT JOIN classvals cv
    ON cv.id = cpa.value_classval_id
%s
ORDER BY cp.character_id ASC, cpa.attribute_id ASC, cpat.tag_id ASC
SQL,
            $whereSql
        )
    );

    $stmt->execute($params);

    return normalize_character_appearance_rows($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function normalize_character_appearance_rows(array|false $rows): array
{
    if (!is_array($rows)) {
        return [];
    }

    $normalized = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $valueClassvalId = $row['value_classval_id'] ?? null;
        $valueClassvalCode = $row['value_classval_code'] ?? null;
        $valueClassvalLabel = $row['value_classval_label'] ?? null;

        $normalized[] = [
            'character_id' => trim((string) ($row['character_id'] ?? '')),
            'attribute_id' => isset($row['attribute_id']) ? (int) $row['attribute_id'] : 0,
            'attribute_type_id' => trim((string) ($row['attribute_type_id'] ?? '')),
            'value_classval_id' => $valueClassvalId === null ? null : trim((string) $valueClassvalId),
            'value_classval_code' => $valueClassvalCode === null ? null : trim((string) $valueClassvalCode),
            'value_classval_label' => $valueClassvalLabel === null ? null : trim((string) $valueClassvalLabel),
            'tag_id' => trim((string) ($row['tag_id'] ?? '')),
            'tag_code' => trim((string) ($row['tag_code'] ?? '')),
            'tag_label' => trim((string) ($row['tag_label'] ?? '')),
        ];
    }

    return $normalized;
}

function normalize_required_character_id(string $characterId): string
{
    $characterId = trim($characterId);

    if ($characterId === '') {
        throw new InvalidArgumentException('character_id must be a non-empty string');
    }

    return $characterId;
}

function normalize_optional_character_id(?string $characterId): ?string
{
    if ($characterId === null) {
        return null;
    }

    $characterId = trim($characterId);

    if ($characterId === '') {
        return null;
    }

    return $characterId;
}
