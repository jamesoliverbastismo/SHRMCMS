
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../db.php';

$conn->set_charset('utf8mb4');

if (!isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'Administrator') {
    http_response_code(403);
    exit('Access denied. Only administrators can manage staff.');
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_msg = '';
$error_msg = '';
$search = trim($_GET['search'] ?? '');
$editing_staff = null;

$username = $_SESSION['admin_username'] ?? 'Administrator';

/* Handle Add, Update, and Delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error_msg = 'Invalid form submission. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id = filter_var(
                $_POST['id'] ?? 0,
                FILTER_VALIDATE_INT
            );

            $fullname = trim($_POST['fullname'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($id === false || $id < 0) {
                $error_msg = 'Invalid staff record.';
            } elseif ($fullname === '' || $role === '') {
                $error_msg = 'Full name and role are required.';
            } elseif (
                mb_strlen($fullname) > 100 ||
                mb_strlen($role) > 100
            ) {
                $error_msg = 'Name or role exceeds the allowed length.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_msg = 'Please enter a valid email address.';
            } elseif (mb_strlen($email) > 255) {
                $error_msg = 'Email address is too long.';
            } elseif (mb_strlen($password) > 255) {
                $error_msg = 'Password exceeds the allowed length.';
            } elseif ($id === 0 && $password === '') {
                $error_msg = 'A password is required for new staff accounts.';
            } else {
                $hashedPassword = $password !== ''
                    ? password_hash($password, PASSWORD_DEFAULT)
                    : null;

                if ($id > 0) {
                    $staffIdNo = 'STAFF-' . str_pad(
                        (string)$id,
                        4,
                        '0',
                        STR_PAD_LEFT
                    );

                    if ($hashedPassword !== null) {
                        $stmt = $conn->prepare(
                            "UPDATE staff
                             SET fullname = ?, role = ?, email = ?,
                                 staff_id_no = COALESCE(NULLIF(staff_id_no, ''), ?),
                                 password = ?
                             WHERE id = ?"
                        );
                        $stmt->bind_param(
                            'sssssi',
                            $fullname,
                            $role,
                            $email,
                            $staffIdNo,
                            $hashedPassword,
                            $id
                        );
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE staff
                             SET fullname = ?, role = ?, email = ?,
                                 staff_id_no = COALESCE(NULLIF(staff_id_no, ''), ?)
                             WHERE id = ?"
                        );
                        $stmt->bind_param(
                            'ssssi',
                            $fullname,
                            $role,
                            $email,
                            $staffIdNo,
                            $id
                        );
                    }

                    if ($stmt->execute()) {
                        $success_msg = 'Staff record updated successfully.';
                        $editing_staff = null;
                    } else {
                        $error_msg = 'Unable to save staff record. Please check the database.';
                    }

                    $stmt->close();
                } else {
                    $conn->begin_transaction();

                    try {
                        $stmt = $conn->prepare(
                            'INSERT INTO staff (fullname, role, email, password)
                             VALUES (?, ?, ?, ?)'
                        );
                        $stmt->bind_param(
                            'ssss',
                            $fullname,
                            $role,
                            $email,
                            $hashedPassword
                        );

                        if (!$stmt->execute()) {
                            throw new mysqli_sql_exception($stmt->error);
                        }

                        $newStaffId = (int)$conn->insert_id;
                        $staffIdNo = 'STAFF-' . str_pad(
                            (string)$newStaffId,
                            4,
                            '0',
                            STR_PAD_LEFT
                        );
                        $idStmt = $conn->prepare(
                            'UPDATE staff SET staff_id_no = ? WHERE id = ?'
                        );
                        $idStmt->bind_param('si', $staffIdNo, $newStaffId);

                        if (!$idStmt->execute()) {
                            throw new mysqli_sql_exception($idStmt->error);
                        }

                        $idStmt->close();
                        $stmt->close();
                        $conn->commit();

                        $success_msg = 'Staff member added successfully. Staff ID: ' . $staffIdNo;
                        $editing_staff = null;
                    } catch (mysqli_sql_exception $exception) {
                        $conn->rollback();
                        error_log('Staff management save failed: ' . $exception->getMessage());
                        $error_msg = 'Unable to save staff record. Please check the database.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                $error_msg = 'Invalid staff record.';
            } else {
                $stmt = $conn->prepare(
                    'DELETE FROM staff WHERE id = ?'
                );
                $stmt->bind_param('i', $id);

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $success_msg = 'Staff member deleted successfully.';
                } else {
                    $error_msg = 'Staff record was not found or could not be deleted.';
                }

                $stmt->close();
            }
        }
    }
}

/* Load record for editing */
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);

if ($editId && $editId > 0) {
    $stmt = $conn->prepare(
        'SELECT id, staff_id_no, fullname, role, email
         FROM staff WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editing_staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editing_staff) {
        $error_msg = 'Staff record not found.';
    }
}

/* Search staff records */
$sql = 'SELECT id, staff_id_no, fullname, role, email FROM staff';
$params = [];
$types = '';

if ($search !== '') {
    $sql .= ' WHERE staff_id_no LIKE ? OR fullname LIKE ? OR role LIKE ? OR email LIKE ?';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
    $types = 'ssss';
}

$sql .= ' ORDER BY id DESC';

$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$staff_result = $stmt->get_result();

$count_result = $conn->query('SELECT COUNT(*) AS total FROM staff');
$total_staff = (int)($count_result->fetch_assoc()['total'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management | SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/admin-dashboard.css?v=20261115-admin-menu-cleanup">
    <link rel="stylesheet" href="../../css/staff-management.css?v=20261114-icons">
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

    <nav aria-label="Administrator navigation">
        <a href="admin_dashboard.php"><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"> Dashboard</a>
        <a href="user_management.php"><img class="ui-icon" src="../../image/icon-users.svg" alt="" aria-hidden="true"> User Accounts</a>
        <a href="staff_management.php" class="active" aria-current="page"><img class="ui-icon" src="../../image/icon-staff.svg" alt="" aria-hidden="true"> Staff Management</a>
        <a href="system_reports.php"><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"> Clinic Reports</a>
        <a href="system_settings.php"><img class="ui-icon" src="../../image/icon-settings.svg" alt="" aria-hidden="true"> Security &amp; Settings</a>
        <a href="../logout.php" class="logout-link"
           onclick="return confirm('Are you sure you want to log out?')">
            <img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"> Logout
        </a>
    </nav>
</aside>

<main class="admin-main-content">
    <header class="admin-header-bar">
        <div>
            <h2>Staff Management</h2>
            <p class="staff-page-subtitle">Manage your campus clinic personnel.</p>
        </div>

        <div class="admin-profile-info">
            <img src="../../image/logo2.png" alt="Administrator profile">
            <div class="admin-profile-text">
                <h4><?= e($username) ?></h4>
                <p>Administrator</p>
            </div>
        </div>
    </header>

    <div class="admin-dashboard-container">

        <?php if ($success_msg !== ''): ?>
            <div class="staff-alert staff-alert-success"><?= e($success_msg) ?></div>
        <?php endif; ?>

        <?php if ($error_msg !== ''): ?>
            <div class="staff-alert staff-alert-error"><?= e($error_msg) ?></div>
        <?php endif; ?>

        <section class="staff-summary-card">
            <div class="staff-summary-icon"><img class="ui-icon" src="../../image/icon-staff.svg" alt="" aria-hidden="true"></div>
            <div>
                <p>Total Registered Staff</p>
                <h2><?= number_format($total_staff) ?></h2>
                <span>Clinic personnel directory</span>
            </div>
        </section>

        <section class="staff-panel" id="staff-form">
            <div class="staff-panel-heading">
                <div>
                    <h3><?= $editing_staff ? 'Edit Staff Member' : 'Add New Clinic Staff' ?></h3>
                    <p>
                        <?= $editing_staff
                            ? 'Update the staff information below.'
                            : 'Enter the information of the clinic staff member.' ?>
                    </p>
                </div>

                <?php if ($editing_staff): ?>
                    <a class="staff-btn staff-btn-light" href="staff_management.php">
                        Cancel Edit
                    </a>
                <?php endif; ?>
            </div>

            <form action="staff_management.php" method="POST" class="staff-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)($editing_staff['id'] ?? 0) ?>">

                <div class="staff-form-grid">
                    <div class="staff-field">
                        <label for="fullname">Full Name <span>*</span></label>
                        <input type="text" id="fullname" name="fullname"
                               maxlength="100" required
                               placeholder="e.g. Maria Santos"
                               value="<?= e($editing_staff['fullname'] ?? '') ?>">
                    </div>

                    <div class="staff-field">
                        <label for="role">Role / Position <span>*</span></label>
                        <input type="text" id="role" name="role"
                               maxlength="100" required
                               placeholder="e.g. Clinic Nurse"
                               value="<?= e($editing_staff['role'] ?? '') ?>">
                    </div>

                    <div class="staff-field">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               maxlength="255"
                               placeholder="staff@example.com"
                               value="<?= e($editing_staff['email'] ?? '') ?>">
                    </div>

                    <?php if ($editing_staff): ?>
                        <div class="staff-field">
                            <label for="staff_id_no">Staff ID</label>
                            <input type="text" id="staff_id_no"
                                   value="<?= e($editing_staff['staff_id_no'] ?? '') ?>"
                                   readonly>
                        </div>
                    <?php endif; ?>

                    <div class="staff-field">
                        <label for="password">
                            <?= $editing_staff ? 'New Password' : 'Password' ?>
                            <?php if (!$editing_staff): ?><span>*</span><?php endif; ?>
                        </label>
                        <input type="password" id="password" name="password"
                               maxlength="255"
                               <?= $editing_staff ? '' : 'required' ?>
                               autocomplete="new-password"
                               placeholder="<?= $editing_staff
                                   ? 'Leave blank to keep current password'
                                   : 'Enter a password' ?>">
                        <small>
                            <?= $editing_staff
                                ? 'Leave blank to keep the current password.'
                                : 'The password will be securely hashed.' ?>
                        </small>
                    </div>
                </div>

                <div class="staff-form-actions">
                    <button type="submit" class="staff-btn staff-btn-primary">
                        <?= $editing_staff ? 'Save Changes' : '+ Add Staff' ?>
                    </button>

                    <?php if (!$editing_staff): ?>
                        <button type="reset" class="staff-btn staff-btn-light">
                            Clear Form
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="staff-panel">
            <div class="staff-panel-heading staff-list-heading">
                <div>
                    <h3>Clinic Staff List</h3>
                    <p>Search, edit, and manage registered clinic personnel.</p>
                </div>

                <form class="staff-search" method="GET" action="staff_management.php">
                    <input type="search" name="search"
                           value="<?= e($search) ?>"
                           placeholder="Search staff ID, name, role, email..."
                           aria-label="Search staff">
                    <button type="submit" class="staff-btn staff-btn-primary">Search</button>

                    <?php if ($search !== ''): ?>
                        <a href="staff_management.php" class="staff-btn staff-btn-light">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="staff-table-wrap">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Full Name</th>
                            <th>Role / Position</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($staff_result->num_rows > 0): ?>
                        <?php while ($staff = $staff_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong class="staff-id">
                                        <?= e($staff['staff_id_no'] ?: '—') ?>
                                    </strong>
                                </td>
                                <td><?= e($staff['fullname']) ?></td>
                                <td><span class="staff-role"><?= e($staff['role']) ?></span></td>
                                <td><?= e($staff['email'] ?: '—') ?></td>
                                <td>
                                    <div class="staff-row-actions">
                                        <a class="staff-edit-link"
                                           href="staff_management.php?edit=<?= (int)$staff['id'] ?>#staff-form">
                                            Edit
                                        </a>

                                        <form method="POST" action="staff_management.php"
                                              onsubmit="return confirm('Delete this staff member? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id"
                                                   value="<?= (int)$staff['id'] ?>">
                                            <button type="submit" class="staff-delete-btn">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="staff-empty">
                                <span><img class="ui-icon" src="../../image/icon-staff.svg" alt="" aria-hidden="true"></span>
                                <strong>No staff records found</strong>
                                <p>
                                    <?= $search !== ''
                                        ? 'Try another search term.'
                                        : 'Add a staff member using the form above.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="staff-table-footer">
                Showing <?= number_format($staff_result->num_rows) ?> result(s)
                <?php if ($search !== ''): ?>
                    for <strong><?= e($search) ?></strong>
                <?php endif; ?>
            </div>
        </section>

    </div>

</main>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
