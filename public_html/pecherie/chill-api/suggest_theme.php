<?php

require_once __DIR__ . '/private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/private/framework/expression/entity_event_theme_link_suggester.php';

$pdo = makePdo();

$result = suggest_entity_event_theme_link(
    $pdo,
    'res_expr_1_6_2_shay_seed'
);

print_r($result);