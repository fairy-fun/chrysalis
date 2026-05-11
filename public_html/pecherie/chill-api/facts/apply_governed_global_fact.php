<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../private/framework/api/api_bootstrap.php';
require_once __DIR__ . '/../../../../private/framework/facts/apply_fact.php';

requireAuth();

$body = getJsonBody();

$pdo = makePdo('write');