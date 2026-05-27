<?php

declare(strict_types=1);

require_once __DIR__ . '/calendar_event_scene_signal_extractor.php';
require_once __DIR__ . '/calendar_event_scene_classifier.php';
require_once __DIR__ . '/calendar_event_metadata_mapper.php';

/**
 * Corpus semantic provider for calendar-event metadata derivation.
 *
 * Public entrypoint for the framework prose metadata deriver.
 */
function derive_chrysalis_calendar_event_semantic_metadata(
    string $proseBody,
    array $context = []
): ?array {

    $signals = extract_chrysalis_calendar_event_scene_signals($proseBody);

    if ($signals === []) {
        return null;
    }

    $classification = classify_chrysalis_calendar_event_scene($signals);

    if ($classification === null) {
        return null;
    }

    return map_chrysalis_scene_class_to_calendar_event_metadata(
        $classification,
        $signals,
        $context
    );
}
