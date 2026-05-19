<?php

declare(strict_types=1);

/**
 * Derive calendar beat metadata from prose.
 *
 * Live DB contract:
 * - sxnzlfun_chrysalis.calendar_beat_type_rules
 * - sxnzlfun_chrysalis.cvt_calendar_beat_type
 *
 * Supported live matcher:
 * - contains
 */
function derive_calendar_beat(PDO $pdo, string $proseBody, array $context = []): array
{
    $normalized = normalize_calendar_beat_text($proseBody);

    if ($normalized === '') {
        return [
            'beat_type_id' => null,
            'beat_code' => null,
            'label' => null,
            'beat_hash' => null,
            'score' => 0,
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            r.set_id,
            r.code,
            r.pattern,
            r.match_type,
            r.weight,
            r.priority,
            bt.id AS beat_type_id,
            bt.label AS beat_label
        FROM sxnzlfun_chrysalis.calendar_beat_type_rules r
        INNER JOIN sxnzlfun_chrysalis.cvt_calendar_beat_type bt
            ON bt.set_id = r.set_id
           AND bt.code = r.code
        WHERE r.is_active = 1
        ORDER BY r.priority DESC, r.weight DESC, r.id ASC
    ");

    $stmt->execute();

    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $scores = [];

    foreach ($rules as $rule) {
        $pattern = normalize_calendar_beat_text((string)($rule['pattern'] ?? ''));

        if ($pattern === '') {
            continue;
        }

        $matchType = strtolower((string)($rule['match_type'] ?? 'contains'));

        $matched = false;

        switch ($matchType) {
            case 'contains':
                $matched = str_contains($normalized, $pattern);
                break;

            case 'equals':
                $matched = ($normalized === $pattern);
                break;

            case 'regex':
                $matched = @preg_match((string)$rule['pattern'], $proseBody) === 1;
                break;

            default:
                continue 2;
        }

        if (!$matched) {
            continue;
        }

        $beatTypeId = (string)$rule['beat_type_id'];

        if (!isset($scores[$beatTypeId])) {
            $scores[$beatTypeId] = [
                'beat_type_id' => $beatTypeId,
                'beat_code' => (string)$rule['code'],
                'label' => (string)$rule['beat_label'],
                'score' => 0,
                'priority' => (int)$rule['priority'],
                'matched_patterns' => [],
            ];
        }

        $weight = (int)$rule['weight'];

        $scores[$beatTypeId]['score'] += $weight;
        $scores[$beatTypeId]['priority'] = max(
            $scores[$beatTypeId]['priority'],
            (int)$rule['priority']
        );
        $scores[$beatTypeId]['matched_patterns'][] = (string)$rule['pattern'];
    }

    if ($scores === []) {
        return derive_calendar_beat_fallback($normalized);
    }

    usort(
        $scores,
        static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }

            return strcmp($a['beat_type_id'], $b['beat_type_id']);
        }
    );

    $winner = $scores[0];

    $winner['beat_hash'] = calendar_beat_hash(
        (string)$winner['beat_type_id'],
        $normalized
    );

    return $winner;
}

function normalize_calendar_beat_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    $text = preg_replace('/\s+/', ' ', $text) ?? '';

    return mb_strtolower(trim($text));
}

function calendar_beat_hash(string $beatTypeId, string $normalizedProse): string
{
    return sha1(
        mb_strtolower(trim($beatTypeId)) . '|' . $normalizedProse
    );
}

function derive_calendar_beat_fallback(string $normalizedProse): array
{
    $label = derive_calendar_beat_fallback_label($normalizedProse);

    return [
        'beat_type_id' => null,
        'beat_code' => null,
        'label' => $label,
        'beat_hash' => calendar_beat_hash('unclassified', $normalizedProse),
        'score' => 0,
        'matched_patterns' => [],
    ];
}

function derive_calendar_beat_fallback_label(string $normalizedProse): string
{
    if ($normalizedProse === '') {
        return 'Subevent';
    }

    $sentences = preg_split('/(?<=[.!?])\s+/', $normalizedProse);

    if (is_array($sentences) && isset($sentences[0])) {
        $candidate = trim($sentences[0]);

        if ($candidate !== '') {
            return calendar_beat_title_case(
                mb_substr($candidate, 0, 80)
            );
        }
    }

    return calendar_beat_title_case(
        mb_substr($normalizedProse, 0, 80)
    );
}

function calendar_beat_title_case(string $text): string
{
    $text = trim($text);

    if ($text === '') {
        return 'Subevent';
    }

    return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
}