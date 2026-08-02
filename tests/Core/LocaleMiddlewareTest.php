<?php

declare(strict_types=1);

namespace Tests\Core;

use Closure;
use Core\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\I18n\Translator;
use Core\Middleware\LocaleDetectionMiddleware;
use Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * Unit test cho Core\Middleware\LocaleDetectionMiddleware (Phase 13, i18n MVP, CMS-050) - goi
 * process() truc tiep voi Request dung tay (khong can Router/ModuleManager - middleware khong phu
 * thuoc route that). Kiem tra dung thu tu uu tien: route param > query string > session > cookie
 * > default.
 */
final class LocaleMiddlewareTest extends TestCase
{
    private Config $config;
    private Session $session;
    private LocaleDetectionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->config = new Config(__DIR__ . '/../Fixtures/config');
        $this->session = new Session($this->config);
        $this->middleware = new LocaleDetectionMiddleware($this->config, $this->session);
    }

    protected function tearDown(): void
    {
        Translator::setGlobalInstance(null);

        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    private function next(): Closure
    {
        return static fn (Request $request): Response => Response::html('ok');
    }

    public function testDetectsLocaleFromRouteParam(): void
    {
        $request = (new Request('GET', '/en/about', 'example.com'))->withRouteParams(['locale' => 'en']);

        $this->middleware->process($request, $this->next());

        self::assertSame('en', Translator::globalInstance()->getLocale());
    }

    public function testDetectsLocaleFromQueryStringWhenNoRouteParam(): void
    {
        $request = new Request('GET', '/about', 'example.com', ['lang' => 'en']);

        $this->middleware->process($request, $this->next());

        self::assertSame('en', Translator::globalInstance()->getLocale());
    }

    public function testDetectsLocaleFromCookieWhenNoRouteOrQuery(): void
    {
        $request = new Request('GET', '/about', 'example.com', [], [], [], [], [], ['locale' => 'en']);

        $this->middleware->process($request, $this->next());

        self::assertSame('en', Translator::globalInstance()->getLocale());
    }

    public function testDetectsLocaleFromSessionWhenStarted(): void
    {
        $this->session->start();
        $this->session->set('locale.current', 'en');

        $request = new Request('GET', '/about', 'example.com');

        $this->middleware->process($request, $this->next());

        self::assertSame('en', Translator::globalInstance()->getLocale());
    }

    public function testDefaultsToViWhenNoSignalPresent(): void
    {
        $request = new Request('GET', '/about', 'example.com');

        $this->middleware->process($request, $this->next());

        self::assertSame('vi', Translator::globalInstance()->getLocale());
    }

    public function testUnsupportedLocaleFallsBackToDefault(): void
    {
        $request = new Request('GET', '/about', 'example.com', ['lang' => 'fr']);

        $this->middleware->process($request, $this->next());

        self::assertSame('vi', Translator::globalInstance()->getLocale());
    }

    public function testRouteParamTakesPriorityOverQueryString(): void
    {
        $request = (new Request('GET', '/vi/about', 'example.com', ['lang' => 'en']))->withRouteParams(['locale' => 'vi']);

        $this->middleware->process($request, $this->next());

        self::assertSame('vi', Translator::globalInstance()->getLocale());
    }

    public function testResponseCarriesDetectedLocaleAsCookie(): void
    {
        $request = new Request('GET', '/about', 'example.com', ['lang' => 'en']);

        $response = $this->middleware->process($request, $this->next());

        self::assertArrayHasKey('locale', $response->getCookies());
        self::assertStringStartsWith('locale=en', $response->getCookies()['locale']);
    }
}
