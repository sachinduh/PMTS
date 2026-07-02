<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(80) NOT NULL, permission_key VARCHAR(120) NOT NULL, is_allowed TINYINT(1) DEFAULT 1, UNIQUE KEY uq_role_perm(role_name, permission_key))");
$d=input_json(); if(empty($d['role_name'])||empty($d['permission_key'])) fail('Role name and permission key are required.');
$stmt=$pdo->prepare("INSERT INTO role_permissions (role_name, permission_key, is_allowed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_allowed=VALUES(is_allowed)"); $stmt->execute([$d['role_name'],$d['permission_key'],!empty($d['is_allowed'])?1:0]); ok(['message'=>'Permission updated.']);
