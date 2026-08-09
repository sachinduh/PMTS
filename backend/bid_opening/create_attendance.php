<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$pdo->exec("CREATE TABLE IF NOT EXISTS bid_opening_attendance (id INT AUTO_INCREMENT PRIMARY KEY, procurement_id INT NOT NULL, meeting_date DATE, meeting_time TIME, location VARCHAR(255), committee_members TEXT, bidder_representatives TEXT, remarks TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$d=input_json(); if(empty($d['procurement_id'])) fail('Procurement ID required.');
$stmt=$pdo->prepare("INSERT INTO bid_opening_attendance (procurement_id, meeting_date, meeting_time, location, committee_members, bidder_representatives, remarks) VALUES (?,?,?,?,?,?,?)");
$stmt->execute([$d['procurement_id'],$d['meeting_date']??null,$d['meeting_time']??null,$d['location']??null,$d['committee_members']??null,$d['bidder_representatives']??null,$d['remarks']??null]);
ok(['message'=>'Bid opening attendance saved.']);
