<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Csrf;
use Core\Session;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    private Session $session;
    private Csrf $csrf;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->session = new Session($config);
        $this->session->start();
        $this->csrf = new Csrf($this->session);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    public function testTokenGeneratesAndPersistsAcrossCalls(): void
    {
        $first = $this->csrf->token();
        $second = $this->csrf->token();

        self::assertSame($first, $second);
    }

    public function testTokenIsSixtyFourCharacterHexString(): void
    {
        $token = $this->csrf->token();

        self::assertSame(64, \strlen($token));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testTokenReturnsExistingValueInsteadOfRegeneratingWhenAlreadySet(): void
    {
        $this->session->set('csrf.token', 'fixed-existing-token');

        self::assertSame('fixed-existing-token', $this->csrf->token());
    }

    public function testVerifyReturnsTrueForMatchingToken(): void
    {
        $token = $this->csrf->token();

        self::assertTrue($this->csrf->verify($token));
    }

    public function testVerifyReturnsFalseForMismatchedToken(): void
    {
        $this->csrf->token();

        self::assertFalse($this->csrf->verify('wrong-token'));
    }

    public function testVerifyReturnsFalseWhenNoTokenInSession(): void
    {
        self::assertFalse($this->csrf->verify('anything'));
    }
}
