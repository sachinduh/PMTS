<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$d=input_json(); if(empty($d['id'])||empty($d['new_password'])) fail('User ID and new password are required.');
$hash=password_hash($d['new_password'], PASSWORD_DEFAULT); $stmt=$pdo->prepare("UPDATE users SET password=? WHERE id=?"); $stmt->execute([$hash,$d['id']]); ok(['message'=>'Password changed successfully.']);
