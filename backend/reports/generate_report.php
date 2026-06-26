<?php
// ============================================================
//  PMTS – POST /reports/generate_report.php
//  Generate and store a report snapshot
//  Body: { "report_title": "May 2026 Summary", "report_type": "procurement_summary", "filters": {...} }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/audit_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $authUser = requireRole(['it_admin', 'director', 'procurement_officer', 'accountant']);
    $input    = json_decode(file_get_contents('php://input'), true);

    $title      = trim($input['report_title'] ?? '');
    $reportType = trim($input['report_type']  ?? 'procurement_summary');
    $filters    = $input['filters'] ?? [];

    $validTypes = ['procurement_summary', 'delay_analysis', 'financial_summary',
                   'vendor_performance', 'audit_report', 'custom'];

    if (!$title) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'report_title is required.']);
        exit;
    }
    if (!in_array($reportType, $validTypes, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid report_type.']);
        exit;
    }

    $pdo        = getPDO();
    $reportData = [];

    switch ($reportType) {
        case 'procurement_summary':
            $stmt = $pdo->query(
                "SELECT current_status, procurement_type, COUNT(*) AS count, SUM(estimated_amount) AS total_value
                 FROM procurements
                 GROUP BY current_status, procurement_type
                 ORDER BY current_status"
            );
            $reportData['by_status_type'] = $stmt->fetchAll();

            $stmt2 = $pdo->query(
                "SELECT COUNT(*) AS total, SUM(estimated_amount) AS total_value FROM procurements"
            );
            $reportData['totals'] = $stmt2->fetch();
            break;

        case 'delay_analysis':
            $stmt = $pdo->query(
                "SELECT da.*, p.procurement_id AS proc_ref_id, p.title
                 FROM delay_alerts da
                 JOIN procurements p ON p.id = da.procurement_id
                 WHERE da.status = 'active'
                 ORDER BY FIELD(da.risk_level,'critical','high','medium','low')"
            );
            $reportData['active_delays'] = $stmt->fetchAll();

            $stmt2 = $pdo->query(
                "SELECT risk_level, COUNT(*) AS count FROM delay_alerts WHERE status = 'active' GROUP BY risk_level"
            );
            $reportData['by_risk_level'] = $stmt2->fetchAll();
            break;

        case 'financial_summary':
            $stmt = $pdo->query(
                "SELECT p.procurement_id, p.title, p.estimated_amount,
                        fa.approved_amount, fa.budget_available, fa.approval_status,
                        u.full_name AS accountant_name
                 FROM financial_approvals fa
                 JOIN procurements p ON p.id = fa.procurement_id
                 LEFT JOIN users u ON u.id = fa.accountant_id
                 ORDER BY fa.approved_at DESC"
            );
            $reportData['financial_approvals'] = $stmt->fetchAll();

            $stmt2 = $pdo->query(
                "SELECT SUM(po.amount) AS total_po_value, COUNT(*) AS po_count FROM purchase_orders po WHERE po.status != 'cancelled'"
            );
            $reportData['po_summary'] = $stmt2->fetch();
            break;

        case 'vendor_performance':
            $stmt = $pdo->query(
                "SELECT s.supplier_name, COUNT(DISTINCT ps.procurement_id) AS bids_submitted,
                        COUNT(DISTINCT po.id) AS pos_awarded,
                        SUM(po.amount) AS total_awarded_value
                 FROM suppliers s
                 LEFT JOIN procurement_suppliers ps ON ps.supplier_id = s.id
                 LEFT JOIN purchase_orders po ON po.supplier_id = s.id
                 GROUP BY s.id, s.supplier_name
                 ORDER BY pos_awarded DESC"
            );
            $reportData['vendor_stats'] = $stmt->fetchAll();
            break;

        case 'audit_report':
            $stmt = $pdo->prepare(
                "SELECT al.*, u.full_name AS user_name, u.role
                 FROM audit_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 ORDER BY al.created_at DESC
                 LIMIT 500"
            );
            $stmt->execute();
            $reportData['audit_logs'] = $stmt->fetchAll();
            break;

        default:
            $reportData['raw_filters'] = $filters;
            break;
    }

    $reportData['generated_at'] = date('Y-m-d H:i:s');
    $reportData['generated_by'] = $authUser['email'];
    $reportData['filters']      = $filters;

    // Store report
    $insertStmt = $pdo->prepare(
        "INSERT INTO reports (report_title, report_type, generated_by, report_data)
         VALUES (?, ?, ?, ?)"
    );
    $insertStmt->execute([$title, $reportType, $authUser['sub'], json_encode($reportData)]);
    $reportId = (int) $pdo->lastInsertId();

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'GENERATE_REPORT',
        'reports',
        "Generated report: $title (Type: $reportType)"
    );

    http_response_code(201);
    echo json_encode([
        'success'     => true,
        'message'     => "Report '$title' generated successfully.",
        'report_id'   => $reportId,
        'report_data' => $reportData,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to generate report.']);
    error_log("PMTS GenerateReport Error: " . $e->getMessage());
}
