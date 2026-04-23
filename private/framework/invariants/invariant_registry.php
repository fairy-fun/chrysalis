<?php

declare(strict_types=1);

require_once __DIR__ . '/../entity/validate_event_graph_identity_contract.php';

return [
    'event_graph_identity_contract' => [
        'label' => 'event graph identity contract',
        'runner' => static function (PDO $pdo): void {
            validate_event_graph_identity_contract($pdo);
        },
    ],
];
