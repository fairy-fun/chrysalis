<?php


declare(strict_types=1);

function resolve_medley_core(PDO $pdo, int $medleyId): array
{
    $sql = <<<'SQL'
SELECT
    cur.medley_sequence,
    cur.medley_subsequence,
    cur.segment_id,
    cur.segment_name,
    cur.figure_order,
    cur.figure_id,
    cur.figure_name,
    cur.dance_id,
    nxt.figure_id AS next_figure_id,
    nxt.figure_name AS next_figure_name,
    ft.transition_legality_id
FROM (
    SELECT
        ms.sequence_index AS medley_sequence,
        ms.subsequence_index AS medley_subsequence,
        s.id AS segment_id,
        s.name AS segment_name,
        sf.sequence_index AS figure_order,
        f.id AS figure_id,
        f.canonical_name AS figure_name,
        f.dance_id,
        ROW_NUMBER() OVER (
            ORDER BY
                ms.sequence_index,
                ms.subsequence_index,
                COALESCE(sf.sequence_index, 0),
                s.id,
                COALESCE(f.id, 0)
        ) AS row_num
    FROM medley_segments AS ms
    INNER JOIN segments AS s
        ON s.id = ms.segment_id
    LEFT JOIN segment_figures AS sf
        ON sf.segment_id = s.id
    LEFT JOIN figures AS f
        ON f.id = sf.figure_id
    WHERE ms.medley_id = :medley_id
) AS cur
LEFT JOIN (
    SELECT
        ms.sequence_index AS medley_sequence,
        ms.subsequence_index AS medley_subsequence,
        s.id AS segment_id,
        s.name AS segment_name,
        sf.sequence_index AS figure_order,
        f.id AS figure_id,
        f.canonical_name AS figure_name,
        f.dance_id,
        ROW_NUMBER() OVER (
            ORDER BY
                ms.sequence_index,
                ms.subsequence_index,
                COALESCE(sf.sequence_index, 0),
                s.id,
                COALESCE(f.id, 0)
        ) AS row_num
    FROM medley_segments AS ms
    INNER JOIN segments AS s
        ON s.id = ms.segment_id
    LEFT JOIN segment_figures AS sf
        ON sf.segment_id = s.id
    LEFT JOIN figures AS f
        ON f.id = sf.figure_id
    WHERE ms.medley_id = :medley_id_next
) AS nxt
    ON nxt.row_num = cur.row_num + 1
LEFT JOIN figure_transitions AS ft
    ON ft.predecessor_figure_id = cur.figure_id
   AND ft.successor_figure_id = nxt.figure_id
   AND ft.dance_id = cur.dance_id
ORDER BY cur.row_num
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':medley_id', $medleyId, PDO::PARAM_INT);
    $stmt->bindValue(':medley_id_next', $medleyId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}