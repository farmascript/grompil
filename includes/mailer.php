<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Sun 2026-10-25 21:36:11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Sends system messages through the configured mail service.
 *
 */

declare(strict_types=1);

require_once __DIR__ . '/database_config.php';

/**
 * Sends an email using the SMTP settings in the selected private credentials file.
 *
 * @throws RuntimeException when mail is not configured or cannot be sent.
 *
 * @param string $recipient The recipient email address.
 * @param string $subject The email subject.
 * @param string $htmlBody The HTML email body.
 * @param string $textBody The plain-text email body.
 * @return void
 */
function grompil_send_mail(string $recipient, string $subject, string $htmlBody, string $textBody = ''): void
{
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        throw new RuntimeException('The mail library is missing. Run composer install.');
    }
    require_once $autoloadPath;

    if (!is_file(grompil_credentials_path())) {
        throw new RuntimeException('The private credentials file is missing.');
    }

    $credentials = grompil_credentials_config();
    $config = is_array($credentials['mail'] ?? null)
        ? $credentials['mail']
        : [];
    foreach (['host', 'port', 'username', 'password', 'from_email', 'from_name'] as $key) {
        if (!isset($config[$key]) || trim((string) $config[$key]) === '') {
            throw new RuntimeException('SMTP configuration is incomplete: ' . $key . '.');
        }
    }

    $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
    $smtpEncryption = match ($encryption) {
        'tls', 'starttls' => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
        'ssl', 'smtps' => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
        'none' => '',
        default => throw new RuntimeException('Invalid SMTP encryption setting.'),
    };

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string) $config['host'];
    $mail->Port = (int) $config['port'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $config['username'];
    $mail->Password = (string) $config['password'];
    $mail->SMTPSecure = $smtpEncryption;
    $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
    $mail->setFrom((string) $config['from_email'], (string) $config['from_name']);
    $mail->addAddress($recipient);
    if (!empty($config['reply_to'])) {
        $mail->addReplyTo((string) $config['reply_to']);
    }
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));
    $mail->send();
}

