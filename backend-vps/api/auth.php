<?php
/**
 * Authentication API
 * Endpoints: /api/auth.php?action=register|login|logout|me
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Simple JWT Implementation
class JWT {
    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + JWT_EXPIRY;
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }
    
    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        
        [$base64Header, $base64Payload, $base64Signature] = $parts;
        
        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, JWT_SECRET, true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if (!hash_equals($expectedSignature, $base64Signature)) return null;
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);
        
        if ($payload['exp'] < time()) return null;
        
        return $payload;
    }
}

// Get current user from token
function getCurrentUser() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    
    $payload = JWT::decode($matches[1]);
    if (!$payload) return null;
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT u.*, GROUP_CONCAT(ur.role) as roles FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id WHERE u.id = ? GROUP BY u.id");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        unset($user['password_hash']);
        $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : ['user'];
    }
    
    return $user;
}

// Validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 255;
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
}

function validatePassword($password) {
    return strlen($password) >= 8 && strlen($password) <= 100;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        
        // Validation
        if (!validateUsername($username)) {
            http_response_code(400);
            echo json_encode(['error' => 'Username harus 3-50 karakter, hanya huruf, angka, dan underscore']);
            exit;
        }
        
        if (!validateEmail($email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Email tidak valid']);
            exit;
        }
        
        if (!validatePassword($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Password minimal 8 karakter']);
            exit;
        }
        
        $pdo = getDB();
        
        // Check existing user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email atau username sudah terdaftar']);
            exit;
        }
        
        // Create user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $passwordHash]);
        $userId = $pdo->lastInsertId();
        
        // Assign default role
        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'user')");
        $stmt->execute([$userId]);
        
        // Generate token
        $token = JWT::encode(['user_id' => $userId]);
        
        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'roles' => ['user']
            ]
        ]);
        break;
        
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        
        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Email dan password diperlukan']);
            exit;
        }
        
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT u.*, GROUP_CONCAT(ur.role) as roles FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id WHERE u.email = ? GROUP BY u.id");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Email atau password salah']);
            exit;
        }
        
        if (!$user['is_active']) {
            http_response_code(403);
            echo json_encode(['error' => 'Akun dinonaktifkan']);
            exit;
        }
        
        $token = JWT::encode(['user_id' => $user['id']]);
        unset($user['password_hash']);
        $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : ['user'];
        $user['is_admin'] = in_array('admin', $user['roles']) ? 1 : 0;
        
        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => $user
        ]);
        break;
        
    case 'google':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $credential = $data['credential'] ?? '';
        if (!$credential) { http_response_code(400); echo json_encode(['error' => 'Credential Google diperlukan']); exit; }

        // Verify Google ID token via Google's tokeninfo endpoint
        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $g = json_decode($resp, true);
        if ($code !== 200 || empty($g['email']) || ($g['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
            http_response_code(401); echo json_encode(['error' => 'Token Google tidak valid']); exit;
        }
        if (($g['email_verified'] ?? 'true') === 'false') {
            http_response_code(401); echo json_encode(['error' => 'Email Google belum terverifikasi']); exit;
        }

        $email = $g['email'];
        $name = $g['name'] ?? explode('@', $email)[0];
        $picture = $g['picture'] ?? null;
        $sub = $g['sub'];

        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT u.*, GROUP_CONCAT(ur.role) as roles FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id WHERE u.email = ? GROUP BY u.id");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Create username from email prefix, ensure unique
            $base = preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]);
            if (strlen($base) < 3) $base = 'user' . $base;
            $username = substr($base, 0, 40); $i = 1;
            while (true) {
                $c = $pdo->prepare("SELECT id FROM users WHERE username = ?"); $c->execute([$username]);
                if (!$c->fetch()) break;
                $username = substr($base, 0, 36) . $i; $i++;
            }
            $pdo->prepare("INSERT INTO users (username, email, avatar_url, oauth_provider, oauth_id) VALUES (?,?,?,'google',?)")
                ->execute([$username, $email, $picture, $sub]);
            $userId = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'user')")->execute([$userId]);
            $stmt = $pdo->prepare("SELECT u.*, GROUP_CONCAT(ur.role) as roles FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id WHERE u.id = ? GROUP BY u.id");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } else {
            // Existing user: link google + refresh avatar if empty
            $pdo->prepare("UPDATE users SET oauth_provider='google', oauth_id=?, avatar_url=COALESCE(NULLIF(avatar_url,''), ?) WHERE id=?")
                ->execute([$sub, $picture, $user['id']]);
            if (empty($user['avatar_url'])) $user['avatar_url'] = $picture;
        }

        if (!$user['is_active']) { http_response_code(403); echo json_encode(['error' => 'Akun dinonaktifkan']); exit; }

        $token = JWT::encode(['user_id' => $user['id']]);
        unset($user['password_hash']);
        $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : ['user'];
        $user['is_admin'] = in_array('admin', $user['roles']) ? 1 : 0;
        echo json_encode(['success' => true, 'token' => $token, 'user' => $user]);
        break;

    case 'me':
        $user = getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $user['is_admin'] = in_array('admin', $user['roles'] ?? []) ? 1 : 0;
        echo json_encode(['user' => $user]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
