<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Thin SMTP wrapper around PHPMailer. Every caller goes through send() —
 * if MAIL_HOST isn't configured yet (e.g. before the admin has set up SMTP
 * credentials), this logs what would have been sent and returns false
 * instead of throwing, so a missing mail config never breaks the job
 * pipeline or any other caller.
 */
final class Mailer
{
    public function isConfigured(): bool
    {
        return env('MAIL_HOST', '') !== '';
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        if (!$this->isConfigured()) {
            Logger::info('Mailer: SMTP not configured, skipping send', [
                'to' => $toEmail,
                'subject' => $subject,
            ]);

            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST', '');
            $mail->Port = (int) env('MAIL_PORT', '587');
            $mail->SMTPAuth = true;
            $mail->Username = env('MAIL_USERNAME', '');
            $mail->Password = env('MAIL_PASSWORD', '');

            $encryption = env('MAIL_ENCRYPTION', 'tls');
            $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom(env('MAIL_FROM_ADDRESS', 'noreply@example.com'), env('MAIL_FROM_NAME', 'AI Karaoke Maker'));
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

            $mail->send();

            Logger::info('Mailer: sent', ['to' => $toEmail, 'subject' => $subject]);

            return true;
        } catch (PHPMailerException | Throwable $e) {
            Logger::error('Mailer: send failed', ['to' => $toEmail, 'subject' => $subject, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
