<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

defined('SMTP_HOST') || define('SMTP_HOST', 'yuuka.kawaiihost.net');
defined('SMTP_PORT') || define('SMTP_PORT', 587);
defined('SMTP_USER') || define('SMTP_USER', 'noreply@playall.me');
defined('SMTP_PASS') || define('SMTP_PASS', 'Quin0Yukie');
defined('SITE_NAME') || define('SITE_NAME', 'PlayAll Stream');
defined('SITE_URL') || define('SITE_URL', 'https://playall.me');

function sendEmail($to, $toName, $subject, $htmlBody) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_USER, SITE_NAME);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendCommentReplyNotif($toEmail, $toName, $commenterName, $commentText, $contentTitle, $contentUrl) {
    $subject = "💬 {$commenterName} membalas komentarmu di PlayAll Stream";
    $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0f0f0f;font-family:Arial,sans-serif;">
  <div style="max-width:600px;margin:0 auto;background:#1a1a1a;border-radius:12px;overflow:hidden;">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#dc2626,#991b1b);padding:30px;text-align:center;">
      <div style="font-size:32px;margin-bottom:8px;">▶️</div>
      <h1 style="color:#fff;margin:0;font-size:22px;">PlayAll Stream</h1>
      <p style="color:#fca5a5;margin:8px 0 0;font-size:13px;">Notifikasi Komentar</p>
    </div>
    <!-- Body -->
    <div style="padding:30px;">
      <p style="color:#e5e5e5;font-size:15px;margin:0 0 16px;">Hei <strong style="color:#fff;">{$toName}</strong>! 👋</p>
      <p style="color:#a3a3a3;font-size:14px;margin:0 0 20px;">
        <strong style="color:#f87171;">{$commenterName}</strong> membalas komentarmu di:
      </p>
      <!-- Content Card -->
      <div style="background:#262626;border-radius:8px;padding:16px;margin-bottom:20px;border-left:3px solid #dc2626;">
        <p style="color:#f87171;font-size:12px;margin:0 0 6px;text-transform:uppercase;letter-spacing:1px;">📺 {$contentTitle}</p>
        <p style="color:#e5e5e5;font-size:14px;margin:0;line-height:1.6;">{$commentText}</p>
      </div>
      <!-- CTA Button -->
      <div style="text-align:center;margin:24px 0;">
        <a href="{$contentUrl}" style="background:#dc2626;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:bold;font-size:14px;display:inline-block;">
          💬 Lihat Balasan
        </a>
      </div>
      <p style="color:#525252;font-size:12px;text-align:center;margin:0;">
        Kamu menerima email ini karena berlangganan notifikasi di PlayAll Stream.<br>
        <a href="{$contentUrl}" style="color:#dc2626;">Kunjungi PlayAll Stream</a>
      </p>
    </div>
    <!-- Footer -->
    <div style="background:#111;padding:16px;text-align:center;">
      <p style="color:#404040;font-size:12px;margin:0;">© 2026 PlayAll Stream · noreply@playall.me</p>
    </div>
  </div>
</body>
</html>
HTML;
    return sendEmail($toEmail, $toName, $subject, $body);
}
