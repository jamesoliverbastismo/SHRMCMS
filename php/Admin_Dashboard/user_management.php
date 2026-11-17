
<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    ($_SESSION['user_role'] ?? '') !== 'Administrator'
) {
    header('Location: ../login.php');
    exit();
}

$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectUsers(string $message, string $type = 'success'): void
{
    header(
        'Location: user_management.php?message=' .
        urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

// CSRF protection
if (empty($_SESSION['user_management_csrf'])) {
    $_SESSION['user_management_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['user_management_csrf'];

$message = trim($_GET['message'] ?? '');
$messageType = ($_GET['type'] ?? 'success') === 'error'
    ? 'error'
    : 'success';

$search = trim($_GET['search'] ?? '');
$editingUser = null;

// =============================================
// HANDLE ADD, EDIT, STATUS CHANGE, AND DELETE
// =============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)) {
        redirectUsers('Invalid form submission. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    // -----------------------------------------
    // ADD OR UPDATE USER
    // -----------------------------------------
    if ($action === 'save') {
        $idValue = $_POST['id'] ?? 0;
        $id = filter_var($idValue, FILTER_VALIDATE_INT);
        $id = $id === false ? 0 : (int)$id;
        $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
        $idNumber = is_string($_POST['id_number'] ?? null) ? trim($_POST['id_number']) : '';
        $emailInput = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $email = $emailInput === '' ? null : $emailInput;
        $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($name === '' || $idNumber === '') {
            redirectUsers('Name and login ID are required.', 'error');
        }
        if (mb_strlen($name) > 100 || strlen($idNumber) > 50) {
            redirectUsers('Name or login ID exceeds the allowed length.', 'error');
        }
        if (!in_array($role, ['Staff', 'Student'], true)) {
            redirectUsers('Please select a valid account role.', 'error');
        }
        if ($role === 'Staff' && $email === null) {
            redirectUsers('Staff accounts require an email address for account management.', 'error');
        }
        if ($email !== null && (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            redirectUsers('Please enter a valid email address or leave it blank for a student.', 'error');
        }
        if ($id < 0) {
            redirectUsers('Invalid user ID.', 'error');
        }
        if ($id === 0 && strlen($password) < 8) {
            redirectUsers('New account passwords must contain at least 8 characters.', 'error');
        }
        if ($password !== '' && strlen($password) < 8) {
            redirectUsers('A new password must contain at least 8 characters.', 'error');
        }

        $existingUser = null;
        $existingRole = '';
        $existingStaff = null;
        $studentProfileId = 0;
        if ($id > 0) {
            $findUser = $conn->prepare(
                'SELECT id, name, email, role, password, student_id_no
                 FROM users WHERE id = ? LIMIT 1'
            );
            $findUser->bind_param('i', $id);
            $findUser->execute();
            $existingUser = $findUser->get_result()->fetch_assoc();
            $findUser->close();
            if (!$existingUser) {
                redirectUsers('User account not found.', 'error');
            }
            $existingRole = strtolower((string)$existingUser['role']);

            if ($existingRole === 'staff' && !empty($existingUser['email'])) {
                $findStaff = $conn->prepare(
                    'SELECT id, staff_id_no FROM staff WHERE email = ? LIMIT 2'
                );
                $findStaff->bind_param('s', $existingUser['email']);
                $findStaff->execute();
                $staffResult = $findStaff->get_result();
                if ($staffResult->num_rows > 1) {
                    $findStaff->close();
                    redirectUsers('This staff email is linked to multiple staff records.', 'error');
                }
                $existingStaff = $staffResult->fetch_assoc() ?: null;
                $findStaff->close();
            }

            $profileByUser = $conn->prepare(
                'SELECT id FROM students WHERE user_id = ? LIMIT 1'
            );
            $profileByUser->bind_param('i', $id);
            $profileByUser->execute();
            $linkedProfile = $profileByUser->get_result()->fetch_assoc();
            $profileByUser->close();
            if ($linkedProfile) {
                $studentProfileId = (int)$linkedProfile['id'];
            }
        }

        if ($email !== null) {
            $checkAdmin = $conn->prepare(
                'SELECT id FROM admin WHERE email = ? LIMIT 1'
            );
            $checkAdmin->bind_param('s', $email);
            $checkAdmin->execute();
            $adminEmailExists = $checkAdmin->get_result()->num_rows > 0;
            $checkAdmin->close();
            if ($adminEmailExists) {
                redirectUsers('This email belongs to an administrator.', 'error');
            }

            if ($id > 0) {
                $checkEmail = $conn->prepare(
                    'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1'
                );
                $checkEmail->bind_param('si', $email, $id);
            } else {
                $checkEmail = $conn->prepare(
                    'SELECT id FROM users WHERE email = ? LIMIT 1'
                );
                $checkEmail->bind_param('s', $email);
            }
            $checkEmail->execute();
            $emailDuplicate = $checkEmail->get_result()->num_rows > 0;
            $checkEmail->close();
            if ($emailDuplicate) {
                redirectUsers('This email is already registered.', 'error');
            }
        }

        if ($role === 'Student') {
            if ($id > 0) {
                $checkId = $conn->prepare(
                    'SELECT id FROM users WHERE student_id_no = ? AND id <> ? LIMIT 1'
                );
                $checkId->bind_param('si', $idNumber, $id);
            } else {
                $checkId = $conn->prepare(
                    'SELECT id FROM users WHERE student_id_no = ? LIMIT 1'
                );
                $checkId->bind_param('s', $idNumber);
            }
            $checkId->execute();
            $duplicateStudentId = $checkId->get_result()->num_rows > 0;
            $checkId->close();
            if ($duplicateStudentId) {
                redirectUsers('This student ID is already assigned to a user account.', 'error');
            }

            $checkProfile = $conn->prepare(
                'SELECT id, user_id FROM students WHERE student_id_no = ? LIMIT 1'
            );
            $checkProfile->bind_param('s', $idNumber);
            $checkProfile->execute();
            $studentProfile = $checkProfile->get_result()->fetch_assoc();
            $checkProfile->close();
            if ($studentProfile && !empty($studentProfile['user_id']) && (int)$studentProfile['user_id'] !== $id) {
                redirectUsers('This student profile is already linked to another user account.', 'error');
            }
            if ($studentProfile && $studentProfileId > 0 && (int)$studentProfile['id'] !== $studentProfileId) {
                redirectUsers('This account is linked to a different student profile.', 'error');
            }
            if ($studentProfile) {
                $studentProfileId = (int)$studentProfile['id'];
            }

            $checkStaffId = $conn->prepare(
                'SELECT id FROM staff WHERE staff_id_no = ? LIMIT 1'
            );
            $checkStaffId->bind_param('s', $idNumber);
            $checkStaffId->execute();
            $staffIdCollision = $checkStaffId->get_result()->num_rows > 0;
            $checkStaffId->close();
            if ($staffIdCollision) {
                redirectUsers('This ID is already assigned to a staff account.', 'error');
            }
        } else {
            if ($existingStaff) {
                $checkStaffId = $conn->prepare(
                    'SELECT id FROM staff WHERE staff_id_no = ? AND id <> ? LIMIT 1'
                );
                $existingStaffId = (int)$existingStaff['id'];
                $checkStaffId->bind_param('si', $idNumber, $existingStaffId);
            } else {
                $checkStaffId = $conn->prepare(
                    'SELECT id FROM staff WHERE staff_id_no = ? LIMIT 1'
                );
                $checkStaffId->bind_param('s', $idNumber);
            }
            $checkStaffId->execute();
            $duplicateStaffId = $checkStaffId->get_result()->num_rows > 0;
            $checkStaffId->close();
            if ($duplicateStaffId) {
                redirectUsers('This staff ID is already assigned to another account.', 'error');
            }

            $checkStudentId = $conn->prepare(
                'SELECT id FROM students WHERE student_id_no = ? LIMIT 1'
            );
            $checkStudentId->bind_param('s', $idNumber);
            $checkStudentId->execute();
            $studentIdCollision = $checkStudentId->get_result()->num_rows > 0;
            $checkStudentId->close();
            if ($studentIdCollision) {
                redirectUsers('This ID is already assigned to a student account.', 'error');
            }
        }

        $accountPassword = $password !== ''
            ? password_hash($password, PASSWORD_DEFAULT)
            : (string)($existingUser['password'] ?? '');
        if ($accountPassword === '') {
            redirectUsers('A password is required for this account.', 'error');
        }
        $studentIdNo = $role === 'Student' ? $idNumber : null;

        try {
            $conn->begin_transaction();
            if ($id > 0) {
                $stmt = $conn->prepare(
                    'UPDATE users
                     SET name = ?, email = ?, role = ?, password = ?, student_id_no = ?
                     WHERE id = ?'
                );
                $stmt->bind_param('sssssi', $name, $email, $role, $accountPassword, $studentIdNo, $id);
                $stmt->execute();
                $stmt->close();
                $userId = $id;
            } else {
                $status = 'Active';
                $stmt = $conn->prepare(
                    'INSERT INTO users (name, email, role, password, status, student_id_no)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('ssssss', $name, $email, $role, $accountPassword, $status, $studentIdNo);
                $stmt->execute();
                $userId = (int)$conn->insert_id;
                $stmt->close();
            }

            if ($role === 'Student') {
                if ($existingStaff) {
                    $deleteStaff = $conn->prepare('DELETE FROM staff WHERE id = ?');
                    $existingStaffId = (int)$existingStaff['id'];
                    $deleteStaff->bind_param('i', $existingStaffId);
                    $deleteStaff->execute();
                    $deleteStaff->close();
                    $accountType = 'staff';
                    $deletePasswordFlag = $conn->prepare(
                        'DELETE FROM initial_password_changes WHERE account_type = ? AND account_id = ?'
                    );
                    $deletePasswordFlag->bind_param('si', $accountType, $existingStaffId);
                    $deletePasswordFlag->execute();
                    $deletePasswordFlag->close();
                }

                if ($studentProfileId > 0) {
                    $studentStmt = $conn->prepare(
                        'UPDATE students
                         SET student_id_no = ?, fullname = ?, user_id = ?, email = ?, password = ?
                         WHERE id = ?'
                    );
                    $studentStmt->bind_param(
                        'ssissi',
                        $idNumber,
                        $name,
                        $userId,
                        $email,
                        $accountPassword,
                        $studentProfileId
                    );
                } else {
                    $studentStmt = $conn->prepare(
                        'INSERT INTO students (student_id_no, fullname, user_id, email, password)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $studentStmt->bind_param('ssiss', $idNumber, $name, $userId, $email, $accountPassword);
                }
                $studentStmt->execute();
                $studentStmt->close();
            } else {
                if ($studentProfileId > 0) {
                    $detachStudent = $conn->prepare(
                        'UPDATE students SET user_id = NULL, password = NULL WHERE user_id = ?'
                    );
                    $detachStudent->bind_param('i', $userId);
                    $detachStudent->execute();
                    $detachStudent->close();
                    $accountType = 'student';
                    $deletePasswordFlag = $conn->prepare(
                        'DELETE FROM initial_password_changes WHERE account_type = ? AND account_id = ?'
                    );
                    $deletePasswordFlag->bind_param('si', $accountType, $userId);
                    $deletePasswordFlag->execute();
                    $deletePasswordFlag->close();
                }

                if ($existingStaff) {
                    $staffStmt = $conn->prepare(
                        'UPDATE staff
                         SET staff_id_no = ?, password = ?, fullname = ?, role = ?, email = ?
                         WHERE id = ?'
                    );
                    $existingStaffId = (int)$existingStaff['id'];
                    $staffStmt->bind_param(
                        'sssssi',
                        $idNumber,
                        $accountPassword,
                        $name,
                        $role,
                        $email,
                        $existingStaffId
                    );
                } else {
                    $staffStmt = $conn->prepare(
                        'INSERT INTO staff (staff_id_no, password, fullname, role, email)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $staffStmt->bind_param('sssss', $idNumber, $accountPassword, $name, $role, $email);
                }
                $staffStmt->execute();
                $staffStmt->close();
            }

            $conn->commit();
            redirectUsers(
                $id > 0 ? 'User account updated successfully.' : 'New user account created successfully.'
            );
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('User management save failed: ' . $exception->getMessage());
            redirectUsers(
                'Could not save the account. Check the ID number and database structure, then try again.',
                'error'
            );
        }
    }
    // ACTIVATE / DEACTIVATE ACCOUNT
    // -----------------------------------------
    if ($action === 'toggle_status') {
        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            redirectUsers('Invalid user account.', 'error');
        }

        $stmt = $conn->prepare(
            "UPDATE users
             SET status = CASE
                 WHEN status = 'Active' THEN 'Inactive'
                 ELSE 'Active'
             END
             WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        redirectUsers(
            $changed > 0
                ? 'Account status updated.'
                : 'Account not found or status unchanged.',
            $changed > 0 ? 'success' : 'error'
        );
    }

    // -----------------------------------------
    // DELETE USER
    // -----------------------------------------
    if ($action === 'delete') {
        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            redirectUsers('Invalid user account.', 'error');
        }

        $stmt = $conn->prepare(
            'SELECT id, name, email, role, student_id_no
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            redirectUsers('User account not found.', 'error');
        }

        try {
            $conn->begin_transaction();

            if (strtolower((string)$user['role']) === 'staff') {
                $staff = $conn->prepare(
                    'SELECT id FROM staff WHERE email = ? LIMIT 2'
                );
                $staff->bind_param('s', $user['email']);
                $staff->execute();
                $staffResult = $staff->get_result();
                if ($staffResult->num_rows > 1) {
                    $staff->close();
                    throw new RuntimeException('Staff email is linked to multiple staff records.');
                }
                $staffRecord = $staffResult->fetch_assoc();
                $staff->close();
                if ($staffRecord) {
                    $staffId = (int)$staffRecord['id'];
                    $deleteStaff = $conn->prepare('DELETE FROM staff WHERE id = ?');
                    $deleteStaff->bind_param('i', $staffId);
                    $deleteStaff->execute();
                    $deleteStaff->close();
                    $accountType = 'staff';
                    $deleteFlag = $conn->prepare(
                        'DELETE FROM initial_password_changes WHERE account_type = ? AND account_id = ?'
                    );
                    $deleteFlag->bind_param('si', $accountType, $staffId);
                    $deleteFlag->execute();
                    $deleteFlag->close();
                }
            } elseif (strtolower((string)$user['role']) === 'student') {
                $studentId = (int)$user['id'];
                $detachStudent = $conn->prepare(
                    'UPDATE students SET user_id = NULL, password = NULL WHERE user_id = ?'
                );
                $detachStudent->bind_param('i', $studentId);
                $detachStudent->execute();
                $detachStudent->close();
                $accountType = 'student';
                $deleteFlag = $conn->prepare(
                    'DELETE FROM initial_password_changes WHERE account_type = ? AND account_id = ?'
                );
                $deleteFlag->bind_param('si', $accountType, $studentId);
                $deleteFlag->execute();
                $deleteFlag->close();
            }

            $delete = $conn->prepare(
                'DELETE FROM users WHERE id = ?'
            );
            $delete->bind_param('i', $id);
            $delete->execute();
            $deleted = $delete->affected_rows;
            $delete->close();

            $conn->commit();

            redirectUsers(
                $deleted
                    ? 'User account deleted successfully.'
                    : 'Could not delete the account.',
                $deleted ? 'success' : 'error'
            );
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('User deletion failed: ' . $exception->getMessage());

            redirectUsers(
                'Could not delete this account. It may be linked to other records.',
                'error'
            );
        }
    }
}

// =============================================
// LOAD USER FOR EDITING
// =============================================

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);

if ($editId && $editId > 0) {
    $stmt = $conn->prepare(
        "SELECT u.id, u.name, u.email, u.role, u.status,
                CASE
                    WHEN LOWER(u.role) = 'student' THEN u.student_id_no
                    WHEN LOWER(u.role) = 'staff' THEN staff.staff_id_no
                    ELSE NULL
                END AS login_id
         FROM users u
         LEFT JOIN staff
            ON staff.email = u.email AND LOWER(u.role) = 'staff'
         WHERE u.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editingUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editingUser) {
        $message = 'User account not found.';
        $messageType = 'error';
    }
}

// =============================================
// SEARCH AND LIST USERS
// =============================================

$sql = "SELECT u.id, u.name, u.email, u.role, u.status,
               CASE
                   WHEN LOWER(u.role) = 'student' THEN u.student_id_no
                   WHEN LOWER(u.role) = 'staff' THEN staff.staff_id_no
                   ELSE NULL
               END AS login_id
        FROM users u
        LEFT JOIN staff
            ON staff.email = u.email AND LOWER(u.role) = 'staff'";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " WHERE u.name LIKE ?
        OR COALESCE(u.email, '') LIKE ?
        OR u.role LIKE ?
        OR CAST(u.id AS CHAR) LIKE ?
        OR COALESCE(u.student_id_no, staff.staff_id_no, '') LIKE ?";
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term, $term];
    $types = 'sssss';
}

$sql .= ' ORDER BY u.id DESC';

$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$userResult = $stmt->get_result();

$userList = [];
while ($row = $userResult->fetch_assoc()) {
    $userList[] = $row;
}

$stmt->close();

$totalUsers = (int)($conn->query(
    'SELECT COUNT(*) AS total FROM users'
)->fetch_assoc()['total'] ?? 0);

$activeUsers = (int)($conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE status = 'Active'"
)->fetch_assoc()['total'] ?? 0);

$staffUsers = (int)($conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'Staff'"
)->fetch_assoc()['total'] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | SHRCMS</title>
    <link rel="stylesheet" href="../../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/admin-dashboard.css?v=20261117-admin-operations">


    <link rel="stylesheet" href="../../css/user-management.css">
</head>

<body class="admin-dashboard-body">

<aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
        <img src="../../image/logo2.png" alt="SHRCMS Logo">
        <div>
            <h3>SHRCMS</h3>
            <p>Clinic Management</p>
        </div>
    </div>

    <nav>
        <a href="admin_dashboard.php"><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"> Dashboard</a>
        <a href="user_management.php" class="active"><img class="ui-icon" src="../../image/icon-users.svg" alt="" aria-hidden="true"> User Accounts</a>
        <a href="staff_management.php"><img class="ui-icon" src="../../image/icon-staff.svg" alt="" aria-hidden="true"> Staff Management</a>
        <a href="system_reports.php"><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"> System Reports</a>
        <a href="system_settings.php"><img class="ui-icon" src="../../image/icon-settings.svg" alt="" aria-hidden="true"> System Settings</a>
        <a href="../logout.php" class="logout-link"><img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"> Logout</a>
    </nav>
</aside>

<main class="admin-main-content">
    <header class="admin-header-bar">
        <h2>User Management</h2>

        <div class="admin-profile-info">
            <img src="../../image/logo2.png" alt="Administrator">
            <div class="admin-profile-text">
                <h4><?= e($_SESSION['admin_username'] ?? 'Administrator') ?></h4>
                <p>Administrator</p>
            </div>
        </div>
    </header>

    <div class="um-content">

        <?php if ($message !== ''): ?>
            <div class="um-alert <?= $messageType === 'error' ? 'um-alert-error' : 'um-alert-success' ?>"
                 role="status">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <div class="um-cards">
            <div class="um-card">
                <span>Total User Accounts</span>
                <strong><?= number_format($totalUsers) ?></strong>
            </div>
            <div class="um-card">
                <span>Active Accounts</span>
                <strong><?= number_format($activeUsers) ?></strong>
            </div>
            <div class="um-card">
                <span>Staff Accounts</span>
                <strong><?= number_format($staffUsers) ?></strong>
            </div>
        </div>

        <section class="um-panel" aria-labelledby="um-roster-heading">
            <h2 class="um-heading" id="um-roster-heading">Import staff, teacher, and student accounts</h2>
            <p class="um-subtitle">
                Upload a UTF-8 CSV with the exact header
                <code>role,id_number,full_name,email,birthday,course_year</code>.
                Use Student, Staff, or Teacher for role and YYYY-MM-DD for birthday.
                Email may be blank for students; staff and teachers still need an email.
                Course/year may be blank for staff and teachers.
            </p>

            <pre class="um-csv-example">role,id_number,full_name,email,birthday,course_year
Student,STUDENT-ID,Example Student,,2005-04-15,BSIT-1ST YR
Teacher,TEACHER-ID,Example Teacher,teacher@example.edu,1985-09-20,</pre>

            <p class="um-subtitle">
                Birthday is used only as a temporary password, hashed immediately, and not stored as a date.
                Users must choose a new password at first login. Existing IDs are updated; missing accounts are created.
                The complete file is validated and imported as one transaction, with a limit of 500 rows.
            </p>

            <form action="import_user_roster.php" method="POST" enctype="multipart/form-data"
                  class="um-roster-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <label for="roster-csv">CSV file</label>
                <input id="roster-csv" type="file" name="roster_csv" accept=".csv,text/csv"
                       required>
                <button class="um-btn um-primary" type="submit">Upload and import CSV</button>
            </form>
        </section>

        <section class="um-panel" id="user-form">
            <h2 class="um-heading">
                <?= $editingUser ? 'Edit User Account' : 'Add New User' ?>
            </h2>
            <p class="um-subtitle">
                <?= $editingUser
                    ? 'Update the account details below. The ID is used to log in. Leave the password blank to keep the current password.'
                    : 'Create an account using the student or staff ID as the login ID. Email is not used to log in.' ?>
            </p>

            <form action="user_management.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= e($editingUser['id'] ?? 0) ?>">

                <div class="um-form">
                    <div class="um-field">
                        <label for="name">Full Name <span class="um-required">*</span></label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="100"
                            required
                            placeholder="Enter full name"
                            value="<?= e($editingUser['name'] ?? '') ?>"
                        >
                    </div>

                    <div class="um-field">
                        <label for="id_number">Student ID / Staff ID <span class="um-required">*</span></label>
                        <input
                            type="text"
                            id="id_number"
                            name="id_number"
                            maxlength="50"
                            required
                            placeholder="Enter the ID used to log in"
                            value="<?= e($editingUser['login_id'] ?? '') ?>"
                        >
                        <small class="um-help">Students log in with their student ID; staff log in with their staff ID.</small>
                    </div>

                    <div class="um-field">
                        <label for="email">Email Address <span class="um-help">(optional for students)</span></label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="100"
                            placeholder="Contact email (not used to log in)"
                            value="<?= e($editingUser['email'] ?? '') ?>"
                        >
                    </div>

                    <div class="um-field">
                        <label for="role">Account Role <span class="um-required">*</span></label>
                        <select id="role" name="role" required>
                            <option value="Staff"
                                <?= strtolower((string)($editingUser['role'] ?? '')) === 'staff' ? 'selected' : '' ?>>
                                Staff
                            </option>
                            <option value="Student"
                                <?= strtolower((string)($editingUser['role'] ?? '')) === 'student' ? 'selected' : '' ?>>
                                Student
                            </option>
                        </select>
                    </div>

                    <div class="um-field">
                        <label for="password">
                            <?= $editingUser ? 'New Password (Optional)' : 'Password' ?>
                            <?php if (!$editingUser): ?>
                                <span class="um-required">*</span>
                            <?php endif; ?>
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            <?= $editingUser ? '' : 'required' ?>
                            autocomplete="new-password"
                            placeholder="<?= $editingUser ? 'Leave blank to keep current password' : 'At least 8 characters' ?>"
                        >
                        <small class="um-help">
                            Passwords are stored as secure hashes.
                        </small>
                    </div>
                </div>

                <div class="um-actions">
                    <button class="um-btn um-primary" type="submit">
                        <?= $editingUser ? 'Save Changes' : '+ Add User' ?>
                    </button>

                    <?php if ($editingUser): ?>
                        <a class="um-btn um-light" href="user_management.php">
                            Cancel Edit
                        </a>
                    <?php else: ?>
                        <button class="um-btn um-light" type="reset">
                            Clear Form
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="um-panel">
            <h2 class="um-heading">Registered User Accounts</h2>
            <p class="um-subtitle">Search, edit, activate, deactivate, or delete accounts.</p>

            <form class="um-search" method="GET" action="user_management.php">
                <input
                    type="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Search name, login ID, email, or role..."
                    aria-label="Search user accounts"
                >
                <button class="um-btn um-primary" type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a class="um-btn um-light" href="user_management.php">Clear</a>
                <?php endif; ?>
            </form>

            <div class="um-table-wrap">
                <table class="um-table">
                    <thead>
                    <tr>
                        <th>Account ID</th>
                        <th>Name</th>
                        <th>Login ID</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if ($userList): ?>
                        <?php foreach ($userList as $user): ?>
                            <?php
                            $isActive = ($user['status'] ?? '') === 'Active';
                            ?>
                            <tr>
                                <td>
                                    <strong>USR-<?= str_pad((string)$user['id'], 3, '0', STR_PAD_LEFT) ?></strong>
                                </td>

                                <td class="um-user-name"><?= e($user['name']) ?></td>
                                <td><?= e($user['login_id'] ?? '') ?></td>
                                <td><?= e($user['email'] ?? '') ?></td>

                                <td>
                                    <span class="um-badge um-role">
                                        <?= e($user['role']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="um-badge <?= $isActive ? 'um-active' : 'um-inactive' ?>">
                                        <?= e($user['status'] ?? 'Unknown') ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="um-row-actions">
                                        <a
                                            class="um-btn um-edit"
                                            href="user_management.php?edit=<?= (int)$user['id'] ?>#user-form"
                                        >Edit</a>

                                        <form method="POST" action="user_management.php">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                            <button class="um-btn um-toggle" type="submit">
                                                <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="user_management.php"
                                            onsubmit="return confirm('Permanently delete this account? This cannot be undone.');"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                            <button class="um-btn um-delete" type="submit">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="um-empty">
                                <?= $search !== ''
                                    ? 'No accounts match your search.'
                                    : 'No user accounts have been registered yet.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</main>

</body>
</html>
