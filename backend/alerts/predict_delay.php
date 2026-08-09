<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();

$pid=$_GET['procurement_id'] ?? (input_json()['procurement_id'] ?? null);
$sql="SELECT p.id, p.title, s.task_name, s.planned_date, s.allowed_delay_days, DATE_ADD(s.planned_date, INTERVAL COALESCE(s.allowed_delay_days, 0) DAY) AS allowed_deadline_date, s.actual_date, s.status FROM procurements p JOIN procurement_time_schedule s ON s.procurement_id=p.id WHERE DATE_ADD(s.planned_date, INTERVAL COALESCE(s.allowed_delay_days, 0) DAY) < CURDATE() AND s.actual_date IS NULL AND s.status <> 'completed'";
$params=[]; if($pid){ $sql.=" AND p.id=?"; $params[]=$pid; }
$stmt=$pdo->prepare($sql); $stmt->execute($params); $delayed=$stmt->fetchAll();
$insert=$pdo->prepare("INSERT INTO delay_alerts (procurement_id, alert_type, message, risk_level, expected_date, status) VALUES (?,?,?,?,?,'active')");
$count=0; foreach($delayed as $d){ $msg='Task delayed: '.$d['task_name'].' for '.$d['title']; $risk=(strtotime($d['planned_date']) < strtotime('-14 days'))?'high':'medium'; $insert->execute([$d['id'],'schedule_delay',$msg,$risk,$d['planned_date']]); $count++; }
ok(['message'=>'Delay prediction completed.','created_alerts'=>$count,'delayed_tasks'=>$delayed]);
