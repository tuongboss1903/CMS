<?php

declare(strict_types=1);

use Core\Database;

/**
 * site_settings - 1 ban ghi/tenant (UNIQUE tenant_id). Global config dung cho Sitemap/Robots.txt/
 * fallback SEO khi Page chua co seo_meta rieng. favicon_id/default_og_image_id tham chieu media,
 * ON DELETE SET NULL (dung tien le og_image_id cua seo_meta - CMS-043).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE site_settings (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            site_name VARCHAR(150) NULL,
            site_tagline VARCHAR(255) NULL,
            default_meta_description VARCHAR(500) NULL,
            default_og_image_id BIGINT NULL,
            favicon_id BIGINT NULL,
            robots_txt_custom TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (default_og_image_id) REFERENCES media(id) ON DELETE SET NULL,
            FOREIGN KEY (favicon_id) REFERENCES media(id) ON DELETE SET NULL
        )");

        $db->statement('CREATE UNIQUE INDEX uq_site_settings_tenant ON site_settings (tenant_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS site_settings');
    },
];
