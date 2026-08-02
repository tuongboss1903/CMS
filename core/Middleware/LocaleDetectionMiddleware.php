<?php

declare(strict_types=1);

namespace Core\Middleware;

use Closure;
use Core\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\I18n\Translator;
use Core\Session;

/**
 * Phase 13 (i18n MVP, CMS-050). Thu tu uu tien xac dinh locale: (1) Route param "locale" (chi co
 * gia tri khi route duoc dang ky voi prefix "/{locale}/..." - CHUA lam o buoc nay, xem canh bao
 * kien truc ben duoi), (2) Query string "?lang=", (3) Session "locale.current" (dung dung namespace
 * da chuan hoa san trong docblock core/Session.php), (4) Cookie "locale", (5) mac dinh
 * config('app.locale', 'vi').
 *
 * *** CANH BAO KIEN TRUC (da xac minh qua core/Router.php that) ***
 * Router::dispatch() MATCH ROUTE TRUOC (dung URI goc), roi moi chay Middleware Pipeline cua dung
 * route da khop - middleware KHONG co co hoi "cat prefix /en roi route lai" vi luc middleware nay
 * chay, Router da chon xong Route tu URI goc. Vi vay yeu cau "bat URL prefix /{locale}/..." KHONG
 * THE hien thuc hoa chi bang middleware don thuan - can dang ky THEM 1 bo route co prefix "/{locale}"
 * (vd $router->group(['prefix' => '/{locale}'], fn ($r) => ...)) song song route khong prefix hien
 * co, de "{locale}" duoc Router bat nhu 1 route param binh thuong roi doc qua routeParam('locale')
 * o day. Day la cong viec cua buoc noi day Route (chua nam trong pham vi Buoc 1/2 hien tai) - KHONG
 * tu y sua core/Router.php (thay doi thu tu match/middleware la thay doi hanh vi Core dang on dinh,
 * ngoai pham vi duoc duyet).
 *
 * Sau khi xac dinh locale: set vao Translator::setGlobalInstance() (de __() dung dung locale trong
 * View), ghi lai Session + Cookie de "dinh" lua chon cho cac request sau (vd nguoi dung chi chon
 * qua ?lang= 1 lan).
 */
final class LocaleDetectionMiddleware implements MiddlewareInterface
{
    private const SUPPORTED_LOCALES = ['vi', 'en'];
    private const COOKIE_NAME = 'locale';
    private const SESSION_KEY = 'locale.current';

    public function __construct(
        private readonly Config $config,
        private readonly Session $session,
    ) {
    }

    public function process(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        $translator = new Translator(
            \dirname(__DIR__, 2) . '/resources/lang',
            $locale,
            'vi'
        );
        Translator::setGlobalInstance($translator);

        if ($this->session->isStarted()) {
            $this->session->set(self::SESSION_KEY, $locale);
        }

        $response = $next($request);

        return $response->withCookie(self::COOKIE_NAME, $locale, ['path' => '/']);
    }

    private function resolveLocale(Request $request): string
    {
        $fromRoute = $request->routeParam('locale');

        if ($fromRoute !== null && $this->isSupported($fromRoute)) {
            return $fromRoute;
        }

        $fromQuery = $request->query('lang');

        if (\is_string($fromQuery) && $this->isSupported($fromQuery)) {
            return $fromQuery;
        }

        if ($this->session->isStarted()) {
            $fromSession = $this->session->get(self::SESSION_KEY);

            if (\is_string($fromSession) && $this->isSupported($fromSession)) {
                return $fromSession;
            }
        }

        $fromCookie = $request->cookie(self::COOKIE_NAME);

        if (\is_string($fromCookie) && $this->isSupported($fromCookie)) {
            return $fromCookie;
        }

        $default = (string) $this->config->get('app.locale', 'vi');

        return $this->isSupported($default) ? $default : 'vi';
    }

    private function isSupported(string $locale): bool
    {
        return \in_array($locale, self::SUPPORTED_LOCALES, true);
    }
}
