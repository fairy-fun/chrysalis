<?php
declare(strict_types=1);

/**
 * Persistence Contract Registry (STATIC)
 *
 * Purpose:
 * - Defines entity_type_id → table mapping
 * - Defines 1-hop lookup field rules
 *
 * Hard rules:
 * - NO DB access
 * - NO inference
 * - NO runtime mutation
 * - CI-authoritative static mapping
 */

const FW_PERSISTENCE_CONTRACTS = [

    'calendar_event' => [
        'table_name' => 'calendar_events',
        'lookup_field' => 'id',
        'lookup_operator' => '=',
        'max_hops' => 1,
    ],

    'calendar_subevent' => [
        'table_name' => 'calendar_subevents',
        'lookup_field' => 'id',
        'lookup_operator' => '=',
        'max_hops' => 1,
    ],

    'calendar_day' => [
        'table_name' => 'calendar_days',
        'lookup_field' => 'id',
        'lookup_operator' => '=',
        'max_hops' => 1,
    ],

    'calendar_week' => [
        'table_name' => 'calendar_weeks',
        'lookup_field' => 'id',
        'lookup_operator' => '=',
        'max_hops' => 1,
    ],

    'calendar_time' => [
        'table_name' => 'calendar_times',
        'lookup_field' => 'id',
        'lookup_operator' => '=',
        'max_hops' => 1,
    ],

    'prose_draft' => [
        'table_name' => 'prose_drafts',
        'lookup_field' => 'id',
        'lookup_operator' => '=',
        'max_hops' => 1,
    ],

    'dream_journal_entry' => [
        'table_name' => 'dream_journal_entries',
        'lookup_field' => 'id',
        'lookup_operator' => '=',
        'max_hops' => 1,
    ],

];

/**
 * Resolve persistence contract for an entity type.
 *
 * @param string $entityTypeId
 * @return array|null
 */
function fw_persistence_contract_resolve(string $entityTypeId): ?array
{
    if (!isset(FW_PERSISTENCE_CONTRACTS[$entityTypeId])) {
        return null;
    }

    return FW_PERSISTENCE_CONTRACTS[$entityTypeId];
}