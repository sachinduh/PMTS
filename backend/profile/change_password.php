<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/validation_helper.php';

function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }

$authUser = requireAuth();
$pdo = getPDO();

$d=input_json();
$id = (int) ($d['id'] ?? $authUser['sub']);
if(empty($id)||empty($d['new_password'])) fail('User ID and new password are required.');

if ($id !== (int) $authUser['sub'] && $authUser['role'] !== 'it_admin') {
    fail('You can change only your own password.', 403);
}

$passwordError = pmtsValidateStrongPassword((string) $d['new_password']);
if ($passwordError !== null) {
    fail($passwordError);
}

if ($id === (int) $authUser['sub'] && $authUser['role'] !== 'it_admin') {
    if (empty($d['current_password'])) {
        fail('Current password is required to change your password.', 400);
    }

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user || !password_verify((string) $d['current_password'], $user['password'])) {
        fail('Current password is incorrect.', 401);
    }
}

$hash=password_hash($d['new_password'], PASSWORD_DEFAULT);
$stmt=$pdo->prepare("UPDATE users SET password=?, failed_login_attempts=0, last_failed_login_at=NULL, account_locked=0, locked_at=NULL, locked_reason=NULL WHERE id=?");
$stmt->execute([$hash,$id]);
ok(['message'=>'Password changed successfully.']);
