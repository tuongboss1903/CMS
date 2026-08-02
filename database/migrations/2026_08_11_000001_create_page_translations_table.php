<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 13 (Multi-language Support i18n, CMS-050, Owner Decision: Translation Table Pattern -
 * xem Architecture Review truoc khi trien khai). 1 dong = 1 ban dich cua 1 page theo 1 locale -
 * KHONG luu JSON blob nhieu locale trong 1 cot (da phan tich va bac bo o Architecture Review,
 * cung ly do CMS-040 tranh phu thuoc kha nang JSON column khac nhau giua SQLite/MySQL).
 *
 * tenant_id duoc denormalize (khong chi suy ra qua JOIN pages) - dung tien le seo_meta/media: moi
 * bang can tra cuu/rang buoc theo tenant deu co cot tenant_id rieng, phuc vu ca UNIQUE
 * (tenant_id, locale, slug) lan Index (tenant_id, locale) ma khong can JOIN.
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE page_translations (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            page_id BIGINT NOT NULL,
            locale VARCHAR(10) NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            content TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            CONSTRAINT unq_page_locale UNIQUE (page_id, locale),
            CONSTRAINT unq_tenant_locale_slug UNIQUE (tenant_id, locale, slug)
        )");

        $db->statement('CREATE INDEX idx_page_trans_tenant_locale ON page_translations (tenant_id, locale)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS page_translations');
    },
];
