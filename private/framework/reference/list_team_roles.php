<?php

declare(strict_types=1);

function list_team_roles(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT
            tr.role_id AS id,
            tr.role_label AS label,

            tr.role_id,
            tr.role_code,
            tr.role_label,
            tr.description,
            tr.sort_order,
            tr.role_category_id,
            tr.role_tier_id,

            rc.role_category_code,
            rc.role_category_label

        FROM team_roles tr
        LEFT JOIN team_role_categories rc
            ON rc.role_category_id = tr.role_category_id

        ORDER BY
            tr.sort_order ASC,
            tr.role_label ASC,
            tr.role_id ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}