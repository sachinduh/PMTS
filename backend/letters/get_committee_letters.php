<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/committee_appointment_helper.php';
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
$pdo = getPDO();
pmtsEnsureCommitteeLettersSchema($pdo);
$pid=$_GET['procurement_id']??null;
$sql="SELECT * FROM committee_letters" . ($pid?" WHERE procurement_id=?":"") . " ORDER BY id DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($pid?[$pid]:[]);
ok(['letters'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
