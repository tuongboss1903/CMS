<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Logger;
use Core\Mail\Drivers\ArrayMailerDriver;
use Core\Mail\Drivers\LogMailerDriver;
use Core\Mail\Drivers\SmtpMailerDriver;
use Core\Mail\Mailer;
use Core\Mail\MailerDriver;
use Core\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit test cho Phase 15 (Notification & Email System, CMS-052) - Core\Mail\* (khong Router/DB,
 * chi View that de render template email that). Dung themes/default/views/emails/* that.
 */
final class MailerTest extends TestCase
{
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = \sys_get_temp_dir() . '/cms-test-mail-' . \uniqid('', true) . '.log';
    }

    protected function tearDown(): void
    {
        if (\is_file($this->logPath)) {
            @\unlink($this->logPath);
        }
    }

    private function realView(): View
    {
        return new View(self::REAL_THEMES_PATH, 'default', 'default');
    }

    public function testLogMailerDriverWritesToLogFile(): void
    {
        $driver = new LogMailerDriver(new Logger($this->logPath));

        $result = $driver->send('user@example.com', 'Tieu de test', '<p>html</p>', 'text thuan');

        self::assertTrue($result);
        self::assertFileExists($this->logPath);
        $content = (string) \file_get_contents($this->logPath);
        self::assertStringContainsString('user@example.com', $content);
        self::assertStringContainsString('Tieu de test', $content);
    }

    public function testArrayMailerDriverCapturesSentEmails(): void
    {
        $driver = new ArrayMailerDriver();

        $driver->send('a@example.com', 'Subject A', '<p>A</p>', 'A');
        $driver->send('b@example.com', 'Subject B', '<p>B</p>', 'B');

        $sent = $driver->sent();
        self::assertCount(2, $sent);
        self::assertSame('a@example.com', $sent[0]['to']);
        self::assertSame('b@example.com', $sent[1]['to']);
    }

    public function testArrayMailerDriverResetClearsSentEmails(): void
    {
        $driver = new ArrayMailerDriver();
        $driver->send('a@example.com', 'Subject', '<p>A</p>', 'A');

        $driver->reset();

        self::assertSame([], $driver->sent());
    }

    public function testMailerSendRendersTemplateAndCallsDriver(): void
    {
        $arrayDriver = new ArrayMailerDriver();
        $mailer = new Mailer($arrayDriver, $this->realView(), new Logger($this->logPath));

        $result = $mailer->send('guest@example.com', 'Binh luan da duoc duyet', 'emails.comment_approved', [
            'guest_name' => 'An',
            'page_title' => 'Trang chu',
            'page_url' => '/trang-chu',
        ]);

        self::assertTrue($result);
        $sent = $arrayDriver->sent();
        self::assertCount(1, $sent);
        self::assertStringContainsString('An', $sent[0]['html']);
        self::assertStringContainsString('Trang chu', $sent[0]['html']);
    }

    public function testMailerDerivesTextFromHtmlViaStripTags(): void
    {
        $arrayDriver = new ArrayMailerDriver();
        $mailer = new Mailer($arrayDriver, $this->realView(), new Logger($this->logPath));

        $mailer->send('guest@example.com', 'Subject', 'emails.comment_rejected', [
            'guest_name' => 'An',
            'page_title' => 'Trang chu',
        ]);

        $text = $arrayDriver->sent()[0]['text'];
        self::assertStringNotContainsString('<', $text);
        self::assertStringContainsString('An', $text);
    }

    public function testMailerSendReturnsFalseWhenTemplateMissing(): void
    {
        $arrayDriver = new ArrayMailerDriver();
        $mailer = new Mailer($arrayDriver, $this->realView(), new Logger($this->logPath));

        $result = $mailer->send('guest@example.com', 'Subject', 'emails.khong_ton_tai', []);

        self::assertFalse($result);
        self::assertSame([], $arrayDriver->sent());
    }

    public function testMailerSendReturnsFalseWhenDriverThrows(): void
    {
        $throwingDriver = new class () implements MailerDriver {
            public function send(string $to, string $subject, string $html, string $text): bool
            {
                throw new RuntimeException('Driver loi gia lap');
            }
        };

        $mailer = new Mailer($throwingDriver, $this->realView(), new Logger($this->logPath));

        $result = $mailer->send('guest@example.com', 'Subject', 'emails.comment_rejected', [
            'guest_name' => 'An',
            'page_title' => 'Trang chu',
        ]);

        self::assertFalse($result);
        self::assertFileExists($this->logPath);
    }

    public function testSmtpMailerDriverReturnsFalseWhenCannotConnect(): void
    {
        $driver = new SmtpMailerDriver(
            '198.51.100.1',
            2525,
            null,
            null,
            'tls',
            'no-reply@example.com',
            'CMS',
            1
        );

        $result = $driver->send('guest@example.com', 'Subject', '<p>html</p>', 'text');

        self::assertFalse($result);
    }
}
