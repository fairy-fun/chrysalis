<?php

declare(strict_types=1);

return [
    'visible_prefixes' => [
        '.github/workflows',
        'private/framework/contracts',
        'public_html/pecherie/chill-api/repo',
    ],

    'visible_files' => [
        'public_html/pecherie/chill-api/index.php',
        'public_html/pecherie/chill-api/query.php',
        'public_html/pecherie/chill-api/tables.php',
        'public_html/pecherie/chill-api/columns.php',
    ],

    'required_operations' => [
        'listRepo' => 'public_html/pecherie/chill-api/repo/list_repo.php',
        'getRepoFile' => 'public_html/pecherie/chill-api/repo/get_repo_file.php',
        'executeSqlRead' => 'public_html/pecherie/chill-api/query.php',
        'tables' => 'public_html/pecherie/chill-api/tables.php',
        'columns' => 'public_html/pecherie/chill-api/columns.php',
        'query' => 'public_html/pecherie/chill-api/query.php',
    ],
];