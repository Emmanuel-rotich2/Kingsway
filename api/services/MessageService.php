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

    /**
     * Render formal business letter email with proper block format
     * Supports structured body sections for professional formatting
     */
    public function renderFormalEmail($subject, $body, $signature, $footer, $media = '', $schoolDetails = [])
    {
        // If body is array, format as formal letter sections
        $formattedBody = $body;
        if (is_array($body)) {
            $formattedBody = $this->formatFormalLetterBody($body);
        }

        // Merge with default school details from config
        $defaultDetails = [
            'address' => defined('SCHOOL_ADDRESS') ? SCHOOL_ADDRESS : '',
            'phone' => defined('SCHOOL_PHONE') ? SCHOOL_PHONE : '',
            'email' => defined('SCHOOL_EMAIL') ? SCHOOL_EMAIL : 'info@kingsway.ac.ke',
            'principal_name' => defined('SCHOOL_PRINCIPAL_NAME') ? SCHOOL_PRINCIPAL_NAME : 'Mr, Bett Junior',
            'principal_title' => defined('SCHOOL_PRINCIPAL_TITLE') ? SCHOOL_PRINCIPAL_TITLE : 'Headteacher',
            'logo' => defined('SCHOOL_LOGO_URL') ? SCHOOL_LOGO_URL : '../../images/logo.jpg'
        ];

        $schoolDetails = array_merge($defaultDetails, $schoolDetails);

        // Load formal template if exists, fallback to bootstrap template
        $templatePath = __DIR__ . '/../modules/communications/templates/formal_email_template.html';
        if (!file_exists($templatePath)) {
            $templatePath = __DIR__ . '/../modules/communications/templates/email_bootstrap_template.html';
        }

        $template = file_get_contents($templatePath);

        // Build logo section - use Content-ID for MIME attachment
        // This is the standard way to embed images in emails (works best with Gmail)
        $logoSection = '';
        if (!empty($schoolDetails['logo'])) {
            $logoPath = __DIR__ . '/../../images/logo.jpg';
            if (file_exists($logoPath)) {
                // Use cid: reference for MIME attachment (will be added in sendEmail)
                $logoSection = '<img src="cid:school_logo" alt="Kingsway Preparatory School Logo" style="max-height: 95px; width: auto; display: block; border-radius: 6px; margin: 0; padding: 0; border: 2px solid rgba(255,255,255,0.1); box-shadow: 0 2px 8px rgba(0,0,0,0.2);" />';
            } else {
                // Fallback to URL if file not found
                $logoUrl = htmlspecialchars($schoolDetails['logo']);
                $logoSection = '<img src="' . $logoUrl . '" alt="Kingsway Preparatory School Logo" style="max-height: 95px; width: auto; display: block; border-radius: 6px; margin: 0; padding: 0; border: none;" />';
            }
        }

        $replacements = [
            '{{subject}}' => $subject,
            '{{body}}' => $formattedBody,
            '{{signature}}' => $signature,
            '{{footer}}' => $footer,
            '{{media}}' => $media,
            '{{date}}' => date('j F Y'),
            '{{school_address}}' => htmlspecialchars($schoolDetails['address']),
            '{{school_phone}}' => htmlspecialchars($schoolDetails['phone']),
            '{{school_email}}' => htmlspecialchars($schoolDetails['email']),
            '{{sender_name}}' => htmlspecialchars($schoolDetails['principal_name']),
            '{{sender_title}}' => htmlspecialchars($schoolDetails['principal_title']),
            '{{school_logo}}' => $logoSection
        ];
        return strtr($template, $replacements);
    }    /**
         * Format email body sections into formal letter format
         * Input array structure:
         * [
         *   'recipient_name' => 'John Doe',
         *   'salutation' => 'Dear Mr. Doe,',
         *   'intro' => 'Thank you for...',
         *   'main_content' => ['Payment Details:', '- Amount: KES 50,000', '- Date: 3-Dec-2025'],
         *   'closing' => 'Should you have questions...',
         *   'sign_off' => 'Sincerely,'
         * ]
         */
    private function formatFormalLetterBody($sections)
    {
        $formatted = '';

        // Salutation
        if (isset($sections['salutation'])) {
            $formatted .= '<p style="margin-bottom: 20px;">' . htmlspecialchars($sections['salutation']) . '</p>';
        }

        // Introduction paragraph
        if (isset($sections['intro']) && !empty($sections['intro'])) {
            $formatted .= '<p style="margin-bottom: 16px; line-height: 1.6;">'
                . nl2br(htmlspecialchars($sections['intro'])) . '</p>';
        }

        // Main content section with formatting
        if (isset($sections['main_content'])) {
            if (is_array($sections['main_content'])) {
                $formatted .= '<div style="margin: 24px 0; line-height: 1.8;">';
                foreach ($sections['main_content'] as $line) {
                    if (substr($line, 0, 1) === '-' || substr($line, 0, 1) === '•') {
                        // Bullet point - indent
                        $formatted .= '<div style="margin-left: 20px; margin-bottom: 8px;">'
                            . htmlspecialchars($line) . '</div>';
                    } else if (substr($line, -1) === ':') {
                        // Header line - bold with spacing
                        $formatted .= '<div style="margin-top: 16px; margin-bottom: 8px; font-weight: bold;">'
                            . htmlspecialchars($line) . '</div>';
                    } else {
                        // Regular content line
                        $formatted .= '<div style="margin-bottom: 8px;">'
                            . htmlspecialchars($line) . '</div>';
                    }
                }
                $formatted .= '</div>';
            } else {
                $formatted .= '<div style="margin: 24px 0; line-height: 1.6;">'
                    . nl2br(htmlspecialchars($sections['main_content'])) . '</div>';
            }
        }

        // Closing paragraph
        if (isset($sections['closing']) && !empty($sections['closing'])) {
            $formatted .= '<p style="margin-bottom: 16px; margin-top: 24px; line-height: 1.6;">'
                . nl2br(htmlspecialchars($sections['closing'])) . '</p>';
        }

        // Sign-off
        if (isset($sections['sign_off'])) {
            $formatted .= '<div style="margin-top: 32px; margin-bottom: 8px;">'
                . htmlspecialchars($sections['sign_off']) . '</div>';
        }

        return $formatted;
    }

    // Render email using Bootstrap template
    public function renderEmail($subject, $body, $signature, $footer, $media = '', $schoolDetails = [])
    {
        // Default to formal rendering if body is array
        if (is_array($body)) {
            return $this->renderFormalEmail($subject, $body, $signature, $footer, $media, $schoolDetails);
        }

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
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            if (is_array($recipients)) {
                foreach ($recipients as $email => $name) {
                    $mail->addAddress($email, $name);
                }
            } else {
                $mail->addAddress($recipients);
            }
            $mail->isHTML(true);
            // Explicitly set content type to HTML with UTF-8 encoding
            $mail->ContentType = 'text/html; charset=UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            // Attach logo as embedded image (MIME attachment with Content-ID)
            $logoPath = __DIR__ . '/../../images/logo.jpg';
            if (file_exists($logoPath)) {
                $mail->addEmbeddedImage($logoPath, 'school_logo', 'logo.jpg', 'base64', 'image/jpeg');
            }

            // Attachments
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);
                }
            }

            // Add plain text alternative
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Log error
            error_log("Email send error: " . $e->getMessage());
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
