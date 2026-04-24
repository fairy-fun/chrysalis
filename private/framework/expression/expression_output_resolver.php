<?php


declare(strict_types=1);

require_once __DIR__ . '/expression_candidate_reader.php';

const EXPRESSION_LAYER_VOICE = 'layer_voice';
const EXPRESSION_LAYER_PSYCH = 'layer_psych';
const EXPRESSION_LAYER_LIMBIC = 'layer_limbic';

function resolve_character_expression_output(PDO $pdo, string $characterId, ?string $domainId = null): array
{
    $characterId = trim($characterId);
    if ($characterId === '') {
        throw new InvalidArgumentException('character_id must be a non-empty string');
    }

    $domainId = is_string($domainId) ? trim($domainId) : null;
    if ($domainId === '') {
        $domainId = null;
    }

    $domainId = resolve_attribute_domain_id($pdo, $domainId);

    $rows = read_expression_candidates($pdo, $characterId);

    $candidatesByAttribute = [];

    foreach ($rows as $row) {
        if ($domainId !== null && (string)($row['domain_id'] ?? '') !== $domainId) {
            continue;
        }

        $candidate = normalise_expression_candidate($row);
        if ($candidate === null) {
            continue;
        }

        $attributeTypeId = $candidate['attribute_type_id'];
        $candidatesByAttribute[$attributeTypeId][] = $candidate;
    }

    if ($domainId !== null && count($rows) > 0 && empty($candidatesByAttribute)) {
        $availableDomains = array_values(array_unique(array_map(
            static fn(array $row): string => (string)($row['domain_id'] ?? ''),
            $rows
        )));

        throw new RuntimeException(
            'No expression candidates matched domain_id=' . $domainId .
            '; available domain_ids=' . implode(',', $availableDomains)
        );
    }

    $winners = [];
    foreach ($candidatesByAttribute as $attributeTypeId => $candidates) {
        usort($candidates, 'compare_expression_candidates');
        $winners[$attributeTypeId] = $candidates[0];
    }

    $voice = [];
    $psych = [];
    $limbic = [];
    $unlayered = [];

    foreach ($winners as $attributeTypeId => $winner) {
        $value = $winner['value'];

        switch ($winner['layer_classval_id']) {
            case EXPRESSION_LAYER_VOICE:
                $voice[$attributeTypeId] = $value;
                break;

            case EXPRESSION_LAYER_PSYCH:
                $psych[$attributeTypeId] = $value;
                break;

            case EXPRESSION_LAYER_LIMBIC:
                $limbic[$attributeTypeId] = $value;
                break;

            default:
                $unlayered[$attributeTypeId] = $value;
                break;
        }
    }

    return [
        'character_id' => $characterId,
        'domain_id' => $domainId,
        'candidate_count' => count($rows),
        'winner_count' => count($winners),
        'voice_state' => $voice,
        'psych_state' => $psych,
        'limbic_state' => $limbic,
        'unlayered_state' => $unlayered,
        'winners' => $winners,
    ];
}

function normalise_expression_candidate(array $row): ?array
{
    $attributeTypeId = trim((string)($row['attribute_type_id'] ?? ''));
    if ($attributeTypeId === '') {
        throw new RuntimeException('Expression candidate is missing attribute_type_id');
    }

    $hasText = array_key_exists('value_text', $row)
        && $row['value_text'] !== null
        && trim((string)$row['value_text']) !== '';

    $hasClassval = array_key_exists('value_classval_id', $row)
        && $row['value_classval_id'] !== null
        && trim((string)$row['value_classval_id']) !== '';

    if ($hasText && $hasClassval) {
        throw new RuntimeException('Expression candidate has both value_text and value_classval_id for ' . $attributeTypeId);
    }

    if (!$hasText && !$hasClassval) {
        return null;
    }

    $priority = $row['priority'];
    if ($priority === null || $priority === '') {
        $priority = PHP_INT_MAX;
    }

    return [
        'profile_id' => (int)$row['profile_id'],
        'profile_type_id' => (string)$row['profile_type_id'],
        'priority' => (int)$priority,
        'profile_updated_at' => (string)$row['profile_updated_at'],

        'attribute_id' => (int)$row['attribute_id'],
        'attribute_type_id' => $attributeTypeId,
        'domain_id' => $row['domain_id'] !== null ? (string)$row['domain_id'] : null,
        'layer_classval_id' => $row['layer_classval_id'] !== null ? (string)$row['layer_classval_id'] : null,

        'value_source' => $hasClassval ? 'classval' : 'text',
        'value' => $hasClassval
            ? trim((string)$row['value_classval_id'])
            : trim((string)$row['value_text']),
    ];
}

function compare_expression_candidates(array $a, array $b): int
{
    $priorityCompare = $a['priority'] <=> $b['priority'];
    if ($priorityCompare !== 0) {
        return $priorityCompare;
    }

    $updatedCompare = strcmp($b['profile_updated_at'], $a['profile_updated_at']);
    if ($updatedCompare !== 0) {
        return $updatedCompare;
    }

    return $b['profile_id'] <=> $a['profile_id'];
}

function resolve_attribute_domain_id(PDO $pdo, ?string $domainId): ?string
{
    if ($domainId === null) {
        return null;
    }

    $domainId = trim($domainId);
    if ($domainId === '') {
        return null;
    }

    if (!expression_domain_aliases_table_exists($pdo)) {
        return $domainId;
    }

    $stmt = $pdo->prepare(
        'SELECT target_domain_id, is_active
         FROM expression_domain_aliases
         WHERE input_domain_id = :input_domain_id
         LIMIT 1'
    );

    $stmt->execute([
        ':input_domain_id' => $domainId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return $domainId;
    }

    if ((int)($row['is_active'] ?? 0) !== 1) {
        throw new RuntimeException('Expression domain alias is inactive for input_domain_id=' . $domainId);
    }

    $mappedDomainId = trim((string)($row['target_domain_id'] ?? ''));
    if ($mappedDomainId === '') {
        throw new RuntimeException('Expression domain alias has empty target_domain_id for input_domain_id=' . $domainId);
    }

    return $mappedDomainId;
}

function expression_domain_aliases_table_exists(PDO $pdo): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
         LIMIT 1'
    );

    $stmt->execute([
        ':table_name' => 'expression_domain_aliases',
    ]);

    $exists = $stmt->fetchColumn() !== false;

    return $exists;
}
