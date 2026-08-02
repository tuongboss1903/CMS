<?php

declare(strict_types=1);

namespace Modules\Public;

use Core\Database;

/**
 * Dung chung giua HomeController/PublicPageController (Owner Decision Phase 5 - tach class thay
 * vi trung lap method, khac fetchSeoMeta()/fetchNavigation() vi logic phuc tap hon: walk vong lap
 * nguoc len pages.parent_id, can gioi han do sau chong du lieu hong gay vong lap vo han). Khong
 * phai Service nghiep vu (khong ghi du lieu, khong business rule) - chi 1 ham dung chung thuan.
 */
final class BreadcrumbBuilder
{
    private const MAX_DEPTH = 20;

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Tra ve chain tu goc -> trang hien tai (bao gom chinh trang hien tai). Chan-tren cung neu vuot
     * MAX_DEPTH (du lieu parent_id hong gay vong lap A->B->A) - chap nhan chain cat cut hon la
     * timeout/vong lap vo han.
     *
     * @return list<array{title: string, slug: string}>
     */
    public function build(int|string $tenantId, int $pageId): array
    {
        $chain = [];
        $currentId = $pageId;
        $depth = 0;

        while ($currentId !== null && $depth < self::MAX_DEPTH) {
            $page = $this->database->selectOne(
                'SELECT parent_id, title, slug FROM pages WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
                [$currentId, $tenantId]
            );

            if ($page === null) {
                break;
            }

            $chain[] = ['title' => (string) $page['title'], 'slug' => (string) $page['slug']];
            $currentId = $page['parent_id'] !== null ? (int) $page['parent_id'] : null;
            $depth++;
        }

        return \array_reverse($chain);
    }
}
