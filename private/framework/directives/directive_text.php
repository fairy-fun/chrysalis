<?php
declare(strict_types=1);

function fw_build_procedure_call_directive_text(string $schemaName, string $procedureName): string
{
    return sprintf('CALL %s.%s();', $schemaName, $procedureName);
}