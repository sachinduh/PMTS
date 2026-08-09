<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }

$authUser = requireAuth();
$pdo = getPDO();

$id = (int) ($_GET['id'] ?? $authUser['sub']);
if(!$id) fail('User ID required.');

if ($id !== (int) $authUser['sub'] && $authUser['role'] !== 'it_admin') {
    fail('You can view only your own profile.', 403);
}

$stmt=$pdo->prepare("SELECT id, full_name, email, phone, nic, user_type, department, organization, role, status, created_at FROM users WHERE id=?");
$stmt->execute([$id]);
$user=$stmt->fetch();
if(!$user) fail('User not found.',404);
ok(['user'=>$user]);
