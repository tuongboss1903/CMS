<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;
use Core\View;

/**
 * POST /admin/pages/{id} - khong PATCH (khong Method Spoofing). Copy logic tu
 * Modules\Page\EditPageController.
 */
final class PageUpdateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');
        $siteId = $this->tenantManager->id();

        $exists = $this->database->selectOne(
            'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
            [$pageId, $siteId]
        );

        if ($exists === null) {
            return Response::html('404 Not Found', 404);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'title' => 'nullable|string',
            'slug' => 'nullable|string',
            'content' => 'nullable|array',
            'template' => 'nullable|string',
            'parent_id' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($pageId, $result->errors(), $data);
        }

        $fields = [];
        $bindings = [];

        if (\array_key_exists('title', $data) && $data['title'] !== null) {
            $fields[] = 'title = ?';
            $bindings[] = (string) $data['title'];
        }

        if (\array_key_exists('slug', $data) && $data['slug'] !== null) {
            $fields[] = 'slug = ?';
            $bindings[] = (string) $data['slug'];
        }

        if (\array_key_exists('content', $data) && $data['content'] !== null) {
            $fields[] = 'content = ?';
            $bindings[] = \json_encode($data['content']);
        }

        if (\array_key_exists('template', $data) && $data['template'] !== null) {
            $fields[] = 'template = ?';
            $bindings[] = (string) $data['template'];
        }

        if (\array_key_exists('parent_id', $data) && $data['parent_id'] !== null && $data['parent_id'] !== '') {
            $parentId = (int) $data['parent_id'];
            $parent = $this->database->selectOne(
                'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
                [$parentId, $siteId]
            );

            if ($parent === null) {
                return $this->renderWithErrors($pageId, ['parent_id' => ['Parent page khong hop le.']], $data);
            }

            $fields[] = 'parent_id = ?';
            $bindings[] = $parentId;
        }

        if ($fields === []) {
            return $this->renderWithErrors($pageId, ['title' => ['Khong co du lieu de cap nhat.']], $data);
        }

        $bindings[] = $pageId;

        $this->database->statement(
            'UPDATE pages SET ' . \implode(', ', $fields) . ' WHERE id = ?',
            $bindings
        );

        return Response::redirect('/admin/pages');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(int $pageId, array $errors, array $data): Response
    {
        $siteId = $this->tenantManager->id();

        $parents = $this->database->select(
            'SELECT id, title FROM pages WHERE tenant_id = ? AND deleted_at IS NULL AND id != ? ORDER BY title ASC',
            [$siteId, $pageId]
        );

        $html = $this->view->render('admin.pages.pages.edit', [
            'page' => ['id' => $pageId],
            'parents' => $parents,
            'errors' => $errors,
            'old' => [
                'title' => (string) ($data['title'] ?? ''),
                'slug' => (string) ($data['slug'] ?? ''),
                'content_html' => (string) ($data['content']['html'] ?? ''),
                'template' => (string) ($data['template'] ?? ''),
                'parent_id' => (string) ($data['parent_id'] ?? ''),
            ],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
