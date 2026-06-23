<?php
// ============================================================
//  PMTS – JWT Auth Helper
//  Simple HMAC-SHA256 signed tokens. No external libraries.
// ============================================================

define('JWT_SECRET', 'PMTS_Badulla_Hospital_2026_SuperSecret!@#$');
define('JWT_EXPIRY', 86400); // 24 hours in seconds

// ---- Base64 URL helpers ----

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    $pad  = 4 - strlen($data) % 4;
    $data = $data . ($pad < 4 ? str_repeat('=', $pad) : '');
    return base64_decode(strtr($data, '-_', '+/'));
}

// ---- Token generation ----

function generateJWT(int $userId, string $role, string $email): string {
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'sub'   => $userId,
        'role'  => $role,
        'email' => $email,
        'iat'   => time(),
        'exp'   => time() + JWT_EXPIRY,
    ]));
    $signature = base64url_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );
    return "$header.$payload.$signature";
}

// ---- Token verification ----

function verifyJWT(?string $token): ?array {
    if (!$token) return null;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;

    $expectedSig = base64url_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );
    if (!hash_equals($expectedSig, $sig)) return null;

    $data = json_decode(base64url_decode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) return null;
    return $data;
}

// ---- Request helpers ----

function getAuthUser(): ?array {
    $authHeader = '';
    if (function_exists('getallheaders')) {
        $headers    = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    // Fallback for servers where getallheaders() may not work
    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!str_starts_with($authHeader, 'Bearer ')) return null;
    $token = substr($authHeader, 7);
    return verifyJWT($token);
}

function requireAuth(): array {
    $user = getAuthUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login first.']);
        exit;
    }
    return $user;
}

function requireRole(array $allowedRoles): array {
    $user = requireAuth();
    if (!in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. You do not have permission to perform this action.'
        ]);
        exit;
    }
    return $user;
}
