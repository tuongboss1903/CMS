<?php

declare(strict_types=1);

use Core\Database;

/**
 * Trien khai media_folders/media_variants da CHINH THUC chot trong database-design.md muc 4.1,
 * bi hoan lai o CMS-041 (Owner Decision: "chua co consumer that/chua co thu vien xu ly anh"). Ca
 * 2 dieu kien do gio da giai quyet - UX audit yeu cau Media co Folder + Thumbnail that,
 * ext-gd co san (built-in PHP, khong them composer package). media_usages VAN CHUA trien khai
 * (ngoai pham vi - can hook vao moi luong luu Page/Block, rui ro rong hon nhieu, de danh CMS rieng).
 *
 * folder_id o bang media KHONG FK cung (dung tien le sites.plan_id/menu_items.reference_id -
 * validate o Service/Controller layer) - tranh ALTER TABLE them constraint tren bang media dang
 * co du lieu that giua chu ky song, dung nguyen tac da chot o database-design.md muc 7.6.
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE media_folders (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            parent_id BIGINT NULL,
            name VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES media_folders(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE INDEX idx_media_folders_tenant_id ON media_folders (tenant_id)');

        $db->statement('ALTER TABLE media ADD COLUMN folder_id BIGINT NULL');
        $db->statement('CREATE INDEX idx_media_tenant_folder ON media (tenant_id, folder_id)');

        $db->statement("CREATE TABLE media_variants (
            {$primaryKey},
            media_id BIGINT NOT NULL,
            size_type VARCHAR(20) NOT NULL,
            path VARCHAR(500) NOT NULL,
            width INT NULL,
            height INT NULL,
            FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE UNIQUE INDEX uq_media_variants ON media_variants (media_id, size_type)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS media_variants');

        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver !== 'sqlite') {
            // SQLite (< 3.35) khong ho tro DROP COLUMN truc tiep - chap nhan gioi han nay cho
            // rollback local/test, dung tien le da chap nhan o migration alter_seo_meta truoc do.
            $db->statement('ALTER TABLE media DROP COLUMN folder_id');
        }

        $db->statement('DROP TABLE IF EXISTS media_folders');
    },
];
