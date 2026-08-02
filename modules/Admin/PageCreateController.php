<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Database\QueryException;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\Validator;
use Core\View;

/**
 * POST /admin/pages - copy logic tu Modules\Page\CreatePageController. content gui len dang
 * content[html] (Quill.js xuat HTML that, gan vao input hidden truoc submit) - luu dung quy uoc
 * {"html": "..."} thay vi {"text": "..."} cu (Owner Decision Phase 3), khong sua Modules\Page\*.
 */
final class PageCreateController
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly Validator $validator,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();

        $result = $this->validator->validate($data, [
            'title' => 'required|string',
            'slug' => 'required|string',
            'content' => 'nullable|array',
            'template' => 'nullable|string',
            'parent_id' => 'nullable|integer',
        ]);

        if ($result->fails()) {
            return $this->renderWithErrors($result->errors(), $data);
        }

        $siteId = $this->tenantManager->id();

        if (\array_key_exists('parent_id', $data) && $data['parent_id'] !== null && $data['parent_id'] !== '') {
            $parentId = (int) $data['parent_id'];
            $parent = $this->database->selectOne(
                'SELECT id FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
                [$parentId, $siteId]
            );

            if ($parent === null) {
                return $this->renderWithErrors(['parent_id' => ['Parent page khong hop le.']], $data);
            }
        } else {
            $parentId = null;
        }

        $title = (string) $data['title'];
        $slug = (string) $data['slug'];
        $content = \array_key_exists('content', $data) && $data['content'] !== null
            ? \json_encode($data['content'])
            : null;
        $template = \array_key_exists('template', $data) && $data['template'] !== null && $data['template'] !== ''
            ? (string) $data['template']
            : null;

        try {
            $this->database->insert(
                'INSERT INTO pages (tenant_id, parent_id, title, slug, content, template, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$siteId, $parentId, $title, $slug, $content, $template, 'draft', $this->auth->id()]
            );
        } catch (QueryException $exception) {
            return $this->renderWithErrors(['slug' => ['Slug da ton tai.']], $data);
        }

        return Response::redirect('/admin/pages');
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $data
     */
    private function renderWithErrors(array $errors, array $data): Response
    {
        $parents = $this->database->select(
            'SELECT id, title FROM pages WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY title ASC',
            [$this->tenantManager->id()]
        );

        $html = $this->view->render('admin.pages.pages.create', [
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
