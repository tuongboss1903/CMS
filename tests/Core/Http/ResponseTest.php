<?php

declare(strict_types=1);

namespace Tests\Core\Http;

use Core\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testJsonHtmlRedirectFactoriesStillWork(): void
    {
        $json = Response::json(['a' => 1]);
        self::assertSame(200, $json->getStatusCode());
        self::assertSame('{"a":1}', $json->getBody());
        self::assertSame('application/json; charset=utf-8', $json->getHeaders()['Content-Type']);

        $html = Response::html('hello');
        self::assertSame('hello', $html->getBody());
        self::assertSame('text/html; charset=utf-8', $html->getHeaders()['Content-Type']);

        $redirect = Response::redirect('/login', 301);
        self::assertSame(301, $redirect->getStatusCode());
        self::assertSame('/login', $redirect->getHeaders()['Location']);
    }

    public function testGetCookiesReturnsEmptyArrayByDefault(): void
    {
        self::assertSame([], (new Response())->getCookies());
    }

    public function testWithHeaderAddsHeaderWithoutMutatingOriginal(): void
    {
        $original = new Response('body', 200, ['X-A' => '1']);

        $withHeader = $original->withHeader('X-B', '2');

        self::assertNotSame($original, $withHeader);
        self::assertSame(['X-A' => '1'], $original->getHeaders());
        self::assertSame(['X-A' => '1', 'X-B' => '2'], $withHeader->getHeaders());
    }

    public function testWithHeadersMergesMultipleHeaders(): void
    {
        $response = (new Response('', 200, ['X-A' => '1']))->withHeaders(['X-B' => '2', 'X-C' => '3']);

        self::assertSame(['X-A' => '1', 'X-B' => '2', 'X-C' => '3'], $response->getHeaders());
    }

    public function testWithHeaderOverwritesExistingHeaderOfSameName(): void
    {
        $response = (new Response('', 200, ['Content-Type' => 'text/html']))
            ->withHeader('Content-Type', 'application/json');

        self::assertSame('application/json', $response->getHeaders()['Content-Type']);
    }

    public function testWithStatusChangesStatusCodeOnlyAndDoesNotMutateOriginal(): void
    {
        $original = new Response('body', 200);

        $withStatus = $original->withStatus(201);

        self::assertSame(200, $original->getStatusCode());
        self::assertSame(201, $withStatus->getStatusCode());
        self::assertSame('body', $withStatus->getBody());
    }

    public function testWithCookieAddsFormattedSetCookieStringWithDefaults(): void
    {
        $response = (new Response())->withCookie('session_id', 'abc123');

        $cookies = $response->getCookies();

        self::assertArrayHasKey('session_id', $cookies);
        self::assertStringContainsString('session_id=abc123', $cookies['session_id']);
        self::assertStringContainsString('Path=/', $cookies['session_id']);
        self::assertStringContainsString('HttpOnly', $cookies['session_id']);
        self::assertStringNotContainsString('Secure', $cookies['session_id']);
    }

    public function testWithCookieRespectsCustomOptions(): void
    {
        $response = (new Response())->withCookie('token', 'xyz', [
            'path' => '/admin',
            'domain' => 'example.com',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Strict',
            'expires' => 1893456000,
        ]);

        $cookie = $response->getCookies()['token'];

        self::assertStringContainsString('token=xyz', $cookie);
        self::assertStringContainsString('Path=/admin', $cookie);
        self::assertStringContainsString('Domain=example.com', $cookie);
        self::assertStringContainsString('Secure', $cookie);
        self::assertStringNotContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('SameSite=Strict', $cookie);
        self::assertStringContainsString('Expires=', $cookie);
    }

    public function testWithCookieOverwritesPreviousCookieOfSameName(): void
    {
        $response = (new Response())->withCookie('a', '1')->withCookie('a', '2');

        self::assertCount(1, $response->getCookies());
        self::assertStringContainsString('a=2', $response->getCookies()['a']);
    }

    public function testMultipleDistinctCookiesCoexist(): void
    {
        $response = (new Response())->withCookie('a', '1')->withCookie('b', '2');

        self::assertCount(2, $response->getCookies());
        self::assertArrayHasKey('a', $response->getCookies());
        self::assertArrayHasKey('b', $response->getCookies());
    }

    public function testWithCacheSetsPublicCacheControlByDefault(): void
    {
        $response = (new Response())->withCache(3600);

        self::assertSame('public, max-age=3600', $response->getHeaders()['Cache-Control']);
    }

    public function testWithCacheSupportsPrivateVisibility(): void
    {
        $response = (new Response())->withCache(60, false);

        self::assertSame('private, max-age=60', $response->getHeaders()['Cache-Control']);
    }

    public function testNoCacheSetsNoStoreCacheControl(): void
    {
        $response = (new Response())->noCache();

        self::assertSame('no-store, no-cache, must-revalidate', $response->getHeaders()['Cache-Control']);
    }

    public function testChainedWithCallsDoNotMutateOriginalInstance(): void
    {
        $original = new Response('body', 200, ['X-A' => '1']);

        $chained = $original->withHeader('X-B', '2')->withStatus(201)->withCookie('c', 'v');

        self::assertSame(200, $original->getStatusCode());
        self::assertSame(['X-A' => '1'], $original->getHeaders());
        self::assertSame([], $original->getCookies());

        self::assertSame(201, $chained->getStatusCode());
        self::assertSame(['X-A' => '1', 'X-B' => '2'], $chained->getHeaders());
        self::assertArrayHasKey('c', $chained->getCookies());
    }
}
