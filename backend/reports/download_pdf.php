<?php
require_once __DIR__ . '/../config/db.php';
$pdo=getPDO(); $id=$_GET['id'] ?? 0; $stmt=$pdo->prepare("SELECT * FROM reports WHERE id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r) die('Report not found');
header('Content-Type: text/html; charset=UTF-8');
?><!doctype html><html><head><title><?=htmlspecialchars($r['report_title'])?></title><style>body{font-family:Arial;margin:40px}.center{text-align:center}pre{white-space:pre-wrap;border:1px solid #ddd;padding:15px}@media print{button{display:none}}</style></head><body><button onclick="window.print()">Print / Save as PDF</button><div class="center"><h2>PMTS - Badulla Hospital</h2><h3><?=htmlspecialchars($r['report_title'])?></h3></div><p><b>Type:</b> <?=htmlspecialchars($r['report_type'])?></p><p><b>Generated:</b> <?=htmlspecialchars($r['created_at'])?></p><pre><?=htmlspecialchars($r['report_data'] ?? '')?></pre></body></html>
