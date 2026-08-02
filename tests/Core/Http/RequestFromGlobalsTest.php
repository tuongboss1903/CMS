<?php

declare(strict_types=1);

namespace Tests\Core\Http;

use Core\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Http\PhpInputStreamStub;

/**
 * Regression test cho gap phat hien o Architecture Review sau CMS-006: Request::fromGlobals()
 * truoc day chi doc $_POST, bo sot JSON body (chuan bat buoc cho /api/v1/*, xem api-document.md /
 * 02-module-auth.md POST /api/v1/auth/login) vi PHP khong tu dien $_POST khi Content-Type la
 * application/json.
 */
final class RequestFromGlobalsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer;

    /** @var array<string, mixed> */
    private array $originalPost;

    /** @var array<string, mixed> */
    private array $originalFiles;

    /** @var array<string, mixed> */
    private array $originalCookie;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalPost = $_POST;
        $this->originalFiles = $_FILES;
        $this->originalCookie = $_COOKIE;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_POST = $this->originalPost;
        $_FILES = $this->originalFiles;
        $_COOKIE = $this->originalCookie;
    }

    public function testFromGlobalsParsesJsonBodyWhenContentTypeIsJson(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/auth/login',
            'HTTP_HOST' => 'example.com',
            'CONTENT_TYPE' => 'application/json',
        ];
        $_POST = [];

        $this->withPhpInput('{"email":"a@example.com","password":"secret"}', function (): void {
            $request = Request::fromGlobals();

            self::assertSame('a@example.com', $request->input('email'));
            self::assertSame('secret', $request->input('password'));
            self::assertSame('application/json', $request->header('Content-Type'));
        });
    }

    public function testFromGlobalsFallsBackToEmptyArrayWhenJsonBodyIsInvalid(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/auth/login',
            'HTTP_HOST' => 'example.com',
            'CONTENT_TYPE' => 'application/json',
        ];
        $_POST = [];

        $this->withPhpInput('not-valid-json{{{', function (): void {
            $request = Request::fromGlobals();

            self::assertNull($request->input('email'));
        });
    }

    public function testFromGlobalsUsesPostArrayForFormUrlEncodedContentType(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/admin/login',
            'HTTP_HOST' => 'example.com',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ];
        $_POST = ['email' => 'a@example.com'];

        $request = Request::fromGlobals();

        self::assertSame('a@example.com', $request->input('email'));
    }

    public function testFromGlobalsExtractsFilesCookiesAndServer(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/upload',
            'HTTP_HOST' => 'example.com',
            'REMOTE_ADDR' => '203.0.113.10',
        ];
        $_POST = [];
        $_FILES = ['avatar' => ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 10]];
        $_COOKIE = ['session_id' => 'cookie-value'];

        $request = Request::fromGlobals();

        self::assertSame($_FILES['avatar'], $request->file('avatar'));
        self::assertSame('cookie-value', $request->cookie('session_id'));
        self::assertSame('203.0.113.10', $request->ip());
    }

    private function withPhpInput(string $content, callable $callback): void
    {
        PhpInputStreamStub::$content = $content;

        \stream_wrapper_unregister('php');
        \stream_wrapper_register('php', PhpInputStreamStub::class);

        try {
            $callback();
        } finally {
            \stream_wrapper_restore('php');
        }
    }
}
