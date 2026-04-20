<?php
declare(strict_types=1);

function fw_file_contains_direct_primitive_call(string $filePath, string $primitiveName): bool
{
    $contents = file_get_contents($filePath);
    if ($contents === false) {
        throw new RuntimeException("Unable to read file: {$filePath}");
    }

    return strpos($contents, $primitiveName . '(') !== false
        || strpos($contents, 'CALL ' . $primitiveName . '(') !== false;
}

function fw_find_direct_primitive_call_violations(
    array $filePaths,
    array $protectedPrimitives,
    array $allowedCallers
): array {
    $violations = [];

    foreach ($filePaths as $filePath) {
        $normalized = str_replace('\\', '/', $filePath);

        foreach ($allowedCallers as $allowed) {
            if (str_ends_with($normalized, $allowed)) {
                continue 2;
            }
        }

        foreach ($protectedPrimitives as $primitive) {
            if (fw_file_contains_direct_primitive_call($filePath, $primitive)) {
                $violations[] = [
                    'file' => $normalized,
                    'primitive' => $primitive,
                ];
            }
        }
    }

    return $violations;
}