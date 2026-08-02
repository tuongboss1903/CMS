<?php

declare(strict_types=1);

namespace Modules\Media;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * DELETE /media/{id} - Database::transaction() (DELETE media + UPDATE sites.storage_used_bytes)
 * truoc, unlink() file vat ly SAU KHI commit thanh cong (dam bao DB luon nhat quan; neu unlink
 * that bai/file da mat, khong throw - chi la file rac con sot lai o disk, chap nhan duoc).
 * $storagePath co gia tri mac dinh tro toi storage/app/media that, test override qua
 * Container::singleton() voi thu muc temp rieng - cung pattern UploadMediaController.
 */
final class DeleteMediaController
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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $mediaId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $media = $this->database->selectOne(
            'SELECT id, path, size FROM media WHERE id = ? AND tenant_id = ?',
            [$mediaId, $siteId]
        );

        if ($media === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $this->database->transaction(function (Database $db) use ($mediaId, $siteId, $media): void {
            $db->statement('DELETE FROM media WHERE id = ?', [$mediaId]);
            $db->statement(
                'UPDATE sites SET storage_used_bytes = storage_used_bytes - ? WHERE id = ?',
                [(int) $media['size'], $siteId]
            );
        });

        $fullPath = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $media['path'];

        if (\is_file($fullPath)) {
            @\unlink($fullPath);
        }

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Xoa thanh cong.',
            'errors' => [],
        ]);
    }
}
