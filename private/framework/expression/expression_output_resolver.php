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