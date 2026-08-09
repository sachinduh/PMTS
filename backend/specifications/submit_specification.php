<?php
// ============================================================
// PMTS – POST /specifications/submit_specification.php
// Saves a specification committee review against a procurement.
// Body: { spec_id: 5, review_notes: "...", status: "reviewed" }
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

function pmtsEnsureSpecificationReviewTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS specification_reviews (
          id INT(11) NOT NULL AUTO_INCREMENT,
          procurement_id INT(11) NOT NULL,
          reviewer_id INT(11) NOT NULL,
          review_notes TEXT NOT NULL,
          status ENUM('pending','reviewed','approved','rejected') NOT NULL DEFAULT 'reviewed',
          reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_spec_review_proc (procurement_id),
          KEY idx_spec_review_reviewer (reviewer_id),
          CONSTRAINT fk_spec_review_proc FOREIGN KEY (procurement_id) REFERENCES procurements(id)
            ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT fk_spec_review_user FOREIGN KEY (reviewer_id) REFERENCES users(id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Specification Committee reviews for procurement files'"
    );
}

try {
    $authUser = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $procurementId = (int) ($input['spec_id'] ?? $input['procurement_id'] ?? 0);
    $reviewNotes = trim((string) ($input['review_notes'] ?? ''));
    $status = trim((string) ($input['status'] ?? 'reviewed'));
    $allowedStatuses = ['pending', 'reviewed', 'approved', 'rejected'];

    if (!$procurementId || $reviewNotes === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Specification/procurement and review notes are required.']);
        exit;
    }

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid specification review status.']);
        exit;
    }

    if (!in_array($authUser['role'] ?? '', ['specification_committee', 'procurement_officer', 'it_admin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Your role cannot submit specification reviews.']);
        exit;
    }

    $pdo = getPDO();
    pmtsEnsureSpecificationReviewTable($pdo);

    $procStmt = $pdo->prepare("SELECT id, procurement_id, title FROM procurements WHERE id = ? LIMIT 1");
    $procStmt->execute([$procurementId]);
    $procurement = $procStmt->fetch(PDO::FETCH_ASSOC);

    if (!$procurement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Procurement not found.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO specification_reviews (procurement_id, reviewer_id, review_notes, status)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$procurementId, $authUser['sub'], $reviewNotes, $status]);

    createAuditLog(
        $pdo,
        $authUser['sub'],
        'SUBMIT_SPECIFICATION_REVIEW',
        'specification_reviews',
        "Specification review submitted for {$procurement['procurement_id']} with status {$status}"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Specification review submitted successfully.',
        'id' => (int) $pdo->lastInsertId(),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit specification review.']);
    error_log('PMTS SubmitSpecification Error: ' . $e->getMessage());
}
