<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
require_once '../config/auth_helper.php';
$user = getCurrentUser();
if (!$user) { http_response_code(401); echo json_encode(['error'=>'Login diperlukan']); exit; }

if (!isset($_FILES['image'])) { echo json_encode(['error'=>'File tidak ditemukan']); exit; }

$file = $_FILES['image'];
$allowed = ['image/jpeg','image/png','image/gif','image/webp'];
if (!in_array($file['type'], $allowed)) { echo json_encode(['error'=>'Format tidak didukung']); exit; }
if ($file['size'] > 5 * 1024 * 1024) { echo json_encode(['error'=>'File terlalu besar (max 5MB)']); exit; }

$imgbbKey = '0320656486c832a4241636068a86afc5';
$base64 = base64_encode(file_get_contents($file['tmp_name']));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['key' => $imgbbKey, 'image' => $base64]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($data && $data['success']) {
    echo json_encode(['success' => true, 'url' => $data['data']['url']]);
} else {
    echo json_encode(['error' => 'Upload ke ImgBB gagal', 'detail' => $data]);
}
