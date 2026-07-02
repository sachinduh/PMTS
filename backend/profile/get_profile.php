<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$id=$_GET['id'] ?? 0; if(!$id) fail('User ID required.');
$stmt=$pdo->prepare("SELECT id, full_name, email, phone, nic, user_type, department, organization, role, status, created_at FROM users WHERE id=?"); $stmt->execute([$id]); $user=$stmt->fetch(); if(!$user) fail('User not found.',404); ok(['user'=>$user]);
