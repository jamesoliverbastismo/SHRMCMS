
<?php
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
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

$username = $_SESSION['admin_username'] ?? 'Administrator';

// Count records in the existing database tables.
function getCount(mysqli $conn, string $table): int
{
    // Table names are fixed internally, not supplied by users.
    $allowed = ['students', 'admin', 'staff', 'consultations'];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");

    if (!$result) {
        return 0;
    }

    return (int)($result->fetch_assoc()['total'] ?? 0);
}

$studentCount = getCount($conn, 'students');
$adminCount = getCount($conn, 'admin');
$staffCount = getCount($conn, 'staff');
$consultationCount = getCount($conn, 'consultations');

// Choose the report type.
$reportType = $_GET['report'] ?? 'overview';

$allowedReports = ['overview', 'students', 'consultations'];

if (!in_array($reportType, $allowedReports, true)) {
    $reportType = 'overview';
}

$students = [];
$consultations = [];
$reportError = '';
$auditRows = [];

rbacEnsureAuditTable($conn);
rbacLog($conn, 'view', 'System Reports', 'Report', $reportType, 'Administrator accessed a system report');
$auditResult = @$conn->query(
    'SELECT id, actor_name, actor_role, actor_user_id, action, module,
            record_type, record_id, details, ip_address, created_at
     FROM audit_trail
     ORDER BY created_at DESC, id DESC
     LIMIT 200'
);
if ($auditResult) {
    while ($auditRow = $auditResult->fetch_assoc()) {
        $auditRows[] = $auditRow;
    }
}

// Student report.
if ($reportType === 'students') {
    $result = $conn->query(
        'SELECT student_id_no, fullname, age, gender,
                course_year, contact, created_at
         FROM students
         ORDER BY fullname ASC'
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    } else {
        $reportError = 'Unable to load student records.';
    }
}

// Consultation report.
// This uses the consultations schema defined for this project.
if ($reportType === 'consultations') {
    $result = $conn->query(
        'SELECT c.consultation_date, c.reason, c.remarks,
                c.staff_name, s.student_id_no, s.fullname
         FROM consultations c
         LEFT JOIN students s ON c.student_id = s.id
         ORDER BY c.consultation_date DESC'
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $consultations[] = $row;
        }
    } else {
        $reportError = 'Unable to load consultation records. Check that the consultations table and its columns exist.';
    }
}

$reportTitles = [
    'overview' => 'System Overview Report',
    'students' => 'Student Records Report',
    'consultations' => 'Clinic Consultation Report'
];

$reportTitle = $reportTitles[$reportType];
$generatedAt = date('F d, Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports | SHRCMS</title>
    <link rel="stylesheet" href="../../css/Shared/style.css?v=20261114-icons">


    <link rel="stylesheet" href="../../css/Admin/system-reports.css">
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
        <a href="admin_dashboard.php"><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"> Dashboard</a>
        <a href="user_management.php"><img class="ui-icon" src="../../image/icon-users.svg" alt="" aria-hidden="true"> User Accounts</a>
        <a href="staff_management.php"><img class="ui-icon" src="../../image/icon-staff.svg" alt="" aria-hidden="true"> Staff Management</a>
        <a href="system_reports.php" class="active" aria-current="page"><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"> Clinic Reports</a>
        <a href="system_settings.php"><img class="ui-icon" src="../../image/icon-settings.svg" alt="" aria-hidden="true"> Security &amp; Settings</a>
        <a href="../logout.php" class="logout-link"
           onclick="return confirm('Are you sure you want to log out?');">
            <img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"> Logout
        </a>
    </nav>
</aside>

<main class="admin-main-content">
    <header class="admin-header-bar">
        <h2>System Reports</h2>

        <div class="admin-profile-info">
            <img src="../../image/logo2.png" alt="Administrator">
            <div class="admin-profile-text">
                <h4><?= e($username) ?></h4>
                <p>Administrator</p>
            </div>
        </div>
    </header>

    <div class="admin-dashboard-container">
        <div class="admin-section-title">Reports Overview</div>
        <p class="report-intro">
            View system statistics and generate reports from the records
            stored in the Student Health Record and Clinic Management System.
        </p>

        <section class="admin-stats-grid">
            <div class="admin-stat-card">
                <div class="admin-stat-header">
                    <span>Total Admin Accounts</span>
                    <span><img class="ui-icon" src="../../image/icon-users.svg" alt="" aria-hidden="true"></span>
                </div>
                <div class="admin-stat-number">
                    <?= number_format($adminCount) ?>
                </div>
            </div>

            <div class="admin-stat-card students">
                <div class="admin-stat-header">
                    <span>Registered Students</span>
                    <span><img class="ui-icon" src="../../image/icon-student.svg" alt="" aria-hidden="true"></span>
                </div>
                <div class="admin-stat-number">
                    <?= number_format($studentCount) ?>
                </div>
            </div>

            <div class="admin-stat-card staff">
                <div class="admin-stat-header">
                    <span>Clinic Staff</span>
                    <span><img class="ui-icon" src="../../image/icon-clinic.svg" alt="" aria-hidden="true"></span>
                </div>
                <div class="admin-stat-number">
                    <?= number_format($staffCount) ?>
                </div>
            </div>

            <div class="admin-stat-card consultations">
                <div class="admin-stat-header">
                    <span>Total Consultations</span>
                    <span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span>
                </div>
                <div class="admin-stat-number">
                    <?= number_format($consultationCount) ?>
                </div>
            </div>
        </section>

        <section class="report-panel no-print">
            <div class="report-panel-heading">
                <div>
                    <h3>Generate a Report</h3>
                    <p>Select a report type to view the corresponding records.</p>
                </div>
            </div>

            <form method="GET" action="system_reports.php"
                  class="report-toolbar">
                <select class="report-select" name="report" aria-label="Report type">
                    <option value="overview"
                        <?= $reportType === 'overview' ? 'selected' : '' ?>>
                        System Overview
                    </option>
                    <option value="students"
                        <?= $reportType === 'students' ? 'selected' : '' ?>>
                        Student Records
                    </option>
                    <option value="consultations"
                        <?= $reportType === 'consultations' ? 'selected' : '' ?>>
                        Clinic Consultations
                    </option>
                </select>

                <button type="submit" class="report-button">
                    View Report
                </button>

                <button type="button" class="report-button secondary"
                        onclick="window.print()">
                    <img class="ui-icon" src="../../image/icon-print.svg" alt="" aria-hidden="true"> Print / Save as PDF
                </button>
            </form>
        </section>

        <?php if ($reportError !== ''): ?>
            <div class="report-notice"><?= e($reportError) ?></div>
        <?php endif; ?>

        <section class="report-panel">
            <div class="report-panel-heading">
                <div>
                    <h3><?= e($reportTitle) ?></h3>
                    <p>Generated on <?= e($generatedAt) ?></p>
                </div>
                <span>
                    <?php if ($reportType === 'students'): ?>
                        <?= number_format(count($students)) ?> record(s)
                    <?php elseif ($reportType === 'consultations'): ?>
                        <?= number_format(count($consultations)) ?> record(s)
                    <?php else: ?>
                        Current system totals
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($reportType === 'overview'): ?>
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Report Category</th>
                                <th>Total Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Administrator Accounts</td>
                                <td><?= number_format($adminCount) ?></td>
                            </tr>
                            <tr>
                                <td>Registered Students</td>
                                <td><?= number_format($studentCount) ?></td>
                            </tr>
                            <tr>
                                <td>Clinic Staff</td>
                                <td><?= number_format($staffCount) ?></td>
                            </tr>
                            <tr>
                                <td>Clinic Consultations</td>
                                <td><?= number_format($consultationCount) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($reportType === 'students'): ?>
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Course / Year</th>
                                <th>Contact</th>
                                <th>Date Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($students): ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= e($student['student_id_no']) ?></td>
                                    <td><?= e($student['fullname']) ?></td>
                                    <td><?= e($student['age'] ?? '—') ?></td>
                                    <td><?= e($student['gender'] ?: '—') ?></td>
                                    <td><?= e($student['course_year'] ?: '—') ?></td>
                                    <td><?= e($student['contact'] ?: '—') ?></td>
                                    <td>
                                        <?= !empty($student['created_at'])
                                            ? e(date('M d, Y', strtotime($student['created_at'])))
                                            : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="report-empty">
                                    No student records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($reportType === 'consultations'): ?>
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Reason</th>
                                <th>Remarks</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($consultations): ?>
                            <?php foreach ($consultations as $consultation): ?>
                                <tr>
                                    <td><?= e($consultation['consultation_date']) ?></td>
                                    <td><?= e($consultation['student_id_no'] ?? '—') ?></td>
                                    <td><?= e($consultation['fullname'] ?? '—') ?></td>
                                    <td><?= e($consultation['reason']) ?></td>
                                    <td><?= e($consultation['remarks']) ?></td>
                                    <td><?= e($consultation['staff_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="report-empty">
                                    No consultation records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="report-panel" id="audit-trail">
            <div class="report-panel-heading">
                <div>
                    <h3>Audit Trail</h3>
                    <p>Recent account access and record changes. The trail is append-only.</p>
                </div>
                <span><?= number_format(count($auditRows)) ?> event(s)</span>
            </div>
            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>Actor</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Record</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($auditRows): ?>
                        <?php foreach ($auditRows as $audit): ?>
                            <tr>
                                <td><?= e(date('M d, Y h:i A', strtotime($audit['created_at']))) ?></td>
                                <td><?= e($audit['actor_name']) ?></td>
                                <td><?= e($audit['actor_role']) ?></td>
                                <td><?= e($audit['action']) ?></td>
                                <td><?= e($audit['module']) ?></td>
                                <td><?= e(($audit['record_type'] ?: '—') . ($audit['record_id'] ? ' #' . $audit['record_id'] : '')) ?></td>
                                <td><?= e($audit['details'] ?: '—') ?></td>
                                <td><?= e($audit['ip_address'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="report-empty">No audit events have been recorded.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

</main>

</body>
</html>

<?php
$conn->close();
?>
