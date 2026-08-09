<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$d=input_json(); if(empty($d['id'])||empty($d['status'])) fail('Alert ID and status are required.');
$stmt=$pdo->prepare("UPDATE delay_alerts SET status=? WHERE id=?"); $stmt->execute([$d['status'],$d['id']]); ok(['message'=>'Alert status updated.']);
