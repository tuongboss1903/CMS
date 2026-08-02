<?php

declare(strict_types=1);

use Core\Database;

/**
 * Phase 16 (Security & Audit Log System, CMS-053). tenant_id NULLABLE (khac moi bang truoc do -
 * co that su can thiet: su kien mien tenant nhu "auth.login_failed" voi email khong ton tai co
 * the xay ra TRUOC khi xac dinh duoc site nao, hoac Request toi domain khong khop site nao ca).
 * user_id NULLABLE (guest/failed login chua xac thuc duoc danh tinh). old_values/new_values luu
 * TEXT (JSON tu Application layer tu encode/decode) - dung Owner Decision CMS-040, khong dung cot
 * JSON native (tranh phu thuoc kha nang khac nhau SQLite/MySQL).
 */
return [
    'up' => static function (Database $db): void {
        $driver = $db->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $primaryKey = $driver === 'sqlite'
            ? 'id INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'id BIGINT AUTO_INCREMENT PRIMARY KEY';

        $db->statement("CREATE TABLE audit_logs (
            {$primaryKey},
            tenant_id BIGINT NULL,
            user_id BIGINT NULL,
            event VARCHAR(100) NOT NULL,
            auditable_type VARCHAR(20) NULL,
            auditable_id BIGINT NULL,
            old_values TEXT NULL,
            new_values TEXT NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES sites(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        $db->statement('CREATE INDEX idx_audit_logs_tenant_created ON audit_logs (tenant_id, created_at)');
        $db->statement('CREATE INDEX idx_audit_logs_tenant_event ON audit_logs (tenant_id, event)');
        $db->statement('CREATE INDEX idx_audit_logs_tenant_user ON audit_logs (tenant_id, user_id)');
    },
    'down' => static function (Database $db): void {
        $db->statement('DROP TABLE IF EXISTS audit_logs');
    },
];
