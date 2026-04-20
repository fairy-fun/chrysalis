<?php
declare(strict_types=1);

require_once __DIR__ . '/../../framework/bootstrap.php';

$repoRoot = realpath(__DIR__ . '/../../..');
if ($repoRoot === false) {
    fwrite(STDERR, "Unable to resolve repo root\n");
    exit(2);
}

$targetPrimitive = 'fw_register_system_procedure';
$extensions = ['php', 'sql'];
$violations = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($repoRoot, FilesystemIterator::SKIP_DOTS)
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

    $path = str_replace('\\', '/', $file->getPathname());

    foreach (FW_ALLOWED_PRIMITIVE_CALLERS as $allowed) {
        if (str_ends_with($path, $allowed)) {
            continue 2;
        }
    }

    $contents = file_get_contents($file->getPathname());
    if ($contents === false) {
        continue;
    }

    if (
        strpos($contents, $targetPrimitive . '(') !== false ||
        strpos($contents, 'CALL ' . $targetPrimitive . '(') !== false
    ) {
        $violations[] = $path;
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Direct calls to {$targetPrimitive} detected outside allowlist:\n");
    foreach ($violations as $path) {
        fwrite(STDERR, "- {$path}\n");
    }
    exit(1);
}

fwrite(STDOUT, "OK: no direct registration primitive bypasses detected.\n");
exit(0);