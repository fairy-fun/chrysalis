<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/private/framework/api/api_bootstrap.php';

$pdo = makePdo();

$pdo->exec("
    INSERT INTO entity_type_classvals (id, code, label)
    VALUES ('entity_type_status', 'status', 'Status')
    ON DUPLICATE KEY UPDATE code = VALUES(code)
");

$count = $pdo->exec("
    INSERT INTO entities (id, entity_type_id)
    SELECT DISTINCT s.status_id, 'entity_type_status'
    FROM (
        SELECT status_id AS status_id FROM choreography_progress_history
        UNION
        SELECT new_status_id AS status_id FROM choreography_progress_history
        UNION
        SELECT previous_status_id AS status_id FROM choreography_progress_history
        UNION
        SELECT status_id AS status_id FROM company_assignments
        UNION
        SELECT status_id AS status_id FROM relationship_status_history
        UNION
        SELECT status_id AS status_id FROM status_history
        UNION
        SELECT status_id AS status_id FROM team_admin_assignments
        UNION
        SELECT status_id AS status_id FROM team_memberships
    ) s
    WHERE s.status_id IS NOT NULL
      AND s.status_id <> ''
      AND NOT EXISTS (
          SELECT 1 FROM entities e WHERE e.id = s.status_id
      )
");

echo "OK: Mirrored status entities: " . (string)$count . PHP_EOL;