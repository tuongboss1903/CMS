<?php

declare(strict_types=1);

namespace Modules\Media;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * PATCH /media/{id} - chi cho sua alt_text/title/caption (khong file_name/path/mime_type/size).
 * Partial update giong EditPageController. 404 cho media khong thuoc tenant hien tai.
 */
final class UpdateMediaController
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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $mediaId = (int) $request->routeParam('id');

        $exists = $this->database->selectOne(
            'SELECT id FROM media WHERE id = ? AND tenant_id = ?',
            [$mediaId, $this->tenantManager->id()]
        );

        if ($exists === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'alt_text' => 'nullable|string',
            'title' => 'nullable|string',
            'caption' => 'nullable|string',
        ]);

        if ($result->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Du lieu khong hop le.',
                'errors' => $result->errors(),
            ], 422);
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
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Khong co du lieu de cap nhat.',
                'errors' => [],
            ], 422);
        }

        $bindings[] = $mediaId;

        $this->database->statement(
            'UPDATE media SET ' . \implode(', ', $fields) . ' WHERE id = ?',
            $bindings
        );

        return Response::json([
            'success' => true,
            'data' => null,
            'message' => 'Cap nhat thanh cong.',
            'errors' => [],
        ]);
    }
}
