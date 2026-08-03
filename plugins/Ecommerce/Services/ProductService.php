<?php

declare(strict_types=1);

namespace Plugins\Ecommerce\Services;

use Core\Cache;
use Core\Database;

/**
 * Phase 19 (Ecommerce MVP, CMS-056). Cache-aware qua Core\Cache co san (khong tu viet cache rieng) -
 * danh sach san pham "published" public dung remember(), tu forget() dung 1 key khi Product*Controller
 * ghi/xoa (khong dung tag - remember() khong ho tro $tags, quy mo MVP chua can, xem Architecture
 * Analysis muc 3).
 */
final class ProductService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly Cache $cache,
        private readonly Database $database,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function publishedList(int|string $tenantId): array
    {
        return $this->cache->remember(
            $this->publishedCacheKey($tenantId),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->database->select(
                "SELECT * FROM products WHERE tenant_id = ? AND status = 'published' AND deleted_at IS NULL ORDER BY created_at DESC",
                [$tenantId]
            )
        );
    }

    public function forgetPublishedListCache(int|string $tenantId): void
    {
        $this->cache->forget($this->publishedCacheKey($tenantId));
    }

    /** @param array<string, mixed> $product */
    public function effectivePrice(array $product, ?array $variant): float
    {
        if ($variant !== null && $variant['price_override'] !== null) {
            return (float) $variant['price_override'];
        }

        return (float) $product['price'];
    }

    private function publishedCacheKey(int|string $tenantId): string
    {
        return "tenant:{$tenantId}:products:published";
    }
}
