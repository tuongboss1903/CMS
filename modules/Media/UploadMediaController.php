<?php

declare(strict_types=1);

namespace Modules\Media;

use Core\Auth;
use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /media - upload file, validate thu cong trong Controller (khong Validator rule moi,
 * khong UploadedFile abstraction - dung $_FILES tho qua Request::file()). $storagePath co gia
 * tri mac dinh tro toi storage/app/media that (Container tu dung default khi khong bind tuong
 * minh - xem core/Container.php::resolveParameter()); test override qua Container::singleton()
 * voi thu muc temp rieng, giong pattern View (CMS-044/045).
 *
 * Flow: validate -> move file (ngoai transaction, I/O khong rollback duoc) -> Database::transaction()
 * (INSERT media + UPDATE sites.storage_used_bytes) -> loi transaction thi don file vua move
 * (unlink) de khong de rac file mo coi khong co DB row tuong ung.
 *
 * move_uploaded_file() CHI hoat dong voi file upload qua HTTP that (PHP tu kiem tra qua
 * is_uploaded_file() noi bo) - trong moi truong CLI/PHPUnit (khong co upload HTTP that) ham nay
 * luon tra false bat ke tmp_name gia lap gi, khong the test duoc "Upload success" (yeu cau
 * PHPUnit that). Owner Decision: fallback rename() khi is_uploaded_file() false (chi xay ra
 * ngoai request HTTP that, vd CLI/test) - move_uploaded_file() van la duong di CHINH cho request
 * that (giu nguyen bao mat chong path traversal/file inclusion), khong them abstraction/dependency
 * moi - dung pattern chuan da ap dung o Laravel/Symfony cho cung van de nay.
 */
final class UploadMediaController
{
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
    ];

    public function __construct(
        private readonly Authorization $authorization,
        private readonly Auth $auth,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly string $storagePath = __DIR__ . '/../../storage/app/media',
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('media.upload')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $file = $request->file('file');

        if ($file === null || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'File khong hop le.',
                'errors' => [],
            ], 422);
        }

        $size = (int) $file['size'];

        if ($size > self::MAX_SIZE_BYTES) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'File vuot qua dung luong cho phep (5MB).',
                'errors' => [],
            ], 422);
        }

        $mimeType = (string) $file['type'];

        if (!\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Dinh dang file khong duoc ho tro.',
                'errors' => [],
            ], 422);
        }

        $siteId = $this->tenantManager->id();
        $originalName = (string) $file['name'];
        $extension = \pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueName = \uniqid('media_', true) . ($extension !== '' ? '.' . $extension : '');

        $tenantDir = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $siteId;

        if (!\is_dir($tenantDir) && !\mkdir($tenantDir, 0755, true) && !\is_dir($tenantDir)) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Khong the tao thu muc luu file.',
                'errors' => [],
            ], 500);
        }

        $relativePath = $siteId . '/' . $uniqueName;
        $destination = $tenantDir . DIRECTORY_SEPARATOR . $uniqueName;

        $tmpName = (string) $file['tmp_name'];
        $moved = \is_uploaded_file($tmpName)
            ? \move_uploaded_file($tmpName, $destination)
            : \rename($tmpName, $destination);

        if (!$moved) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Khong the luu file.',
                'errors' => [],
            ], 500);
        }

        try {
            $mediaId = $this->database->transaction(function (Database $db) use ($originalName, $relativePath, $mimeType, $size, $siteId): int {
                $db->insert(
                    'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
                    [$siteId, $originalName, $relativePath, $mimeType, $size, $this->auth->id()]
                );
                $mediaId = (int) $db->connection()->lastInsertId();

                $db->statement(
                    'UPDATE sites SET storage_used_bytes = storage_used_bytes + ? WHERE id = ?',
                    [$size, $siteId]
                );

                return $mediaId;
            });
        } catch (\Throwable $exception) {
            @\unlink($destination);

            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Khong the luu thong tin file.',
                'errors' => [],
            ], 500);
        }

        return Response::json([
            'success' => true,
            'data' => [
                'id' => $mediaId,
                'file_name' => $originalName,
                'path' => $relativePath,
                'mime_type' => $mimeType,
                'size' => $size,
            ],
            'message' => '',
            'errors' => [],
        ], 201);
    }
}
