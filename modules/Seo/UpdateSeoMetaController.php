<?php

declare(strict_types=1);

namespace Modules\Seo;

use Core\Authorization;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;

/**
 * PATCH /seo/{entity_type}/{entity_id} - upsert (SELECT roi INSERT/UPDATE), khong transaction
 * (chi 1 cau SQL ghi/lan goi), khong retry, khong lock (Owner Decision Final Design CMS-043).
 * Partial update - field khong gui giu nguyen gia tri cu khi UPDATE.
 */
final class UpdateSeoMetaController
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
        if (!$this->authorization->can('seo.update')) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Forbidden.',
                'errors' => [],
            ], 403);
        }

        $entityType = (string) $request->routeParam('entity_type');
        $entityId = (int) $request->routeParam('entity_id');

        $entityResult = $this->validator->validate(
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            ['entity_type' => 'required|in:page', 'entity_id' => 'required|integer']
        );

        if ($entityResult->fails()) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $siteId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$entityId, $siteId]
        );

        if ($page === null) {
            return Response::json([
                'success' => false,
                'data' => null,
                'message' => 'Not Found',
                'errors' => [],
            ], 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'canonical' => 'nullable|string|max:500',
            'og_image_id' => 'nullable|integer',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'is_index' => 'nullable|boolean',
            'is_follow' => 'nullable|boolean',
            'schema_type' => 'nullable|string|max:50',
            'schema_data' => 'nullable|array',
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

        foreach (['title', 'description', 'canonical', 'og_title', 'og_description', 'schema_type'] as $field) {
            if (\array_key_exists($field, $data)) {
                $fields[$field] = $data[$field] !== null ? (string) $data[$field] : null;
            }
        }

        foreach (['is_index', 'is_follow'] as $field) {
            if (\array_key_exists($field, $data) && $data[$field] !== null) {
                $fields[$field] = \in_array($data[$field], [true, 1, '1'], true) ? 1 : 0;
            }
        }

        if (\array_key_exists('og_image_id', $data)) {
            $ogImageId = null;

            if ($data['og_image_id'] !== null) {
                $ogImageId = (int) $data['og_image_id'];
                $media = $this->database->selectOne(
                    'SELECT id FROM media WHERE id = ? AND tenant_id = ?',
                    [$ogImageId, $siteId]
                );

                if ($media === null) {
                    return Response::json([
                        'success' => false,
                        'data' => null,
                        'message' => 'Anh OG khong hop le.',
                        'errors' => [],
                    ], 422);
                }
            }

            $fields['og_image_id'] = $ogImageId;
        }

        if (\array_key_exists('schema_data', $data)) {
            $fields['schema_data'] = $data['schema_data'] !== null ? \json_encode($data['schema_data']) : null;
        }

        $existing = $this->database->selectOne(
            'SELECT id FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, $entityType, $entityId]
        );

        if ($existing === null) {
            $this->database->insert(
                'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, title, description, canonical, og_image_id, og_title, og_description, is_index, is_follow, schema_type, schema_data)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $siteId,
                    $entityType,
                    $entityId,
                    $fields['title'] ?? null,
                    $fields['description'] ?? null,
                    $fields['canonical'] ?? null,
                    $fields['og_image_id'] ?? null,
                    $fields['og_title'] ?? null,
                    $fields['og_description'] ?? null,
                    $fields['is_index'] ?? 1,
                    $fields['is_follow'] ?? 1,
                    $fields['schema_type'] ?? null,
                    $fields['schema_data'] ?? null,
                ]
            );

            $metaId = (int) $this->database->connection()->lastInsertId();
        } else {
            $metaId = (int) $existing['id'];

            if ($fields !== []) {
                $setClauses = [];
                $bindings = [];

                foreach ($fields as $column => $value) {
                    $setClauses[] = "{$column} = ?";
                    $bindings[] = $value;
                }

                $bindings[] = $metaId;

                $this->database->statement(
                    'UPDATE seo_meta SET ' . \implode(', ', $setClauses) . ' WHERE id = ?',
                    $bindings
                );
            }
        }

        $meta = $this->database->selectOne('SELECT * FROM seo_meta WHERE id = ?', [$metaId]);
        $meta['schema_data'] = $meta['schema_data'] !== null ? \json_decode((string) $meta['schema_data'], true) : null;

        return Response::json([
            'success' => true,
            'data' => $meta,
            'message' => 'Cap nhat thanh cong.',
            'errors' => [],
        ]);
    }
}
