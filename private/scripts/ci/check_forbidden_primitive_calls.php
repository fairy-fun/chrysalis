<?php
declare(strict_types=1);

require_once __DIR__ . '/../../framework/bootstrap.php';

$repoRoot = realpath(__DIR__ . '/../../..');
if ($repoRoot === false) {
    fwrite(STDERR, "Unable to resolve repo root\n");
    exit(2);
}

$scanRoots = [
    $repoRoot . '/private',
    $repoRoot . '/public_html',
];

$extensions = ['php', 'sql'];
$filePaths = [];

foreach ($scanRoots as $scanRoot) {
    if (!is_dir($scanRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $extensions, true)) {
            continue;
        }

        $filePaths[] = $file->getPathname();
    }
}

sort($filePaths);

$rawPrimitiveViolations = fw_find_direct_primitive_call_violations(
    $filePaths,
    FW_PROTECTED_PRIMITIVES,
    FW_ALLOWED_PRIMITIVE_CALLERS
);

$dbAdapterViolations = fw_find_direct_primitive_call_violations(
    $filePaths,
    FW_PROTECTED_DB_ADAPTERS,
    FW_ALLOWED_DB_ADAPTER_CALLERS
);

if ($rawPrimitiveViolations !== [] || $dbAdapterViolations !== []) {
    fwrite(STDERR, "Forbidden direct protected-layer calls detected:\n");

    foreach ($rawPrimitiveViolations as $violation) {
        fwrite(
            STDERR,
            "- raw primitive bypass: {$violation['file']} -> {$violation['primitive']}\n"
        );
    }

    foreach ($dbAdapterViolations as $violation) {
        fwrite(
            STDERR,
            "- DB adapter bypass: {$violation['file']} -> {$violation['primitive']}\n"
        );
    }

    exit(1);
}

fwrite(STDOUT, "OK: no forbidden direct protected-layer calls detected.\n");
exit(0);