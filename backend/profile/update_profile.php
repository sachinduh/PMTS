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
if(!$id) fail('User ID required.');

if ($id !== (int) $authUser['sub'] && $authUser['role'] !== 'it_admin') {
    fail('You can update only your own profile.', 403);
}

$fullName = trim((string) ($d['full_name'] ?? ''));
$nameError = pmtsValidateFullName($fullName);
if ($nameError !== null) {
    fail($nameError);
}

$hasProfilePicture = array_key_exists('profile_picture', $d);
$profilePicture = $hasProfilePicture ? trim((string) $d['profile_picture']) : null;
if ($profilePicture !== null && $profilePicture !== '') {
    if (strlen($profilePicture) > 3500000 || !preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $profilePicture)) {
        fail('Invalid profile image. Please choose a JPG, PNG, GIF, or WebP image smaller than 2MB.');
    }
}

if ($hasProfilePicture) {
    $stmt=$pdo->prepare("UPDATE users SET full_name=?, phone=?, nic=?, department=?, organization=?, profile_picture=? WHERE id=?");
    $stmt->execute([$fullName, $d['phone']??null, $d['nic']??null, $d['department']??null, $d['organization']??null, $profilePicture === '' ? null : $profilePicture, $id]);
} else {
    $stmt=$pdo->prepare("UPDATE users SET full_name=?, phone=?, nic=?, department=?, organization=? WHERE id=?");
    $stmt->execute([$fullName, $d['phone']??null, $d['nic']??null, $d['department']??null, $d['organization']??null, $id]);
}
ok(['message'=>'Profile updated successfully.']);
