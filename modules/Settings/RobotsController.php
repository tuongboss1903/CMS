<?php

declare(strict_types=1);

namespace Modules\Settings;

use Core\Http\Request;
use Core\Http\Response;

/**
 * GET /robots.txt - phuc vu noi dung dong theo tenant. Co robots_txt_custom (Admin nhap) -> tra
 * nguyen van. Chua co -> mac dinh "an toan" (cho phep toan bo, tro Sitemap) - khong chan indexing
 * ngoai y muon cho tenant chua cau hinh gi.
 */
final class RobotsController
{
    public function __construct(private readonly SiteSettingsManager $settings)
    {
    }

    public function handle(Request $request): Response
    {
        $settings = $this->settings->get();

        $custom = $settings['robots_txt_custom'] ?? null;

        if (\is_string($custom) && \trim($custom) !== '') {
            $body = $custom;
        } else {
            $baseUrl = 'https://' . $request->getHost();
            $body = "User-agent: *\nAllow: /\nSitemap: {$baseUrl}/sitemap.xml\n";
        }

        return new Response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
