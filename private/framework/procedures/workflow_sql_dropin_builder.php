<?php

declare(strict_types=1);

function fw_workflow_sql_literal(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
}

function fw_workflow_operator_sql_dropin(string $title, string $sql): array
{
    return [
        'title' => $title,
        'sql' => trim($sql),
    ];
}

function fw_workflow_calendar_book_week_sql_dropin(int $weekId): array
{
    return fw_workflow_operator_sql_dropin(
        'Verify created Book week',
        sprintf(
            <<<'SQL'
SELECT
    cbw.id,
    cbw.entity_id,
    cbw.projection_id,
    cp.projection_code,
    cp.projection_title,
    cbw.week_index,
    cbw.summary,
    cbw.notes,
    cbw.real_date_start_id,
    start_date_lookup.date_value AS real_date_start,
    cbw.real_date_end_id,
    end_date_lookup.date_value AS real_date_end,
    cbw.created_at,
    cbw.updated_at
FROM sxnzlfun_chrysalis.calendar_book_weeks cbw
INNER JOIN sxnzlfun_chrysalis.calendar_projections cp
    ON cp.id = cbw.projection_id
LEFT JOIN sxnzlfun_chrysalis.dates start_date_lookup
    ON start_date_lookup.id = cbw.real_date_start_id
LEFT JOIN sxnzlfun_chrysalis.dates end_date_lookup
    ON end_date_lookup.id = cbw.real_date_end_id
WHERE cbw.id = %s
LIMIT 1;
SQL,
            fw_workflow_sql_literal($weekId)
        )
    );
}

function fw_workflow_calendar_book_day_sql_dropin(int $dayId): array
{
    return fw_workflow_operator_sql_dropin(
        'Verify created Book day',
        sprintf(
            <<<'SQL'
SELECT
    cbd.id,
    cbd.entity_id,
    cbd.projection_id,
    cp.projection_code,
    cbd.week_id,
    cbw.week_index,
    cbd.day_index,
    cbd.day_of_week_id,
    dow.code AS day_of_week_code,
    dow.label AS day_of_week_label,
    cbd.summary,
    cbd.notes,
    cbd.real_date_start_id,
    start_date_lookup.date_value AS real_date_start,
    cbd.real_date_end_id,
    end_date_lookup.date_value AS real_date_end,
    cbd.created_at,
    cbd.updated_at
FROM sxnzlfun_chrysalis.calendar_book_days cbd
INNER JOIN sxnzlfun_chrysalis.calendar_book_weeks cbw
    ON cbw.id = cbd.week_id
INNER JOIN sxnzlfun_chrysalis.calendar_projections cp
    ON cp.id = cbd.projection_id
LEFT JOIN sxnzlfun_chrysalis.day_of_week_classvals dow
    ON dow.id = cbd.day_of_week_id
LEFT JOIN sxnzlfun_chrysalis.dates start_date_lookup
    ON start_date_lookup.id = cbd.real_date_start_id
LEFT JOIN sxnzlfun_chrysalis.dates end_date_lookup
    ON end_date_lookup.id = cbd.real_date_end_id
WHERE cbd.id = %s
LIMIT 1;
SQL,
            fw_workflow_sql_literal($dayId)
        )
    );
}

function fw_workflow_calendar_book_time_sql_dropin(int $timeId): array
{
    return fw_workflow_operator_sql_dropin(
        'Verify created Book time',
        sprintf(
            <<<'SQL'
SELECT
    cbt.id,
    cbt.entity_id,
    cbt.projection_id,
    cp.projection_code,
    cbt.day_id,
    cbd.week_id,
    cbw.week_index,
    cbd.day_index,
    cbt.time_index,
    cbt.time_label_id,
    cv.label AS time_label,
    cbt.summary,
    cbt.notes,
    cbd.real_date_start_id AS day_real_date_start_id,
    start_date_lookup.date_value AS day_real_date_start,
    cbd.real_date_end_id AS day_real_date_end_id,
    end_date_lookup.date_value AS day_real_date_end,
    cbt.created_at,
    cbt.updated_at
FROM sxnzlfun_chrysalis.calendar_book_times cbt
INNER JOIN sxnzlfun_chrysalis.calendar_book_days cbd
    ON cbd.id = cbt.day_id
INNER JOIN sxnzlfun_chrysalis.calendar_book_weeks cbw
    ON cbw.id = cbd.week_id
INNER JOIN sxnzlfun_chrysalis.calendar_projections cp
    ON cp.id = cbt.projection_id
LEFT JOIN sxnzlfun_chrysalis.calendar_time_label_classvals cv
    ON TRIM(cv.id) = TRIM(cbt.time_label_id)
LEFT JOIN sxnzlfun_chrysalis.dates start_date_lookup
    ON start_date_lookup.id = cbd.real_date_start_id
LEFT JOIN sxnzlfun_chrysalis.dates end_date_lookup
    ON end_date_lookup.id = cbd.real_date_end_id
WHERE cbt.id = %s
LIMIT 1;
SQL,
            fw_workflow_sql_literal($timeId)
        )
    );
}

function fw_workflow_calendar_book_event_sql_dropin(int $eventId): array
{
    return fw_workflow_operator_sql_dropin(
        'Verify created Book event',
        sprintf(
            <<<'SQL'
SELECT
    ce.id,
    ce.entity_id,
    ce.projection_id,
    cp.projection_code,
    ce.book_time_id,
    cbt.day_id,
    cbd.week_id,
    cbw.week_index,
    cbd.day_index,
    cbt.time_index,
    ce.event_index,
    ce.sequence_index,
    ce.layer_id,
    ce.summary,
    ce.notes,
    ce.real_date_start_id,
    event_start_lookup.date_value AS event_real_date_start,
    ce.real_date_end_id,
    event_end_lookup.date_value AS event_real_date_end,
    cbd.real_date_start_id AS book_day_real_date_start_id,
    day_start_lookup.date_value AS book_day_real_date_start,
    cbd.real_date_end_id AS book_day_real_date_end_id,
    day_end_lookup.date_value AS book_day_real_date_end,
    ce.created_at,
    ce.updated_at
FROM sxnzlfun_chrysalis.calendar_events ce
INNER JOIN sxnzlfun_chrysalis.calendar_projections cp
    ON cp.id = ce.projection_id
LEFT JOIN sxnzlfun_chrysalis.calendar_book_times cbt
    ON cbt.id = ce.book_time_id
LEFT JOIN sxnzlfun_chrysalis.calendar_book_days cbd
    ON cbd.id = cbt.day_id
LEFT JOIN sxnzlfun_chrysalis.calendar_book_weeks cbw
    ON cbw.id = cbd.week_id
LEFT JOIN sxnzlfun_chrysalis.dates event_start_lookup
    ON event_start_lookup.id = ce.real_date_start_id
LEFT JOIN sxnzlfun_chrysalis.dates event_end_lookup
    ON event_end_lookup.id = ce.real_date_end_id
LEFT JOIN sxnzlfun_chrysalis.dates day_start_lookup
    ON day_start_lookup.id = cbd.real_date_start_id
LEFT JOIN sxnzlfun_chrysalis.dates day_end_lookup
    ON day_end_lookup.id = cbd.real_date_end_id
WHERE ce.id = %s
LIMIT 1;
SQL,
            fw_workflow_sql_literal($eventId)
        )
    );
}
