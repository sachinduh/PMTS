<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (id INT AUTO_INCREMENT PRIMARY KEY, role_name VARCHAR(80) NOT NULL, permission_key VARCHAR(120) NOT NULL, is_allowed TINYINT(1) DEFAULT 1, UNIQUE KEY uq_role_perm(role_name, permission_key))");
$stmt=$pdo->query("SELECT * FROM role_permissions ORDER BY role_name, permission_key"); ok(['permissions'=>$stmt->fetchAll()]);
