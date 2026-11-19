<?php
declare(strict_types=1);

function rbacHasRole(array $allowedRoles): bool
{
    $role = (string)($_SESSION['user_role'] ?? '');
    return isset($_SESSION['admin_logged_in'])
        && $_SESSION['admin_logged_in'] === true
        && in_array($role, $allowedRoles, true);
}

function rbacRequireRole(array $allowedRoles, string $redirect = 'login.php'): void
{
    if (!rbacHasRole($allowedRoles)) {
        header('Location: ' . $redirect);
        exit();
    }
}

function rbacSessionTarget(): string
{
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return 'login.php';
    }
    $role = (string)($_SESSION['user_role'] ?? '');
    if ($role === 'Administrator') {
        return 'Admin_Dashboard/admin_dashboard.php';
    }
    if ($role === 'Staff') {
        return 'Staff_Dashboard/staff_dashboard.php';
    }
    if ($role === 'Teacher') {
        return 'Teacher_Dashboard/teacher_dashboard.php';
    }
    if ($role === 'Student') {
        return 'Student_Dashboard/student_dashboard.php';
    }
    return 'login.php';
}

function rbacEnsureTeacherReturnSchema(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS teacher_course_assignments (
            id INT NOT NULL AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            course_year VARCHAR(50) NOT NULL,
            can_view_passes TINYINT(1) NOT NULL DEFAULT 0,
            can_view_authorization TINYINT(1) NOT NULL DEFAULT 0,
            can_view_return_records TINYINT(1) NOT NULL DEFAULT 0,
            can_confirm_returns TINYINT(1) NOT NULL DEFAULT 0,
            can_review TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_teacher_course (teacher_id, course_year),
            CONSTRAINT fk_tca_teacher FOREIGN KEY (teacher_id) REFERENCES staff(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $assignmentColumns = [];
    $assignmentResult = $conn->query('SHOW COLUMNS FROM teacher_course_assignments');
    while ($column = $assignmentResult->fetch_assoc()) {
        $assignmentColumns[$column['Field']] = true;
    }
    $assignmentResult->free();

    if (!isset($assignmentColumns['can_confirm_returns'])) {
        $conn->query(
            'ALTER TABLE teacher_course_assignments
             ADD COLUMN can_confirm_returns TINYINT(1) NOT NULL DEFAULT 0
             AFTER can_view_return_records'
        );
    }

    $passColumns = [];
    $passResult = $conn->query('SHOW COLUMNS FROM clinic_passes');
    while ($column = $passResult->fetch_assoc()) {
        $passColumns[$column['Field']] = true;
    }
    $passResult->free();

    if (!isset($passColumns['teacher_confirmed_return_at'])) {
        $conn->query(
            'ALTER TABLE clinic_passes
             ADD COLUMN teacher_confirmed_return_at DATETIME DEFAULT NULL
             AFTER returned_at'
        );
    }

    if (!isset($passColumns['return_confirmed_by_teacher_id'])) {
        $conn->query(
            'ALTER TABLE clinic_passes
             ADD COLUMN return_confirmed_by_teacher_id INT DEFAULT NULL
             AFTER returned_at'
        );
    }
}

function rbacEnsureAuditTable(mysqli $conn): void
{
    @$conn->query(
        'CREATE TABLE IF NOT EXISTS audit_trail (
            id INT NOT NULL AUTO_INCREMENT,
            actor_name VARCHAR(100) NOT NULL,
            actor_role VARCHAR(50) NOT NULL,
            actor_user_id INT DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            module VARCHAR(100) NOT NULL,
            record_type VARCHAR(100) DEFAULT NULL,
            record_id VARCHAR(100) DEFAULT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_audit_created (created_at),
            INDEX idx_audit_actor_role (actor_role),
            INDEX idx_audit_module (module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function rbacLog(mysqli $conn, string $action, string $module, string $recordType = '', string $recordId = '', string $details = ''): void
{
    rbacEnsureAuditTable($conn);
    $actorName = (string)($_SESSION['admin_username'] ?? 'Unknown');
    $actorRole = (string)($_SESSION['user_role'] ?? 'Unknown');
    $actorUserId = (int)($_SESSION['user_id'] ?? 0);
    $ipAddress = (string)(
        $_SERVER['REMOTE_ADDR'] ??
        $_SERVER['HTTP_X_FORWARDED_FOR'] ??
        ''
    );
    $ipAddress = substr(trim($ipAddress), 0, 45);
    if ($actorUserId < 1) {
        $actorUserId = 0;
    }

    $stmt = @$conn->prepare(
        'INSERT INTO audit_trail
         (actor_name, actor_role, actor_user_id, action, module, record_type, record_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $recordType = $recordType !== '' ? $recordType : null;
    $recordId = $recordId !== '' ? $recordId : null;
    $details = $details !== '' ? $details : null;
    $stmt->bind_param(
        'ssissssss',
        $actorName,
        $actorRole,
        $actorUserId,
        $action,
        $module,
        $recordType,
        $recordId,
        $details,
        $ipAddress
    );
    @$stmt->execute();
    $stmt->close();
}
