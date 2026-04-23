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

function require_table(PDO $pdo, string $tableName): void
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
         LIMIT 1'
    );

    $stmt->execute([':table_name' => $tableName]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException('Required table missing: ' . $tableName);
    }
}

function require_classval(PDO $pdo, string $id, ?string $code = null): void
{
    $sql = 'SELECT id, code
            FROM classvals
            WHERE id = :id';

    $params = [':id' => $id];

    if ($code !== null) {
        $sql .= ' AND code = :code';
        $params[':code'] = $code;
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        throw new RuntimeException(
            'Missing required classval: ' . $id . ($code !== null ? ' / ' . $code : '')
        );
    }
}

function require_entity_type(PDO $pdo, string $id, ?string $code = null): void
{
    $sql = 'SELECT id, code
            FROM entity_type_classvals
            WHERE id = :id';

    $params = [':id' => $id];

    if ($code !== null) {
        $sql .= ' AND code = :code';
        $params[':code'] = $code;
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        throw new RuntimeException(
            'Missing required entity type: ' . $id . ($code !== null ? ' / ' . $code : '')
        );
    }
}


function upsert_entity(PDO $pdo, string $entityId, string $entityTypeId): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO entities (
            id,
            entity_type_id
        )
        VALUES (
            :id,
            :entity_type_id
        )
        ON DUPLICATE KEY UPDATE
            entity_type_id = VALUES(entity_type_id)'
    );

    $stmt->execute([
        ':id' => $entityId,
        ':entity_type_id' => $entityTypeId,
    ]);
}

function upsert_entity_text(
    PDO $pdo,
    string $entityId,
    string $entityTypeId,
    string $canonicalLabel,
    ?string $summary = null,
    ?string $description = null,
    ?string $searchText = null,
    int $nlPriority = 0
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO entity_texts (
            entity_id,
            canonical_label,
            summary,
            description,
            search_text,
            created_at,
            updated_at,
            nl_priority,
            entity_type_id
        )
        VALUES (
            :entity_id,
            :canonical_label,
            :summary,
            :description,
            :search_text,
            NOW(),
            NOW(),
            :nl_priority,
            :entity_type_id
        )
        ON DUPLICATE KEY UPDATE
            canonical_label = VALUES(canonical_label),
            summary = VALUES(summary),
            description = VALUES(description),
            search_text = VALUES(search_text),
            updated_at = NOW(),
            nl_priority = VALUES(nl_priority),
            entity_type_id = VALUES(entity_type_id)'
    );

    $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_STR);
    $stmt->bindValue(':canonical_label', $canonicalLabel, PDO::PARAM_STR);
    $stmt->bindValue(':entity_type_id', $entityTypeId, PDO::PARAM_STR);
    $stmt->bindValue(':nl_priority', $nlPriority, PDO::PARAM_INT);

    if ($summary === null) {
        $stmt->bindValue(':summary', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':summary', $summary, PDO::PARAM_STR);
    }

    if ($description === null) {
        $stmt->bindValue(':description', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
    }

    if ($searchText === null) {
        $stmt->bindValue(':search_text', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':search_text', $searchText, PDO::PARAM_STR);
    }

    $stmt->execute();
}

function upsert_classval(
    PDO $pdo,
    string $id,
    string $classvalTypeId,
    string $code,
    string $label
): void {
    $select = $pdo->prepare(
        'SELECT id
         FROM classvals
         WHERE id = :id
            OR code = :code
         LIMIT 1'
    );

    $select->execute([
        ':id' => $id,
        ':code' => $code,
    ]);

    $existingId = $select->fetchColumn();

    if ($existingId !== false) {
        if ((string)$existingId !== $id) {
            throw new RuntimeException(
                'Classval canonical mismatch for code ' . $code .
                '; expected id ' . $id .
                ', found canonical id ' . (string)$existingId
            );
        }

        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO classvals (
            id,
            classval_type_id,
            code,
            label,
            created_at
        )
        VALUES (
            :id,
            :classval_type_id,
            :code,
            :label,
            NOW()
        )'
    );

    $insert->execute([
        ':id' => $id,
        ':classval_type_id' => $classvalTypeId,
        ':code' => $code,
        ':label' => $label,
    ]);
}

function upsert_attribute_type_layer(PDO $pdo, string $attributeTypeId, string $layerClassvalId): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO attribute_type_layer_map (
            attribute_type_id,
            layer_classval_id
        )
        VALUES (
            :attribute_type_id,
            :layer_classval_id
        )
        ON DUPLICATE KEY UPDATE
            layer_classval_id = VALUES(layer_classval_id)'
    );

    $stmt->execute([
        ':attribute_type_id' => $attributeTypeId,
        ':layer_classval_id' => $layerClassvalId,
    ]);
}

function require_attribute_type_layer(PDO $pdo, string $attributeTypeId, string $expectedLayerId): void
{
    $stmt = $pdo->prepare(
        'SELECT layer_classval_id
         FROM attribute_type_layer_map
         WHERE attribute_type_id = :attribute_type_id
         LIMIT 1'
    );

    $stmt->execute([':attribute_type_id' => $attributeTypeId]);
    $actual = $stmt->fetchColumn();

    if ($actual === false) {
        throw new RuntimeException(
            'Missing attribute_type_layer_map row for attribute_type_id: ' . $attributeTypeId
        );
    }

    $actualLayer = (string) $actual;

    if ($actualLayer !== $expectedLayerId) {
        throw new RuntimeException(
            'attribute_type_layer_map mismatch for ' . $attributeTypeId .
            '; expected ' . $expectedLayerId .
            ', got ' . $actualLayer
        );
    }
}

function upsert_profile_type_priority(PDO $pdo, string $profileTypeId, int $priority): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO profile_type_priority (
            profile_type_id,
            priority
        )
        VALUES (
            :profile_type_id,
            :priority
        )
        ON DUPLICATE KEY UPDATE
            priority = VALUES(priority)'
    );

    $stmt->execute([
        ':profile_type_id' => $profileTypeId,
        ':priority' => $priority,
    ]);
}

function upsert_character_profile(
    PDO $pdo,
    int $profileId,
    string $profileTypeId,
    string $characterId,
    string $updatedAt
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO character_profiles (
            profile_id,
            profile_type_id,
            character_id,
            entity_id,
            profile_json,
            updated_at
        )
        VALUES (
            :profile_id,
            :profile_type_id,
            :character_id,
            :entity_id,
            :profile_json,
            :updated_at
        )
        ON DUPLICATE KEY UPDATE
            profile_type_id = VALUES(profile_type_id),
            character_id = VALUES(character_id),
            entity_id = VALUES(entity_id),
            profile_json = VALUES(profile_json),
            updated_at = VALUES(updated_at)'
    );

    $stmt->execute([
        ':profile_id' => $profileId,
        ':profile_type_id' => $profileTypeId,
        ':character_id' => $characterId,
        ':entity_id' => $profileId,
        ':profile_json' => '{}',
        ':updated_at' => $updatedAt,
    ]);
}

function upsert_profile_attribute(
    PDO $pdo,
    int $profileId,
    string $attributeTypeId,
    ?string $valueText,
    ?string $valueClassvalId
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO character_profile_attributes (
            profile_id,
            attribute_type_id,
            value_text,
            value_classval_id
        )
        VALUES (
            :profile_id,
            :attribute_type_id,
            :value_text,
            :value_classval_id
        )
        ON DUPLICATE KEY UPDATE
            value_text = VALUES(value_text),
            value_classval_id = VALUES(value_classval_id)'
    );

    $stmt->bindValue(':profile_id', $profileId, PDO::PARAM_INT);
    $stmt->bindValue(':attribute_type_id', $attributeTypeId, PDO::PARAM_STR);

    if ($valueText === null) {
        $stmt->bindValue(':value_text', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':value_text', $valueText, PDO::PARAM_STR);
    }

    if ($valueClassvalId === null) {
        $stmt->bindValue(':value_classval_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':value_classval_id', $valueClassvalId, PDO::PARAM_STR);
    }

    $stmt->execute();
}

function resolve_classval_id(PDO $pdo, string $code): string
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM classvals
         WHERE code = :code
         LIMIT 1'
    );

    $stmt->execute([':code' => $code]);

    $id = $stmt->fetchColumn();

    if ($id === false) {
        throw new RuntimeException('Missing required classval for code: ' . $code);
    }

    return (string)$id;
}

function resolve_entity_type_id(PDO $pdo, string $code): string
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM entity_type_classvals
         WHERE code = :code
         LIMIT 1'
    );

    $stmt->execute([':code' => $code]);

    $id = $stmt->fetchColumn();

    if ($id === false) {
        throw new RuntimeException('Missing required entity type for code: ' . $code);
    }

    return (string)$id;
}


function ensure_attribute_domain_map(PDO $pdo, string $attributeTypeId, int $domainId): void
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM attribute_domain_map
         WHERE attribute_type_id = :attribute_type_id
           AND domain_id = :domain_id
         LIMIT 1'
    );

    $stmt->execute([
        ':attribute_type_id' => $attributeTypeId,
        ':domain_id' => $domainId,
    ]);

    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO attribute_domain_map (
            attribute_type_id,
            domain_id
        )
        VALUES (
            :attribute_type_id,
            :domain_id
        )'
    );

    $insert->execute([
        ':attribute_type_id' => $attributeTypeId,
        ':domain_id' => $domainId,
    ]);
}

function ensure_profile_type_domain_map(PDO $pdo, string $profileTypeId, int $domainId): void
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM profile_type_domain_map
         WHERE profile_type_id = :profile_type_id
           AND domain_id = :domain_id
         LIMIT 1'
    );

    $stmt->execute([
        ':profile_type_id' => $profileTypeId,
        ':domain_id' => $domainId,
    ]);

    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO profile_type_domain_map (
            profile_type_id,
            domain_id
        )
        VALUES (
            :profile_type_id,
            :domain_id
        )'
    );

    $insert->execute([
        ':profile_type_id' => $profileTypeId,
        ':domain_id' => $domainId,
    ]);
}

$medleyCode = 'CI_MEDLEY_1';
$medleyName = 'CI Test Medley';

$figure1Id = null;
$figure2Id = null;
$segmentId = null;
$medleyId = null;

$expressionTestCharacterId = 'CI_CHAR_EXPR_1';
$expressionDomainMatchId = 101;

try {
    $pdo->beginTransaction();

    require_table($pdo, 'classvals');
    require_table($pdo, 'figures');
    require_table($pdo, 'segments');
    require_table($pdo, 'medleys');
    require_table($pdo, 'medley_segments');
    require_table($pdo, 'segment_figures');
    require_table($pdo, 'figure_transitions');
    require_table($pdo, 'character_profiles');
    require_table($pdo, 'character_profile_attributes');
    require_table($pdo, 'profile_type_priority');
    require_table($pdo, 'profile_type_domain_map');
    require_table($pdo, 'attribute_type_layer_map');
    require_table($pdo, 'attribute_domain_map');
    require_table($pdo, 'entities');
    require_table($pdo, 'entity_texts');

    /*
     * Enforce canonical classval semantics (event_theme → event_has_theme)
     * before CI fixture seeding continues.
     */
    $checkCanonical = $pdo->prepare(
        'SELECT 1
         FROM classvals
         WHERE id = :canonical_id
           AND code = :canonical_code
         LIMIT 1'
    );

    $checkCanonical->execute([
        ':canonical_id' => 'fact_event_has_theme',
        ':canonical_code' => 'event_has_theme',
    ]);

    if ($checkCanonical->fetchColumn() === false) {
        throw new RuntimeException(
            'Missing canonical classval fact_event_has_theme / event_has_theme'
        );
    }

    $checkDeprecated = $pdo->prepare(
        'SELECT id, code
         FROM classvals
         WHERE id = :deprecated_id
            OR code = :deprecated_code
         LIMIT 1'
    );

    $checkDeprecated->execute([
        ':deprecated_id' => 'fact_type_event_theme',
        ':deprecated_code' => 'event_theme',
    ]);

    $deprecatedRow = $checkDeprecated->fetch(PDO::FETCH_ASSOC);

    if ($deprecatedRow !== false) {
        throw new RuntimeException(
            'Deprecated classval still present: ' .
            ($deprecatedRow['id'] ?? '[unknown id]') .
            ' / ' .
            ($deprecatedRow['code'] ?? '[unknown code]') .
            '. Clean the database outside CI before seeding.'
        );
    }

    ok('Verified canonical classval semantics (event_theme → event_has_theme)');

    /*
     * Expression output fixture prerequisites.
     *
     * These attribute type IDs are stable semantic IDs used by CI.
     * CI owns the layer-map rows required for this fixture and upserts them
     * idempotently before validating them.
     */
    $attributeVoicePriority = 'attr_ci_voice_priority';
    $attributePsychUpdated = 'attr_ci_psych_updated';
    $attributeLimbicProfile = 'attr_ci_limbic_profile_id';
    $attributeVoiceDomain = 'attr_ci_voice_domain';

    upsert_attribute_type_layer($pdo, $attributeVoicePriority, 'layer_voice');
    upsert_attribute_type_layer($pdo, $attributePsychUpdated, 'layer_psych');
    upsert_attribute_type_layer($pdo, $attributeLimbicProfile, 'layer_limbic');
    upsert_attribute_type_layer($pdo, $attributeVoiceDomain, 'layer_voice');

    require_attribute_type_layer($pdo, $attributeVoicePriority, 'layer_voice');
    require_attribute_type_layer($pdo, $attributePsychUpdated, 'layer_psych');
    require_attribute_type_layer($pdo, $attributeLimbicProfile, 'layer_limbic');
    require_attribute_type_layer($pdo, $attributeVoiceDomain, 'layer_voice');

    // Upsert CI classvals (idempotent)
    upsert_classval($pdo, 'ci_voice_priority_low', 'cvt_scalar_level', 'ci_voice_priority_low', 'CI Voice Priority Low');
    upsert_classval($pdo, 'ci_voice_priority_high', 'cvt_scalar_level', 'ci_voice_priority_high', 'CI Voice Priority High');
    upsert_classval($pdo, 'ci_psych_older', 'cvt_scalar_level', 'ci_psych_older', 'CI Psych Older');
    upsert_classval($pdo, 'ci_psych_newer', 'cvt_scalar_level', 'ci_psych_newer', 'CI Psych Newer');
    upsert_classval($pdo, 'ci_limbic_lower_profile', 'cvt_scalar_level', 'ci_limbic_lower_profile', 'CI Limbic Lower Profile');
    upsert_classval($pdo, 'ci_limbic_higher_profile', 'cvt_scalar_level', 'ci_limbic_higher_profile', 'CI Limbic Higher Profile');
    upsert_classval($pdo, 'ci_voice_domain_visible', 'cvt_scalar_level', 'ci_voice_domain_visible', 'CI Voice Domain Visible');
    upsert_classval($pdo, 'ci_voice_domain_hidden', 'cvt_scalar_level', 'ci_voice_domain_hidden', 'CI Voice Domain Hidden');

// Then verify (keeps CI strict)
    require_classval($pdo, 'ci_voice_priority_low', 'ci_voice_priority_low');
    require_classval($pdo, 'ci_voice_priority_high', 'ci_voice_priority_high');
    require_classval($pdo, 'ci_psych_older', 'ci_psych_older');
    require_classval($pdo, 'ci_psych_newer', 'ci_psych_newer');
    require_classval($pdo, 'ci_limbic_lower_profile', 'ci_limbic_lower_profile');
    require_classval($pdo, 'ci_limbic_higher_profile', 'ci_limbic_higher_profile');
    require_classval($pdo, 'ci_voice_domain_visible', 'ci_voice_domain_visible');
    require_classval($pdo, 'ci_voice_domain_hidden', 'ci_voice_domain_hidden');


    /*
 * Resolve entity type IDs for entity label-resolution fixtures.
 * These must be resolved from live classvals, not hardcoded.
 */
    $entityTypeTheme = resolve_entity_type_id($pdo, 'theme');
    $entityTypeSong  = resolve_entity_type_id($pdo, 'song');
    $entityTypeIdea  = resolve_entity_type_id($pdo, 'idea');

    require_entity_type($pdo, $entityTypeTheme, 'theme');
    require_entity_type($pdo, $entityTypeSong,  'song');
    require_entity_type($pdo, $entityTypeIdea,  'idea');



    /*
     * Entity label-resolution fixture.
     *
     * Live DB contract:
     * - entities.id is the entity identity anchor
     * - entity_texts.entity_id is the canonical text row for that entity
     * - canonical_label_normalized is a generated column
     * - uniqueness is enforced on (entity_type_id, canonical_label_normalized)
     *
     * Therefore:
     * - exact match must be type-scoped
     * - ambiguity can only be tested across different entity types
     */

    $entityExactThemeId = 'ci_entity_theme_betrayal';
    $entityAmbiguousSongId = 'ci_entity_song_betrayal';
    $entityUniqueIdeaId = 'ci_entity_idea_truth_over_comfort';



    upsert_entity($pdo, $entityExactThemeId, $entityTypeTheme);
    upsert_entity_text(
        $pdo,
        $entityExactThemeId,
        $entityTypeTheme,
        'Betrayal',
        null,
        null,
        'Betrayal',
        0
    );

    upsert_entity($pdo, $entityAmbiguousSongId, $entityTypeSong);
    upsert_entity_text(
        $pdo,
        $entityAmbiguousSongId,
        $entityTypeSong,
        'Betrayal',
        null,
        null,
        'Betrayal',
        0
    );

    upsert_entity($pdo, $entityUniqueIdeaId, $entityTypeIdea);
    upsert_entity_text(
        $pdo,
        $entityUniqueIdeaId,
        $entityTypeIdea,
        'Truth Over Comfort',
        null,
        null,
        'Truth Over Comfort',
        0
    );

    ok('Seeded CI entity label-resolution data');

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

    /*
     * Expression-output fixture.
     *
     * We seed:
     * - priority winner on layer_voice
     * - updated_at winner on layer_psych
     * - profile_id winner on layer_limbic
     * - domain-mapped extra voice attribute
     *
     * Live DB rule:
     * every attribute_type_id used in character_profile_attributes must already
     * have at least one row in attribute_domain_map before insert.
     */

    upsert_profile_type_priority($pdo, 'ci_profile_low', 10);
    upsert_profile_type_priority($pdo, 'ci_profile_mid', 20);
    upsert_profile_type_priority($pdo, 'ci_profile_high', 30);

    ensure_profile_type_domain_map($pdo, 'ci_profile_low', $expressionDomainMatchId);
    ensure_profile_type_domain_map($pdo, 'ci_profile_mid', $expressionDomainMatchId);
    ensure_profile_type_domain_map($pdo, 'ci_profile_high', $expressionDomainMatchId);

    /*
     * Profile IDs are fixed and explicit so CI can assert actual winners.
     */
    upsert_character_profile($pdo, 9101, 'ci_profile_low',  $expressionTestCharacterId, '2026-01-01 09:00:00');
    upsert_character_profile($pdo, 9102, 'ci_profile_high', $expressionTestCharacterId, '2026-01-01 09:00:00');

    upsert_character_profile($pdo, 9103, 'ci_profile_mid',  $expressionTestCharacterId, '2026-01-01 08:00:00');
    upsert_character_profile($pdo, 9104, 'ci_profile_mid',  $expressionTestCharacterId, '2026-01-01 10:00:00');

    upsert_character_profile($pdo, 9105, 'ci_profile_mid',  $expressionTestCharacterId, '2026-01-01 11:00:00');
    upsert_character_profile($pdo, 9106, 'ci_profile_mid',  $expressionTestCharacterId, '2026-01-01 11:00:00');

    upsert_character_profile($pdo, 9107, 'ci_profile_mid',  $expressionTestCharacterId, '2026-01-01 12:00:00');

    /*
     * Domain mappings MUST exist before any attribute insert.
     *
     * All CI expression attributes are mapped to the same matching test domain
     * so the domain-filtered resolver path remains valid under strict-mode DB
     * enforcement.
     */


    ensure_attribute_domain_map($pdo, $attributeVoicePriority, $expressionDomainMatchId);
    ensure_attribute_domain_map($pdo, $attributePsychUpdated, $expressionDomainMatchId);
    ensure_attribute_domain_map($pdo, $attributeLimbicProfile, $expressionDomainMatchId);
    ensure_attribute_domain_map($pdo, $attributeVoiceDomain, $expressionDomainMatchId);

    /*
     * Priority decides: 9102 beats 9101.
     */
    upsert_profile_attribute($pdo, 9101, $attributeVoicePriority, null, 'ci_voice_priority_low');
    upsert_profile_attribute($pdo, 9102, $attributeVoicePriority, null, 'ci_voice_priority_high');

    /*
     * updated_at decides: 9104 beats 9103.
     */
    upsert_profile_attribute($pdo, 9103, $attributePsychUpdated, null, 'ci_psych_older');
    upsert_profile_attribute($pdo, 9104, $attributePsychUpdated, null, 'ci_psych_newer');

    /*
     * profile_id decides: 9106 beats 9105.
     */
    upsert_profile_attribute($pdo, 9105, $attributeLimbicProfile, null, 'ci_limbic_lower_profile');
    upsert_profile_attribute($pdo, 9106, $attributeLimbicProfile, null, 'ci_limbic_higher_profile');

    /*
     * Extra voice attribute included in both default and domain-filtered output.
     */
    upsert_profile_attribute($pdo, 9107, $attributeVoiceDomain, null, 'ci_voice_domain_visible');

    /*
     * Ensure the other seeded attributes are unmapped for this domain test.
     * Under the fixed resolver, unmapped attributes remain eligible when
     * domain_id is provided.
     */


    $pdo->commit();
    ok('Seeded CI medley data');
    ok('Seeded CI expression output data');
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

    'expression_test_character_id' => $expressionTestCharacterId,
    'expression_test_domain_match_id' => $expressionDomainMatchId,

    'expression_expected_default' => [
        'layer_voice' => [
            [
                'attribute_type_id' => 'attr_ci_voice_domain',
                'profile_id' => 9107,
                'value_text' => null,
                'value_classval_id' => 'ci_voice_domain_visible',
            ],
            [
                'attribute_type_id' => 'attr_ci_voice_priority',
                'profile_id' => 9102,
                'value_text' => null,
                'value_classval_id' => 'ci_voice_priority_high',
            ],
        ],
        'layer_psych' => [
            [
                'attribute_type_id' => 'attr_ci_psych_updated',
                'profile_id' => 9104,
                'value_text' => null,
                'value_classval_id' => 'ci_psych_newer',
            ],
        ],
        'layer_limbic' => [
            [
                'attribute_type_id' => 'attr_ci_limbic_profile_id',
                'profile_id' => 9106,
                'value_text' => null,
                'value_classval_id' => 'ci_limbic_higher_profile',
            ],
        ],
    ],

    'expression_expected_domain_filtered' => [
        'layer_voice' => [
            [
                'attribute_type_id' => 'attr_ci_voice_domain',
                'profile_id' => 9107,
                'value_text' => null,
                'value_classval_id' => 'ci_voice_domain_visible',
            ],
            [
                'attribute_type_id' => 'attr_ci_voice_priority',
                'profile_id' => 9102,
                'value_text' => null,
                'value_classval_id' => 'ci_voice_priority_high',
            ],
        ],
        'layer_psych' => [
            [
                'attribute_type_id' => 'attr_ci_psych_updated',
                'profile_id' => 9104,
                'value_text' => null,
                'value_classval_id' => 'ci_psych_newer',
            ],
        ],
        'layer_limbic' => [
            [
                'attribute_type_id' => 'attr_ci_limbic_profile_id',
                'profile_id' => 9106,
                'value_text' => null,
                'value_classval_id' => 'ci_limbic_higher_profile',
            ],
        ],
    ],

    'entity_resolution' => [
        'typed_exact_match' => [
            'entity_id' => 'ci_entity_theme_betrayal',
            'entity_type_id' => $entityTypeTheme,
            'canonical_label' => 'Betrayal',
        ],
        'cross_type_ambiguous_label' => [
            'canonical_label' => 'Betrayal',
            'entity_ids' => [
                'ci_entity_theme_betrayal',
                'ci_entity_song_betrayal',
            ],
            'entity_type_ids' => [
                $entityTypeTheme,
                $entityTypeSong,
            ],
        ],
        'globally_unique_label' => [
            'entity_id' => 'ci_entity_idea_truth_over_comfort',
            'entity_type_id' => $entityTypeIdea,
            'canonical_label' => 'Truth Over Comfort',
        ],
        'no_match_probe' => [
            'entity_type_id' => $entityTypeTheme,
            'canonical_label' => 'CI Missing Entity',
        ],
    ],

    // Legacy compatibility keys expected by current CI tests
    'entity_test_subject_entity_id' => 'ci_entity_theme_betrayal',
    'entity_test_subject_entity_type_id' => $entityTypeTheme,
    'entity_test_subject_canonical_label' => 'Betrayal',

    'entity_test_ambiguous_entity_id_1' => 'ci_entity_theme_betrayal',
    'entity_test_ambiguous_entity_type_id_1' => $entityTypeTheme,
    'entity_test_ambiguous_entity_id_2' => 'ci_entity_song_betrayal',
    'entity_test_ambiguous_entity_type_id_2' => $entityTypeSong,
    'entity_test_ambiguous_canonical_label' => 'Betrayal',

    'entity_test_unique_entity_id' => 'ci_entity_idea_truth_over_comfort',
    'entity_test_unique_entity_type_id' => $entityTypeIdea,
    'entity_test_unique_canonical_label' => 'Truth Over Comfort',

    'entity_test_no_match_entity_type_id' => $entityTypeTheme,
    'entity_test_no_match_canonical_label' => 'CI Missing Entity',
];

$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fail('Unable to encode seeded_ids.json');
}

if (file_put_contents($seedOutputPath, $json . PHP_EOL) === false) {
    fail('Unable to write seeded_ids.json');
}

ok('Wrote seeded_ids.json');
ok('seedOutputPath=' . $seedOutputPath);
ok('seeded_ids_exists=' . (is_file($seedOutputPath) ? 'yes' : 'no'));