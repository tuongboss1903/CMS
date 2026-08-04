<?php

declare(strict_types=1);

use Core\Database;

/**
 * Nhat ky hoat dong CUA Super Admin (platform_admins) - tach rieng khoi audit_logs (bang do gan
 * user_id vao FK users, khong the luu platform_admin_id vao do ma khong vi pham FK/lan nghia).
 * site_id NULLABLE - co gia tri khi hanh dong nham vao 1 site cu the (vd site.suspend), NULL cho
 * hanh dong khong gan site nao (vd auth.login_failed). Cung nguyen tac Silent-Fail voi
 * core/Security/AuditLogger.php (khong lam gian doan request chinh).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE platform_audit_logs (
            {$primaryKey},
            platform_admin_id BIGINT NULL,
            site_id BIGINT NULL,
            event VARCHAR(100) NOT NULL,
            auditable_type VARCHAR(20) NULL,
            auditable_id BIGINT NULL,
            old_values TEXT NULL,
            new_values TEXT NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (platform_admin_id) REFERENCES platform_admins(id) ON DELETE SET NULL,
            FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL
        )");

        $db->statement('CREATE INDEX idx_platform_audit_logs_created ON platform_audit_logs (created_at)');
        $db->statement('CREATE INDEX idx_platform_audit_logs_event ON platform_audit_logs (event)');
        $db->statement('CREATE INDEX idx_platform_audit_logs_site ON platform_audit_logs (site_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS platform_audit_logs');
    },
];
