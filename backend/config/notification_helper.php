<?php
// ============================================================
// PMTS notification helper
// Sends in-app notifications to individual users or all active
// users in a given role.
// ============================================================

if (!function_exists('pmtsNotifyUser')) {
    function pmtsNotifyUser(PDO $pdo, int $userId, string $title, string $message, string $type = 'info'): bool
    {
        if ($userId <= 0) return false;

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                 VALUES (?, ?, ?, ?, 0, NOW())"
            );
            $stmt->execute([$userId, $title, $message, $type]);
            return true;
        } catch (Throwable $e) {
            error_log('PMTS NotifyUser Error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('pmtsNotifyRole')) {
    function pmtsNotifyRole(PDO $pdo, string $role, string $title, string $message, string $type = 'info', array $excludeUserIds = []): int
    {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND status = 'active'");
        $stmt->execute([$role]);

        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $userId = (int) $user['id'];
            if (in_array($userId, $excludeUserIds, true)) {
                continue;
            }
            if (pmtsNotifyUser($pdo, $userId, $title, $message, $type)) {
                $count++;
            }
        }
        return $count;
    }
}

if (!function_exists('pmtsNotifyRoles')) {
    function pmtsNotifyRoles(PDO $pdo, array $roles, string $title, string $message, string $type = 'info', array $excludeUserIds = []): int
    {
        $count = 0;
        foreach (array_unique($roles) as $role) {
            $count += pmtsNotifyRole($pdo, $role, $title, $message, $type, $excludeUserIds);
        }
        return $count;
    }
}

if (!function_exists('pmtsResponsibleRolesForStatus')) {
    function pmtsResponsibleRolesForStatus(string $status): array
    {
        $map = [
            'draft'                  => ['procurement_officer'],
            'submitted'              => ['procurement_officer'],
            'under_review'           => ['procurement_officer'],
            'specification_approval' => ['specification_committee'],
            'tender_preparation'     => ['procurement_officer'],
            'advertised'             => ['procurement_officer'],
            'bid_received'           => ['procurement_officer'],
            'technical_evaluation'   => ['tec_member'],
            'bid_evaluation'         => ['bec_member'],
            'financial_evaluation'   => ['accountant'],
            'awarded'                => ['procurement_officer', 'accountant'],
            'purchase_order_issued'  => ['procurement_officer', 'accountant'],
            'contract_signed'        => ['procurement_officer', 'accountant'],
            'completed'              => ['director', 'procurement_officer', 'accountant'],
            'cancelled'              => ['director', 'procurement_officer'],
            'on_hold'                => ['director', 'procurement_officer'],
        ];

        return $map[$status] ?? ['director', 'procurement_officer'];
    }
}
