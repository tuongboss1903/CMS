<?php

declare(strict_types=1);

namespace Core\Cache;

/**
 * Toi gian co chu dich - KHONG co tag o day (Cache facade tu quan ly tag qua registry key bang
 * chinh get()/put()/forget() co ban, xem core/Cache.php) - de driver moi (Memcached...) de viet,
 * khong phai tu implement co che tag rieng cho tung driver.
 *
 * Quy uoc: get() tra null cho CA "khong ton tai" LAN "gia tri luu that su la null" - han che da
 * biet, chap nhan duoc vi du lieu cache (ket qua query, HTML fragment...) hiem khi can phan biet
 * 2 truong hop nay.
 */
interface CacheDriver
{
    public function get(string $key): mixed;

    /** $ttlSeconds null = khong bao gio het han */
    public function put(string $key, mixed $value, ?int $ttlSeconds): void;

    public function has(string $key): bool;

    public function forget(string $key): void;

    /** Xoa TOAN BO du lieu duoi driver nay - dung than trong. */
    public function flush(): void;
}
