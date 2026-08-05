<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * POST /admin/media - copy logic tu Modules\Media\UploadMediaController (validate size/mime,
 * move_uploaded_file()/rename() fallback cho CLI/test, transaction INSERT + cong don
 * storage_used_bytes, don file neu transaction loi). Loi tra ve redirect /admin/media (khong co
 * trang Upload rieng - Modal tren chinh list.php), khac PageCreateController (co trang Create
 * rieng nen render lai form voi errors).
 */
final class MediaUploadController
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
            return Response::html('403 Forbidden', 403);
        }

        $file = $request->file('file');

        if ($file === null || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return Response::redirect('/admin/media');
        }

        $size = (int) $file['size'];

        if ($size > self::MAX_SIZE_BYTES) {
            return Response::redirect('/admin/media');
        }

        $claimedMimeType = (string) $file['type'];

        if (!\in_array($claimedMimeType, self::ALLOWED_MIME_TYPES, true)) {
            return Response::redirect('/admin/media');
        }

        /**
         * Khong tin $file['type'] (Content-Type client tu khai bao, gia mao duoc) - doc lai magic
         * byte that qua finfo_file() de xac dinh mime type that, dong bo Modules\Media\UploadMediaController.
         */
        $detectedMimeType = false;
        $tmpNameForDetection = (string) $file['tmp_name'];

        if (\is_readable($tmpNameForDetection)) {
            $finfo = \finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = $finfo !== false ? \finfo_file($finfo, $tmpNameForDetection) : false;

            if ($finfo !== false) {
                \finfo_close($finfo);
            }
        }

        if ($detectedMimeType === false || !\in_array($detectedMimeType, self::ALLOWED_MIME_TYPES, true)) {
            return Response::redirect('/admin/media');
        }

        $mimeType = $detectedMimeType;

        $siteId = $this->tenantManager->id();

        if ($this->exceedsStorageQuota($siteId, $size)) {
            return Response::redirect('/admin/media');
        }

        $originalName = (string) $file['name'];
        $extension = \pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueName = \uniqid('media_', true) . ($extension !== '' ? '.' . $extension : '');

        $tenantDir = \rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $siteId;

        if (!\is_dir($tenantDir) && !\mkdir($tenantDir, 0755, true) && !\is_dir($tenantDir)) {
            return Response::redirect('/admin/media');
        }

        $relativePath = $siteId . '/' . $uniqueName;
        $destination = $tenantDir . DIRECTORY_SEPARATOR . $uniqueName;

        $tmpName = (string) $file['tmp_name'];
        $moved = \is_uploaded_file($tmpName)
            ? \move_uploaded_file($tmpName, $destination)
            : \rename($tmpName, $destination);

        if (!$moved) {
            return Response::redirect('/admin/media');
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

            return Response::redirect('/admin/media');
        }

        $this->generateThumbnail($mediaId, $destination, $mimeType, $tenantDir, (string) $siteId);

        return Response::redirect('/admin/media');
    }

    /**
     * Sinh media_variants (size_type='thumbnail', khop ENUM da chot database-design.md muc 4.1)
     * qua ext-gd (built-in PHP, khong them composer package - dung y Owner Decision CMS-041 goc:
     * "chua co thu vien xu ly anh" gio da khong con dung, ext-gd luon co san trong PHP chuan).
     * Best-effort tuyet doi - moi loi (GD khong load duoc anh, extension bi tat, ghi file that
     * bai...) chi log-silent va bo qua, KHONG lam that bai request upload (thumbnail la tien ich
     * hien thi, khong phai du lieu bat buoc - Admin Grid/List fallback ve anh goc qua
     * MediaThumbnailController khi khong co variant).
     */
    private function generateThumbnail(int $mediaId, string $sourcePath, string $mimeType, string $tenantDir, string $siteId): void
    {
        if (!\function_exists('imagecreatetruecolor') || !\in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            return;
        }

        try {
            /** @var array<string, callable(string): (\GdImage|false)> $loaders */
            $loaders = [
                'image/jpeg' => \imagecreatefromjpeg(...),
                'image/png' => \imagecreatefrompng(...),
                'image/gif' => \imagecreatefromgif(...),
            ];

            $source = @$loaders[$mimeType]($sourcePath);

            if ($source === false) {
                return;
            }

            $originalWidth = \imagesx($source);
            $originalHeight = \imagesy($source);

            $targetWidth = \min(300, $originalWidth);
            $targetHeight = (int) \round($originalHeight * ($targetWidth / $originalWidth));

            $thumbnail = \imagecreatetruecolor($targetWidth, $targetHeight);
            \imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $originalWidth, $originalHeight);

            $thumbnailName = 'thumb_' . \pathinfo($sourcePath, PATHINFO_FILENAME) . '.jpg';
            $thumbnailDestination = $tenantDir . DIRECTORY_SEPARATOR . $thumbnailName;

            if (\imagejpeg($thumbnail, $thumbnailDestination, 82)) {
                $this->database->insert(
                    'INSERT INTO media_variants (media_id, size_type, path, width, height) VALUES (?, ?, ?, ?, ?)',
                    [$mediaId, 'thumbnail', $siteId . '/' . $thumbnailName, $targetWidth, $targetHeight]
                );
            }

            \imagedestroy($source);
            \imagedestroy($thumbnail);
        } catch (\Throwable) {
            // Silent-fail co chu dich - xem docblock o tren.
        }
    }

    /**
     * Buoc 4 (Subscription/Goi dich vu, CMS-065): site khong gan goi hoac goi khong dat
     * max_storage_mb (NULL = khong gioi han) thi KHONG bi chan - zero-regression cho moi site
     * hien co (plan_id mac dinh NULL tu truoc gio). Bang "plans" co the chua ton tai o fixture
     * test cu chua migrate them (cung nguyen tac try/catch fallback da dung o
     * Modules\Admin\DashboardController::fetchAnalyticsSummary()) - bat Throwable, coi nhu
     * khong gioi han thay vi lam vo request upload.
     */
    private function exceedsStorageQuota(int|string|null $siteId, int $uploadSize): bool
    {
        try {
            $row = $this->database->selectOne(
                'SELECT sites.storage_used_bytes, plans.max_storage_mb
                    FROM sites LEFT JOIN plans ON plans.id = sites.plan_id
                    WHERE sites.id = ?',
                [$siteId]
            );
        } catch (\Throwable) {
            return false;
        }

        if ($row === null || $row['max_storage_mb'] === null) {
            return false;
        }

        $limitBytes = (int) $row['max_storage_mb'] * 1024 * 1024;

        return ((int) $row['storage_used_bytes'] + $uploadSize) > $limitBytes;
    }
}
