<?php

declare(strict_types=1);

namespace Modules\Settings;

use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\TenantManager;

/**
 * GET /sitemap.xml - thu thap toan bo Page cong khai (status='published', chua bi xoa mem) cua
 * tenant hien tai, render XML sitemap chuan (protocol sitemaps.org). Public, khong Authorization::can().
 *
 * Gioi han da biet: Request khong co isSecure()/scheme() (core/Http/Request.php chua ho tro) -
 * mac dinh dung 'https://' cho <loc> - chap nhan duoc vi da phan trien khai thuc te dung TLS,
 * ghi nhan la Technical Debt neu can ho tro ca HTTP thuan sau nay.
 */
final class SitemapController
{
    public function __construct(
        private readonly Database $database,
        private readonly TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request): Response
    {
        $tenantId = $this->tenantManager->id();

        $pages = $this->database->select(
            'SELECT slug, updated_at FROM pages WHERE tenant_id = ? AND status = ? AND deleted_at IS NULL ORDER BY id ASC',
            [$tenantId, 'published']
        );

        $baseUrl = 'https://' . $request->getHost();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $page) {
            $loc = $baseUrl . '/' . $page['slug'];
            $xml .= '<url><loc>' . \htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';

            if ($page['updated_at'] !== null) {
                $xml .= '<lastmod>' . \htmlspecialchars((string) $page['updated_at'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</lastmod>';
            }

            $xml .= '</url>' . "\n";
        }

        $xml .= '</urlset>';

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
