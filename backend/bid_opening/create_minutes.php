<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$pdo->exec("CREATE TABLE IF NOT EXISTS bid_opening_minutes (id INT AUTO_INCREMENT PRIMARY KEY, procurement_id INT NOT NULL, bidder_name VARCHAR(200) NOT NULL, bid_price_without_vat DECIMAL(15,2), vat_amount DECIMAL(15,2), bid_price_with_vat DECIMAL(15,2), bid_security_status VARCHAR(50), bank_name VARCHAR(150), security_amount DECIMAL(15,2), remarks TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$d=input_json(); if(empty($d['procurement_id'])||empty($d['bidder_name'])) fail('Procurement ID and bidder name required.');
$stmt=$pdo->prepare("INSERT INTO bid_opening_minutes (procurement_id,bidder_name,bid_price_without_vat,vat_amount,bid_price_with_vat,bid_security_status,bank_name,security_amount,remarks) VALUES (?,?,?,?,?,?,?,?,?)");
$stmt->execute([$d['procurement_id'],$d['bidder_name'],$d['bid_price_without_vat']??null,$d['vat_amount']??null,$d['bid_price_with_vat']??null,$d['bid_security_status']??'not_submitted',$d['bank_name']??null,$d['security_amount']??null,$d['remarks']??null]);
ok(['message'=>'Bid opening minutes saved.']);
