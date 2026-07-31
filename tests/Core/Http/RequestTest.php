<?php

declare(strict_types=1);

namespace Tests\Core\Http;

use Core\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testWithRouteParamsReturnsNewInstanceWithoutMutatingOriginal(): void
    {
        $original = new Request('GET', '/users/5', 'example.com');

        $withParams = $original->withRouteParams(['id' => '5']);

        self::assertNotSame($original, $withParams);
        self::assertNull($original->routeParam('id'));
        self::assertSame('5', $withParams->routeParam('id'));
    }

    public function testQueryAndInputReadFromConstructorData(): void
    {
        $request = new Request('GET', '/search', 'example.com', query: ['q' => 'cms'], body: ['name' => 'A']);

        self::assertSame('cms', $request->query('q'));
        self::assertSame('A', $request->input('name'));
        self::assertNull($request->query('missing'));
    }

    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $request = new Request('GET', '/', 'example.com', headers: ['X-CUSTOM' => 'value']);

        self::assertSame('value', $request->header('x-custom'));
        self::assertSame('value', $request->header('X-Custom'));
    }

    public function testMethodUriPathAreAliasesOfExistingGetters(): void
    {
        $request = new Request('POST', '/users', 'example.com');

        self::assertSame($request->getMethod(), $request->method());
        self::assertSame($request->getUri(), $request->uri());
        self::assertSame($request->getUri(), $request->path());
    }

    public function testAllMergesQueryAndBodyWithBodyTakingPrecedence(): void
    {
        $request = new Request(
            'POST',
            '/',
            'example.com',
            query: ['a' => '1', 'shared' => 'from-query'],
            body: ['b' => '2', 'shared' => 'from-body']
        );

        self::assertSame(['a' => '1', 'shared' => 'from-body', 'b' => '2'], $request->all());
    }

    public function testHasChecksPresenceAcrossQueryAndBody(): void
    {
        $request = new Request('GET', '/', 'example.com', query: ['a' => '1'], body: ['b' => null]);

        self::assertTrue($request->has('a'));
        self::assertTrue($request->has('b'));
        self::assertFalse($request->has('missing'));
    }

    public function testFilledRequiresPresentAndNonEmptyValue(): void
    {
        $request = new Request(
            'GET',
            '/',
            'example.com',
            query: ['name' => 'Alice', 'empty' => '', 'zero' => 0]
        );

        self::assertTrue($request->filled('name'));
        self::assertFalse($request->filled('empty'));
        self::assertTrue($request->filled('zero'));
        self::assertFalse($request->filled('missing'));
    }

    public function testCookieReturnsValueOrDefault(): void
    {
        $request = new Request('GET', '/', 'example.com', cookies: ['session_id' => 'abc123']);

        self::assertSame('abc123', $request->cookie('session_id'));
        self::assertNull($request->cookie('missing'));
        self::assertSame('fallback', $request->cookie('missing', 'fallback'));
    }

    public function testFileReturnsRawUploadedFileEntryOrNull(): void
    {
        $fileEntry = ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 3];
        $request = new Request('POST', '/', 'example.com', files: ['avatar' => $fileEntry]);

        self::assertSame($fileEntry, $request->file('avatar'));
        self::assertNull($request->file('missing'));
    }

    public function testIpReadsRemoteAddrOnlyNotForwardedHeaders(): void
    {
        $request = new Request(
            'GET',
            '/',
            'example.com',
            headers: ['X-FORWARDED-FOR' => '9.9.9.9'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        self::assertSame('127.0.0.1', $request->ip());
    }

    public function testUserAgentReadsHeader(): void
    {
        $request = new Request('GET', '/', 'example.com', headers: ['USER-AGENT' => 'PHPUnit-Agent']);

        self::assertSame('PHPUnit-Agent', $request->userAgent());
        self::assertNull((new Request('GET', '/', 'example.com'))->userAgent());
    }

    public function testIsMethodComparesCaseInsensitively(): void
    {
        $request = new Request('POST', '/', 'example.com');

        self::assertTrue($request->isMethod('post'));
        self::assertTrue($request->isMethod('POST'));
        self::assertFalse($request->isMethod('GET'));
    }

    public function testAjaxDetectsXRequestedWithHeader(): void
    {
        $ajaxRequest = new Request('GET', '/', 'example.com', headers: ['X-REQUESTED-WITH' => 'XMLHttpRequest']);
        $normalRequest = new Request('GET', '/', 'example.com');

        self::assertTrue($ajaxRequest->ajax());
        self::assertFalse($normalRequest->ajax());
    }

    public function testJsonDetectsJsonContentType(): void
    {
        $jsonRequest = new Request('POST', '/', 'example.com', headers: ['CONTENT-TYPE' => 'application/json']);
        $formRequest = new Request('POST', '/', 'example.com', headers: ['CONTENT-TYPE' => 'application/x-www-form-urlencoded']);

        self::assertTrue($jsonRequest->json());
        self::assertFalse($formRequest->json());
    }

    public function testWithRouteParamsPreservesFilesCookiesAndServer(): void
    {
        $original = new Request(
            'GET',
            '/',
            'example.com',
            files: ['f' => ['name' => 'x']],
            cookies: ['c' => 'v'],
            server: ['REMOTE_ADDR' => '1.2.3.4']
        );

        $withParams = $original->withRouteParams(['id' => '1']);

        self::assertSame(['name' => 'x'], $withParams->file('f'));
        self::assertSame('v', $withParams->cookie('c'));
        self::assertSame('1.2.3.4', $withParams->ip());
    }
}
