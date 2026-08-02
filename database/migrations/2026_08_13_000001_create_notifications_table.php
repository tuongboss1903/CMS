<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 15 (Notification & Email System, CMS-052). In-app notification cho Admin (chuong thong
 * bao) - KHAC voi viec gui email (Core\Mail\Mailer, khong luu bang). read_at NULL = chua doc,
 * dung quy uoc "cot timestamp nullable thay boolean" da ap dung cho pages.published_at.
 * notifiable_type MVP chi 'comment' (dung tien le seo_meta/comments - CMS-043/CMS-051).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE notifications (
            {$primaryKey},
            tenant_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            type VARCHAR(50) NOT NULL,
            notifiable_type VARCHAR(20) NOT NULL,
            notifiable_id BIGINT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body VARCHAR(500) NOT NULL,
            read_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $db->statement('CREATE INDEX idx_notifications_tenant_user_read ON notifications (tenant_id, user_id, read_at)');
        $db->statement('CREATE INDEX idx_notifications_tenant_created ON notifications (tenant_id, created_at)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS notifications');
    },
];
