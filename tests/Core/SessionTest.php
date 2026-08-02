<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Session;
use Core\SessionException;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->session = new Session($config);
    }

    protected function tearDown(): void
    {
        // Don sach tuyet doi de khong lam hong test sau, ke ca khi 1 assertion fail giua chung.
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    public function testDoesNotStartSessionInConstructor(): void
    {
        self::assertFalse($this->session->isStarted());
    }

    public function testStartActuallyStartsPhpSession(): void
    {
        $this->session->start();

        self::assertTrue($this->session->isStarted());
        self::assertSame(PHP_SESSION_ACTIVE, \session_status());
    }

    public function testStartAppliesCookieParamsFromConfig(): void
    {
        $this->session->start();

        $params = \session_get_cookie_params();

        self::assertSame(30 * 60, $params['lifetime']);
        self::assertFalse($params['secure']);
        self::assertTrue($params['httponly']);
        self::assertSame('test_session', \session_name());
    }

    public function testSetAndGetSupportDotNotationNamespace(): void
    {
        $this->session->start();

        $this->session->set('auth.user_id', 5);
        $this->session->set('auth.roles', ['admin']);

        self::assertSame(5, $this->session->get('auth.user_id'));
        self::assertSame(['admin'], $this->session->get('auth.roles'));
        self::assertNull($this->session->get('auth.permissions'));
    }

    public function testHasReturnsTrueFalseCorrectly(): void
    {
        $this->session->start();
        $this->session->set('csrf.token', 'abc');

        self::assertTrue($this->session->has('csrf.token'));
        self::assertFalse($this->session->has('csrf.missing'));
    }

    public function testRemoveDeletesOnlyTargetedNestedKey(): void
    {
        $this->session->start();
        $this->session->set('auth.user_id', 5);
        $this->session->set('auth.roles', ['admin']);

        $this->session->remove('auth.user_id');

        self::assertNull($this->session->get('auth.user_id'));
        self::assertSame(['admin'], $this->session->get('auth.roles'));
    }

    public function testFlashDataIsReadableExactlyOneRequestAfterSet(): void
    {
        // Request A: dat flash, "ket thuc" request bang session_write_close().
        $this->session->start();
        $this->session->flash('success', 'Da luu');
        $this->session->flash('error', 'Co canh bao');
        \session_write_close();

        // Request B: flash cua Request A phai doc duoc (Multiple Flash - ca 2 key).
        $this->session->start();
        self::assertSame('Da luu', $this->session->getFlash('success'));
        self::assertSame('Co canh bao', $this->session->getFlash('error'));
        \session_write_close();

        // Request C: flash khong con ton tai nua, du Request B co doc hay khong.
        $this->session->start();
        self::assertNull($this->session->getFlash('success'));
        self::assertNull($this->session->getFlash('error'));
    }

    public function testRegenerateChangesSessionId(): void
    {
        $this->session->start();
        $oldId = \session_id();

        $this->session->regenerate();

        self::assertNotSame($oldId, \session_id());
    }

    public function testDestroyClearsDataAndEndsSession(): void
    {
        $this->session->start();
        $this->session->set('auth.user_id', 5);

        $this->session->destroy();

        self::assertFalse($this->session->isStarted());
        self::assertSame([], $_SESSION);
    }

    public function testGetBeforeStartThrowsSessionException(): void
    {
        $this->expectException(SessionException::class);

        $this->session->get('auth.user_id');
    }

    public function testSetBeforeStartThrowsSessionException(): void
    {
        $this->expectException(SessionException::class);

        $this->session->set('auth.user_id', 1);
    }

    public function testFlashBeforeStartThrowsSessionException(): void
    {
        $this->expectException(SessionException::class);

        $this->session->flash('success', 'x');
    }
}
