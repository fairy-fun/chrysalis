<?php


declare(strict_types=1);

final class ExpressionLayers
{
    public const VOICE = 'layer_voice';
    public const PSYCH = 'layer_psych';
    public const LIMBIC = 'layer_limbic';
}

function resolve_character_expression_output(
    PDO    $pdo,
    string $characterId,
    ?int   $characterEntityId,
    ?int   $domainId,
    ?int   $interlocutorEntityId,
    ?int   $socialContextId
): array
{
    $candidates = fetch_expression_candidates($pdo, $characterId, $domainId);
    $winners = pick_expression_winners($candidates);

    $resolvedOutput = [
        'layer_voice' => [],
        'layer_psych' => [],
        'layer_limbic' => [],
    ];

    foreach ($winners as $winner) {
        $item = [
            'attribute_type_id' => (string)$winner['attribute_type_id'],
            'profile_id' => (int)$winner['profile_id'],
            'value_text' => $winner['value_text'],
            'value_classval_id' => $winner['value_classval_id'] !== null
                ? (string)$winner['value_classval_id']
                : null,
        ];

        switch ((string)$winner['layer_classval_id']) {
            case ExpressionLayers::VOICE:
                $resolvedOutput['layer_voice'][] = $item;
                break;

            case ExpressionLayers::PSYCH:
                $resolvedOutput['layer_psych'][] = $item;
                break;

            case ExpressionLayers::LIMBIC:
                $resolvedOutput['layer_limbic'][] = $item;
                break;

            default:
                throw new RuntimeException(
                    'Unsupported layer_classval_id: ' . (string)$winner['layer_classval_id']
                );
        }
    }

    // Stable ordering for CI + deterministic output
    foreach ($resolvedOutput as &$layerItems) {
        usort(
            $layerItems,
            static fn(array $a, array $b): int => strcmp($a['attribute_type_id'], $b['attribute_type_id'])
        );
    }
    unset($layerItems);

    return [
        'context' => [
            'character_id' => $characterId,
            'character_entity_id' => $characterEntityId,
            'domain_id' => $domainId,
            'interlocutor_entity_id' => $interlocutorEntityId,
            'social_context_id' => $socialContextId,
        ],
        'resolved_output' => $resolvedOutput,
        'override_rules' => [],
        'surface_directives' => [],
    ];
}

function fetch_expression_candidates(PDO $pdo, string $characterId, ?int $domainId): array
{
    $sql = <<<'SQL'
SELECT
    cp.character_id,
    cp.profile_id,
    cp.profile_type_id,
    ptp.priority AS profile_priority,
    cp.updated_at AS profile_updated_at,
    atlm.layer_classval_id,
    cpa.attribute_type_id,
    cpa.value_text,
    cpa.value_classval_id
FROM sxnzlfun_chrysalis.character_profiles cp
INNER JOIN sxnzlfun_chrysalis.character_profile_attributes cpa
    ON cpa.profile_id = cp.profile_id
INNER JOIN sxnzlfun_chrysalis.profile_type_priority ptp
    ON ptp.profile_type_id = cp.profile_type_id
INNER JOIN sxnzlfun_chrysalis.attribute_type_layer_map atlm
    ON atlm.attribute_type_id = cpa.attribute_type_id
WHERE cp.character_id = :character_id
  AND (
        :domain_id_null IS NULL
        OR NOT EXISTS (
            SELECT 1
            FROM sxnzlfun_chrysalis.attribute_domain_map adm_any
            WHERE adm_any.attribute_type_id = cpa.attribute_type_id
        )
        OR EXISTS (
            SELECT 1
            FROM sxnzlfun_chrysalis.attribute_domain_map adm_match
            WHERE adm_match.attribute_type_id = cpa.attribute_type_id
              AND adm_match.domain_id = :domain_id_match
        )
      )
ORDER BY
    cpa.attribute_type_id ASC,
    ptp.priority DESC,
    cp.updated_at DESC,
    cp.profile_id DESC
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':character_id', $characterId, PDO::PARAM_STR);

    if ($domainId === null) {
        $stmt->bindValue(':domain_id_null', null, PDO::PARAM_NULL);
        $stmt->bindValue(':domain_id_match', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':domain_id_null', $domainId, PDO::PARAM_INT);
        $stmt->bindValue(':domain_id_match', $domainId, PDO::PARAM_INT);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


function pick_expression_winners(array $candidates): array
{
    $winners = [];

    foreach ($candidates as $candidate) {
        $key = (string)$candidate['attribute_type_id'];

        if (!isset($winners[$key])) {
            $winners[$key] = $candidate;
            continue;
        }

        if (is_better_expression_candidate($candidate, $winners[$key])) {
            $winners[$key] = $candidate;
        }
    }

    ksort($winners, SORT_STRING);

    return array_values($winners);
}

function is_better_expression_candidate(array $a, array $b): bool
{
    if ((int)$a['profile_priority'] !== (int)$b['profile_priority']) {
        return (int)$a['profile_priority'] > (int)$b['profile_priority'];
    }

    $aTime = strtotime((string)$a['profile_updated_at']);
    $bTime = strtotime((string)$b['profile_updated_at']);

    if ($aTime !== $bTime) {
        return $aTime > $bTime;
    }

    return (int)$a['profile_id'] > (int)$b['profile_id'];
}