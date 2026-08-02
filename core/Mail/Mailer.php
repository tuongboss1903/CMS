<?php

declare(strict_types=1);

namespace Core\Mail;

use Core\Logger;
use Core\View;
use Throwable;

/**
 * Phase 15 (Notification & Email System, CMS-052). Facade DUY NHAT cho tang tren goi (Controller/
 * Service KHONG duoc goi MailerDriver truc tiep) - cung mo hinh Core\Cache la facade cho
 * CacheDriver. Render template qua Core\View co san (KHONG Engine template moi) - template email
 * la file HTML doc lap trong themes/default/views/emails/*.php, KHONG extend()/section() (chi 3
 * template, khong can Layout rieng cho email).
 *
 * SILENT-FAIL TUYET DOI (nguyen tac bat buoc Phase 15): moi Throwable (View::render() loi bien,
 * driver nem exception...) deu bi bat noi bo, ghi qua Logger dung chung cua he thong (khac
 * LogMailerDriver - Logger nay la storage/logs/app.log, ghi LOI thuc su chu khong phai noi dung
 * mail), tra false - KHONG BAO GIO lam gian doan HTTP request cua Controller goi no.
 */
final class Mailer
{
    public function __construct(
        private readonly MailerDriver $driver,
        private readonly View $view,
        private readonly Logger $errorLogger,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function send(string $to, string $subject, string $template, array $data = []): bool
    {
        try {
            $html = $this->view->render($template, $data);
            $text = $this->htmlToText($html);

            return $this->driver->send($to, $subject, $html, $text);
        } catch (Throwable $exception) {
            $this->errorLogger->log('error', 'Mailer::send() that bai', [
                'to' => $to,
                'subject' => $subject,
                'template' => $template,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /** Derive ban text tu HTML qua strip_tags() - Owner Decision (khong viet template .txt rieng). */
    private function htmlToText(string $html): string
    {
        return \trim(\html_entity_decode(\strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }
}
