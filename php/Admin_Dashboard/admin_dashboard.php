<?php
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    ($_SESSION['user_role'] ?? '') !== 'Administrator'
) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../rbac_audit.php';
rbacRequireRole(['Administrator'], '../login.php');
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectDashboard(string $message, string $type = 'success'): void
{
    header(
        'Location: admin_dashboard.php?message=' .
        urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

if (empty($_SESSION['admin_dashboard_csrf'])) {
    $_SESSION['admin_dashboard_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['admin_dashboard_csrf'];

try {
    rbacEnsureTeacherReturnSchema($conn);
} catch (mysqli_sql_exception $exception) {
    error_log('Teacher return schema update failed: ' . $exception->getMessage());
    redirectDashboard('Teacher permissions could not be prepared. Please contact the system administrator.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        redirectDashboard('Invalid form submission. Refresh and try again.', 'error');
    }

    if (($_POST['action'] ?? '') === 'save_teacher_permissions') {
        $teacherId = filter_var(
            $_POST['teacher_id'] ?? null,
            FILTER_VALIDATE_INT
        );
        $courseYear = trim($_POST['course_year'] ?? '');

        if (!$teacherId || $teacherId < 1 || $courseYear === '') {
            redirectDashboard('Select a teacher and course/year.', 'error');
        }

        try {
            $teacherStmt = $conn->prepare(
                "SELECT id FROM staff WHERE id = ? AND LOWER(TRIM(role)) = 'teacher' LIMIT 1"
            );
            $teacherStmt->bind_param('i', $teacherId);
            $teacherStmt->execute();
            $teacherExists = $teacherStmt->get_result()->num_rows === 1;
            $teacherStmt->close();

            $courseStmt = $conn->prepare(
                'SELECT course_year FROM students
                 WHERE course_year COLLATE utf8mb4_unicode_ci =
                       ? COLLATE utf8mb4_unicode_ci
                   AND course_year IS NOT NULL
                   AND TRIM(course_year) <> \'\'
                 LIMIT 1'
            );
            $courseStmt->bind_param('s', $courseYear);
            $courseStmt->execute();
            $courseExists = $courseStmt->get_result()->num_rows === 1;
            $courseStmt->close();

            if (!$teacherExists || !$courseExists) {
                redirectDashboard('The selected teacher or course/year is unavailable.', 'error');
            }

            $canViewPasses = isset($_POST['can_view_passes']) ? 1 : 0;
            $canViewAuthorization = isset($_POST['can_view_authorization']) ? 1 : 0;
            $canViewReturnRecords = isset($_POST['can_view_return_records']) ? 1 : 0;
            $canConfirmReturns = isset($_POST['can_confirm_returns']) ? 1 : 0;
            $canReview = isset($_POST['can_review']) ? 1 : 0;

            if (
                !$canViewPasses &&
                !$canViewAuthorization &&
                !$canViewReturnRecords &&
                !$canConfirmReturns &&
                !$canReview
            ) {
                redirectDashboard('Grant at least one permission or leave existing permissions unchanged.', 'error');
            }

            $permissionStmt = $conn->prepare(
                'INSERT INTO teacher_course_assignments
                    (teacher_id, course_year, can_view_passes,
                     can_view_authorization, can_view_return_records,
                     can_confirm_returns, can_review)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    can_view_passes = VALUES(can_view_passes),
                    can_view_authorization = VALUES(can_view_authorization),
                    can_view_return_records = VALUES(can_view_return_records),
                    can_confirm_returns = VALUES(can_confirm_returns),
                    can_review = VALUES(can_review)'
            );
            $permissionStmt->bind_param(
                'isiiiii',
                $teacherId,
                $courseYear,
                $canViewPasses,
                $canViewAuthorization,
                $canViewReturnRecords,
                $canConfirmReturns,
                $canReview
            );
            $permissionStmt->execute();
            $permissionStmt->close();

            redirectDashboard('Teacher permissions saved.');
        } catch (mysqli_sql_exception $exception) {
            error_log('Teacher permission update failed: ' . $exception->getMessage());
            redirectDashboard(
                'Unable to save permissions. Ensure the teacher-permissions tables are installed.',
                'error'
            );
        }
    }

    if (($_POST['action'] ?? '') === 'remove_teacher_permissions') {
        $assignmentId = filter_var(
            $_POST['assignment_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$assignmentId || $assignmentId < 1) {
            redirectDashboard('Invalid teacher permission assignment.', 'error');
        }

        try {
            $removeStmt = $conn->prepare(
                'DELETE FROM teacher_course_assignments WHERE id = ?'
            );
            $removeStmt->bind_param('i', $assignmentId);
            $removeStmt->execute();
            $removed = $removeStmt->affected_rows === 1;
            $removeStmt->close();

            redirectDashboard(
                $removed
                    ? 'Teacher course/year permissions revoked.'
                    : 'Permission assignment was not found.',
                $removed ? 'success' : 'error'
            );
        } catch (mysqli_sql_exception $exception) {
            error_log('Teacher permission removal failed: ' . $exception->getMessage());
            redirectDashboard('Unable to revoke teacher permissions.', 'error');
        }
    }

    redirectDashboard('Unknown dashboard action.', 'error');
}

$message = trim($_GET['message'] ?? '');
$messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';
$username = $_SESSION['admin_username'] ?? 'Administrator';
rbacLog($conn, 'view', 'Admin Dashboard', 'Page', 'admin_dashboard', 'Administrator dashboard accessed');
$dashboardError = '';
$teacherPermissionTablesReady = false;
$stats = [
    'accounts' => 0,
    'students' => 0,
    'staff' => 0,
    'teachers' => 0,
    'consultations' => 0,
    'today_consultations' => 0,
    'health_records' => 0,
];
$recentActivity = [];
$teachers = [];
$courseYears = [];
$teacherAssignments = [];

try {
    $countQueries = [
        'accounts' => 'SELECT
            (SELECT COUNT(*) FROM users) +
            (SELECT COUNT(*) FROM admin) AS total',
        'students' => 'SELECT COUNT(*) AS total FROM students',
        'staff' => "SELECT COUNT(*) AS total FROM staff
                    WHERE LOWER(TRIM(role)) <> 'teacher'",
        'teachers' => "SELECT COUNT(*) AS total FROM teachers
                       WHERE LOWER(TRIM(status)) = 'active'",
        'consultations' => 'SELECT COUNT(*) AS total FROM consultations',
        'today_consultations' => 'SELECT COUNT(*) AS total FROM consultations
                                  WHERE consultation_date = CURDATE()',
        'health_records' => 'SELECT COUNT(*) AS total FROM health_documentation',
    ];

    foreach ($countQueries as $key => $query) {
        $result = $conn->query($query);
        $stats[$key] = (int)($result->fetch_assoc()['total'] ?? 0);
        $result->free();
    }

    $activityResult = $conn->query(
        'SELECT c.consultation_date AS activity_date,
                c.reason AS activity,
                c.staff_name AS actor,
                s.fullname AS student_name,
                s.student_id_no
         FROM consultations c
         LEFT JOIN students s
           ON s.student_id_no COLLATE utf8mb4_unicode_ci =
              c.student_id COLLATE utf8mb4_unicode_ci
           OR CAST(s.id AS CHAR) COLLATE utf8mb4_unicode_ci =
              c.student_id COLLATE utf8mb4_unicode_ci
         ORDER BY c.consultation_date DESC, c.id DESC
         LIMIT 8'
    );

    while ($row = $activityResult->fetch_assoc()) {
        $row['activity_type'] = 'Clinic visit';
        $recentActivity[] = $row;
    }
    $activityResult->free();

    $accountActivityResult = $conn->query(
        'SELECT created_at AS activity_date, name, role
         FROM users
         ORDER BY created_at DESC, id DESC
         LIMIT 8'
    );
    while ($row = $accountActivityResult->fetch_assoc()) {
        $recentActivity[] = [
            'activity_date' => $row['activity_date'],
            'activity' => 'Account created',
            'actor' => 'Not recorded',
            'student_name' => $row['name'],
            'student_id_no' => $row['role'],
            'activity_type' => 'User account',
        ];
    }
    $accountActivityResult->free();

    usort($recentActivity, static function (array $first, array $second): int {
        return strtotime((string)$second['activity_date'])
            <=> strtotime((string)$first['activity_date']);
    });
    $recentActivity = array_slice($recentActivity, 0, 10);

    $teacherAccountResult = $conn->query(
        'SELECT id, teacher_id_no, fullname, email, password
         FROM teachers
         WHERE LOWER(TRIM(status)) = \'active\''
    );
    while ($teacherAccount = $teacherAccountResult->fetch_assoc()) {
        $teacherEmail = trim((string)($teacherAccount['email'] ?? ''));
        $teacherName = (string)$teacherAccount['fullname'];

        $mirrorLookup = $conn->prepare(
            'SELECT id FROM staff
             WHERE LOWER(TRIM(role)) = \'teacher\'
               AND (
                    (? <> \'\' AND LOWER(TRIM(email)) = LOWER(TRIM(?)))
                    OR LOWER(TRIM(fullname)) = LOWER(TRIM(?))
               )
             LIMIT 1'
        );
        $mirrorLookup->bind_param('sss', $teacherEmail, $teacherEmail, $teacherName);
        $mirrorLookup->execute();
        $mirrorRow = $mirrorLookup->get_result()->fetch_assoc();
        $mirrorLookup->close();
        $staffId = (int)($mirrorRow['id'] ?? 0);

        if ($staffId < 1) {
            $staffIdNo = 'TEACHER-' . str_pad((string)$teacherAccount['id'], 4, '0', STR_PAD_LEFT);
            $mirrorInsert = $conn->prepare(
                "INSERT INTO staff
                 (staff_id_no, password, fullname, role, email)
                 VALUES (?, ?, ?, 'Teacher', ?)"
            );
            $mirrorInsert->bind_param(
                'ssss',
                $staffIdNo,
                $teacherAccount['password'],
                $teacherName,
                $teacherEmail
            );
            $mirrorInsert->execute();
            $staffId = (int)$mirrorInsert->insert_id;
            $mirrorInsert->close();
        } else {
            $mirrorUpdate = $conn->prepare(
                "UPDATE staff
                 SET staff_id_no = COALESCE(NULLIF(staff_id_no, ''), ?),
                     fullname = ?, role = 'Teacher', email = ?, password = ?
                 WHERE id = ?"
            );
            $staffIdNo = 'TEACHER-' . str_pad((string)$teacherAccount['id'], 4, '0', STR_PAD_LEFT);
            $mirrorUpdate->bind_param(
                'ssssi',
                $staffIdNo,
                $teacherName,
                $teacherEmail,
                $teacherAccount['password'],
                $staffId
            );
            $mirrorUpdate->execute();
            $mirrorUpdate->close();
        }

        $teachers[] = [
            'id' => $staffId,
            'fullname' => $teacherName,
            'teacher_id_no' => (string)$teacherAccount['teacher_id_no'],
            'role' => 'Teacher',
        ];
    }
    $teacherAccountResult->free();

    $courseYearsResult = $conn->query(
        "SELECT DISTINCT course_year FROM students
         WHERE course_year IS NOT NULL AND TRIM(course_year) <> ''
         ORDER BY course_year"
    );
    while ($row = $courseYearsResult->fetch_assoc()) {
        $courseYears[] = $row['course_year'];
    }
    $courseYearsResult->free();

    $teacherPermissionTablesReady = true;

    if ($teacherPermissionTablesReady) {
        $assignmentResult = $conn->query(
            'SELECT a.id, a.teacher_id, a.course_year,
                    a.can_view_passes, a.can_view_authorization,
                    a.can_view_return_records, a.can_confirm_returns, a.can_review,
                    s.fullname
             FROM teacher_course_assignments a
             INNER JOIN staff s ON s.id = a.teacher_id
             ORDER BY s.fullname, a.course_year'
        );
        while ($row = $assignmentResult->fetch_assoc()) {
            $teacherAssignments[] = $row;
        }
        $assignmentResult->free();
    }
} catch (mysqli_sql_exception $exception) {
    error_log('Admin dashboard query failed: ' . $exception->getMessage());
    $dashboardError = 'Some dashboard information could not be loaded. Check the database setup and try again.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SHRCMS</title>
    <link rel="stylesheet" href="../../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/admin-dashboard.css?v=20261117-admin-operations">
</head>

<body class="admin-dashboard-body">
    <aside class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <img src="../../image/logo2.png" alt="SHRCMS Logo">
            <div>
                <h3>SHRCMS</h3>
                <p>Student Health Record &amp; Clinic Management System</p>
            </div>
        </div>
        <nav aria-label="Administrator navigation">
            <a href="admin_dashboard.php" class="active" aria-current="page"><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"> Dashboard</a>
            <a href="user_management.php"><img class="ui-icon" src="../../image/icon-users.svg" alt="" aria-hidden="true"> User Accounts</a>
            <a href="staff_management.php"><img class="ui-icon" src="../../image/icon-staff.svg" alt="" aria-hidden="true"> Staff Management</a>
            <a href="system_reports.php"><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"> System Reports</a>
            <a href="system_settings.php"><img class="ui-icon" src="../../image/icon-settings.svg" alt="" aria-hidden="true"> Security &amp; Settings</a>
            <a href="../logout.php" class="logout-link"><img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"> Logout</a>
        </nav>
    </aside>

    <main class="admin-main-content">
        <header class="admin-header-bar">
            <div>
                <span class="admin-page-eyebrow">ADMINISTRATION</span>
                <h1>Admin Dashboard</h1>
            </div>
            <div class="admin-profile-info">
                <img src="../../image/logo2.png" alt="">
                <div class="admin-profile-text">
                    <h4><?= e($username) ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
        </header>

        <div class="admin-dashboard-container">
            <?php if ($message !== ''): ?>
                <div class="admin-dashboard-alert <?= $messageType === 'error' ? 'is-error' : 'is-success' ?>"
                     role="<?= $messageType === 'error' ? 'alert' : 'status' ?>">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>
            <?php if ($dashboardError !== ''): ?>
                <div class="admin-dashboard-alert is-error" role="alert"><?= e($dashboardError) ?></div>
            <?php endif; ?>

            <section aria-labelledby="overview-heading">
                <div class="admin-dashboard-section-heading">
                    <div>
                        <h2 id="overview-heading">System overview</h2>
                        <p>Current account and clinic activity at a glance.</p>
                    </div>
                    <a class="admin-text-link" href="system_reports.php">View all reports <span aria-hidden="true">→</span></a>
                </div>

                <div class="admin-stats-grid admin-stats-grid-wide">
                    <article class="admin-stat-card users">
                        <div class="admin-stat-header">
                            <span>User &amp; admin accounts</span>
                            <img class="ui-icon" src="../../image/icon-users.svg" alt="" aria-hidden="true">
                        </div>
                        <strong class="admin-stat-number"><?= number_format($stats['accounts']) ?></strong>
                        <a href="user_management.php">Manage accounts →</a>
                    </article>
                    <article class="admin-stat-card students">
                        <div class="admin-stat-header">
                            <span>Students</span>
                            <img class="ui-icon" src="../../image/icon-student.svg" alt="" aria-hidden="true">
                        </div>
                        <strong class="admin-stat-number"><?= number_format($stats['students']) ?></strong>
                        <span class="admin-stat-caption">Health profiles</span>
                    </article>
                    <article class="admin-stat-card staff">
                        <div class="admin-stat-header">
                            <span>Clinic staff</span>
                            <img class="ui-icon" src="../../image/icon-clinic.svg" alt="" aria-hidden="true">
                        </div>
                        <strong class="admin-stat-number"><?= number_format($stats['staff']) ?></strong>
                        <a href="staff_management.php">Manage staff →</a>
                    </article>
                    <article class="admin-stat-card consultations">
                        <div class="admin-stat-header">
                            <span>Clinic visits</span>
                            <img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true">
                        </div>
                        <strong class="admin-stat-number"><?= number_format($stats['consultations']) ?></strong>
                        <span class="admin-stat-caption"><?= number_format($stats['today_consultations']) ?> recorded today</span>
                    </article>
                    <article class="admin-stat-card health-records">
                        <div class="admin-stat-header">
                            <span>Health records</span>
                            <img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true">
                        </div>
                        <strong class="admin-stat-number"><?= number_format($stats['health_records']) ?></strong>
                        <a href="system_reports.php">Open clinic reports →</a>
                    </article>
                    <article class="admin-stat-card teachers">
                        <div class="admin-stat-header">
                            <span>Teachers</span>
                            <img class="ui-icon" src="../../image/icon-profile.svg" alt="" aria-hidden="true">
                        </div>
                        <strong class="admin-stat-number"><?= number_format($stats['teachers']) ?></strong>
                        <a href="user_management.php?search=Teacher">View teacher accounts →</a>
                    </article>
                </div>
            </section>

            <section class="admin-operations-grid" aria-label="Administration tools">
                <article class="admin-operation-card">
                    <span class="admin-operation-icon"><img src="../../image/icon-users.svg" alt="" aria-hidden="true"></span>
                    <div>
                        <h3>User accounts &amp; permissions</h3>
                        <p>Create and manage portal accounts, staff roles, and teacher access to assigned course/year clinic information.</p>
                        <a href="user_management.php">Manage user accounts →</a>
                    </div>
                </article>
                <article class="admin-operation-card">
                    <span class="admin-operation-icon security"><img src="../../image/icon-settings.svg" alt="" aria-hidden="true"></span>
                    <div>
                        <h3>System security</h3>
                        <p>Review system configuration and limit access to authorized administrator accounts.</p>
                        <a href="system_settings.php">Open security &amp; settings →</a>
                    </div>
                </article>
                <article class="admin-operation-card">
                    <span class="admin-operation-icon reports"><img src="../../image/icon-reports.svg" alt="" aria-hidden="true"></span>
                    <div>
                        <h3>Clinic reports &amp; statistics</h3>
                        <p>Review student totals, consultation records, and current clinic activity reports.</p>
                        <a href="system_reports.php">View clinic reports →</a>
                    </div>
                </article>
                <article class="admin-operation-card">
                    <span class="admin-operation-icon backup"><img src="../../image/icon-document.svg" alt="" aria-hidden="true"></span>
                    <div>
                        <h3>Database backup</h3>
                        <p>Download a SQL export directly. The backup is streamed to your browser and is not saved in the web directory.</p>
                        <form method="post" action="download_backup.php"
                              onsubmit="return confirm('Download a database backup containing student and clinic information? Store it securely.');">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <button type="submit" class="admin-download-button">Download SQL backup</button>
                        </form>
                    </div>
                </article>
            </section>

            <section class="admin-permissions-panel" aria-labelledby="teacher-permissions-heading">
                <div class="admin-dashboard-section-heading">
                    <div>
                        <h2 id="teacher-permissions-heading">Teacher course/year permissions</h2>
                        <p>Grant the minimum clinic record access needed for each teacher assignment.</p>
                    </div>
                </div>

                <?php if (!$teacherPermissionTablesReady): ?>
                    <div class="admin-setup-notice" role="status">
                        Teacher permission storage is not installed yet. Import the teacher dashboard tables from the project SQL file before assigning access.
                    </div>
                <?php elseif (!$teachers): ?>
                    <div class="admin-setup-notice" role="status">
                        No active teacher accounts were found. Add a Teacher account in User Accounts before assigning clinic access.
                    </div>
                <?php elseif (!$courseYears): ?>
                    <div class="admin-setup-notice" role="status">
                        <strong><?= number_format(count($teachers)) ?> active teacher account(s) found.</strong>
                        Student profiles do not have a course/year value yet, so class permissions cannot be assigned. Add the course/year in
                        <a href="../Staff_Dashboard/student_profiles.php">Student Profiles</a>, then return here to assign teacher access.
                        <div class="admin-table-wrap">
                            <table class="admin-permissions-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Teacher</th>
                                        <th scope="col">Teacher ID</th>
                                        <th scope="col">Account</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td><?= e($teacher['fullname']) ?></td>
                                            <td><?= e($teacher['teacher_id_no']) ?></td>
                                            <td>Active</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="post" class="admin-permission-form">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_teacher_permissions">

                        <label>
                            Teacher
                            <select name="teacher_id" required>
                                <option value="">Select a teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= (int)$teacher['id'] ?>"><?= e($teacher['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Course / year
                            <select name="course_year" required>
                                <option value="">Select a course/year</option>
                                <?php foreach ($courseYears as $courseYear): ?>
                                    <option value="<?= e($courseYear) ?>"><?= e($courseYear) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <fieldset class="admin-permission-options">
                            <legend>Allowed information and actions</legend>
                            <label><input type="checkbox" name="can_view_passes" value="1"> View clinic passes</label>
                            <label><input type="checkbox" name="can_view_authorization" value="1"> View authorization status</label>
                            <label><input type="checkbox" name="can_view_return_records" value="1"> View return-to-class records</label>
                            <label><input type="checkbox" name="can_confirm_returns" value="1"> Confirm student returned to class</label>
                            <label><input type="checkbox" name="can_review" value="1"> Mark passes as reviewed</label>
                        </fieldset>

                        <button class="admin-save-permissions" type="submit">Save permissions</button>
                    </form>

                    <?php if ($teacherAssignments): ?>
                        <div class="admin-table-wrap">
                            <table class="admin-permissions-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Teacher</th>
                                        <th scope="col">Course / year</th>
                                        <th scope="col">Passes</th>
                                        <th scope="col">Authorization</th>
                                        <th scope="col">Return records</th>
                                        <th scope="col">Confirm return</th>
                                        <th scope="col">Review</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacherAssignments as $assignment): ?>
                                        <tr>
                                            <td><?= e($assignment['fullname']) ?></td>
                                            <td><?= e($assignment['course_year']) ?></td>
                                            <td><?= (int)$assignment['can_view_passes'] ? 'Allowed' : '—' ?></td>
                                            <td><?= (int)$assignment['can_view_authorization'] ? 'Allowed' : '—' ?></td>
                                            <td><?= (int)$assignment['can_view_return_records'] ? 'Allowed' : '—' ?></td>
                                            <td><?= (int)$assignment['can_confirm_returns'] ? 'Allowed' : '—' ?></td>
                                            <td><?= (int)$assignment['can_review'] ? 'Allowed' : '—' ?></td>
                                            <td>
                                                <form method="post" class="admin-revoke-form"
                                                      onsubmit="return confirm('Revoke this teacher course/year assignment?');">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="remove_teacher_permissions">
                                                    <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                                                    <button type="submit" class="admin-revoke-button">Revoke</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="admin-activity-panel" aria-labelledby="activity-heading">
                <div class="admin-dashboard-section-heading">
                    <div>
                        <h2 id="activity-heading">Recent clinic activity</h2>
                        <p>Latest clinic visits and registered accounts. Logins and administrative changes are not tracked.</p>
                    </div>
                    <a class="admin-text-link" href="system_reports.php?report=consultations">All consultations <span aria-hidden="true">→</span></a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-activity-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Record type</th>
                                <th scope="col">Student / account</th>
                                <th scope="col">Activity</th>
                                <th scope="col">Recorded by</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recentActivity): ?>
                                <tr><td colspan="5" class="admin-empty-row">No recent clinic or account activity is available.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <tr>
                                        <td><?= e($activity['activity_date']) ?></td>
                                        <td><?= e($activity['activity_type']) ?></td>
                                        <td>
                                            <strong><?= e($activity['student_name'] ?: 'Student record unavailable') ?></strong>
                                            <?php if (!empty($activity['student_id_no'])): ?>
                                                <small><?= e($activity['student_id_no']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($activity['activity'] ?: 'Clinic consultation') ?></td>
                                        <td><?= e($activity['actor'] ?: 'Not recorded') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</body>

</html>
