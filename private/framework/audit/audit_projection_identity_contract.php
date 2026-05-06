<?php

declare(strict_types=1);

function assert_projection_identity_contract(): void
{
    $frameworkPath = realpath(__DIR__ . '/..');

    if ($frameworkPath === false) {
        throw new RuntimeException(
            'Unable to resolve framework path.'
        );
    }

    $runtimeViolationCommand = <<<BASH
grep -RInE "\['projection_entity_id'\]|->projection_entity_id|nto[0-9]*\.projection_entity_id|:projection_entity_id" {$frameworkPath} \
| grep -v "calendar_node_ensurer.php" || true
BASH;

    $runtimeViolations = trim(
        (string) shell_exec($runtimeViolationCommand)
    );

    if ($runtimeViolations !== '') {
        throw new RuntimeException(
            "Projection identity runtime contract violated.\n\n"
            . $runtimeViolations
        );
    }

    $compatibilityAuditCommand = <<<BASH
grep -RIn "projection_entity_id" {$frameworkPath} \
| grep -v "calendar_projection_resolver.php" \
| grep -v "calendar_node_ensurer.php" \
| grep -v "'projection_entity_id' =>" \
| grep -v "projection_entity_id still exists" || true
BASH;

    $compatibilityAudit = trim(
        (string) shell_exec($compatibilityAuditCommand)
    );

    $expectedCompatibilityReference = trim(
        <<<'TEXT'
/private/framework/expression/character_next_beat_suggester.php:20:            $projectionEntityId = calendar_projection_entity_id($pdo, $projectionId);
TEXT
    );

    /*
     * grep may return either absolute or relative paths depending on
     * execution environment. Normalize to suffix match.
     */
    if ($compatibilityAudit !== '') {

        $normalized = preg_replace(
            '#^.*?/private/framework/#m',
            '/private/framework/',
            $compatibilityAudit
        );

        $normalized = trim((string) $normalized);

        if ($normalized !== $expectedCompatibilityReference) {
            throw new RuntimeException(
                "Unexpected projection_entity_id compatibility references detected.\n\n"
                . $compatibilityAudit
            );
        }
    }
}