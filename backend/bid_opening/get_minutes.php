<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$pdo->exec("CREATE TABLE IF NOT EXISTS bid_opening_minutes (id INT AUTO_INCREMENT PRIMARY KEY, procurement_id INT NOT NULL, bidder_name VARCHAR(200) NOT NULL, bid_price_without_vat DECIMAL(15,2), vat_amount DECIMAL(15,2), bid_price_with_vat DECIMAL(15,2), bid_security_status VARCHAR(50), bank_name VARCHAR(150), security_amount DECIMAL(15,2), remarks TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pid=$_GET['procurement_id'] ?? null; $stmt=$pdo->prepare("SELECT * FROM bid_opening_minutes WHERE (? IS NULL OR procurement_id=?) ORDER BY id DESC"); $stmt->execute([$pid,$pid]); ok(['minutes'=>$stmt->fetchAll()]);
