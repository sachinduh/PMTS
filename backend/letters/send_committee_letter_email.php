<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email_helper.php';
require_once __DIR__ . '/committee_appointment_helper.php';
function input_json(){ return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=[]){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function fail($msg,$code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }
$pdo = getPDO();
pmtsEnsureCommitteeLettersSchema($pdo);
pmtsEnsureEmailLogSchema($pdo);

$d=input_json(); if(empty($d['id'])) fail('Letter ID is required.');
$stmt=$pdo->prepare("SELECT l.*, p.procurement_id AS procurement_code, p.title, p.tender_number FROM committee_letters l LEFT JOIN procurements p ON p.id=l.procurement_id WHERE l.id=?");
$stmt->execute([(int)$d['id']]);
$letter=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$letter) fail('Committee letter not found.', 404);

$procurement = [
  'procurement_id' => $letter['procurement_code'] ?? '',
  'title' => $letter['title'] ?? '',
  'tender_number' => $letter['tender_number'] ?? '',
];
$subject = pmtsBuildCommitteeEmailSubject($procurement, $letter['committee_type']);
$emailResult = pmtsSendEmail($letter['member_email'] ?? '', $subject, pmtsBuildCommitteeEmailBody($letter, $procurement));
pmtsLogEmailAttempt($pdo, $letter['member_email'] ?? '', $subject, $emailResult, 'committee_letters', (int)$letter['id']);
$attemptAt = date('Y-m-d H:i:s');
$status = $emailResult['success'] ? 'sent' : 'failed';
$sentAt = $emailResult['success'] ? $attemptAt : null;
$errorText = $emailResult['success'] ? null : ($emailResult['message'] ?? 'Unknown email error');
$update=$pdo->prepare("UPDATE committee_letters SET sent_at=?, last_email_attempt_at=?, email_status=?, email_error=? WHERE id=?");
$update->execute([$sentAt, $attemptAt, $status, $errorText, (int)$d['id']]);

if(!$emailResult['success']) {
  ok(['message'=>'Email sending failed. Status was saved in SQL. Error: ' . $errorText, 'email_status'=>$status, 'email_error'=>$errorText]);
}
ok(['message'=>'Committee appointment email sent successfully.', 'email_status'=>$status, 'sent_at'=>$sentAt]);
