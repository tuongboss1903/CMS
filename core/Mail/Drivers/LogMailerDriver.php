<?php

declare(strict_types=1);

namespace Core\Mail\Drivers;

use Core\Logger;
use Core\Mail\MailerDriver;

/**
 * Phase 15 (CMS-052). Ghi noi dung mail vao storage/logs/mail.log qua Core\Logger co san (KHONG
 * gui that) - dung cho local/test, driver mac dinh ('log') khi khong cau hinh MAIL_DRIVER. Tai su
 * dung nguyen Logger::log() - Logger da khong throw/khong biet HTTP tu truoc (CMS-024).
 */
final class LogMailerDriver implements MailerDriver
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function send(string $to, string $subject, string $html, string $text): bool
    {
        $this->logger->log('info', 'Mail (log driver - khong gui that)', [
            'to' => $to,
            'subject' => $subject,
            'text' => $text,
        ]);

        return true;
    }
}
