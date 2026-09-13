<?php

declare(strict_types=1);

namespace Core\Mailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use RuntimeException;

/**
 * Service d'envoi d'e-mails.
 *
 * Deux drivers disponibles (MAIL_DRIVER dans .env) :
 *   - 'smtp' : envoi via SMTP avec PHPMailer (recommandé — Mailtrap en dev)
 *   - 'mail' : fonction mail() native PHP (nécessite un serveur configuré)
 *
 * Usage :
 *   $mailer->send(
 *       to:      'alice@example.com',
 *       subject: 'Confirmez votre compte',
 *       html:    '<p>Cliquez <a href="...">ici</a></p>',
 *   );
 */
final class Mailer
{
    public function __construct(
        private string $driver,
        private string $host,
        private int    $port,
        private string $username,
        private string $password,
        private string $encryption,
        private string $from,
        private string $fromName,
    ) {}

    /**
     * @throws RuntimeException si l'envoi échoue
     */
    public function send(string $to, string $subject, string $html): void
    {
        match ($this->driver) {
            'smtp'  => $this->sendSmtp($to, $subject, $html),
            default => $this->sendMail($to, $subject, $html),
        };
    }

    // -------------------------------------------------------------------------
    // Drivers
    // -------------------------------------------------------------------------

    private function sendSmtp(string $to, string $subject, string $html): void
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $this->host;
        $mail->Port       = $this->port;
        $mail->SMTPAuth   = $this->username !== '';
        $mail->Username   = $this->username;
        $mail->Password   = $this->password;
        $mail->SMTPSecure = match ($this->encryption) {
            'ssl'   => PHPMailer::ENCRYPTION_SMTPS,
            'tls'   => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        $mail->setFrom($this->from, $this->fromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);

        if (!$mail->send()) {
            throw new RuntimeException("Échec d'envoi d'e-mail : " . $mail->ErrorInfo);
        }
    }

    private function sendMail(string $to, string $subject, string $html): void
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->from}>\r\n";

        if (!mail($to, $subject, $html, $headers)) {
            throw new RuntimeException("Échec d'envoi d'e-mail via mail().");
        }
    }
}
