<?php

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
}

function ok(string $message): void
{
    fwrite(STDOUT, 'OK: ' . $message . PHP_EOL);
}

$repoRoot = dirname(__DIR__, 3);
$configPath = $repoRoot . '/pecherie_config.php';
$ciConfigPath = $repoRoot . '/pecherie_ci_config.php';
$seedOutputPath = $repoRoot . '/private/framework/ci/.seeded_ids.json';

if (!is_file($ciConfigPath) && !is_file($configPath)) {
    fail('Missing config file (run write_ci_config.php or provide an existing server config)');
}

if (is_file($seedOutputPath) && !unlink($seedOutputPath)) {
    fail('Unable to remove stale seeded_ids.json before seeding');
}

require_once $repoRoot . '/private/framework/api/api_bootstrap.php';

try {
    $pdo = makePdo();
} catch (Throwable $e) {
    fail('Unable to create PDO: ' . $e->getMessage());
}

$medleyCode = 'CI_MEDLEY_1';
$medleyName = 'CI Test Medley';

$figure1Id = null;
$figure2Id = null;
$segmentId = null;
$medleyId = null;

try {
    $pdo->beginTransaction();

    /*
     * Seed figures by business key: figures.classval_id
     */
    $stmt = $pdo->prepare(
        <<<'SQL'
INSERT INTO figures (
    classval_id,
    dance_id,
    canonical_name,
    figure_type_id,
    created_at
)
VALUES (
    :classval_id,
    :dance_id,
    :canonical_name,
    :figure_type_id,
    NOW()
)
ON DUPLICATE KEY UPDATE
    canonical_name = VALUES(canonical_name)
SQL
    );

    $stmt->execute([
        ':classval_id' => 'CI_FIG_1',
        ':dance_id' => 1,
        ':canonical_name' => 'CI Figure A',
        ':figure_type_id' => 'basic',
    ]);

    $stmt->execute([
        ':classval_id' => 'CI_FIG_2',
        ':dance_id' => 1,
        ':canonical_name' => 'CI Figure B',
        ':figure_type_id' => 'basic',
    ]);

    $figureLookup = $pdo->prepare(
        <<<'SQL'
SELECT id
FROM figures
WHERE classval_id = :classval_id
LIMIT 1
SQL
    );

    $figureLookup->execute([':classval_id' => 'CI_FIG_1']);
    $figure1Id = $figureLookup->fetchColumn();

    $figureLookup->execute([':classval_id' => 'CI_FIG_2']);
    $figure2Id = $figureLookup->fetchColumn();

    if ($figure1Id === false || $figure2Id === false) {
        throw new RuntimeException('Unable to resolve seeded figure ids');
    }

    $figure1Id = (int) $figure1Id;
    $figure2Id = (int) $figure2Id;

    if ($figure1Id < 1 || $figure2Id < 1) {
        throw new RuntimeException('Resolved seeded figure ids were invalid');
    }

    /*
     * Seed segment by business key: segments.code
     */
    $stmt = $pdo->prepare(
        <<<'SQL'
INSERT INTO segments (
    code,
    name,
    dance_id,
    segment_content_type_id,
    created_at
)
VALUES (
    :code,
    :name,
    :dance_id,
    :segment_content_type_id,
    NOW()
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    dance_id = VALUES(dance_id),
    segment_content_type_id = VALUES(segment_content_type_id)
SQL
    );

    $stmt->execute([
        ':code' => 'CI_SEG_1',
        ':name' => 'CI Segment 1',
        ':dance_id' => 1,
        ':segment_content_type_id' => 'SEGMENT_CONTENT_DANCE',
    ]);

    $segmentLookup = $pdo->prepare(
        <<<'SQL'
SELECT id
FROM segments
WHERE code = :code
LIMIT 1
SQL
    );

    $segmentLookup->execute([':code' => 'CI_SEG_1']);
    $segmentId = $segmentLookup->fetchColumn();

    if ($segmentId === false) {
        throw new RuntimeException('Unable to resolve seeded segment id');
    }

    $segmentId = (int) $segmentId;

    if ($segmentId < 1) {
        throw new RuntimeException('Resolved seeded segment id was invalid');
    }

    /*
     * Seed medley by business key: medleys.code
     */
    $stmt = $pdo->prepare(
        <<<'SQL'
INSERT INTO medleys (
    code,
    name,
    search_name,
    created_at
)
VALUES (
    :code,
    :name,
    :search_name,
    NOW()
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    search_name = VALUES(search_name)
SQL
    );

    $stmt->execute([
        ':code' => $medleyCode,
        ':name' => $medleyName,
        ':search_name' => $medleyName,
    ]);

    $medleyLookup = $pdo->prepare(
        <<<'SQL'
SELECT id
FROM medleys
WHERE code = :code
LIMIT 1
SQL
    );

    $medleyLookup->execute([':code' => $medleyCode]);
    $medleyId = $medleyLookup->fetchColumn();

    if ($medleyId === false) {
        throw new RuntimeException('Unable to resolve seeded medley id');
    }

    $medleyId = (int) $medleyId;

    if ($medleyId < 1) {
        throw new RuntimeException('Resolved seeded medley id was invalid');
    }

    /*
     * Seed medley segment ordering.
     */
    $stmt = $pdo->prepare(
        <<<'SQL'
INSERT INTO medley_segments (
    medley_id,
    segment_id,
    sequence_index,
    subsequence_index,
    created_at
)
VALUES (
    :medley_id,
    :segment_id,
    :sequence_index,
    :subsequence_index,
    NOW()
)
ON DUPLICATE KEY UPDATE
    segment_id = VALUES(segment_id)
SQL
    );

    $stmt->execute([
        ':medley_id' => $medleyId,
        ':segment_id' => $segmentId,
        ':sequence_index' => 1,
        ':subsequence_index' => 1,
    ]);

    /*
     * Seed segment figures ordering.
     */
    $stmt = $pdo->prepare(
        <<<'SQL'
INSERT INTO segment_figures (
    segment_id,
    figure_id,
    sequence_index,
    created_at
)
VALUES (
    :segment_id,
    :figure_id,
    :sequence_index,
    NOW()
)
ON DUPLICATE KEY UPDATE
    figure_id = VALUES(figure_id)
SQL
    );

    $stmt->execute([
        ':segment_id' => $segmentId,
        ':figure_id' => $figure1Id,
        ':sequence_index' => 1,
    ]);

    $stmt->execute([
        ':segment_id' => $segmentId,
        ':figure_id' => $figure2Id,
        ':sequence_index' => 2,
    ]);

    /*
     * Seed one legal transition.
     */
    $stmt = $pdo->prepare(
        <<<'SQL'
INSERT INTO figure_transitions (
    predecessor_figure_id,
    successor_figure_id,
    transition_legality_id,
    dance_id,
    created_at
)
VALUES (
    :predecessor_figure_id,
    :successor_figure_id,
    :transition_legality_id,
    :dance_id,
    NOW()
)
ON DUPLICATE KEY UPDATE
    transition_legality_id = VALUES(transition_legality_id)
SQL
    );

    $stmt->execute([
        ':predecessor_figure_id' => $figure1Id,
        ':successor_figure_id' => $figure2Id,
        ':transition_legality_id' => 'legal',
        ':dance_id' => 1,
    ]);

    $pdo->commit();
    ok('Seeded CI medley data');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail('Seeding failed: ' . $e->getMessage());
}

$data = [
    'medley_id' => $medleyId,
    'medley_name' => $medleyName,
    'figure_1_id' => $figure1Id,
    'figure_2_id' => $figure2Id,
];

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fail('Unable to encode seeded_ids.json');
}

if (file_put_contents($seedOutputPath, $json . PHP_EOL) === false) {
    fail('Unable to write seeded_ids.json');
}

ok('Wrote seeded_ids.json');