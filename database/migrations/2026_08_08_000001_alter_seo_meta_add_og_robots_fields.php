<?php

declare(strict_types=1);

use Core\Database;

/**
 * Mo rong seo_meta: og_title/og_description (tach rieng khoi title/description chung, phuc vu
 * chia se mang xa hoi khac noi dung SEO thuan) + is_index/is_follow (dieu khien <meta name="robots">
 * - CHI luu DB o buoc nay, CHUA render ra Public (Owner Decision - xem core-architecture.md).
 * ALTER rieng tung cot (khong gop 1 cau ALTER) de tuong thich ca SQLite/MySQL.
 */
return [
    'up' => static function (Database $db): void {
        $db->statement('ALTER TABLE seo_meta ADD COLUMN og_title VARCHAR(255) NULL');
        $db->statement('ALTER TABLE seo_meta ADD COLUMN og_description VARCHAR(500) NULL');
        $db->statement('ALTER TABLE seo_meta ADD COLUMN is_index BOOLEAN NOT NULL DEFAULT 1');
        $db->statement('ALTER TABLE seo_meta ADD COLUMN is_follow BOOLEAN NOT NULL DEFAULT 1');
    },
    'down' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            // SQLite (< 3.35) khong ho tro DROP COLUMN truc tiep - chap nhan gioi han nay cho
            // rollback local/test, dung tien le da chap nhan cho cac migration truoc.
            return;
        }

        $db->statement('ALTER TABLE seo_meta DROP COLUMN og_title');
        $db->statement('ALTER TABLE seo_meta DROP COLUMN og_description');
        $db->statement('ALTER TABLE seo_meta DROP COLUMN is_index');
        $db->statement('ALTER TABLE seo_meta DROP COLUMN is_follow');
    },
];
