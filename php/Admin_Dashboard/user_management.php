<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../rbac_audit.php';
rbacRequireRole(['Administrator'], '../login.php');

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

function generateUserPassword(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$';
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

function handleUserIdUpload(array $file, string &$error): ?string
{
    $error = '';
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        $error = 'Failed to upload user ID file.';
        return null;
    }
    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'ID file must be 5 MB or smaller.';
        return null;
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
        $error = 'Only JPG, JPEG, PNG, or PDF files are allowed.';
        return null;
    }

    $targetDir = __DIR__ . '/../../uploads/user_ids';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        $error = 'Unable to create upload folder.';
        return null;
    }

    $fileName = 'uid_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $fullPath = $targetDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        $error = 'Unable to store uploaded ID file.';
        return null;
    }

    return 'uploads/user_ids/' . $fileName;
}

if (empty($_SESSION['user_management_csrf'])) {
    $_SESSION['user_management_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['user_management_csrf'];
$message = trim($_GET['message'] ?? '');
$messageType = ($_GET['type'] ?? 'success') === 'error' ? 'error' : 'success';
$search = trim($_GET['search'] ?? '');
$editingUser = null;
$allowedRoles = ['Administrator', 'Staff', 'Student', 'Teacher'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        redirectUsers('Invalid form submission. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $idValue = $_POST['id'] ?? 0;
        $id = filter_var($idValue, FILTER_VALIDATE_INT);
        $id = $id === false ? 0 : (int)$id;
        $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
        $idNumber = is_string($_POST['id_number'] ?? null) ? trim($_POST['id_number']) : '';
        $emailInput = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $email = $emailInput === '' ? null : $emailInput;
        $role = is_string($_POST['role'] ?? null) ? trim($_POST['role']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($name === '' || $idNumber === '') {
            redirectUsers('Name and login ID are required.', 'error');
        }
        if (mb_strlen($name) > 100 || strlen($idNumber) > 50) {
            redirectUsers('Name or login ID exceeds the allowed length.', 'error');
        }
        if (!in_array($role, $allowedRoles, true)) {
            redirectUsers('Please select a valid account role (Administrator, Staff, Student, or Teacher).', 'error');
        }
        if (in_array($role, ['Administrator', 'Staff', 'Teacher'], true) && $email === null) {
            redirectUsers($role . ' accounts require an email address for account management.', 'error');
        }
        if ($email !== null && (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            redirectUsers('Please enter a valid email address or leave it blank for a student.', 'error');
        }
        if ($id < 0) {
            redirectUsers('Invalid user ID.', 'error');
        }
        if ($password !== '' && strlen($password) < 8) {
            redirectUsers('A password must contain at least 8 characters.', 'error');
        }

        $uploadErr = '';
        $uploadedIdPath = handleUserIdUpload($_FILES['id_document'] ?? [], $uploadErr);
        if ($uploadErr !== '') {
            redirectUsers($uploadErr, 'error');
        }

        $existingUser = null;
        $existingStaff = null;
        $existingTeacher = null;
        $existingAdmin = null;
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

            if (!empty($existingUser['email'])) {
                $findStaff = $conn->prepare('SELECT id, staff_id_no FROM staff WHERE email = ? LIMIT 2');
                $findStaff->bind_param('s', $existingUser['email']);
                $findStaff->execute();
                $staffResult = $findStaff->get_result();
                if ($staffResult->num_rows > 1) {
                    $findStaff->close();
                    redirectUsers('This email is linked to multiple staff records.', 'error');
                }
                $existingStaff = $staffResult->fetch_assoc() ?: null;
                $findStaff->close();

                $findTeacher = $conn->prepare('SELECT id, teacher_id_no FROM teachers WHERE email = ? LIMIT 1');
                $findTeacher->bind_param('s', $existingUser['email']);
                $findTeacher->execute();
                $existingTeacher = $findTeacher->get_result()->fetch_assoc() ?: null;
                $findTeacher->close();

                $findAdmin = $conn->prepare('SELECT id, admin_id_no FROM admin WHERE email = ? LIMIT 1');
                $findAdmin->bind_param('s', $existingUser['email']);
                $findAdmin->execute();
                $existingAdmin = $findAdmin->get_result()->fetch_assoc() ?: null;
                $findAdmin->close();
            }

            $profileByUser = $conn->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
            $profileByUser->bind_param('i', $id);
            $profileByUser->execute();
            $linkedProfile = $profileByUser->get_result()->fetch_assoc();
            $profileByUser->close();
            if ($linkedProfile) {
                $studentProfileId = (int)$linkedProfile['id'];
            }
        }

        if ($email !== null) {
            if ($id > 0) {
                $checkEmail = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                $checkEmail->bind_param('si', $email, $id);
            } else {
                $checkEmail = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $checkEmail->bind_param('s', $email);
            }
            $checkEmail->execute();
            $emailDuplicate = $checkEmail->get_result()->num_rows > 0;
            $checkEmail->close();
            if ($emailDuplicate) {
                redirectUsers('This email is already registered.', 'error');
            }

            if ($existingAdmin) {
                $checkAdminDup = $conn->prepare('SELECT id FROM admin WHERE email = ? AND id <> ? LIMIT 1');
                $checkAdminDup->bind_param('si', $email, $existingAdmin['id']);
            } else {
                $checkAdminDup = $conn->prepare('SELECT id FROM admin WHERE email = ? LIMIT 1');
                $checkAdminDup->bind_param('s', $email);
            }
            $checkAdminDup->execute();
            $adminEmailDup = $checkAdminDup->get_result()->num_rows > 0;
            $checkAdminDup->close();
            if ($adminEmailDup) {
                redirectUsers('This email is already used by another administrator account.', 'error');
            }
        }

        $generatedPassword = null;
        if ($id === 0 && $password === '') {
            $generatedPassword = generateUserPassword(10);
            $password = $generatedPassword;
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
                if ($studentProfileId > 0) {
                    if ($uploadedIdPath !== null) {
                        $studentStmt = $conn->prepare(
                            'UPDATE students
                             SET student_id_no = ?, fullname = ?, user_id = ?, email = ?, id_document = ?, password = ?
                             WHERE id = ?'
                        );
                        $studentStmt->bind_param('ssisssi', $idNumber, $name, $userId, $email, $uploadedIdPath, $accountPassword, $studentProfileId);
                    } else {
                        $studentStmt = $conn->prepare(
                            'UPDATE students
                             SET student_id_no = ?, fullname = ?, user_id = ?, email = ?, password = ?
                             WHERE id = ?'
                        );
                        $studentStmt->bind_param('ssissi', $idNumber, $name, $userId, $email, $accountPassword, $studentProfileId);
                    }
                } else {
                    $studentStmt = $conn->prepare(
                        'INSERT INTO students (student_id_no, fullname, user_id, email, id_document, password)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $studentStmt->bind_param('ssisss', $idNumber, $name, $userId, $email, $uploadedIdPath, $accountPassword);
                }
                $studentStmt->execute();
                $studentStmt->close();
            } elseif ($role === 'Teacher') {
                if ($existingTeacher) {
                    $existingTeacherId = (int)$existingTeacher['id'];
                    if ($uploadedIdPath !== null) {
                        $teacherStmt = $conn->prepare(
                            'UPDATE teachers
                             SET teacher_id_no = ?, fullname = ?, email = ?, password = ?, id_document = ?
                             WHERE id = ?'
                        );
                        $teacherStmt->bind_param('sssssi', $idNumber, $name, $email, $accountPassword, $uploadedIdPath, $existingTeacherId);
                    } else {
                        $teacherStmt = $conn->prepare(
                            'UPDATE teachers
                             SET teacher_id_no = ?, fullname = ?, email = ?, password = ?
                             WHERE id = ?'
                        );
                        $teacherStmt->bind_param('ssssi', $idNumber, $name, $email, $accountPassword, $existingTeacherId);
                    }
                } else {
                    $teacherStmt = $conn->prepare(
                        'INSERT INTO teachers (teacher_id_no, fullname, email, password, id_document)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $teacherStmt->bind_param('sssss', $idNumber, $name, $email, $accountPassword, $uploadedIdPath);
                }
                $teacherStmt->execute();
                $teacherStmt->close();
            } elseif ($role === 'Administrator') {
                $adminIdShort = mb_substr($idNumber, 0, 20);
                if ($existingAdmin) {
                    $existingAdminId = (int)$existingAdmin['id'];
                    $adminStmt = $conn->prepare(
                        'UPDATE admin
                         SET admin_id_no = ?, username = ?, email = ?, password = ?
                         WHERE id = ?'
                    );
                    $adminStmt->bind_param('ssssi', $adminIdShort, $name, $email, $accountPassword, $existingAdminId);
                } else {
                    $adminStmt = $conn->prepare(
                        'INSERT INTO admin (admin_id_no, username, email, password)
                         VALUES (?, ?, ?, ?)'
                    );
                    $adminStmt->bind_param('ssss', $adminIdShort, $name, $email, $accountPassword);
                }
                $adminStmt->execute();
                $adminStmt->close();
            } else {
                // Staff only
                if ($existingStaff) {
                    $existingStaffId = (int)$existingStaff['id'];
                    if ($uploadedIdPath !== null) {
                        $staffStmt = $conn->prepare(
                            'UPDATE staff
                             SET staff_id_no = ?, password = ?, fullname = ?, role = ?, email = ?, id_document = ?
                             WHERE id = ?'
                        );
                        $staffStmt->bind_param('ssssssi', $idNumber, $accountPassword, $name, $role, $email, $uploadedIdPath, $existingStaffId);
                    } else {
                        $staffStmt = $conn->prepare(
                            'UPDATE staff
                             SET staff_id_no = ?, password = ?, fullname = ?, role = ?, email = ?
                             WHERE id = ?'
                        );
                        $staffStmt->bind_param('sssssi', $idNumber, $accountPassword, $name, $role, $email, $existingStaffId);
                    }
                } else {
                    $staffStmt = $conn->prepare(
                        'INSERT INTO staff (staff_id_no, password, fullname, role, email, id_document)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $staffStmt->bind_param('ssssss', $idNumber, $accountPassword, $name, $role, $email, $uploadedIdPath);
                }
                $staffStmt->execute();
                $staffStmt->close();
            }

            $conn->commit();

            rbacLog(
                $conn,
                $id > 0 ? 'update' : 'create',
                'User Management',
                'User',
                (string)$userId,
                $role . ' account ' . ($id > 0 ? 'updated' : 'created')
            );

            $msg = $id > 0
                ? 'User account updated successfully.'
                : "New {$role} account created successfully." . ($generatedPassword !== null ? " Generated Password: {$generatedPassword}" : '');

            redirectUsers($msg);
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('User management save failed: ' . $exception->getMessage());
            redirectUsers('Could not save the account. Check the ID number, email uniqueness, and database structure, then try again.', 'error');
        }
    }

    if ($action === 'toggle_status') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            redirectUsers('Invalid user account.', 'error');
        }

        $stmt = $conn->prepare(
            "UPDATE users
             SET status = CASE WHEN status = 'Active' THEN 'Inactive' ELSE 'Active' END
             WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        rbacLog($conn, 'status_change', 'User Management', 'User', (string)$id, 'Account status activated or deactivated');

        redirectUsers(
            $changed > 0 ? 'Account status updated.' : 'Account not found or status unchanged.',
            $changed > 0 ? 'success' : 'error'
        );
    }

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            redirectUsers('Invalid user account.', 'error');
        }

        $stmt = $conn->prepare('SELECT id, email, role FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            redirectUsers('User account not found.', 'error');
        }

        try {
            $conn->begin_transaction();
            if (strtolower((string)$user['role']) === 'student') {
                $detachStudent = $conn->prepare('UPDATE students SET user_id = NULL, password = NULL WHERE user_id = ?');
                $detachStudent->bind_param('i', $id);
                $detachStudent->execute();
                $detachStudent->close();
            }

            $delete = $conn->prepare('DELETE FROM users WHERE id = ?');
            $delete->bind_param('i', $id);
            $delete->execute();
            $deleted = $delete->affected_rows;
            $delete->close();
            $conn->commit();

            rbacLog($conn, 'delete', 'User Management', 'User', (string)$id, 'User account deleted');

            redirectUsers(
                $deleted ? 'User account deleted successfully.' : 'Could not delete the account.',
                $deleted ? 'success' : 'error'
            );
        } catch (Throwable $exception) {
            $conn->rollback();
            error_log('User deletion failed: ' . $exception->getMessage());
            redirectUsers('Could not delete this account. It may be linked to other records.', 'error');
        }
    }
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId && $editId > 0) {
    $stmt = $conn->prepare(
        "SELECT u.id, u.name, u.email, u.role, u.status,
                CASE
                    WHEN LOWER(u.role) = 'student' THEN u.student_id_no
                    WHEN LOWER(u.role) = 'administrator' THEN adm.admin_id_no
                    WHEN LOWER(u.role) = 'teacher' THEN tch.teacher_id_no
                    WHEN LOWER(u.role) = 'staff' THEN staff.staff_id_no
                    ELSE NULL
                END AS login_id,
                CASE
                    WHEN LOWER(u.role) = 'student' THEN st.id_document
                    WHEN LOWER(u.role) = 'teacher' THEN tch.id_document
                    WHEN LOWER(u.role) = 'staff' THEN staff.id_document
                    ELSE NULL
                END AS id_document
         FROM users u
         LEFT JOIN admin adm ON adm.email = u.email AND LOWER(u.role) = 'administrator'
         LEFT JOIN teachers tch ON tch.email = u.email AND LOWER(u.role) = 'teacher'
         LEFT JOIN staff ON staff.email = u.email AND LOWER(u.role) = 'staff'
         LEFT JOIN students st ON st.user_id = u.id AND LOWER(u.role) = 'student'
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

$sql = "SELECT u.id, u.name, u.email, u.role, u.status,
               CASE
                   WHEN LOWER(u.role) = 'student' THEN u.student_id_no
                   WHEN LOWER(u.role) = 'administrator' THEN adm.admin_id_no
                   WHEN LOWER(u.role) = 'teacher' THEN tch.teacher_id_no
                   WHEN LOWER(u.role) = 'staff' THEN staff.staff_id_no
                   ELSE NULL
               END AS login_id,
               CASE
                   WHEN LOWER(u.role) = 'student' THEN st.id_document
                   WHEN LOWER(u.role) = 'teacher' THEN tch.id_document
                   WHEN LOWER(u.role) = 'staff' THEN staff.id_document
                   ELSE NULL
               END AS id_document
        FROM users u
        LEFT JOIN admin adm ON adm.email = u.email AND LOWER(u.role) = 'administrator'
        LEFT JOIN teachers tch ON tch.email = u.email AND LOWER(u.role) = 'teacher'
        LEFT JOIN staff ON staff.email = u.email AND LOWER(u.role) = 'staff'
        LEFT JOIN students st ON st.user_id = u.id AND LOWER(u.role) = 'student'";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " WHERE u.name LIKE ?
        OR COALESCE(u.email, '') LIKE ?
        OR u.role LIKE ?
        OR CAST(u.id AS CHAR) LIKE ?
        OR COALESCE(u.student_id_no, staff.staff_id_no, tch.teacher_id_no, adm.admin_id_no, '') LIKE ?";
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

$totalUsers = (int)($conn->query('SELECT COUNT(*) AS total FROM users')->fetch_assoc()['total'] ?? 0);
$activeUsers = (int)($conn->query("SELECT COUNT(*) AS total FROM users WHERE status = 'Active'")->fetch_assoc()['total'] ?? 0);
$staffUsers = (int)($conn->query("SELECT COUNT(*) AS total FROM users WHERE role IN ('Staff', 'Teacher', 'Administrator')")->fetch_assoc()['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | SHRCMS</title>
    <link rel="stylesheet" href="../../css/Shared/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/Admin/admin-dashboard.css?v=20261117-admin-operations">
    <link rel="stylesheet" href="../../css/Admin/user-management.css">
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
                <div class="um-alert <?= $messageType === 'error' ? 'um-alert-error' : 'um-alert-success' ?>" role="status">
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
                    <span>Admin / Staff / Teacher</span>
                    <strong><?= number_format($staffUsers) ?></strong>
                </div>
            </div>

            <section class="um-panel" id="user-form">
                <h2 class="um-heading"><?= $editingUser ? 'Edit User Account' : 'Add New User' ?></h2>
                <p class="um-subtitle">
                    <?= $editingUser
                        ? 'Update the account details below. Leave the password blank to keep the current password.'
                        : 'Select a role (Administrator, Staff, Student, or Teacher). Leave the password blank to automatically generate a temporary password.' ?>
                </p>

                <form action="user_management.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= e($editingUser['id'] ?? 0) ?>">

                    <div class="um-form">
                        <div class="um-field">
                            <label for="name">Full Name <span class="um-required">*</span></label>
                            <input type="text" id="name" name="name" maxlength="100" required
                                placeholder="Enter full name" value="<?= e($editingUser['name'] ?? '') ?>">
                        </div>

                        <div class="um-field">
                            <label for="id_number">Admin / Staff / Teacher / Student ID <span class="um-required">*</span></label>
                            <input type="text" id="id_number" name="id_number" maxlength="50" required
                                placeholder="Enter the ID used to log in" value="<?= e($editingUser['login_id'] ?? '') ?>">
                        </div>

                        <div class="um-field">
                            <label for="email">Email Address <span class="um-help">(optional for students)</span></label>
                            <input type="email" id="email" name="email" maxlength="100"
                                placeholder="Contact email" value="<?= e($editingUser['email'] ?? '') ?>">
                        </div>

                        <div class="um-field">
                            <label for="role">Account Role <span class="um-required">*</span></label>
                            <select id="role" name="role" required>
                                <?php foreach ($allowedRoles as $roleOption): ?>
                                    <option value="<?= e($roleOption) ?>" <?= strcasecmp((string)($editingUser['role'] ?? ''), $roleOption) === 0 ? 'selected' : '' ?>>
                                        <?= e($roleOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="um-field">
                            <label for="id_document">ID / Credential Upload (JPG, PNG, PDF)</label>
                            <input type="file" id="id_document" name="id_document" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if (!empty($editingUser['id_document'])): ?>
                                <small class="um-help"><a href="../../<?= e($editingUser['id_document']) ?>" target="_blank">View uploaded ID</a></small>
                            <?php endif; ?>
                        </div>

                        <div class="um-field">
                            <label for="password"><?= $editingUser ? 'New Password (Optional)' : 'Password (Optional - Auto-generated if blank)' ?></label>
                            <input type="password" id="password" name="password" minlength="8"
                                autocomplete="new-password"
                                placeholder="<?= $editingUser ? 'Leave blank to keep current password' : 'Leave blank to auto-generate' ?>">
                        </div>
                    </div>

                    <div class="um-actions">
                        <button class="um-btn um-primary" type="submit">
                            <?= $editingUser ? 'Save Changes' : '+ Add User' ?>
                        </button>

                        <?php if ($editingUser): ?>
                            <a class="um-btn um-light" href="user_management.php">Cancel Edit</a>
                        <?php else: ?>
                            <button class="um-btn um-light" type="reset">Clear Form</button>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="um-panel" id="csv-import">
                <h2 class="um-heading">Upload User CSV</h2>
                <p class="um-subtitle">
                    Create or update accounts in bulk. The <strong>ID</strong> becomes the login ID and <strong>birthdate</strong> becomes the initial password.
                    Accounts imported from the CSV must change their password after first login.
                </p>
                <div class="um-csv-example">
                    id,name,role,birthdate,email,course_year<br>
                    2026-0001,Juan Dela Cruz,Student,2005-06-15,,BSIT-1
                </div>
                <form action="import_user_roster.php" method="POST" enctype="multipart/form-data" class="um-roster-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <label for="roster_csv">CSV File</label>
                    <input type="file" id="roster_csv" name="roster_csv" accept=".csv,text/csv" required>
                    <button type="submit" class="um-btn um-primary">Upload and Create Users</button>
                </form>
            </section>

            <section class="um-panel">
                <h2 class="um-heading">Registered User Accounts</h2>
                <p class="um-subtitle">Search, edit, activate, deactivate, or delete accounts.</p>

                <form class="um-search" method="GET" action="user_management.php">
                    <input type="search" name="search" value="<?= e($search) ?>"
                        placeholder="Search name, login ID, email, or role..." aria-label="Search user accounts">
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
                                    <?php $isActive = ($user['status'] ?? '') === 'Active'; ?>
                                    <tr>
                                        <td><strong>USR-<?= str_pad((string)$user['id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
                                        <td class="um-user-name"><?= e($user['name']) ?></td>
                                        <td><?= e($user['login_id'] ?? '') ?></td>
                                        <td><?= e($user['email'] ?? '') ?></td>
                                        <td><span class="um-badge um-role"><?= e($user['role']) ?></span></td>
                                        <td>
                                            <span class="um-badge <?= $isActive ? 'um-active' : 'um-inactive' ?>">
                                                <?= e($user['status'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="um-row-actions">
                                                <a class="um-btn um-edit" href="user_management.php?edit=<?= (int)$user['id'] ?>#user-form">Edit</a>

                                                <form method="POST" action="user_management.php">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                    <button class="um-btn um-toggle" type="submit">
                                                        <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                                    </button>
                                                </form>

                                                <form method="POST" action="user_management.php"
                                                    onsubmit="return confirm('Permanently delete this account? This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                                    <button class="um-btn um-delete" type="submit">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="um-empty">
                                        <?= $search !== '' ? 'No accounts match your search.' : 'No user accounts have been registered yet.' ?>
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