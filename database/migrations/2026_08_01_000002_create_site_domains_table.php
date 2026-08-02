<?php

declare(strict_types=1);

use Core\Database;

return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE site_domains (
            {$primaryKey},
            site_id BIGINT NOT NULL,
            domain VARCHAR(255) NOT NULL,
            is_primary BOOLEAN NOT NULL DEFAULT 0,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE UNIQUE INDEX uq_site_domains_domain ON site_domains (domain)');
        $db->statement('CREATE INDEX idx_site_domains_site_id ON site_domains (site_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS site_domains');
    },
];
