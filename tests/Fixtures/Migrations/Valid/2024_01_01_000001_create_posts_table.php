<?php

declare(strict_types=1);

use Core\Database;

return [
    'up' => static function (Database $db): void {
        $db->statement('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE posts');
    },
];
