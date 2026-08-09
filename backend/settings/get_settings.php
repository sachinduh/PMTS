<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$defaults=['hospital_name'=>'Badulla Hospital','system_name'=>'PMTS','support_email'=>'admin@badullahospital.lk','backup_enabled'=>'yes'];
foreach($defaults as $k=>$v){ $stmt=$pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?,?)"); $stmt->execute([$k,$v]); }
$stmt=$pdo->query("SELECT setting_key, setting_value FROM system_settings"); $settings=[]; foreach($stmt->fetchAll() as $r){$settings[$r['setting_key']]=$r['setting_value'];} ok(['settings'=>$settings]);
