<?php

declare(strict_types=1);

// Cau hinh cho TenantResolver (core/Middleware/TenantResolverMiddleware.php, task CMS-009+).
// Xac dinh site theo domain qua bang site_domains (01-module-tenant.md) - KHONG khai bao
// danh sach tenant tinh o day, DB la nguon du lieu that.
return [
    'resolution' => 'domain',
    // Nhom route /system-admin/* danh cho Super Admin toan he thong, khong di qua
    // TenantResolver theo domain thong thuong (xem 01-module-tenant.md muc 8).
    'system_admin' => [
        'domains' => array_values(array_filter(explode(',', getenv('SYSTEM_ADMIN_DOMAINS') ?: ''))),
        'route_prefix' => '/system-admin',
    ],
    // Moi cache key phai co prefix nay + id tenant, vd "tenant:{id}:..." (cms-architecture-proposal.md muc 9).
    'cache_prefix' => 'tenant',
];
