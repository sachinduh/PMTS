<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$d=input_json(); foreach(($d['settings'] ?? $d) as $k=>$v){ $stmt=$pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)"); $stmt->execute([$k,$v]); } ok(['message'=>'System settings updated successfully.']);
