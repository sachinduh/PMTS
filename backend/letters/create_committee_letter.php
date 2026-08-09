<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/committee_appointment_helper.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();
pmtsEnsureCommitteeLettersSchema($pdo);

$d=input_json();
if(empty($d['procurement_id'])||empty($d['committee_type'])||empty($d['member_name'])) fail('Procurement ID, committee type and member name are required.');
$committeeType = pmtsNormalizeCommitteeType($d['committee_type']);
$position = $d['committee_position'] ?? 'Member';
$procurementId = (int)$d['procurement_id'];

$procStmt = $pdo->prepare("SELECT id, procurement_id, title, tender_number FROM procurements WHERE id = ?");
$procStmt->execute([$procurementId]);
$procurement = $procStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$plannedDate = !empty($d['planned_date']) ? substr((string)$d['planned_date'], 0, 10) : pmtsGetCommitteeTaskPlannedDate($pdo, $procurementId, $committeeType);
$letterDate = !empty($d['letter_date']) ? substr((string)$d['letter_date'], 0, 10) : ($plannedDate ?: date('Y-m-d'));
if (!$plannedDate) $plannedDate = $letterDate;

$body=trim($d['letter_body'] ?? '');
if($body===''){
  $body=pmtsBuildCommitteeLetterBody($procurement, $committeeType, $position, $plannedDate);
}
$stmt=$pdo->prepare("INSERT INTO committee_letters (procurement_id, user_id, committee_type, committee_position, member_name, member_designation, member_email, letter_date, appointment_planned_date, letter_body, email_status, email_error) VALUES (?,?,?,?,?,?,?,?,?,?, 'not_sent', NULL)");
$stmt->execute([$procurementId,$d['user_id']??null,$committeeType,$position,$d['member_name'],$d['member_designation']??null,$d['member_email']??null,$letterDate,$plannedDate,$body]);
ok(['message'=>'Committee letter created successfully.','id'=>$pdo->lastInsertId()]);
