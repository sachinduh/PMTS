<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$d=input_json(); if(empty($d['id'])) fail('Purchase order ID required.');
$stmt=$pdo->prepare("UPDATE purchase_orders SET po_number=?, supplier_id=?, po_date=?, amount=?, status=?, remarks=? WHERE id=?");
$stmt->execute([$d['po_number']??'', $d['supplier_id']??0, $d['po_date']??null, $d['amount']??null, $d['status']??'draft', $d['remarks']??null, $d['id']]); ok(['message'=>'Purchase order updated successfully.']);
