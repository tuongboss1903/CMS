<?php

declare(strict_types=1);

// Fixture rieng cho Unit Test - SQLite in-memory, khong dung config/database.php that (production).
return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ],
    ],
];
