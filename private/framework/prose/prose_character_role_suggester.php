<?php

declare(strict_types=1);

function fetch_calendar_relation_role_vocabulary(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            id,
            code,
            label
        FROM calendar_relation_role_classvals
        ORDER BY id ASC
    ");

    $roles = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = trim((string)($row['id'] ?? ''));
        $code = trim((string)($row['code'] ?? ''));
        $label = trim((string)($row['label'] ?? ''));

        if ($id === '') {
            continue;
        }

        $normalizedCode = mb_strtolower($code !== '' ? $code : $id);
        $normalizedId = mb_strtolower($id);

        $record = [
            'id' => $id,
            'code' => $code !== '' ? $code : $id,
            'label' => $label !== '' ? $label : ($code !== '' ? $code : $id),
        ];

        $roles[$normalizedId] = $record;
        $roles[$normalizedCode] = $record;
    }

    return $roles;
}

function prose_character_role_sentence_excerpt(
    string $proseBody,
    array $surfaceForms
): ?string {

    $sentences = preg_split('/(?<=[.!?])\s+/u', $proseBody) ?: [];

    foreach ($surfaceForms as $surfaceForm) {
        $surface = trim((string)$surfaceForm);

        if ($surface === '') {
            continue;
        }

        foreach ($sentences as $sentence) {
            if (!is_string($sentence)) {
                continue;
            }

            if (mb_stripos($sentence, $surface) !== false) {
                return trim($sentence);
            }
        }
    }

    return null;
}

function prose_character_role_detect_from_excerpt(
    string $excerpt,
    array $rolesByKey
): ?array {

    $signals = [
        'role_observer' => [
            'observed',
            'watched',
            'watching',
            'witnessed',
            'from the doorway',
            'looked on',
            'stood by',
        ],
        'role_initiator' => [
            'approached',
            'greeted',
            'called to',
            'called out',
            'initiated',
            'started the conversation',
            'began speaking',
        ],
        'role_instructor' => [
            'taught',
            'instructed',
            'guided',
            'showed',
            'explained to',
        ],
        'role_student' => [
            'learned from',
            'studied under',
            'listened carefully',
            'followed the lesson',
        ],
        'role_subject' => [
            'was questioned',
            'was examined',
            'was interviewed',
            'was observed',
        ],
    ];

    $excerptLower = mb_strtolower($excerpt);

    foreach ($signals as $roleCode => $phrases) {
        foreach ($phrases as $phrase) {
            if (!str_contains($excerptLower, mb_strtolower($phrase))) {
                continue;
            }

            $role = $rolesByKey[mb_strtolower($roleCode)] ?? null;

            if (!is_array($role)) {
                continue;
            }

            return [
                'role' => $role,
                'signals' => [$phrase],
            ];
        }
    }

    return null;
}

function suggest_prose_character_roles(
    PDO $pdo,
    string $proseBody,
    array $approvedCharacters,
    array $context = []
): array {

    $rolesByKey = fetch_calendar_relation_role_vocabulary($pdo);
    $suggestions = [];

    foreach ($approvedCharacters as $character) {
        if (!is_array($character)) {
            continue;
        }

        $entityId = trim((string)($character['resolved_entity_id'] ?? ''));

        if ($entityId === '') {
            continue;
        }

        $surfaceForms = $character['surface_forms'] ?? [];

        if (!is_array($surfaceForms)) {
            $surfaceForms = [];
        }

        $excerpt = prose_character_role_sentence_excerpt(
            $proseBody,
            $surfaceForms
        );

        $detected = is_string($excerpt)
            ? prose_character_role_detect_from_excerpt($excerpt, $rolesByKey)
            : null;

        $role = is_array($detected) ? ($detected['role'] ?? null) : null;
        $signals = is_array($detected) ? ($detected['signals'] ?? []) : [];

        $suggestions[] = [
            'resolved_entity_id' => $entityId,
            'candidate_label' => $character['candidate_label'] ?? $entityId,
            'surface_forms' => $surfaceForms,
            'suggested_role_id' => is_array($role) ? ($role['id'] ?? null) : null,
            'suggested_role_code' => is_array($role) ? ($role['code'] ?? null) : null,
            'suggested_role_label' => is_array($role) ? ($role['label'] ?? null) : null,
            'role_confidence' => is_array($role) ? 0.7 : 0.0,
            'role_evidence' => [
                'surface_excerpt' => $excerpt,
                'signals' => $signals,
            ],
            'role_resolution_status' => is_array($role) ? 'suggested' : 'unresolved',
        ];
    }

    return [
        'roles' => $suggestions,
        'role_suggestion_count' => count(array_filter(
            $suggestions,
            static fn (array $suggestion): bool =>
                trim((string)($suggestion['suggested_role_id'] ?? '')) !== ''
        )),
        'role_vocabulary' => array_values(array_reduce(
            $rolesByKey,
            static function (array $carry, mixed $role): array {
                if (!is_array($role)) {
                    return $carry;
                }

                $id = trim((string)($role['id'] ?? ''));

                if ($id === '' || isset($carry[$id])) {
                    return $carry;
                }

                $carry[$id] = $role;

                return $carry;
            },
            []
        )),
        'approval_required' => true,
    ];
}
