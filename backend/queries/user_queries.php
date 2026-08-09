<?php
// ============================================================
// PMTS – User/Auth database query helper
// Keep SQL statements here instead of inside endpoint files.
// Endpoint files should call these functions and handle response logic only.
// ============================================================

function pmtsDbColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function pmtsRoleLabel(string $role): string
{
    $labels = [
        'director' => 'Director',
        'accountant' => 'Accountant',
        'procurement_officer' => 'Procurement Officer',
        'bec_member' => 'BEC Committee Member',
        'specification_committee' => 'Specification Committee Member',
        'it_admin' => 'IT Admin',
        'pending' => 'Pending',
    ];
    return $labels[$role] ?? $role;
}

function pmtsAllowedRegistrationRoles(): array
{
    return [
        'director',
        'accountant',
        'procurement_officer',
        'bec_member',
        'specification_committee',
        'it_admin',
    ];
}

function pmtsAllowedApproverRoles(): array
{
    // IT Admin is intentionally excluded. Public approval cannot create another IT Admin.
    return [
        'director',
        'accountant',
        'procurement_officer',
        'bec_member',
        'specification_committee',
    ];
}

function pmtsAllowedAutoItAdminEmails(): array
{
    // Retained for backward compatibility. The first IT Admin is determined
    // only by whether an active, role-locked IT Admin already exists.
    $envEmails = getenv('PMTS_AUTO_IT_ADMIN_EMAILS') ?: '';
    if ($envEmails === '') {
        return [];
    }

    return array_values(array_filter(array_unique(array_map(
        static fn(string $email): string => strtolower(trim($email)),
        explode(',', $envEmails)
    ))));
}

function pmtsEnsureAccountSecurityColumns(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'"
    );
    $stmt->execute();
    $columns = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    $addColumn = function (string $name, string $sql) use ($pdo, $columns): void {
        if (!isset($columns[$name])) {
            $pdo->exec($sql);
        }
    };

    $addColumn('failed_login_attempts', "ALTER TABLE users ADD COLUMN failed_login_attempts INT(11) NOT NULL DEFAULT 0 AFTER status");
    $addColumn('last_failed_login_at', "ALTER TABLE users ADD COLUMN last_failed_login_at TIMESTAMP NULL DEFAULT NULL AFTER failed_login_attempts");
    $addColumn('account_locked', "ALTER TABLE users ADD COLUMN account_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER last_failed_login_at");
    $addColumn('locked_at', "ALTER TABLE users ADD COLUMN locked_at TIMESTAMP NULL DEFAULT NULL AFTER account_locked");
    $addColumn('locked_reason', "ALTER TABLE users ADD COLUMN locked_reason VARCHAR(255) DEFAULT NULL AFTER locked_at");
    $addColumn('unlocked_by', "ALTER TABLE users ADD COLUMN unlocked_by INT(11) DEFAULT NULL AFTER locked_reason");
    $addColumn('unlocked_at', "ALTER TABLE users ADD COLUMN unlocked_at TIMESTAMP NULL DEFAULT NULL AFTER unlocked_by");
    $addColumn('profile_picture', "ALTER TABLE users ADD COLUMN profile_picture LONGTEXT DEFAULT NULL AFTER organization");

    $indexStmt = $pdo->prepare("SHOW INDEX FROM users WHERE Key_name = 'idx_users_account_locked'");
    $indexStmt->execute();
    if (!$indexStmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD INDEX idx_users_account_locked (account_locked)");
    }

    $done = true;
}

function pmtsEnsureRegistrationRoleColumns(PDO $pdo): void
{
    if (!pmtsDbColumnExists($pdo, 'users', 'requested_role')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN requested_role ENUM('director','accountant','procurement_officer','bec_member','specification_committee','it_admin') DEFAULT NULL COMMENT 'Requested role selected during registration' AFTER role");
    } else {
        $columnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'requested_role'");
        $column = $columnStmt ? $columnStmt->fetch(PDO::FETCH_ASSOC) : null;
        $type = strtolower((string)($column['Type'] ?? ''));
        if ($type !== '' && strpos($type, 'it_admin') === false) {
            $pdo->exec("ALTER TABLE users MODIFY requested_role ENUM('director','accountant','procurement_officer','bec_member','specification_committee','it_admin') DEFAULT NULL COMMENT 'Requested role selected during registration'");
        }
    }

    if (!pmtsDbColumnExists($pdo, 'users', 'role_locked')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role_locked TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = assigned role is fixed and cannot be changed/removed' AFTER requested_role");
    }
}

function pmtsActiveLockedItAdminCount(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'it_admin' AND status = 'active' AND role_locked = 1");
    return (int) $stmt->fetchColumn();
}

function pmtsActiveItAdminCount(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'it_admin' AND status = 'active'");
    return (int) $stmt->fetchColumn();
}

function pmtsCanAutoApproveItAdmin(PDO $pdo, string $email): bool
{
    // Only ONE IT Admin is allowed in PMTS.
    // The first IT Admin can self-register only when no active, role-locked IT Admin exists.
    // Allowed email lists are no longer used to create extra IT Admin accounts.
    return pmtsActiveLockedItAdminCount($pdo) === 0;
}

function pmtsPrimaryItAdminId(PDO $pdo): ?int
{
    $stmt = $pdo->query(
        "SELECT id
         FROM users
         WHERE role = 'it_admin'
           AND status = 'active'
           AND role_locked = 1
         ORDER BY id ASC
         LIMIT 1"
    );
    $id = $stmt ? $stmt->fetchColumn() : false;
    return $id ? (int) $id : null;
}

function pmtsIsPrimaryItAdmin(PDO $pdo, int $userId): bool
{
    $primaryId = pmtsPrimaryItAdminId($pdo);
    return $primaryId !== null && $primaryId === $userId;
}

function pmtsRegistrationRoles(PDO $pdo): array
{
    $roles = [
        ['value' => 'director', 'label' => 'Director'],
        ['value' => 'accountant', 'label' => 'Accountant'],
        ['value' => 'procurement_officer', 'label' => 'Procurement Officer'],
        ['value' => 'bec_member', 'label' => 'BEC Committee Member'],
        ['value' => 'specification_committee', 'label' => 'Specification Committee Member'],
    ];

    if (pmtsActiveLockedItAdminCount($pdo) === 0) {
        $roles[] = ['value' => 'it_admin', 'label' => 'IT Admin'];
    }

    return $roles;
}

function pmtsFetchUserByEmailForLogin(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, full_name, email, phone, nic, user_type, department, organization, profile_picture,
                password, role, requested_role, role_locked, status,
                failed_login_attempts, last_failed_login_at, account_locked,
                locked_at, locked_reason
         FROM users
         WHERE email = ?
         LIMIT 1"
    );
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function pmtsFetchAuthUserById(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, full_name, email, phone, nic, user_type, department, organization, profile_picture,
                role, requested_role, role_locked, status,
                failed_login_attempts, last_failed_login_at, account_locked,
                locked_at, locked_reason, unlocked_by, unlocked_at
         FROM users
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function pmtsRecordFailedLogin(PDO $pdo, int $userId, int $failedAttempts, bool $lockAccount): void
{
    if ($lockAccount) {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET failed_login_attempts = ?,
                 last_failed_login_at = NOW(),
                 account_locked = 1,
                 locked_at = NOW(),
                 locked_reason = 'Too many failed password attempts',
                 unlocked_by = NULL,
                 unlocked_at = NULL
             WHERE id = ?"
        );
        $stmt->execute([$failedAttempts, $userId]);
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE users
         SET failed_login_attempts = ?,
             last_failed_login_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$failedAttempts, $userId]);
}

function pmtsResetFailedLogin(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        "UPDATE users
         SET failed_login_attempts = 0,
             last_failed_login_at = NULL
         WHERE id = ?"
    );
    $stmt->execute([$userId]);
}

function pmtsEmailExists(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([strtolower(trim($email))]);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function pmtsInsertRegisteredUser(PDO $pdo, array $userData): int
{
    $columns = [
        'full_name',
        'email',
        'phone',
        'nic',
        'user_type',
        'department',
        'organization',
        'password',
        'role',
        'status',
    ];

    $values = [
        $userData['full_name'],
        $userData['email'],
        $userData['phone'],
        $userData['nic'],
        $userData['user_type'],
        $userData['department'],
        $userData['organization'],
        $userData['password'],
        $userData['role'],
        $userData['status'],
    ];

    if (pmtsDbColumnExists($pdo, 'users', 'requested_role')) {
        $columns[] = 'requested_role';
        $values[] = $userData['requested_role'];
    }
    if (pmtsDbColumnExists($pdo, 'users', 'role_locked')) {
        $columns[] = 'role_locked';
        $values[] = (int) $userData['role_locked'];
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $columnSql = '`' . implode('`, `', $columns) . '`';
    $stmt = $pdo->prepare("INSERT INTO users ($columnSql) VALUES ($placeholders)");
    $stmt->execute($values);

    return (int) $pdo->lastInsertId();
}

function pmtsInsertFirstItAdmin(PDO $pdo, array $adminData): int
{
    $fields = ['full_name', 'email', 'password', 'user_type', 'role', 'status'];
    $values = [
        $adminData['full_name'],
        $adminData['email'],
        $adminData['password'],
        $adminData['user_type'],
        'it_admin',
        'active',
    ];

    if (pmtsDbColumnExists($pdo, 'users', 'requested_role')) {
        $fields[] = 'requested_role';
        $values[] = null;
    }
    if (pmtsDbColumnExists($pdo, 'users', 'role_locked')) {
        $fields[] = 'role_locked';
        $values[] = 1;
    }
    if (pmtsDbColumnExists($pdo, 'users', 'failed_login_attempts')) {
        $fields[] = 'failed_login_attempts';
        $values[] = 0;
    }
    if (pmtsDbColumnExists($pdo, 'users', 'account_locked')) {
        $fields[] = 'account_locked';
        $values[] = 0;
    }

    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $sql = 'INSERT INTO users (`' . implode('`, `', $fields) . '`) VALUES (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    return (int) $pdo->lastInsertId();
}
