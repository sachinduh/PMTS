<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/committee_appointment_helper.php';
$pdo=getPDO(); pmtsEnsureCommitteeLettersSchema($pdo);
$id=$_GET['id'] ?? 0;
$stmt=$pdo->prepare("SELECT l.*, p.title, p.tender_number FROM committee_letters l LEFT JOIN procurements p ON p.id=l.procurement_id WHERE l.id=?");
$stmt->execute([$id]); $l=$stmt->fetch();
if(!$l){ die('Letter not found'); }
$committeeLabel = pmtsNormalizeCommitteeType($l['committee_type']) === 'BEC' ? 'Bid Evaluation Committee (BEC)' : 'Specification Preparation Committee';
header('Content-Type: text/html; charset=UTF-8');
?><!doctype html><html><head><title>Committee Appointment Letter</title><style>body{font-family:Arial;margin:40px;line-height:1.6}.center{text-align:center}.meta{margin:25px 0}.sign{margin-top:60px}@media print{button{display:none}}</style></head><body><button onclick="window.print()">Print / Save as PDF</button><div class="center"><h2>Badulla Hospital</h2><h3>Committee Appointment Letter</h3></div><p>Date: <?=htmlspecialchars($l['letter_date'])?></p><p>To: <b><?=htmlspecialchars($l['member_name'])?></b><br><?=htmlspecialchars($l['member_designation'] ?? '')?></p><div class="meta"><p><b>Procurement:</b> <?=htmlspecialchars($l['title'] ?? ('ID '.$l['procurement_id']))?></p><p><b>Tender No:</b> <?=htmlspecialchars($l['tender_number'] ?? '')?></p><p><b>Committee:</b> <?=htmlspecialchars($committeeLabel)?></p><p><b>Appointment:</b> <?=htmlspecialchars($l['committee_position'] ?? 'Member')?></p><p><b>Planned Appointment Date:</b> <?=htmlspecialchars($l['appointment_planned_date'] ?? $l['letter_date'] ?? '')?></p></div><p><?=nl2br(htmlspecialchars($l['letter_body']))?></p><p class="sign">........................................<br>Authorized Officer<br>Badulla Hospital</p></body></html>
