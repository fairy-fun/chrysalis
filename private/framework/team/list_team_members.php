<?php

function list_team_members(PDO $pdo, string $teamEntityId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            tm.membership_id,
            tm.entity_id AS membership_entity_id,
            tm.member_entity_id,
            tm.team_id,
            tm.role_id,
            tm.role_rank,
            tm.performance_number,
            tm.team_title,

            tr.role_code,
            tr.role_label,

            c.character_id,
            c.char_name_full,
            c.char_name_first,
            c.char_name_last

        FROM teams t
        JOIN team_memberships tm
            ON tm.team_id = t.team_id
        LEFT JOIN team_roles tr
            ON tr.role_id = tm.role_id
        LEFT JOIN characters c
            ON c.entity_id = tm.member_entity_id

        WHERE t.entity_id = :team_entity_id

        ORDER BY
            tm.role_rank ASC,
            tm.performance_number ASC,
            tm.member_entity_id ASC'
    );

    $stmt->execute([
        ':team_entity_id' => $teamEntityId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}