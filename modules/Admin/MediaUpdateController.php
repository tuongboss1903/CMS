<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * POST /admin/media/{id} - copy logic tu Modules\Media\UpdateMediaController (partial update
 * alt_text/title/caption). Loi validate/khong co du lieu -> redirect ve /admin/media (khong co
 * trang Edit rieng, sua inline tren list.php), cung mau silent-redirect da dung o PagePublishController.
 */
final class MediaUpdateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('media.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $mediaId = (int) $request->routeParam('id');

        $exists = $this->database->selectOne(
            'SELECT id FROM media WHERE id = ? AND tenant_id = ?',
            [$mediaId, $this->tenantManager->id()]
        );

        if ($exists === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'alt_text' => 'nullable|string',
            'title' => 'nullable|string',
            'caption' => 'nullable|string',
        ]);

        if ($result->fails()) {
            return Response::redirect('/admin/media');
        }

        $fields = [];
        $bindings = [];

        foreach (['alt_text', 'title', 'caption'] as $field) {
            if (\array_key_exists($field, $data) && $data[$field] !== null) {
                $fields[] = "{$field} = ?";
                $bindings[] = (string) $data[$field];
            }
        }

        if ($fields === []) {
            return Response::redirect('/admin/media');
        }

        $bindings[] = $mediaId;

        $this->database->statement(
            'UPDATE media SET ' . \implode(', ', $fields) . ' WHERE id = ?',
            $bindings
        );

        return Response::redirect('/admin/media');
    }
}
