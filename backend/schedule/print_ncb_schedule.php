<?php
// ============================================================
//  PMTS – GET /schedule/print_ncb_schedule.php
//  Printable procurement time schedule after planned/actual dates are saved.
//  Legacy filename kept for existing frontend links.
//  URL: /backend/schedule/print_ncb_schedule.php?procurement_id=5&token=JWT_TOKEN
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/ncb_tasks.php';
require_once __DIR__ . '/task_file_tracking_helper.php';

header('Content-Type: text/html; charset=UTF-8');

$token = $_GET['token'] ?? '';
$user = verifyJWT($token);
if (!$user) {
    http_response_code(401);
    echo '<h2>Unauthorized. Please login first.</h2>';
    exit;
}

$procId = (int) ($_GET['procurement_id'] ?? 0);
if (!$procId) {
    http_response_code(400);
    echo '<h2>procurement_id is required.</h2>';
    exit;
}

try {
    $pdo = getPDO();

    $procStmt = $pdo->prepare("SELECT * FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procId]);
    $proc = $procStmt->fetch();

    if (!$proc) {
        http_response_code(404);
        echo '<h2>Procurement not found.</h2>';
        exit;
    }

    $columnStmt = $pdo->query("SHOW COLUMNS FROM procurement_time_schedule LIKE 'sort_order'");
    $orderBy = $columnStmt->fetch() ? 'COALESCE(sort_order, id) ASC, id ASC' : 'id ASC';

    $stmt = $pdo->prepare("SELECT * FROM procurement_time_schedule WHERE procurement_id = ? ORDER BY {$orderBy}");
    $stmt->execute([$procId]);
    $tasks = $stmt->fetchAll();
    $trackingSummaries = pmtsGetTaskFileTrackingSummaries($pdo, $procId);

    foreach ($tasks as &$task) {
        $task['responsible_role'] = getResponsibleRoleForTask($task['task_name']);
        $task['file_tracking_summary'] = $trackingSummaries[(int) $task['id']] ?? pmtsEmptyTaskFileTrackingSummary();
    }
    unset($task);

} catch (PDOException $e) {
    http_response_code(500);
    echo '<h2>Failed to load printable schedule.</h2>';
    exit;
}

function e($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmtDate($value): string {
    if (!$value) return '';
    return e(substr((string) $value, 0, 10));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Procurement Time Schedule - <?= e($proc['procurement_id']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 24px; }
        h1 { text-align: center; font-size: 20px; margin-bottom: 4px; }
        h2 { text-align: center; font-size: 15px; margin-top: 0; font-weight: normal; }
        .top-actions { margin-bottom: 16px; text-align: right; }
        button { padding: 8px 14px; cursor: pointer; }
        .details { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .details td { border: 1px solid #444; padding: 7px; font-size: 13px; }
        .details .label { font-weight: bold; width: 20%; background: #f2f2f2; }
        table.schedule { width: 100%; border-collapse: collapse; }
        table.schedule th, table.schedule td { border: 1px solid #333; padding: 6px; font-size: 12px; vertical-align: top; }
        table.schedule th { background: #eaeaea; text-align: center; }
        .payment-box { margin-top: 14px; border: 1px solid #333; padding: 10px; font-size: 13px; font-weight: bold; width: 35%; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .signatures th, .signatures td { border: 1px solid #333; padding: 10px; height: 34px; font-size: 12px; }
        .footer-note { margin-top: 12px; font-size: 11px; color: #444; }
        @media print {
            .top-actions { display: none; }
            body { margin: 10mm; }
            @page { size: A4 landscape; margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="top-actions">
        <button onclick="window.print()">Print Schedule</button>
    </div>

    <h1>Procurement Time Schedule</h1>
    <h2><?= e($proc['procurement_type']) ?> Procurement</h2>

    <table class="details">
        <tr>
            <td class="label">Procurement Title</td>
            <td><?= e($proc['title']) ?></td>
            <td class="label">Tender / Reference No.</td>
            <td><?= e($proc['tender_number']) ?></td>
        </tr>
        <tr>
            <td class="label">Procurement Type</td>
            <td><?= e($proc['procurement_type']) ?></td>
            <td class="label">Category</td>
            <td><?= e($proc['category']) ?></td>
        </tr>
        <tr>
            <td class="label">Estimated Amount</td>
            <td><?= e($proc['estimated_amount']) ?></td>
            <td class="label">Received Date</td>
            <td><?= fmtDate($proc['received_date']) ?></td>
        </tr>
        <tr>
            <td class="label">PMTS Procurement ID</td>
            <td><?= e($proc['procurement_id']) ?></td>
            <td class="label">Printed Date</td>
            <td><?= date('Y-m-d') ?></td>
        </tr>
    </table>

    <table class="schedule">
        <thead>
            <tr>
                <th style="width: 4%;">No.</th>
                <th style="width: 31%;">Activity / Milestone</th>
                <th style="width: 20%;">Responsible Role / Committee</th>
                <th style="width: 11%;">Planned Date</th>
                <th style="width: 11%;">Actual Date</th>
                <th style="width: 9%;">Status</th>
                <th style="width: 12%;">File Tracking</th>
                <th style="width: 12%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $index => $task): ?>
                <tr>
                    <td style="text-align:center;"><?= $index + 1 ?></td>
                    <td><?= e($task['task_name']) ?></td>
                    <td><?= e($task['responsible_role']) ?></td>
                    <td><?= fmtDate($task['planned_date']) ?></td>
                    <td style="text-align:center;"><?= (int) ($task['allowed_delay_days'] ?? 0) ?> day(s)</td>
                    <td><?= fmtDate($task['actual_date']) ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', $task['status']))) ?></td>
                    <td>
                        Total: <?= (int) ($task['file_tracking_summary']['total_files'] ?? 0) ?><br>
                        Done: <?= (int) ($task['file_tracking_summary']['completed_files'] ?? 0) ?><br>
                        Pending: <?= (int) ($task['file_tracking_summary']['pending_files'] ?? 0) ?>
                    </td>
                    <td><?= e($task['remarks']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="payment-box">
        Payment Date: <?= fmtDate($proc['payment_date'] ?? '') ?>
    </div>

    <table class="signatures">
        <tr>
            <th>Role</th>
            <th>Name</th>
            <th>Signature</th>
            <th>Date</th>
        </tr>
        <tr>
            <td>Prepared By - Procurement Officer</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Checked By</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Reviewed By - Director</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <p class="footer-note">Generated by PMTS - Badulla Hospital.</p>
</body>
</html>
