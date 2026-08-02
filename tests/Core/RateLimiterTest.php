<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\RateLimiter;
use Core\Session;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private Session $session;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->session = new Session($config);
        $this->session->start();
        $this->limiter = new RateLimiter($this->session);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];
    }

    public function testFirstHitCreatesNewEntryWithAttemptsOne(): void
    {
        $result = $this->limiter->hit('login', 3, 60);

        self::assertTrue($result);
        self::assertSame(1, $this->limiter->attempts('login'));
    }

    public function testMultipleHitsIncrementAttempts(): void
    {
        $this->limiter->hit('login', 5, 60);
        $this->limiter->hit('login', 5, 60);
        $this->limiter->hit('login', 5, 60);

        self::assertSame(3, $this->limiter->attempts('login'));
    }

    public function testHitReturnsTrueWhenQuotaExactlyReached(): void
    {
        $this->limiter->hit('login', 3, 60);
        $this->limiter->hit('login', 3, 60);
        $result = $this->limiter->hit('login', 3, 60);

        self::assertTrue($result);
        self::assertSame(3, $this->limiter->attempts('login'));
    }

    public function testHitReturnsFalseWhenQuotaExceeded(): void
    {
        $this->limiter->hit('login', 2, 60);
        $this->limiter->hit('login', 2, 60);
        $result = $this->limiter->hit('login', 2, 60);

        self::assertFalse($result);
        self::assertSame(3, $this->limiter->attempts('login'));
    }

    public function testTooManyAttemptsReturnsFalseWhenUnderLimit(): void
    {
        $this->limiter->hit('login', 5, 60);

        self::assertFalse($this->limiter->tooManyAttempts('login', 5));
    }

    public function testTooManyAttemptsReturnsTrueWhenAtOrOverLimit(): void
    {
        $this->limiter->hit('login', 2, 60);
        $this->limiter->hit('login', 2, 60);

        self::assertTrue($this->limiter->tooManyAttempts('login', 2));
    }

    public function testRemainingReturnsCorrectCount(): void
    {
        $this->limiter->hit('login', 5, 60);
        $this->limiter->hit('login', 5, 60);

        self::assertSame(3, $this->limiter->remaining('login', 5));
    }

    public function testRemainingNeverNegative(): void
    {
        $this->limiter->hit('login', 1, 60);
        $this->limiter->hit('login', 1, 60);
        $this->limiter->hit('login', 1, 60);

        self::assertSame(0, $this->limiter->remaining('login', 1));
    }

    public function testAvailableInReturnsRemainingSeconds(): void
    {
        $this->limiter->hit('login', 5, 60);

        $availableIn = $this->limiter->availableIn('login');

        self::assertGreaterThan(0, $availableIn);
        self::assertLessThanOrEqual(60, $availableIn);
    }

    public function testAvailableInReturnsZeroWhenNoEntry(): void
    {
        self::assertSame(0, $this->limiter->availableIn('never-hit'));
    }

    public function testClearRemovesKey(): void
    {
        $this->limiter->hit('login', 5, 60);
        $this->limiter->clear('login');

        self::assertSame(0, $this->limiter->attempts('login'));
        self::assertSame(0, $this->limiter->availableIn('login'));
    }

    public function testHitResetsAfterExpiry(): void
    {
        // Mo phong entry da het han bang cach ghi truc tiep expires_at trong qua khu.
        $this->session->set('rate_limit.login', ['attempts' => 5, 'expires_at' => \time() - 10]);

        $result = $this->limiter->hit('login', 3, 60);

        self::assertTrue($result);
        self::assertSame(1, $this->limiter->attempts('login'));
    }

    public function testTooManyAttemptsReturnsFalseAfterExpiry(): void
    {
        $this->session->set('rate_limit.login', ['attempts' => 5, 'expires_at' => \time() - 10]);

        self::assertFalse($this->limiter->tooManyAttempts('login', 3));
    }

    public function testIndependentKeysDoNotInterfere(): void
    {
        $this->limiter->hit('login', 5, 60);
        $this->limiter->hit('login', 5, 60);
        $this->limiter->hit('contact', 5, 60);

        self::assertSame(2, $this->limiter->attempts('login'));
        self::assertSame(1, $this->limiter->attempts('contact'));
    }
}
