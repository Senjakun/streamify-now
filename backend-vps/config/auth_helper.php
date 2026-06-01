<?php
function getCurrentUser() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) return null;
    $parts = explode('.', $matches[1]);
    if (count($parts) !== 3) return null;
    $payload = json_decode(base64_decode(str_replace(['-','_'],['+','/'], $parts[1])), true);
    if (!$payload || $payload['exp'] < time()) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT u.*, GROUP_CONCAT(ur.role) as roles FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id WHERE u.id = ? GROUP BY u.id");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    if ($user) { unset($user['password_hash']); $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : ['user']; }
    return $user;
}
