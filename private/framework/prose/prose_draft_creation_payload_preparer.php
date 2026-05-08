<?php

declare(strict_types=1);

/**
 * Converts normalized prose intake into
 * a persistence-ready draft creation payload.
 *
 * This layer owns infrastructure metadata.
 */

function prepare_prose_draft_creation_payload(
    PDO   $pdo,
    array $hydratedPayload
): array
{

    $draftPayload = $hydratedPayload;

    /*
     * Infrastructure identity.
     */

    $draftPayload['entity_id']
        = 'prose:' . bin2hex(random_bytes(16));

    /*
     * Persistence workflow defaults.
     */

    $draftPayload['draft_status_id']
        = 'status_draft';

    /*
     * Family orchestration.
     *
     * Null means:
     * create_prose_draft()
     * should allocate a new prose family.
     */

    $draftPayload['prose_family_entity_id']
        = null;

    /*
     * Author orchestration.
     *
     * Attach later once session/auth
     * infrastructure exists.
     */

    $draftPayload['author_entity_id']
        = null;

    return $draftPayload;
}