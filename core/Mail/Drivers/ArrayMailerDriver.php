<?php

declare(strict_types=1);

namespace Core\Mail\Drivers;

use Core\Mail\MailerDriver;

/**
 * Phase 15 (CMS-052). Test double DUY NHAT cho MailerDriver - luu email "da gui" vao mang trong
 * bo nho de PHPUnit assert, KHONG phai driver production (chi 'log'/'smtp' moi la driver that theo
 * dung yeu cau Owner). Dang ky qua Container::singleton(MailerDriver::class, ...) trong setUp() cua
 * test can dispatch qua Controller co goi Mailer.
 */
final class ArrayMailerDriver implements MailerDriver
{
    /** @var list<array{to: string, subject: string, html: string, text: string}> */
    private array $sent = [];

    public function send(string $to, string $subject, string $html, string $text): bool
    {
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'html' => $html, 'text' => $text];

        return true;
    }

    /** @return list<array{to: string, subject: string, html: string, text: string}> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function reset(): void
    {
        $this->sent = [];
    }
}
