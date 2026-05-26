<?php

declare(strict_types=1);

require_once __DIR__ . '/candidate_identity.php';
require_once __DIR__ . '/candidate_priority.php';

function prose_character_sort_resolution_candidates(
    array $candidates
): array {

    usort(
        $candidates,
        static function (array $a, array $b): int {

            $scoreComparison =
                (float)($b['candidate_score'] ?? 0.0)
                <=>
                (float)($a['candidate_score'] ?? 0.0);

            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            $priorityComparison =
                prose_character_arbitration_priority(
                    (string)($b['resolution_method_classval_id'] ?? '')
                )
                <=>
                prose_character_arbitration_priority(
                    (string)($a['resolution_method_classval_id'] ?? '')
                );

            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }

            return strcmp(
                prose_character_build_candidate_identity_key($a),
                prose_character_build_candidate_identity_key($b)
            );
        }
    );

    return array_values($candidates);
}
