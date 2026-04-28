<?php

declare(strict_types=1);

function suggest_entity_event_theme_link(PDO $pdo, ?string $contextEntityId = null): array
{
    $params = [];

    $where = '';
    if ($contextEntityId !== null && trim($contextEntityId) !== '') {
        $where = 'AND r.context_entity_id = :context_entity_id';
        $params['context_entity_id'] = trim($contextEntityId);
    }

    $stmt = $pdo->prepare(<<<SQL
SELECT DISTINCT
    r.context_entity_id AS subject_entity_id,
    'fact_type_event_theme' AS fact_type_id,
    rule.theme_entity_id AS object_entity_id,
    o.attribute_type_id,
    o.output_value_classval_id,
    rule.confidence_classval_id
FROM sxnzlfun_chrysalis.expression_constraint_runs r
JOIN sxnzlfun_chrysalis.expression_constraint_outputs o
    ON o.constraint_run_id = r.id
JOIN sxnzlfun_chrysalis.expression_theme_inference_rules rule
    ON rule.attribute_type_id = o.attribute_type_id
   AND rule.output_value_classval_id = o.output_value_classval_id
   AND rule.is_active = 1
LEFT JOIN sxnzlfun_chrysalis.entity_linked_facts existing
    ON existing.subject_entity_id = r.context_entity_id
   AND existing.fact_type_id = 'fact_type_event_theme'
   AND existing.object_entity_id = rule.theme_entity_id
WHERE existing.id IS NULL
$where
ORDER BY r.created_at DESC, rule.id ASC
SQL);

    $stmt->execute($params);

    $proposals = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $proposals[] = [
            'proposal_type' => 'entity_event_theme_link',
            'subject_entity_id' => $row['subject_entity_id'],
            'fact_type_id' => $row['fact_type_id'],
            'object_entity_id' => $row['object_entity_id'],
            'match_status' => 'suggested',
            'evidence' => [
                'source' => 'expression_constraint_outputs',
                'attribute_type_id' => $row['attribute_type_id'],
                'output_value_classval_id' => $row['output_value_classval_id'],
                'confidence_classval_id' => $row['confidence_classval_id'],
            ],
            'proposed_action' => [
                'table' => 'entity_linked_facts',
                'operation' => 'insert_if_missing',
            ],
            'sql_text' => null,
        ];
    }

    return [
        'status' => 'ok',
        'proposal_count' => count($proposals),
        'proposals' => $proposals,
    ];
}