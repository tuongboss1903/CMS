<?php

declare(strict_types=1);

namespace Modules\Settings;

use Core\Cache;
use Core\Config;
use Core\Database;
use Core\TenantManager;

/**
 * Phase 17 (System Settings & General Configurations, CMS-054). Lop key-value TONG QUAT, bo sung
 * cho SiteSettingsManager (cot co dinh, CMS-042) - KHONG thay the, dung bang rieng "settings"
 * (tranh trung ten "site_settings" da co). Dat trong Modules\Settings\ (khong core/) - dung tien
 * le SiteSettingsManager: day la khai niem nghiep vu (biet "tenant"), khong phai framework thuan
 * nhu Cache/Database.
 *
 * Cache-aware: moi get() uu tien doc Cache truoc (qua Core\Cache co san, KHONG tu viet co che
 * cache rieng), chi cham Database khi cache-miss. set()/forget() tu invalidate dung 1 key lien
 * quan - khong flush() toan bo Cache (tranh anh huong du lieu cache khac khong lien quan).
 *
 * Ma hoa don gian qua ext-openssl (co san trong PHP core, khong Composer package - van dung tinh
 * than Zero-dependency da giu xuyen suot: "khong dependency" nghia la khong thu vien ngoai composer
 * cho Core, khong phai "khong PHP extension chuan" - Database da dung PDO tu truoc). Key ma hoa
 * derive tu app.key (config/app.php, da co san tu CMS-002).
 */
final class SettingManager
{
    private const CACHE_TTL = 3600;
    private const CIPHER = 'aes-256-cbc';

    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = $this->cacheKey($key);
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $row = $this->database->selectOne(
            'SELECT value, is_encrypted FROM settings WHERE tenant_id = ? AND `key` = ?',
            [$this->tenantManager->id(), $key]
        );

        if ($row === null) {
            return $default;
        }

        $value = ((int) $row['is_encrypted']) === 1 ? $this->decrypt((string) $row['value']) : (string) $row['value'];
        $this->cache->put($cacheKey, $value, self::CACHE_TTL);

        return $value;
    }

    public function set(string $key, string $value, string $group = 'general', bool $encrypted = false): void
    {
        $tenantId = $this->tenantManager->id();
        $storedValue = $encrypted ? $this->encrypt($value) : $value;

        $existing = $this->database->selectOne(
            'SELECT id FROM settings WHERE tenant_id = ? AND `key` = ?',
            [$tenantId, $key]
        );

        if ($existing === null) {
            $this->database->insert(
                'INSERT INTO settings (tenant_id, setting_group, `key`, value, is_encrypted) VALUES (?, ?, ?, ?, ?)',
                [$tenantId, $group, $key, $storedValue, $encrypted ? 1 : 0]
            );
        } else {
            $this->database->statement(
                'UPDATE settings SET setting_group = ?, value = ?, is_encrypted = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$group, $storedValue, $encrypted ? 1 : 0, (int) $existing['id']]
            );
        }

        $this->cache->forget($this->cacheKey($key));
    }

    /** @return array<string, string> key => value (da giai ma neu is_encrypted) */
    public function getGroup(string $group): array
    {
        $rows = $this->database->select(
            'SELECT `key`, value, is_encrypted FROM settings WHERE tenant_id = ? AND setting_group = ? ORDER BY `key` ASC',
            [$this->tenantManager->id(), $group]
        );

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row['key']] = ((int) $row['is_encrypted']) === 1
                ? $this->decrypt((string) $row['value'])
                : (string) $row['value'];
        }

        return $result;
    }

    public function forget(string $key): void
    {
        $this->database->statement(
            'DELETE FROM settings WHERE tenant_id = ? AND `key` = ?',
            [$this->tenantManager->id(), $key]
        );

        $this->cache->forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return 'setting:' . (string) $this->tenantManager->id() . ':' . $key;
    }

    private function encryptionKey(): string
    {
        return \hash('sha256', (string) $this->config->get('app.key', ''), true);
    }

    private function encrypt(string $value): string
    {
        $iv = \random_bytes(16);
        $cipherText = \openssl_encrypt($value, self::CIPHER, $this->encryptionKey(), OPENSSL_RAW_DATA, $iv);

        return \base64_encode($iv . (string) $cipherText);
    }

    private function decrypt(string $encoded): string
    {
        $raw = \base64_decode($encoded, true);

        if ($raw === false || \strlen($raw) < 17) {
            return '';
        }

        $iv = \substr($raw, 0, 16);
        $cipherText = \substr($raw, 16);
        $plain = \openssl_decrypt($cipherText, self::CIPHER, $this->encryptionKey(), OPENSSL_RAW_DATA, $iv);

        return $plain === false ? '' : $plain;
    }
}
