<?php

declare(strict_types=1);

/*
 * Calendar source-table repair procedure.
 *
 * This file intentionally does not create calendar_events rows.
 * Row creation remains owned by ensure_calendar_node.
 */

function materialize_calendar_chronology(PDO $pdo, int $projectionId): void
{
    if ($projectionId < 1) {
        throw new InvalidArgumentException('projection_id must be positive');
    }

    $stmt = $pdo->prepare("
        UPDATE calendar_events child
        INNER JOIN calendar_events parent
            ON parent.id = child.parent_event_id
        SET
            child.real_date_start_id =
                COALESCE(child.real_date_start_id, parent.real_date_start_id),

            child.real_date_end_id =
                COALESCE(child.real_date_end_id, parent.real_date_end_id),

            child.week_index =
                COALESCE(child.week_index, parent.week_index),

            child.day_index =
                COALESCE(child.day_index, parent.day_index),

            child.time_index =
                COALESCE(child.time_index, parent.time_index),

            child.event_index =
                COALESCE(child.event_index, parent.event_index),

            child.chronology_address =
                COALESCE(
                    child.chronology_address,
                    CONCAT(
                        parent.week_index,
                        '.',
                        parent.day_index,
                        '.',
                        parent.time_index,
                        '.',
                        parent.event_index,
                        '.',
                        child.subevent_index
                    )
                )
        WHERE child.layer_id = 'calendar_layer_subevent'
          AND parent.layer_id = 'calendar_layer_event'
          AND child.projection_id = :projection_id
          AND parent.projection_id = :projection_id
    ");

    $stmt->execute([
        ':projection_id' => $projectionId,
    ]);

    $validate = $pdo->prepare("
        SELECT COUNT(*)
        FROM calendar_events child
        INNER JOIN calendar_events parent
            ON parent.id = child.parent_event_id
        WHERE child.layer_id = 'calendar_layer_subevent'
          AND parent.layer_id = 'calendar_layer_event'
          AND child.projection_id = :projection_id
          AND parent.projection_id = :projection_id
          AND parent.real_date_start_id IS NOT NULL
          AND (
                child.real_date_start_id IS NULL
             OR child.chronology_address IS NULL
             OR child.week_index IS NULL
             OR child.day_index IS NULL
             OR child.time_index IS NULL
             OR child.event_index IS NULL
          )
    ");

    $validate->execute([
        ':projection_id' => $projectionId,
    ]);

    $missing = (int)$validate->fetchColumn();

    if ($missing > 0) {
        throw new RuntimeException(
            'Chronology materialization incomplete for projection.'
        );
    }
}