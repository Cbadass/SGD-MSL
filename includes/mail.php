<?php
// includes/mail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendMail(string $to, string $subject, string $html): bool {
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = getenv('SMTP_HOST');
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('SMTP_USER');
    $mail->Password   = getenv('SMTP_PASS');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);

    $mail->setFrom(getenv('SMTP_FROM'), getenv('SMTP_FROM_NAME') ?: 'SGD Multisenluz');
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log("Email error: " . $e->getMessage());
    return false;
  }
}
