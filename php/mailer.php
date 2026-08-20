<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function configureSmtp(PHPMailer $mail): void
{
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->Port = SMTP_PORT;

    // Local catchers like Mailpit take no credentials; real providers do.
    // Only turn on auth/TLS when credentials are actually configured.
    if (SMTP_USER !== '') {
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
}

function sendVerificationCode(string $toEmail, string $code): bool
{
    $mail = new PHPMailer(true);
    try {
        configureSmtp($mail);
        $mail->addAddress($toEmail);

        $mail->Subject = 'Your verification code';
        $mail->Body    = "Your verification code is: {$code}\n\nThis code expires in " . MFA_CODE_TTL_MINUTES . " minutes.";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('Mail send failed: ' . $mail->ErrorInfo);
        return false;
    }
}

function sendMagicLoginLink(string $toEmail, string $link): bool
{
    $mail = new PHPMailer(true);
    try {
        configureSmtp($mail);
        $mail->addAddress($toEmail);

        $mail->Subject = 'Your one-time login link';
        $mail->Body    = "Click the link below to log in:\n\n{$link}\n\n"
            . 'This link can only be used once and expires in ' . LOGIN_LINK_TTL_MINUTES . " minutes.\n\n"
            . "If you did not request this, you can ignore this email.";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('Mail send failed: ' . $mail->ErrorInfo);
        return false;
    }
}
