<?php

declare(strict_types=1);

use Core\Database;

return [
    'up' => static function (Database $db): void {
        throw new \RuntimeException('migration-up-failure');
    },
    'down' => static function (Database $db): void {
        throw new \RuntimeException('migration-down-failure');
    },
];
