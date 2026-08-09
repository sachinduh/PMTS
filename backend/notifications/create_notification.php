<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$d=input_json(); if(empty($d['user_id'])||empty($d['title'])||empty($d['message'])) fail('User ID, title and message required.');
$stmt=$pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)"); $stmt->execute([$d['user_id'],$d['title'],$d['message'],$d['type']??'info']); ok(['message'=>'Notification created successfully.']);
