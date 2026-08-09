<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$d=input_json(); if(empty($d['id'])) fail('Supplier ID required.');
$stmt=$pdo->prepare("UPDATE suppliers SET supplier_name=?, contact_person=?, email=?, phone=?, address=? WHERE id=?");
$stmt->execute([$d['supplier_name']??'', $d['contact_person']??null, $d['email']??null, $d['phone']??null, $d['address']??null, $d['id']]); ok(['message'=>'Supplier updated successfully.']);
