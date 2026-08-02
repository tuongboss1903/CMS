<?php

declare(strict_types=1);

namespace Core\Mail;

/**
 * Phase 15 (Notification & Email System, CMS-052). Toi gian co chu dich - cung mo hinh
 * Core\Cache\CacheDriver: 1 method duy nhat, khong biet gi ve template/View. $from KHONG phai
 * tham so cua send() - moi driver tu giu dia chi From rieng luc khoi tao (LogMailerDriver khong
 * can, SmtpMailerDriver nhan qua constructor) - Mailer facade (goi driver) khong can biet From.
 */
interface MailerDriver
{
    public function send(string $to, string $subject, string $html, string $text): bool;
}
