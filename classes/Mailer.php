<?php
// classes/Mailer.php - Handles transactional emails and SMS/OTP
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/src/Psr/Log/LoggerInterface.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{

    /**
     * Sends an email via Gmail SMTP
     */
    public static function sendEmail(string $to, string $subject, string $body): bool
    {
        if (defined('SIMULATE_EMAILS') && SIMULATE_EMAILS) {
            // Keep the previous simulated functionality block just in case
            if (session_status() === PHP_SESSION_NONE)
                session_start();
            $_SESSION['mock_notification'] = [
                'type' => 'email',
                'to' => $to,
                'subject' => $subject,
                'body' => $body
            ];
            error_log("SIMULATED EMAIL to $to | Subject: $subject | Body: $body");
            return true;
        }

        // --- REAL PHPMailer IMPLEMENTATION PIPELINE ---
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SYSTEM_EMAIL, APP_NAME);
            $mail->addAddress($to);

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Sends an SMS / OTP via Fast2SMS
     */
    public static function sendSMS(string $phone, string $message): bool
    {
        if (defined('SIMULATE_SMS') && SIMULATE_SMS) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();
            $_SESSION['mock_notification'] = [
                'type' => 'sms',
                'to' => $phone,
                'subject' => 'SMS Notification',
                'body' => $message
            ];
            error_log("SIMULATED SMS to $phone | Msg: $message");
            return true;
        }

        // --- REAL Twilio PIPELINE ---

        if (empty(TWILIO_FROM)) {
            error_log("Twilio Failed: Missing TWILIO_FROM phone number in config.php");
            return false;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_SID . "/Messages.json";

        $fields = http_build_query([
            'To' => $phone,
            'From' => TWILIO_FROM,
            'Body' => $message
        ]);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, TWILIO_SID . ":" . TWILIO_TOKEN);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("Twilio cURL Error: " . $err);
            return false;
        } else {
            $json = json_decode($response, true);
            // Twilio returns a sid if successful, or code/message on error
            if (!empty($json['sid'])) {
                return true;
            } else {
                error_log("Twilio API Failed: " . $response);
                return false;
            }
        }
    }
}
