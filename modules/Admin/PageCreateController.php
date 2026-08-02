<?php

declare(strict_types=1);

namespace Modules\Admin;

use Core\Auth;
use Core\Authorization;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;
use Core\View;
use Modules\Page\Actions\CreatePageAction;
use Modules\Page\Actions\PageValidationException;

/**
 * POST /admin/pages - logic nghiep vu (validate + INSERT) dung chung qua Actions\CreatePageAction
 * voi Modules\Page\CreatePageController (Pilot Action Class Pattern, Phase 6) - Controller nay
 * chi con trach nhiem HTTP: check permission, goi Action, dinh dang Response HTML/redirect. content
 * gui len dang content[html] (Quill.js), luu dung quy uoc {"html": "..."} (Owner Decision Phase 3).
 *
 * Phase 11 (Visual Page Builder): them nhanh xu ly khi editor_mode='block' - decode
 * content_blocks_json, validate 6 loai block hop le + media_id thuoc dung tenant, ghi de
 * $data['content'] = ['blocks' => [...]] TRUOC KHI goi Action (Action khong doi, van chi nhan
 * $data['content'] la 1 array bat ky - dung phat hien kien truc o Architecture Analysis: schema
 * content la JSON tu do, khong can sua Action/JSON API cho quy uoc moi nay).
 *
 * Phase 13 (i18n, CMS-050): sau khi CreatePageAction tao page goc (locale 'vi') thanh cong, luu
 * them ban dich cac locale khac (hien tai chi 'en') tu $data['translations'][locale] neu Admin co
 * nhap - bo qua locale nao khong nhap du (title/slug rong). KHONG qua Action Class (Action chi biet
 * ve bang "pages", page_translations la khai niem rieng cua Phase 13) - Controller tu thao tac
 * Database truc tiep, dung tien le "trung lap co chu dich" da giu xuyen suot du an.
 */
final class PageCreateController
{
    /** @var list<string> */
    private const VALID_BLOCK_TYPES = ['heading', 'paragraph', 'image', 'hero', 'feature_grid', 'cta'];

    /** @var list<string> Locale co the dich (locale goc 'vi' luu truc tiep trong pages, khong o day). */
    private const TRANSLATABLE_LOCALES = ['en'];

    public function __construct(
        private readonly Authorization $authorization,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly CreatePageAction $action,
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->authorization->can('page.create')) {
            return Response::html('403 Forbidden', 403);
        }

        $data = $request->all();

        if (($data['editor_mode'] ?? 'quill') === 'block') {
            $blocks = $this->decodeAndValidateBlocks((string) ($data['content_blocks_json'] ?? ''));

            if ($blocks === null) {
                return Response::redirect('/admin/pages');
            }

            $data['content'] = ['blocks' => $blocks];
        }

        try {
            $created = $this->action->execute($data, $this->auth->id());
        } catch (PageValidationException $exception) {
            return $this->renderWithErrors($exception->errors(), $data);
        }

        $this->saveTranslations($created['id'], $this->tenantManager->id(), $data);

        return Response::redirect('/admin/pages');
    }

    /** @param array<string, mixed> $data */
    private function saveTranslations(int $pageId, int|string|null $tenantId, array $data): void
    {
        $translations = \is_array($data['translations'] ?? null) ? $data['translations'] : [];

        foreach (self::TRANSLATABLE_LOCALES as $locale) {
            $input = $translations[$locale] ?? null;

            if (!\is_array($input) || empty($input['title']) || empty($input['slug'])) {
                continue;
            }

            $content = isset($input['content']) && $input['content'] !== ''
                ? \json_encode(['html' => (string) $input['content']], JSON_UNESCAPED_UNICODE)
                : null;

            $this->database->insert(
                'INSERT INTO page_translations (tenant_id, page_id, locale, title, slug, content)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$tenantId, $pageId, $locale, (string) $input['title'], (string) $input['slug'], $content]
            );
        }
    }

    /**
     * Tra ve null neu JSON khong hop le, chua block type khong nam trong 6 loai MVP, hoac
     * block "image" tham chieu media_id khong thuoc tenant hien tai (Owner Decision: tu choi
     * toan bo, khong luu 1 phan).
     *
     * @return list<array<string, mixed>>|null
     */
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
    private function renderWithErrors(array $errors, array $data): Response
    {
        $siteId = $this->tenantManager->id();

        $parents = $this->database->select(
            'SELECT id, title FROM pages WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY title ASC',
            [$siteId]
        );

        try {
            $images = $this->database->select(
                "SELECT id, file_name FROM media WHERE tenant_id = ? AND mime_type LIKE 'image/%' ORDER BY file_name ASC",
                [$siteId]
            );
        } catch (\Throwable) {
            $images = [];
        }

        $html = $this->view->render('admin.pages.pages.create', [
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
            'translations' => \is_array($data['translations'] ?? null) ? $data['translations'] : [],
            'csrf_token' => $this->csrf->token(),
        ]);

        return Response::html($html);
    }
}
