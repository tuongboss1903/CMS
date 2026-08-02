<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;
use Modules\Page\Actions\PageNotFoundException;
use Modules\Page\Actions\PageValidationException;
use Modules\Page\Actions\UpdatePageAction;

/**
 * POST /admin/pages/{id} - khong PATCH (khong Method Spoofing). Logic nghiep vu dung chung qua
 * Actions\UpdatePageAction voi Modules\Page\EditPageController (Pilot Action Class Pattern,
 * Phase 6).
 *
 * Phase 11 (Visual Page Builder): them nhanh xu ly khi editor_mode='block' - cung logic
 * decode/validate voi PageCreateController (trung lap co chu dich, dung tien le du an).
 */
final class PageUpdateController
{
    /** @var list<string> */
    private const VALID_BLOCK_TYPES = ['heading', 'paragraph', 'image', 'hero', 'feature_grid', 'cta'];

    public function __construct(
        private readonly Authorization $authorization,
        private readonly Csrf $csrf,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly UpdatePageAction $action,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.update')) {
            return Response::html('403 Forbidden', 403);
        }

        $pageId = (int) $request->routeParam('id');
        $data = $request->all();

        if (($data['editor_mode'] ?? 'quill') === 'block') {
            $blocks = $this->decodeAndValidateBlocks((string) ($data['content_blocks_json'] ?? ''));

            if ($blocks === null) {
                return Response::redirect('/admin/pages');
            }

            $data['content'] = ['blocks' => $blocks];
        }

        try {
            $this->action->execute($pageId, $data);
        } catch (PageNotFoundException) {
            return Response::html('404 Not Found', 404);
        } catch (PageValidationException $exception) {
            return $this->renderWithErrors($pageId, $exception->errors(), $data);
        }

        return Response::redirect('/admin/pages');
    }

    /** @return list<array<string, mixed>>|null */
    private function decodeAndValidateBlocks(string $rawJson): ?array
    {
        $decoded = \json_decode($rawJson, true);

        if (!\is_array($decoded)) {
            return null;
        }

        $siteId = $this->tenantManager->id();

        foreach ($decoded as $block) {
            if (!\is_array($block) || !isset($block['type']) || !\in_array($block['type'], self::VALID_BLOCK_TYPES, true)) {
                return null;
            }

            if ($block['type'] === 'image' && !empty($block['media_id'])) {
                $media = $this->database->selectOne(
                    'SELECT id FROM media WHERE id = ? AND tenant_id = ?',
                    [(int) $block['media_id'], $siteId]
                );

                if ($media === null) {
                    return null;
                }
            }
        }

        return $decoded;
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

        try {
            $images = $this->database->select(
                "SELECT id, file_name FROM media WHERE tenant_id = ? AND mime_type LIKE 'image/%' ORDER BY file_name ASC",
                [$siteId]
            );
        } catch (\Throwable) {
            $images = [];
        }

        $html = $this->view->render('admin.pages.pages.edit', [
            'page' => ['id' => $pageId],
            'parents' => $parents,
            'images' => $images,
            'editor_mode' => (string) ($data['editor_mode'] ?? 'quill'),
            'errors' => $errors,
            'old' => [
                'title' => (string) ($data['title'] ?? ''),
                'slug' => (string) ($data['slug'] ?? ''),
                'content_html' => (string) ($data['content']['html'] ?? ''),
                'template' => (string) ($data['template'] ?? ''),
                'parent_id' => (string) ($data['parent_id'] ?? ''),
                'blocks' => $data['content']['blocks'] ?? [],
            ],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
