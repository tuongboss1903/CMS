<?php

declare(strict_types=1);

namespace Core\Mail\Drivers;

use Core\Mail\MailerDriver;
use RuntimeException;
use Throwable;

/**
 * Phase 15 (CMS-052). Client SMTP toi gian TU VIET (khong PHPMailer/Symfony Mailer) - dung tinh
 * than Zero-dependency da giu xuyen suot du an (Router/Validator/Cache deu tu viet). fsockopen() +
 * doc/ghi lenh van ban thuan theo RFC 5321 rut gon: EHLO -> STARTTLS (neu encryption='tls', qua
 * stream_socket_enable_crypto() co san trong PHP core, khong extension ngoai) -> AUTH LOGIN (neu
 * co username/password) -> MAIL FROM -> RCPT TO -> DATA. KHONG ho tro attachment - MVP
 * multipart/alternative (text + html) don gian.
 *
 * KHONG BAO GIO throw ra ngoai send() - moi loi (khong ket noi duoc, SMTP tra ma loi...) deu tra
 * false, dung hop dong MailerDriver va nguyen tac Silent-fail cua Phase 15.
 */
final class SmtpMailerDriver implements MailerDriver
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly string $encryption,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public function send(string $to, string $subject, string $html, string $text): bool
    {
        $target = $this->encryption === 'ssl' ? 'ssl://' . $this->host : $this->host;
        $socket = @\fsockopen($target, $this->port, $errno, $errstr, $this->timeoutSeconds);

        if ($socket === false) {
            return false;
        }

        \stream_set_timeout($socket, $this->timeoutSeconds);

        try {
            $this->expect($socket, '220');
            $this->command($socket, 'EHLO localhost', '250');

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', '220');

                if (!@\stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return false;
                }

                $this->command($socket, 'EHLO localhost', '250');
            }

            if ($this->username !== null && $this->password !== null) {
                $this->command($socket, 'AUTH LOGIN', '334');
                $this->command($socket, \base64_encode($this->username), '334');
                $this->command($socket, \base64_encode($this->password), '235');
            }

            $this->command($socket, "MAIL FROM:<{$this->fromAddress}>", '250');
            $this->command($socket, "RCPT TO:<{$to}>", '250');
            $this->command($socket, 'DATA', '354');
            $this->command($socket, $this->buildMessage($to, $subject, $html, $text), '250');
            $this->command($socket, 'QUIT', '221');

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            \fclose($socket);
        }
    }

    private function buildMessage(string $to, string $subject, string $html, string $text): string
    {
        $boundary = \bin2hex(\random_bytes(16));

        $headers = [
            "Subject: {$subject}",
            "From: {$this->fromName} <{$this->fromAddress}>",
            "To: {$to}",
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];

        return \implode("\r\n", $headers) . "\r\n\r\n"
            . "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n"
            . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n"
            . "--{$boundary}--\r\n.";
    }

    /** @param resource $socket */
    private function command($socket, string $command, string $expectedCode): void
    {
        \fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expectedCode);
    }

    /** @param resource $socket */
    private function expect($socket, string $expectedCode): void
    {
        $response = '';

        while (($line = \fgets($socket, 515)) !== false) {
            $response .= $line;

            if (!isset($line[3]) || $line[3] === ' ') {
                break;
            }
        }

        if ($response === '' || !\str_starts_with($response, $expectedCode)) {
            throw new RuntimeException("SMTP: ky vong ma {$expectedCode}, nhan duoc: {$response}");
        }
    }
}
