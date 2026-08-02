<?php

declare(strict_types=1);

return [
    'default' => 'file',
    'prefix' => 'test',
    'drivers' => [
        'file' => [
            'path' => \sys_get_temp_dir() . '/cms-app-test-cache',
        ],
    ],
];
