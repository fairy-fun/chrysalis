<?php
declare(strict_types=1);

/**
 * Allocate a system-managed prose draft entity ID.
 *
 * Infrastructure identifiers are not authorial payload.
 */

function allocate_prose_draft_entity_id(
    PDO $pdo
): string {

    while (true) {

        $candidate = 'prose_draft:' . bin2hex(
                random_bytes(16)
            );

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM sxnzlfun_chrysalis.prose_drafts
            WHERE entity_id = :entity_id
        ");

        $stmt->execute([
            ':entity_id' => $candidate,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            return $candidate;
        }
    }
}