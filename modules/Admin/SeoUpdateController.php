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
 * POST /admin/seo/pages/{id} - copy logic upsert tu Modules\Seo\UpdateSeoMetaController y het
 * (SELECT roi INSERT/UPDATE, khong transaction - dung nguyen ly do goc CMS-043). entity_type co
 * dinh 'page'. Form gui schema_data qua textarea JSON tho (field 'schema_data_json') - Controller
 * tu json_decode truoc khi ap dung logic upsert; JSON khong hop le -> silent-redirect, khong luu,
 * cung mau Media/Menu.
 */
final class SeoUpdateController
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
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $siteId]
        );

        if ($page === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        if (\array_key_exists('schema_data_json', $data)) {
            $rawSchemaData = \trim((string) $data['schema_data_json']);

            if ($rawSchemaData === '') {
                $data['schema_data'] = null;
            } else {
                $decoded = \json_decode($rawSchemaData, true);

                if (\json_last_error() !== JSON_ERROR_NONE) {
                    return Response::redirect("/admin/seo/pages/{$pageId}");
                }

                $data['schema_data'] = $decoded;
            }

            unset($data['schema_data_json']);
        }

        if (\array_key_exists('og_image_id', $data) && $data['og_image_id'] === '') {
            $data['og_image_id'] = null;
        }

        $result = $this->validator->validate($data, [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'canonical' => 'nullable|string|max:500',
            'og_image_id' => 'nullable|integer',
            'schema_type' => 'nullable|string|max:50',
            'schema_data' => 'nullable|array',
        ]);

        if ($result->fails()) {
            return Response::redirect("/admin/seo/pages/{$pageId}");
        }

        $fields = [];

        foreach (['title', 'description', 'canonical', 'schema_type'] as $field) {
            if (\array_key_exists($field, $data)) {
                $fields[$field] = $data[$field] !== null ? (string) $data[$field] : null;
            }
        }

        if (\array_key_exists('og_image_id', $data)) {
            $ogImageId = null;

            if ($data['og_image_id'] !== null && $data['og_image_id'] !== '') {
                $ogImageId = (int) $data['og_image_id'];
                $media = $this->database->selectOne(
                    'SELECT id FROM media WHERE id = ? AND tenant_id = ?',
                    [$ogImageId, $siteId]
                );

                if ($media === null) {
                    return Response::redirect("/admin/seo/pages/{$pageId}");
                }
            }

            $fields['og_image_id'] = $ogImageId;
        }

        if (\array_key_exists('schema_data', $data)) {
            $fields['schema_data'] = $data['schema_data'] !== null ? \json_encode($data['schema_data']) : null;
        }

        $existing = $this->database->selectOne(
            'SELECT id FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$siteId, 'page', $pageId]
        );

        if ($existing === null) {
            $this->database->insert(
                'INSERT INTO seo_meta (tenant_id, entity_type, entity_id, title, description, canonical, og_image_id, schema_type, schema_data)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $siteId,
                    'page',
                    $pageId,
                    $fields['title'] ?? null,
                    $fields['description'] ?? null,
                    $fields['canonical'] ?? null,
                    $fields['og_image_id'] ?? null,
                    $fields['schema_type'] ?? null,
                    $fields['schema_data'] ?? null,
                ]
            );
        } elseif ($fields !== []) {
            $setClauses = [];
            $bindings = [];

            foreach ($fields as $column => $value) {
                $setClauses[] = "{$column} = ?";
                $bindings[] = $value;
            }

            $bindings[] = (int) $existing['id'];

            $this->database->statement(
                'UPDATE seo_meta SET ' . \implode(', ', $setClauses) . ' WHERE id = ?',
                $bindings
            );
        }

        return Response::redirect('/admin/seo');
    }
}
