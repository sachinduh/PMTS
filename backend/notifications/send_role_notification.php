<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$d=input_json(); if(empty($d['role'])||empty($d['title'])||empty($d['message'])) fail('Role, title and message required.');
$users=$pdo->prepare("SELECT id FROM users WHERE role=? AND status='active'"); $users->execute([$d['role']]);
$stmt=$pdo->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)"); $count=0;
foreach($users->fetchAll() as $u){ $stmt->execute([$u['id'],$d['title'],$d['message'],$d['type']??'info']); $count++; }
ok(['message'=>'Role notification sent.','count'=>$count]);
