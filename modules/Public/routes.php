<?php

declare(strict_types=1);

use Core\Middleware\AnalyticsTrackingMiddleware;
use Core\Middleware\CsrfMiddleware;
use Core\Middleware\LocaleDetectionMiddleware;

/** @var \Core\Router $router */

/**
 * Phase 13 (i18n, CMS-050): 2 route "/" va "/{slug}" van giu nguyen KHONG prefix (fallback locale
 * mac dinh qua Query String/Session/Cookie/config - LocaleDetectionMiddleware gan vao ca 2 de doc
 * duoc cac nguon nay ngay ca khi khong co prefix URL). Them 1 nhom route "/{locale}/..." rieng -
 * Router::dispatch() match Route TRUOC roi moi chay Middleware (da xac minh o core/Router.php),
 * nen "{locale}" phai la 1 route param dang ky that, KHONG the "cat prefix" bang middleware don
 * thuan (xem canh bao kien truc trong core/Middleware/LocaleDetectionMiddleware.php).
 *
 * *** RUI RO KHOP ROUTE 2-SEGMENT (mo rong Technical Debt CMS-044 da chap nhan cho 1-segment) ***
 * "/{locale}/{slug}" ve mat pattern co the khop CA "/admin/pages", "/admin/login"... (2 segment,
 * "admin" bi hieu nham la {locale}). Router::match() tra ve route dang ky TRUOC tien trong danh
 * sach (core/Router.php::match() - vong lap tra ve match dau tien). Module "admin" hien dang duoc
 * ModuleManager nap TRUOC "public" (thu tu topo tu ModuleManager::discover() - "Admin" dung truoc
 * "Public" theo bang chu cai, va "admin" khong phu thuoc "public" nen khong bi day lui), nen route
 * literal /admin/... luon duoc dang ky va khop TRUOC /{locale}/{slug} - AN TOAN trong cau hinh
 * hien tai, nhung day la thu tu NGAU NHIEN (phu thuoc thu tu thu muc modules/), khong phai bat
 * bien duoc he thong dam bao - can luu y neu co thay doi cau truc module sau nay.
 */
$router->get('/', [\Modules\Public\HomeController::class, 'handle'], [LocaleDetectionMiddleware::class, AnalyticsTrackingMiddleware::class]);
$router->get('/search', [\Modules\Public\SearchController::class, 'handle']);
$router->get('/{slug}', [\Modules\Public\PublicPageController::class, 'handle'], [LocaleDetectionMiddleware::class, AnalyticsTrackingMiddleware::class]);

$router->group(['prefix' => '/{locale}', 'middleware' => [LocaleDetectionMiddleware::class]], function (\Core\Router $router): void {
    $router->get('', [\Modules\Public\HomeController::class, 'handle'], [AnalyticsTrackingMiddleware::class]);
    $router->get('/{slug}', [\Modules\Public\PublicPageController::class, 'handle'], [AnalyticsTrackingMiddleware::class]);
});

/**
 * Phase 14 (Comment/Review System, CMS-051). Route POST dau tien cua Public module - can
 * CsrfMiddleware (truoc gio Public chi co GET). Khong locale-aware (luon redirect ve "/{slug}"
 * khong prefix, bat ke khach dang o "/en/{slug}" hay "/{slug}") - MVP scope da khoa o Architecture
 * Analysis, khong lam trong Phase nay.
 */
$router->post('/{slug}/comments', [\Modules\Public\CommentSubmitController::class, 'handle'], [CsrfMiddleware::class]);
