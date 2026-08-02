<?php

declare(strict_types=1);

/**
 * @var \Core\Router $router
 *
 * 2 route tinh cong khai - PHAI dang ky truoc GET /{slug} (modules/Public) trong thu tu boot de
 * khong bi "nuot" (Router::match() la first-match-wins theo thu tu dang ky - xem core/Router.php).
 * Dam bao qua modules/Public/module.json khai bao "settings" trong dependencies (Settings luon
 * boot truoc Public).
 */
$router->get('/sitemap.xml', [\Modules\Settings\SitemapController::class, 'handle']);
$router->get('/robots.txt', [\Modules\Settings\RobotsController::class, 'handle']);
