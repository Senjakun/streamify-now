<?php
define('DB_HOST', 'mysql');
define('DB_NAME', 'streamify_db');
define('DB_USER', 'streamify_user');
define('DB_PASS', 'rimbamobile2');
define('DB_CHARSET', 'utf8mb4');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}

define('SITE_NAME', 'Playall.me');
define('SITE_URL', 'https://playall.me');
define('JWT_SECRET', '089c01c76039b35d54fb1f04034b023b84674277a50e0c8eefd1e2ffb7445cc4');
define('JWT_EXPIRY', 86400 * 7);
// Google OAuth Client ID (set after creating credentials in Google Cloud Console)
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '181507204765-6bnhhqaq7h0u5d8f341i2ftb17c9jjss.apps.googleusercontent.com');

function setCORSHeaders() {
    $allowed_origins = [SITE_URL, 'http://localhost:5173'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}
