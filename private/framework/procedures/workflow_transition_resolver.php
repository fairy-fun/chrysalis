<?php
declare(strict_types=1);

function fw_resolve_workflow_transition(
    array $transition,
    bool $success,
    array $input = [],
    array $context = []
): ?string {

    return fw_dispatch_workflow_transition(
        $transition,
        $success,
        $input,
        $context
    );
}