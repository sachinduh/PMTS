<?php
// ============================================================
//  PMTS – JWT Auth Helper + server-side session validation
//  Prevents dashboard access by editing browser localStorage.
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../queries/user_queries.php';

define('JWT_SECRET', getenv('PMTS_JWT_SECRET') ?: 'PMTS_Badulla_Hospital_2026_SuperSecret!@#$');
define('JWT_EXPIRY', 86400); // 24 hours in seconds
define('LOGIN_LOCK_MAX_ATTEMPTS', 3);

// ---- Base64 URL helpers ----

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    $pad  = 4 - strlen($data) % 4;
    $data = $data . ($pad < 4 ? str_repeat('=', $pad) : '');
    return base64_decode(strtr($data, '-_', '+/'));
}

function authJsonError(int $httpCode, string $message, string $code = 'AUTH_ERROR'): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => $message,
        'code'    => $code,
    ]);
    exit;
}

/**
 * Backward-compatible wrapper used by existing endpoint files.
 * Actual SQL is stored in backend/queries/user_queries.php.
 */
function ensureAccountSecurityColumns(PDO $pdo): void {
    pmtsEnsureAccountSecurityColumns($pdo);
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
    if (!$data || !isset($data['sub'], $data['exp']) || $data['exp'] < time()) return null;
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
    if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (!str_starts_with($authHeader, 'Bearer ')) return null;
    $token = substr($authHeader, 7);
    return verifyJWT($token);
}

/**
 * Requires a valid JWT AND confirms the user still exists, is active,
 * and is not locked in the database. This prevents dashboard/API access
 * by editing browser application storage.
 */
function requireAuth(): array {
    $payload = getAuthUser();
    if (!$payload) {
        authJsonError(401, 'Unauthorized. Please login first.', 'UNAUTHORIZED');
    }

    $pdo = getPDO();
    ensureAccountSecurityColumns($pdo);

    $user = pmtsFetchAuthUserById($pdo, (int) $payload['sub']);

    if (!$user) {
        authJsonError(401, 'Unauthorized. User account no longer exists.', 'USER_NOT_FOUND');
    }

    if ((int) ($user['account_locked'] ?? 0) === 1) {
        authJsonError(401, 'Your account is locked. Please contact IT Admin to unblock it.', 'ACCOUNT_LOCKED');
    }

    if (($user['status'] ?? '') !== 'active') {
        authJsonError(401, 'Your account is not active. Please contact IT Admin.', 'ACCOUNT_NOT_ACTIVE');
    }

    // IT Admin dashboard/API access requires a real approved role in the database.
    // A requested_role or edited browser storage is never enough.
    if (($user['role'] ?? '') === 'it_admin') {
        if ((int) ($user['role_locked'] ?? 0) !== 1) {
            authJsonError(403, 'IT Admin access is blocked because this account is not role-locked by the system.', 'UNLOCKED_ADMIN_ROLE_BLOCKED');
        }

        if (!pmtsIsPrimaryItAdmin($pdo, (int) $user['id'])) {
            authJsonError(403, 'Only one IT Admin account is allowed to access the system.', 'EXTRA_IT_ADMIN_BLOCKED');
        }
    }

    // Token role must still match database role. This blocks old/tampered tokens after role changes.
    if (($payload['role'] ?? '') !== ($user['role'] ?? '')) {
        authJsonError(401, 'Session role is no longer valid. Please login again.', 'ROLE_MISMATCH');
    }

    return [
        'sub'                   => (int) $user['id'],
        'id'                    => (int) $user['id'],
        'full_name'             => $user['full_name'],
        'email'                 => $user['email'],
        'phone'                 => $user['phone'],
        'nic'                   => $user['nic'],
        'user_type'             => $user['user_type'],
        'department'            => $user['department'],
        'organization'          => $user['organization'],
        'profile_picture'      => $user['profile_picture'] ?? null,
        'role'                  => $user['role'],
        'requested_role'        => $user['requested_role'] ?? null,
        'role_locked'           => (int) ($user['role_locked'] ?? 0),
        'status'                => $user['status'],
        'failed_login_attempts' => (int) ($user['failed_login_attempts'] ?? 0),
        'account_locked'        => (int) ($user['account_locked'] ?? 0),
        'locked_at'             => $user['locked_at'] ?? null,
        'locked_reason'         => $user['locked_reason'] ?? null,
        'iat'                   => $payload['iat'] ?? null,
        'exp'                   => $payload['exp'] ?? null,
    ];
}

function requireRole(array $allowedRoles): array {
    $user = requireAuth();
    if (!in_array($user['role'], $allowedRoles, true)) {
        authJsonError(403, 'Access denied. You do not have permission to perform this action.', 'ACCESS_DENIED');
    }
    return $user;
}
