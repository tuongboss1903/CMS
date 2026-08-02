<?php

declare(strict_types=1);

namespace Modules\Public;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\I18n\Translator;
use Core\TenantManager;
use Core\View;
use Modules\Settings\SiteSettingsManager;

/**
 * GET / - render page danh dau is_homepage cua tenant hien tai. Khong Authorization::can()
 * (public, khong yeu cau dang nhap). Chua co homepage -> 404 (khong phai loi he thong).
 *
 * Public Website Polish: them SEO meta injection (title/description/canonical/OG/JSON-LD, chi khi
 * co ban ghi seo_meta - Option A, khong tu sinh mac dinh) va Navigation Menu (location_key='header',
 * an han <nav> neu chua co Menu - Owner Decision). Trung lap logic voi PublicPageController.php co
 * chu dich (Option A, khong tao abstraction moi).
 *
 * Phase 13 (i18n, CMS-050): xem docblock PublicPageController.php ve resolveTranslation()/
 * fetchAvailableLocales() - trung lap co chu dich, cung logic i18n.
 */
final class HomeController
{
    public function __construct(
        private readonly BreadcrumbBuilder $breadcrumbBuilder,
        private readonly Database $database,
        private readonly SiteSettingsManager $siteSettings,
        private readonly TenantManager $tenantManager,
        private readonly View $view,
    ) {
    }

    public function handle(Request $request): Response
    {
        $tenantId = $this->tenantManager->id();

        $page = $this->database->selectOne(
            'SELECT id, title, content, template FROM pages
             WHERE tenant_id = ? AND is_homepage = 1 AND status = ? AND deleted_at IS NULL',
            [$tenantId, 'published']
        );

        if ($page === null) {
            return $this->render404();
        }

        return $this->render($page);
    }

    /** @param array{id: int|string, title: string, content: string|null, template: string|null} $page */
    private function render(array $page): Response
    {
        $pageId = (int) $page['id'];
        $tenantId = $this->tenantManager->id();

        $templateName = $page['template'] !== null && $page['template'] !== ''
            ? "pages.{$page['template']}"
            : 'pages.default';

        if (!$this->view->exists($templateName)) {
            $templateName = 'pages.default';
        }

        $seo = $this->fetchSeoMeta($tenantId, $pageId);
        $siteSettings = $this->siteSettings->get();
        $ogImageId = $seo['og_image_id'] ?? $siteSettings['default_og_image_id'] ?? null;

        $locale = Translator::globalInstance()->getLocale();
        $translation = $this->resolveTranslation($tenantId, $pageId, $locale);

        $title = $translation['title'] ?? $page['title'];
        $rawContent = $translation !== null ? $translation['content'] : $page['content'];

        $content = \json_decode($rawContent ?? 'null', true);
        $content = $this->resolveBlockImageUrls($content, $tenantId);

        $html = $this->view->render($templateName, [
            'title' => $seo['title'] ?? $title,
            'content' => $content,
            'seo' => $seo,
            'menu' => $this->fetchNavigation($tenantId, $pageId),
            'site_settings' => $siteSettings,
            'breadcrumb' => $this->breadcrumbBuilder->build($tenantId, $pageId),
            'og_image_url' => $this->resolveMediaUrl($tenantId, $ogImageId !== null ? (int) $ogImageId : null),
            'favicon_url' => $this->resolveMediaUrl($tenantId, $siteSettings['favicon_id'] !== null ? (int) $siteSettings['favicon_id'] : null),
            'locale' => $locale,
            'available_locales' => $this->fetchAvailableLocales($tenantId, $pageId),
        ]);

        return Response::html($html);
    }

    /** @return array{title: string, content: string|null}|null */
    private function resolveTranslation(int|string|null $tenantId, int $pageId, string $locale): ?array
    {
        if ($locale === 'vi') {
            return null;
        }

        return $this->database->selectOne(
            'SELECT title, content FROM page_translations WHERE tenant_id = ? AND page_id = ? AND locale = ?',
            [$tenantId, $pageId, $locale]
        );
    }

    /**
     * Bang page_translations chua chac ton tai o moi fixture test cu - bat Throwable, fallback ['vi'].
     *
     * @return list<string>
     */
    private function fetchAvailableLocales(int|string|null $tenantId, int $pageId): array
    {
        try {
            $rows = $this->database->select(
                'SELECT DISTINCT locale FROM page_translations WHERE tenant_id = ? AND page_id = ?',
                [$tenantId, $pageId]
            );
        } catch (\Throwable) {
            return ['vi'];
        }

        return \array_values(\array_unique([
            'vi',
            ...\array_map(static fn (array $row): string => (string) $row['locale'], $rows),
        ]));
    }

    private function render404(): Response
    {
        $siteSettings = $this->siteSettings->get();
        $data = [
            'title' => '404 Not Found',
            'site_settings' => $siteSettings,
            'favicon_url' => $this->resolveMediaUrl($this->tenantManager->id(), $siteSettings['favicon_id'] !== null ? (int) $siteSettings['favicon_id'] : null),
        ];

        if ($this->view->exists('pages.404')) {
            return Response::html($this->view->render('pages.404', $data), 404);
        }

        return Response::html('404 Not Found', 404);
    }

    /** @return array<string, mixed>|null */
    private function fetchSeoMeta(int|string|null $tenantId, int $pageId): ?array
    {
        $meta = $this->database->selectOne(
            'SELECT title, description, canonical, og_image_id, is_index, is_follow, schema_type, schema_data
             FROM seo_meta WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?',
            [$tenantId, 'page', $pageId]
        );

        if ($meta === null) {
            return null;
        }

        $meta['schema_data'] = $meta['schema_data'] !== null ? \json_decode((string) $meta['schema_data'], true) : null;

        return $meta;
    }

    /** Chuyen media_id -> URL cong khai qua MediaServeController (GET /media/{filename}). */
    private function resolveMediaUrl(int|string|null $tenantId, ?int $mediaId): ?string
    {
        if ($mediaId === null) {
            return null;
        }

        $media = $this->database->selectOne(
            'SELECT path FROM media WHERE id = ? AND tenant_id = ?',
            [$mediaId, $tenantId]
        );

        if ($media === null) {
            return null;
        }

        return '/media/' . \basename((string) $media['path']);
    }

    /**
     * Phase 11 (Visual Page Builder): resolve media_id -> URL that cho tung block type='image'
     * TRUOC KHI dua vao View - View khong duoc phep truy van Database (dung nguyen tac kien truc
     * da giu xuyen suot), nen phai lam o Controller. Tai su dung nguyen resolveMediaUrl() da co.
     */
    private function resolveBlockImageUrls(mixed $content, int|string|null $tenantId): mixed
    {
        if (!\is_array($content) || !isset($content['blocks']) || !\is_array($content['blocks'])) {
            return $content;
        }

        foreach ($content['blocks'] as &$block) {
            if (\is_array($block) && ($block['type'] ?? null) === 'image' && !empty($block['media_id'])) {
                $block['url'] = $this->resolveMediaUrl($tenantId, (int) $block['media_id']);
            }
        }
        unset($block);

        return $content;
    }

    /**
     * Location co dinh 'header' (Owner Decision Public Website Polish - PHASE 2). 1 query lay
     * menu_items + 1 query lay slug cac page duoc tham chieu (khong N+1), dung cay bang PHP thuan
     * (cung cach ShowMenuController::buildTree() da lam, khong tai su dung truc tiep - trung lap
     * co chu dich).
     *
     * @return list<array<string, mixed>>
     */
    private function fetchNavigation(int|string|null $tenantId, ?int $activePageId): array
    {
        $menu = $this->database->selectOne(
            'SELECT id FROM menus WHERE tenant_id = ? AND location_key = ?',
            [$tenantId, 'header']
        );

        if ($menu === null) {
            return [];
        }

        $items = $this->database->select(
            'SELECT id, parent_id, label, type, reference_id, url, target, sort_order
             FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC',
            [(int) $menu['id']]
        );

        if ($items === []) {
            return [];
        }

        $pageIds = \array_values(\array_unique(\array_map(
            static fn (array $item): int => (int) $item['reference_id'],
            \array_filter($items, static fn (array $item): bool => $item['type'] === 'page')
        )));

        $slugById = [];

        if ($pageIds !== []) {
            $placeholders = \implode(',', \array_fill(0, \count($pageIds), '?'));
            $pages = $this->database->select(
                "SELECT id, slug FROM pages WHERE id IN ({$placeholders}) AND tenant_id = ?",
                [...$pageIds, $tenantId]
            );

            foreach ($pages as $row) {
                $slugById[(int) $row['id']] = (string) $row['slug'];
            }
        }

        $resolved = \array_map(function (array $item) use ($slugById, $activePageId): array {
            $referenceId = $item['reference_id'] !== null ? (int) $item['reference_id'] : null;

            return [
                'id' => (int) $item['id'],
                'parent_id' => $item['parent_id'],
                'label' => $item['label'],
                'url' => $item['type'] === 'page'
                    ? '/' . ($slugById[$referenceId] ?? '')
                    : (string) $item['url'],
                'target' => $item['target'],
                'active' => $item['type'] === 'page' && $referenceId !== null && $referenceId === $activePageId,
            ];
        }, $items);

        return $this->buildTree($resolved);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function buildTree(array $items): array
    {
        $byParent = [];

        foreach ($items as $item) {
            $parentKey = $item['parent_id'] === null ? '' : (string) $item['parent_id'];
            $byParent[$parentKey][] = $item;
        }

        return $this->attachChildren($byParent[''] ?? [], $byParent);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, list<array<string, mixed>>> $byParent
     * @return list<array<string, mixed>>
     */
    private function attachChildren(array $nodes, array $byParent): array
    {
        foreach ($nodes as &$node) {
            $node['children'] = $this->attachChildren($byParent[(string) $node['id']] ?? [], $byParent);
        }

        return $nodes;
    }
}
