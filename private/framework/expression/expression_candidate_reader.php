<?php


declare(strict_types=1);

function read_expression_candidates(PDO $pdo, string $characterId): array
{
    $characterId = trim($characterId);

    if ($characterId === '') {
        throw new InvalidArgumentException('character_id must be a non-empty string');
    }

    $stmt = $pdo->prepare(<<<'SQL'
SELECT
    cp.profile_id,
    cp.character_id,
    cp.profile_type_id,
    cp.updated_at AS profile_updated_at,

    ptp.priority,

    cpa.attribute_id,
    cpa.attribute_type_id,
    cpa.value_text,
    cpa.value_classval_id,
    cpa.updated_at AS attribute_updated_at,

    adm.domain_id,
    adm.attribute_type_type_id,

    atl.layer_classval_id

FROM sxnzlfun_chrysalis.character_profiles cp

JOIN sxnzlfun_chrysalis.character_profile_attributes cpa
  ON cpa.profile_id = cp.profile_id

LEFT JOIN sxnzlfun_chrysalis.profile_type_priority ptp
  ON ptp.profile_type_id = cp.profile_type_id

LEFT JOIN sxnzlfun_chrysalis.attribute_domain_map adm
  ON adm.attribute_type_id = cpa.attribute_type_id

LEFT JOIN sxnzlfun_chrysalis.attribute_type_layer_map atl
  ON atl.attribute_type_id = cpa.attribute_type_id

WHERE cp.character_id = :character_id

ORDER BY
    COALESCE(ptp.priority, 2147483647) ASC,
    cp.updated_at DESC,
    cp.profile_id DESC,
    cpa.attribute_type_id ASC
SQL
    );

    $stmt->execute([
        'character_id' => $characterId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}