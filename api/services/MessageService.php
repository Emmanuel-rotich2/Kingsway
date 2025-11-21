<?php
namespace App\API\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MessageService
{
    private $db;
    public function __construct($db)
    {
        $this->db = $db;
    }

    // Render email using Bootstrap template
    public function renderEmail($subject, $body, $signature, $footer, $media = '', $schoolDetails = [])
    {
        $template = file_get_contents(__DIR__ . '/../modules/communications/templates/email_bootstrap_template.html');
        $replacements = [
            '{{subject}}' => $subject,
            '{{body}}' => $body,
            '{{signature}}' => $signature,
            '{{footer}}' => $footer,
            '{{media}}' => $media,
            '{{school_address}}' => $schoolDetails['address'] ?? '',
            '{{school_phone}}' => $schoolDetails['phone'] ?? '',
            '{{school_email}}' => $schoolDetails['email'] ?? ''
        ];
        return strtr($template, $replacements);
    }

    // Send email (single or mass)
    public function sendEmail($recipients, $subject, $htmlBody, $attachments = [])
    {
        // Assumes config.php is loaded at application entry point and constants are available
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = 'tls';
            $mail->Port = SMTP_PORT;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            if (is_array($recipients)) {
                foreach ($recipients as $email => $name) {
                    $mail->addAddress($email, $name);
                }
            } else {
                $mail->addAddress($recipients);
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            foreach ($attachments as $file) {
                $mail->addAttachment($file);
            }
            $mail->send();
            return true;
        } catch (Exception $e) {
            // Log error
            return false;
        }
    }

    // Mass sending logic (e.g. for announcements)
    public function sendMassEmail($recipientList, $subject, $htmlBody, $attachments = [])
    {
        foreach ($recipientList as $recipients) {
            $this->sendEmail($recipients, $subject, $htmlBody, $attachments);
        }
    }
}
