<?php
function list_memberships(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT
            tm.membership_id,
            tm.entity_id AS membership_entity_id,
            tm.member_id,
            tm.member_entity_id,

            tm.team_id,
            t.entity_id AS team_entity_id,
            t.team_name,

            tm.role_rank,
            tm.performance_number,
            tm.team_title,

            tm.membership_class_id,
            mcc.label AS membership_class_label,

            tm.status_id,
            tm.status_year_id,

            tr.role_id,
            tr.role_code,
            tr.role_label,
            tmr.is_primary_role

        FROM team_memberships tm
        JOIN teams t
            ON t.team_id = tm.team_id

        LEFT JOIN team_membership_roles tmr
            ON tmr.membership_id = tm.membership_id

        LEFT JOIN team_roles tr
            ON tr.role_id = tmr.role_id

        LEFT JOIN team_membership_class_classvals mcc
            ON mcc.id = tm.membership_class_id

        ORDER BY
            t.team_name ASC,
            tm.role_rank ASC,
            tm.performance_number ASC,
            tr.role_label ASC,
            tm.member_entity_id ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}