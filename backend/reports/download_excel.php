<?php
require_once __DIR__ . '/../config/db.php';
$pdo=getPDO(); $id=$_GET['id'] ?? 0; $stmt=$pdo->prepare("SELECT * FROM reports WHERE id=?"); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r) die('Report not found');
header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="report_'.$id.'.csv"');
$out=fopen('php://output','w'); fputcsv($out,['Report Title','Report Type','Generated At','Report Data']); fputcsv($out,[$r['report_title'],$r['report_type'],$r['created_at'],$r['report_data']]); fclose($out);
