<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private string $logDir;
    private string $logPath;

    protected function setUp(): void
    {
        $this->logDir = \sys_get_temp_dir() . '/cms-logger-test-' . \uniqid('', true);
        $this->logPath = $this->logDir . '/app.log';
    }

    protected function tearDown(): void
    {
        if (\is_file($this->logPath)) {
            @\unlink($this->logPath);
        }

        if (\is_dir($this->logDir)) {
            @\rmdir($this->logDir);
        }
    }

    public function testLogWritesLineToFile(): void
    {
        $logger = new Logger($this->logPath);

        $logger->log('info', 'User login');

        self::assertFileExists($this->logPath);
    }

    public function testLogAppendsMultipleLines(): void
    {
        $logger = new Logger($this->logPath);

        $logger->log('info', 'First entry');
        $logger->log('info', 'Second entry');

        $lines = \array_filter(\explode(PHP_EOL, (string) \file_get_contents($this->logPath)));

        self::assertCount(2, $lines);
    }

    public function testLogIncludesLevel(): void
    {
        $logger = new Logger($this->logPath);

        $logger->log('warning', 'Something happened');

        self::assertStringContainsString('warning:', \file_get_contents($this->logPath));
    }

    public function testLogIncludesMessage(): void
    {
        $logger = new Logger($this->logPath);

        $logger->log('info', 'User login');

        self::assertStringContainsString('User login', \file_get_contents($this->logPath));
    }

    public function testLogIncludesJsonContext(): void
    {
        $logger = new Logger($this->logPath);

        $logger->log('info', 'User login', ['id' => 1, 'email' => 'a@example.com']);

        self::assertStringContainsString('{"id":1,"email":"a@example.com"}', \file_get_contents($this->logPath));
    }

    public function testLogOmitsContextWhenEmpty(): void
    {
        $logger = new Logger($this->logPath);

        $logger->log('info', 'User login');

        $content = (string) \file_get_contents($this->logPath);

        self::assertStringNotContainsString('{', $content);
        self::assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] info: User login\r?\n?$/',
            $content
        );
    }

    public function testCreatesDirectoryAutomatically(): void
    {
        self::assertDirectoryDoesNotExist($this->logDir);

        $logger = new Logger($this->logPath);
        $logger->log('info', 'User login');

        self::assertDirectoryExists($this->logDir);
    }

    public function testAcceptsCustomLevelWithoutValidation(): void
    {
        $logger = new Logger($this->logPath);

        $logger->log('custom_level', 'Some message');

        self::assertStringContainsString('custom_level:', \file_get_contents($this->logPath));
    }
}
