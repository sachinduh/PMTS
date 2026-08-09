<?php
// ============================================================
// PMTS – GET /alerts/get_delay_alerts.php
// Query: ?procurement_id=5&status=active&risk_level=critical
// Returns Director delay alerts with task, color, days late and
// email/notification delivery information.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../procurements/tracking_helper.php';
require_once __DIR__ . '/delay_alert_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    requireAuth();
    $pdo = getPDO();
    pmtsEnsureDelayAlertColumns($pdo);

    $procId    = (int) ($_GET['procurement_id'] ?? 0);
    $status    = trim($_GET['status'] ?? 'active');
    $riskLevel = trim($_GET['risk_level'] ?? '');
    $color     = trim($_GET['alert_color'] ?? '');

    $where  = [];
    $params = [];

    if ($procId) {
        $where[]  = 'da.procurement_id = ?';
        $params[] = $procId;
    }
    if ($status !== '' && $status !== 'all') {
        $where[]  = 'da.status = ?';
        $params[] = $status;
    }
    if ($riskLevel !== '') {
        $where[]  = 'da.risk_level = ?';
        $params[] = $riskLevel;
    }
    if ($color !== '') {
        $where[]  = 'da.alert_color = ?';
        $params[] = $color;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare(
        "SELECT da.*,
                p.procurement_id AS proc_ref_id,
                p.procurement_id AS procurement_code,
                p.title,
                p.title AS procurement_title,
                p.tender_number,
                p.procurement_type,
                p.current_status,
                pts.task_name,
                pts.planned_date,
                pts.actual_date AS task_actual_date,
                pts.status AS task_status
         FROM delay_alerts da
         LEFT JOIN procurements p ON p.id = da.procurement_id
         LEFT JOIN procurement_time_schedule pts ON pts.id = da.schedule_task_id
         $whereClause
         ORDER BY
           FIELD(da.alert_color, 'red','yellow'),
           FIELD(da.risk_level, 'critical','high','medium','low'),
           da.alert_date DESC,
           da.created_at DESC"
    );
    $stmt->execute($params);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'active' => 0,
        'critical' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0,
        'yellow' => 0,
        'red' => 0,
        'emails_sent' => 0,
        'emails_failed' => 0,
    ];

    foreach ($alerts as &$a) {
        $stage = pmtsTrackingStageForStatus($a['current_status'] ?? 'draft');
        $a['current_stage'] = $stage['label'];
        $a['current_stage_label'] = $stage['label'];
        $a['tracking_stage'] = $stage;
        $a['delayed_days'] = (int) ($a['delayed_days'] ?? 0);
        $a['alert_label'] = ($a['alert_color'] ?? 'yellow') === 'red' ? 'Red Delay' : 'Yellow Delay';

        if (($a['status'] ?? '') === 'active') $summary['active']++;
        $summary[$a['risk_level']] = ($summary[$a['risk_level']] ?? 0) + 1;
        $summary[$a['alert_color']] = ($summary[$a['alert_color']] ?? 0) + 1;
        if (($a['email_status'] ?? '') === 'sent') $summary['emails_sent']++;
        if (($a['email_status'] ?? '') === 'failed') $summary['emails_failed']++;
    }
    unset($a);

    echo json_encode([
        'success' => true,
        'alerts' => $alerts,
        'data' => $alerts,
        'summary' => $summary,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch delay alerts.']);
    error_log('PMTS GetDelayAlerts Error: ' . $e->getMessage());
}
