<?php

declare(strict_types=1);

require_once __DIR__ . '/../entity/assert_entity_can_accept_canonical_label.php';

/**
 * Contract harness for canonical label write guard.
 *
 * Assumes CI fixtures exist for these cases.
 *
 * @return array{ok:bool, errors:list<string>}
 */
function validate_canonical_label_write_guard_contract(PDO $pdo): array
{
    $errors = [];

    try {
        assert_entity_can_accept_canonical_label(
            $pdo,
            'ci_entity_song_blue_moon',
            'entity_type_song',
            'Blue Moon'
        );
    } catch (Throwable $e) {
        $errors[] = 'Expected idempotent canonical write to pass, but failed: ' . $e->getMessage();
    }

    try {
        assert_entity_can_accept_canonical_label(
            $pdo,
            'ci_entity_song_blue_moon',
            'entity_type_song',
            'Blue Moon Alt'
        );
        $errors[] = 'Expected different canonical label on same entity to fail, but it passed';
    } catch (Throwable $e) {
        // expected
    }

    try {
        assert_entity_can_accept_canonical_label(
            $pdo,
            'ci_entity_song_blue_moon_other',
            'entity_type_song',
            'Blue Moon'
        );
        $errors[] = 'Expected canonical label reuse across same-type entities to fail, but it passed';
    } catch (Throwable $e) {
        // expected
    }

    return [
        'ok' => count($errors) === 0,
        'errors' => $errors,
    ];
}