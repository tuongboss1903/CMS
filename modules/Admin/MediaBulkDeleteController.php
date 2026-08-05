<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/media/bulk-delete - xoa nhieu Media cung luc (checkbox chon dong, Design Audit
 * Phase 7). Cung logic voi MediaDeleteController (transaction DELETE + tru storage_used_bytes,
 * unlink file SAU KHI commit thanh cong) nhung gop thanh 1 lan thay vi lap POST tung item.
 */
final class MediaBulkDeleteController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly string $storagePath = __DIR__ . '/../../storage/app/media',
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('media.delete')) {
            return Response::html('403 Forbidden', 403);
        }

        $siteId = $this->tenantManager->id();
        $ids = $this->sanitizeIds((array) $request->input('ids', []));

        if ($ids === []) {
            return Response::redirect('/admin/media');
        }

        $idsPlaceholders = \implode(',', \array_fill(0, \count($ids), '?'));

        $mediaItems = $this->database->select(
            "SELECT id, path, size FROM media WHERE tenant_id = ? AND id IN ({$idsPlaceholders})",
            [$siteId, ...$ids]
        );

        if ($mediaItems === []) {
            return Response::redirect('/admin/media');
        }

        $mediaIds = \array_map(static fn (array $row): int => (int) $row['id'], $mediaItems);
        $totalSize = (int) \array_sum(\array_column($mediaItems, 'size'));
        $mediaIdsPlaceholders = \implode(',', \array_fill(0, \count($mediaIds), '?'));

        // Doc truoc list variant (vd thumbnail) de unlink file that SAU KHI transaction commit -
        // row media_variants tu dong bi xoa qua FK CASCADE, nhung file vat ly tren disk thi khong.
        $variants = $this->database->select(
            "SELECT path FROM media_variants WHERE media_id IN ({$mediaIdsPlaceholders})",
            $mediaIds
        );

        $this->database->transaction(function (Database $db) use ($mediaIds, $mediaIdsPlaceholders, $siteId, $totalSize): void {
            $db->statement("DELETE FROM media WHERE id IN ({$mediaIdsPlaceholders})", $mediaIds);
            $db->statement(
                'UPDATE sites SET storage_used_bytes = storage_used_bytes - ? WHERE id = ?',
                [$totalSize, $siteId]
            );
        });

        foreach ($mediaItems as $media) {
            $fullPath = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $media['path'];

            if (\is_file($fullPath)) {
                @\unlink($fullPath);
            }
        }

        foreach ($variants as $variant) {
            $variantPath = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $variant['path'];

            if (\is_file($variantPath)) {
                @\unlink($variantPath);
            }
        }

        return Response::redirect('/admin/media');
    }

    /**
     * @param array<mixed> $rawIds
     * @return list<int>
     */
    private function sanitizeIds(array $rawIds): array
    {
        $ids = \array_map(static fn (mixed $id): int => (int) $id, $rawIds);
        $ids = \array_filter($ids, static fn (int $id): bool => $id > 0);

        return \array_values(\array_unique($ids));
    }
}
