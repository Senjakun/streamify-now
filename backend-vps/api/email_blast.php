<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

$announcementId = intval($argv[1] ?? 0);
if (!$announcementId) { echo "No ID\n"; exit; }

$pdo = getDB();
$ann = $pdo->prepare("SELECT * FROM announcements WHERE id=?");
$ann->execute([$announcementId]);
$announcement = $ann->fetch();
if (!$announcement) { echo "Not found\n"; exit; }

$users = $pdo->query("SELECT email, username FROM users WHERE email IS NOT NULL AND email != ''")->fetchAll();
$total = count($users);
$sent = 0;

echo "Mulai blast ke $total user...\n";

foreach ($users as $user) {
    $bannerHtml = !empty($announcement['image_url'])
        ? '<tr><td><img src="' . htmlspecialchars($announcement['image_url']) . '" width="600" style="width:100%;max-height:220px;object-fit:cover;display:block;" /></td></tr>'
        : '';

    $subject = "📢 " . $announcement['title'] . " - PlayAll Stream";
    
    $body = '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background-color:#0f0f0f;font-family:Segoe UI,Tahoma,sans-serif;">
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#0f0f0f;">
<tr><td align="center" style="padding:20px 10px;">
<table cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#1a1a1a;border-radius:16px;overflow:hidden;">

<tr><td style="background:linear-gradient(135deg,#e50914,#8b0000);padding:24px 30px;text-align:center;">
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr>
<td style="padding-right:12px;">
<table cellpadding="0" cellspacing="0" border="0" width="48" height="48" style="border-radius:50%;background:rgba(255,255,255,0.15);">
<tr><td align="center" valign="middle">
▶
</td></tr></table>
</td>
<td valign="middle"><span style="font-size:24px;font-weight:700;color:#fff;">PlayAll Stream</span></td>
</tr></table>
</td></tr>

' . $bannerHtml . '

<tr><td style="padding:32px;">
<h2 style="margin:0 0 16px;font-size:20px;color:#fff;">Hei ' . htmlspecialchars($user['username']) . '! 👋</h2>
<h1 style="margin:0 0 12px;font-size:24px;font-weight:700;color:#fff;">' . htmlspecialchars($announcement['title']) . '</h1>
<div style="height:3px;background:linear-gradient(90deg,#e50914,transparent);width:80px;margin-bottom:20px;border-radius:2px;"></div>
<p style="margin:0 0 28px;font-size:16px;line-height:1.7;color:#ccc;">' . nl2br(htmlspecialchars($announcement['content'])) . '</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
<tr><td style="background:linear-gradient(135deg,#e50914,#b20710);border-radius:8px;">
<a href="https://playall.me/announcements" style="display:inline-block;padding:14px 36px;font-size:15px;font-weight:600;color:#fff;text-decoration:none;">📖 Lihat Pengumuman</a>
</td></tr></table>
</td></tr>

<tr><td style="padding:0 32px;"><div style="border-top:1px solid #333;"></div></td></tr>
<tr><td style="padding:20px 32px;text-align:center;">
<p style="margin:0 0 6px;font-size:13px;color:#666;">PlayAll Stream - Platform Streaming Terbaik</p>
<p style="margin:0;font-size:12px;color:#555;">© 2026 PlayAll Stream. All rights reserved.</p>
</td></tr>

</table></td></tr></table>
</body></html>';

    $result = sendEmail($user['email'], $user['username'], $subject, $body);
    if ($result) {
        $sent++;
        echo "✓ Terkirim ke {$user['email']} ($sent/$total)\n";
    } else {
        echo "✗ Gagal ke {$user['email']}\n";
    }
    sleep(2);
}
echo "Selesai! $sent/$total email terkirim.\n";
