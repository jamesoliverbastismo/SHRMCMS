<?php
session_start();
if (!empty($_SESSION['must_change_password'])) {
    header('Location: ../change_initial_password.php');
    exit();
}

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    ($_SESSION['user_role'] ?? '') !== 'Teacher'
) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatTeacherDate($value, string $format = 'M j, Y g:i A'): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime((string)$value);
    return $timestamp === false ? '—' : date($format, $timestamp);
}

function redirectTeacher(string $message, string $type = 'success'): void
{
    header(
        'Location: teacher_dashboard.php?message=' .
        urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

$teacherId = (int)($_SESSION['staff_id'] ?? $_SESSION['user_id'] ?? 0);
$teacherName = $_SESSION['admin_username'] ?? 'Teacher';
$databaseError = '';
$assignments = [];
$passes = [];
$summary = [
    'visible_passes' => 0,
    'todays_passes' => 0,
    'pending_authorization' => 0,
    'pending_returns' => 0,
];

if ($teacherId < 1) {
    http_response_code(403);
    exit('Access denied. Teacher account is not linked to a staff record.');
}

if (empty($_SESSION['teacher_dashboard_csrf'])) {
    $_SESSION['teacher_dashboard_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['teacher_dashboard_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $passId = filter_var($_POST['pass_id'] ?? null, FILTER_VALIDATE_INT);

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        redirectTeacher('Invalid form submission. Refresh and try again.', 'error');
    }

    if (($_POST['action'] ?? '') !== 'mark_reviewed' || !$passId || $passId < 1) {
        redirectTeacher('Invalid clinic pass record.', 'error');
    }

    try {
        $stmt = $conn->prepare(
            'INSERT IGNORE INTO teacher_pass_reviews (pass_id, teacher_id)
             SELECT p.id, ?
             FROM clinic_passes p
             INNER JOIN students s
                 ON s.student_id_no COLLATE utf8mb4_unicode_ci =
                    p.student_id_no COLLATE utf8mb4_unicode_ci
             INNER JOIN teacher_course_assignments a
                 ON a.teacher_id = ?
                 AND a.course_year COLLATE utf8mb4_unicode_ci =
                     s.course_year COLLATE utf8mb4_unicode_ci
                 AND a.can_review = 1
             WHERE p.id = ?'
        );
        $stmt->bind_param('iii', $teacherId, $teacherId, $passId);
        $stmt->execute();
        $reviewed = $stmt->affected_rows === 1;
        $stmt->close();

        redirectTeacher(
            $reviewed
                ? 'Clinic pass marked as reviewed.'
                : 'This pass is not assigned to you, or you have already reviewed it.',
            $reviewed ? 'success' : 'error'
        );
    } catch (mysqli_sql_exception $exception) {
        error_log('Teacher pass review failed: ' . $exception->getMessage());
        redirectTeacher('Unable to update the review. Please try again.', 'error');
    }
}

$message = trim($_GET['message'] ?? '');
$messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';

try {
    $assignmentStmt = $conn->prepare(
        'SELECT course_year, can_view_passes, can_view_authorization,
                can_view_return_records, can_review
         FROM teacher_course_assignments
         WHERE teacher_id = ?
           AND (can_view_passes = 1 OR can_view_authorization = 1
                OR can_view_return_records = 1 OR can_review = 1)
         ORDER BY course_year'
    );
    $assignmentStmt->bind_param('i', $teacherId);
    $assignmentStmt->execute();
    $assignmentResult = $assignmentStmt->get_result();

    while ($assignment = $assignmentResult->fetch_assoc()) {
        $assignments[] = $assignment;
    }
    $assignmentStmt->close();

    if ($assignments) {
        $summaryStmt = $conn->prepare(
            'SELECT
                COUNT(IF(a.can_view_passes = 1, 1, NULL)) AS visible_passes,
                COUNT(IF(a.can_view_passes = 1
                         AND DATE(p.pass_date) = CURDATE(), 1, NULL)) AS todays_passes,
                COUNT(IF(a.can_view_authorization = 1
                         AND p.authorization_status = \'Pending\', 1, NULL)) AS pending_authorization,
                COUNT(IF(a.can_view_return_records = 1
                         AND p.return_status = \'Pending\', 1, NULL)) AS pending_returns
             FROM clinic_passes p
             INNER JOIN students s
                 ON s.student_id_no COLLATE utf8mb4_unicode_ci =
                    p.student_id_no COLLATE utf8mb4_unicode_ci
             INNER JOIN teacher_course_assignments a
                 ON a.teacher_id = ?
                 AND a.course_year COLLATE utf8mb4_unicode_ci =
                     s.course_year COLLATE utf8mb4_unicode_ci
             WHERE a.can_view_passes = 1 OR a.can_view_authorization = 1
                   OR a.can_view_return_records = 1 OR a.can_review = 1'
        );
        $summaryStmt->bind_param('i', $teacherId);
        $summaryStmt->execute();
        $summary = $summaryStmt->get_result()->fetch_assoc() ?: $summary;
        $summaryStmt->close();

        $passesStmt = $conn->prepare(
            'SELECT p.id, p.student_id_no, p.pass_date, p.pass_status,
                    p.authorization_status, p.return_status, p.returned_at,
                    s.fullname, s.course_year,
                    a.can_view_passes, a.can_view_authorization,
                    a.can_view_return_records, a.can_review,
                    r.reviewed_at
             FROM clinic_passes p
             INNER JOIN students s
                 ON s.student_id_no COLLATE utf8mb4_unicode_ci =
                    p.student_id_no COLLATE utf8mb4_unicode_ci
             INNER JOIN teacher_course_assignments a
                 ON a.teacher_id = ?
                 AND a.course_year COLLATE utf8mb4_unicode_ci =
                     s.course_year COLLATE utf8mb4_unicode_ci
             LEFT JOIN teacher_pass_reviews r
                 ON r.pass_id = p.id AND r.teacher_id = a.teacher_id
             WHERE a.can_view_passes = 1 OR a.can_view_authorization = 1
                   OR a.can_view_return_records = 1 OR a.can_review = 1
             ORDER BY p.pass_date DESC, p.id DESC
             LIMIT 50'
        );
        $passesStmt->bind_param('i', $teacherId);
        $passesStmt->execute();
        $passesResult = $passesStmt->get_result();

        while ($pass = $passesResult->fetch_assoc()) {
            $passes[] = $pass;
        }
        $passesStmt->close();
    }
} catch (mysqli_sql_exception $exception) {
    error_log('Teacher dashboard query failed: ' . $exception->getMessage());
    $databaseError = 'Clinic pass information is temporarily unavailable. Please contact the clinic administrator.';
}

$canViewPasses = false;
$canViewAuthorization = false;
$canViewReturnRecords = false;
$canReview = false;

foreach ($assignments as $assignment) {
    $canViewPasses = $canViewPasses || (int)$assignment['can_view_passes'] === 1;
    $canViewAuthorization = $canViewAuthorization || (int)$assignment['can_view_authorization'] === 1;
    $canViewReturnRecords = $canViewReturnRecords || (int)$assignment['can_view_return_records'] === 1;
    $canReview = $canReview || (int)$assignment['can_review'] === 1;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | SHRCMS</title>
    <link rel="stylesheet" href="../../css/teacher-dashboard.css?v=20261117">
</head>

<body class="teacher-dashboard-body">
    <div class="teacher-layout">
        <aside class="teacher-sidebar">
            <a class="teacher-brand" href="teacher_dashboard.php">
                <img src="../../image/logo2.png" alt="SHRCMS Logo">
                <span>
                    <strong>SHRCMS</strong>
                    <small>Teacher Portal</small>
                </span>
            </a>

            <p class="teacher-nav-label">TEACHER MENU</p>
            <nav class="teacher-navigation" aria-label="Teacher navigation">
                <a class="teacher-nav-link active" href="teacher_dashboard.php" aria-current="page">
                    <img src="../../image/icon-dashboard.svg" alt="" aria-hidden="true">
                    <span>Dashboard</span>
                </a>
                <a class="teacher-nav-link" href="../logout.php"
                   onclick="return confirm('Are you sure you want to log out?');">
                    <img src="../../image/icon-logout.svg" alt="" aria-hidden="true">
                    <span>Log Out</span>
                </a>
            </nav>

            <div class="teacher-sidebar-note">
                <strong>Permission-based access</strong>
                <span>Only records for your assigned course/year and enabled permissions are shown.</span>
            </div>
        </aside>

        <main class="teacher-main">
            <header class="teacher-header">
                <div>
                    <span class="teacher-eyebrow">TEACHER PORTAL</span>
                    <h1>Clinic Pass Dashboard</h1>
                    <p>Review clinic pass, student authorization, and return-to-class records assigned to you.</p>
                </div>
                <div class="teacher-profile">
                    <span class="teacher-avatar" aria-hidden="true"><?= e(mb_substr($teacherName, 0, 1)) ?></span>
                    <span>
                        <strong><?= e($teacherName) ?></strong>
                        <small>Teacher</small>
                    </span>
                </div>
            </header>

            <section class="teacher-content">
                <?php if ($message !== ''): ?>
                    <div class="teacher-alert <?= $messageType === 'error' ? 'is-error' : 'is-success' ?>"
                         role="<?= $messageType === 'error' ? 'alert' : 'status' ?>">
                        <?= e($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($databaseError !== ''): ?>
                    <div class="teacher-alert is-error" role="alert"><?= e($databaseError) ?></div>
                <?php endif; ?>

                <section class="teacher-welcome">
                    <div>
                        <span class="teacher-welcome-tag">YOUR ASSIGNED CLASSES</span>
                        <h2>Welcome, <?= e($teacherName) ?></h2>
                        <p>Use this dashboard to verify student clinic authorization and return-to-class details. You can mark a pass reviewed when that permission has been assigned.</p>
                        <?php if ($assignments): ?>
                            <div class="teacher-course-list" aria-label="Assigned course and year">
                                <?php foreach ($assignments as $assignment): ?>
                                    <span><?= e($assignment['course_year']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="teacher-welcome-mark" aria-hidden="true">
                        <img src="../../image/icon-clinic.svg" alt="">
                    </div>
                </section>

                <?php if (!$databaseError && !$assignments): ?>
                    <section class="teacher-empty-assignment">
                        <strong>No clinic permissions assigned</strong>
                        <p>Your account does not currently have a course/year assignment with clinic record access. Contact the administrator if you believe this is incorrect.</p>
                    </section>
                <?php endif; ?>

                <?php if ($assignments): ?>
                    <section class="teacher-summary" aria-label="Clinic record summary">
                        <?php if ($canViewPasses): ?>
                            <article class="teacher-stat-card">
                                <span class="teacher-stat-icon blue"><img src="../../image/icon-clinic.svg" alt=""></span>
                                <span class="teacher-stat-label">Assigned clinic passes</span>
                                <strong><?= number_format((int)$summary['visible_passes']) ?></strong>
                                <small><?= number_format((int)$summary['todays_passes']) ?> issued today</small>
                            </article>
                        <?php endif; ?>
                        <?php if ($canViewAuthorization): ?>
                            <article class="teacher-stat-card">
                                <span class="teacher-stat-icon amber"><img src="../../image/icon-check.svg" alt=""></span>
                                <span class="teacher-stat-label">Authorization pending</span>
                                <strong><?= number_format((int)$summary['pending_authorization']) ?></strong>
                                <small>Awaiting an authorization check</small>
                            </article>
                        <?php endif; ?>
                        <?php if ($canViewReturnRecords): ?>
                            <article class="teacher-stat-card">
                                <span class="teacher-stat-icon green"><img src="../../image/icon-clock.svg" alt=""></span>
                                <span class="teacher-stat-label">Return records pending</span>
                                <strong><?= number_format((int)$summary['pending_returns']) ?></strong>
                                <small>Awaiting a return-to-class record</small>
                            </article>
                        <?php endif; ?>
                    </section>

                    <section class="teacher-records-panel">
                        <div class="teacher-panel-heading">
                            <div>
                                <h2>Recent clinic passes</h2>
                                <p>Showing up to 50 records for your assigned course/year permissions.</p>
                            </div>
                            <span class="teacher-record-count"><?= number_format(count($passes)) ?> shown</span>
                        </div>

                        <div class="teacher-table-wrap">
                            <table class="teacher-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Student</th>
                                        <?php if ($canViewPasses): ?>
                                            <th scope="col">Clinic pass</th>
                                            <th scope="col">Pass status</th>
                                        <?php endif; ?>
                                        <?php if ($canViewAuthorization): ?>
                                            <th scope="col">Authorization</th>
                                        <?php endif; ?>
                                        <?php if ($canViewReturnRecords): ?>
                                            <th scope="col">Return to class</th>
                                        <?php endif; ?>
                                        <?php if ($canReview): ?>
                                            <th scope="col">Review</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$passes): ?>
                                        <tr>
                                            <td class="teacher-empty-row"
                                                colspan="<?= 1 + ($canViewPasses ? 2 : 0) + ($canViewAuthorization ? 1 : 0) + ($canViewReturnRecords ? 1 : 0) + ($canReview ? 1 : 0) ?>">
                                                No clinic pass records are available for your assigned classes.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($passes as $pass): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= e($pass['fullname']) ?></strong>
                                                    <small><?= e($pass['course_year']) ?> · <?= e($pass['student_id_no']) ?></small>
                                                </td>
                                                <?php if ((int)$pass['can_view_passes'] === 1): ?>
                                                    <td><?= e(formatTeacherDate($pass['pass_date'])) ?></td>
                                                    <td><span class="teacher-status"><?= e($pass['pass_status']) ?></span></td>
                                                <?php elseif ($canViewPasses): ?>
                                                    <td>—</td>
                                                    <td>—</td>
                                                <?php endif; ?>
                                                <?php if ($canViewAuthorization): ?>
                                                    <td>
                                                        <?php if ((int)$pass['can_view_authorization'] === 1): ?>
                                                            <span class="teacher-status <?= strtolower(str_replace(' ', '-', $pass['authorization_status'])) ?>">
                                                                <?= e($pass['authorization_status']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            —
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                                <?php if ($canViewReturnRecords): ?>
                                                    <td>
                                                        <?php if ((int)$pass['can_view_return_records'] === 1): ?>
                                                            <span class="teacher-status <?= strtolower(str_replace(' ', '-', $pass['return_status'])) ?>">
                                                                <?= e($pass['return_status']) ?>
                                                            </span>
                                                            <?php if (!empty($pass['returned_at'])): ?>
                                                                <small class="teacher-return-time"><?= e(formatTeacherDate($pass['returned_at'])) ?></small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            —
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                                <?php if ($canReview): ?>
                                                    <td>
                                                        <?php if ((int)$pass['can_review'] !== 1): ?>
                                                            <span class="teacher-muted">Not assigned</span>
                                                        <?php elseif (!empty($pass['reviewed_at'])): ?>
                                                            <span class="teacher-reviewed">Reviewed</span>
                                                            <small class="teacher-return-time"><?= e(formatTeacherDate($pass['reviewed_at'])) ?></small>
                                                        <?php else: ?>
                                                            <form method="post" class="teacher-review-form">
                                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                                <input type="hidden" name="pass_id" value="<?= (int)$pass['id'] ?>">
                                                                <input type="hidden" name="action" value="mark_reviewed">
                                                                <button type="submit" class="teacher-review-button">Mark reviewed</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="teacher-privacy-note">This view contains only the clinic information enabled for your account. Clinical notes and diagnoses are not displayed.</p>
                    </section>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>

</html>
