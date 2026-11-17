<?php
session_start();
if (!empty($_SESSION['must_change_password'])) {
    header('Location: ../change_initial_password.php');
    exit();
}

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    ($_SESSION['user_role'] ?? '') !== 'Student'
) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$username = $_SESSION['admin_username'] ?? 'Student';
$userId = (int)($_SESSION['user_id'] ?? 0);

$student = null;
$records = [];
$errorMessage = '';
$search = trim($_GET['search'] ?? '');

// Find the student profile linked to this login account.
if ($userId > 0) {
    $stmt = $conn->prepare(
        'SELECT id, student_id_no, fullname
         FROM students
         WHERE user_id = ?
         LIMIT 1'
    );

    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Fallback for older accounts whose student ID was saved at login.
if (!$student) {
    $studentIdNo = trim(
        (string)($_SESSION['student_id_no'] ?? '')
    );

    if ($studentIdNo !== '') {
        $stmt = $conn->prepare(
            'SELECT id, student_id_no, fullname
             FROM students
             WHERE student_id_no = ?
             LIMIT 1'
        );

        if ($stmt) {
            $stmt->bind_param('s', $studentIdNo);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

if ($student) {
    $username = $student['fullname'];
    $studentIdNo = $student['student_id_no'];

    // Retrieve only this student's health records.
    if ($search !== '') {
        $searchTerm = '%' . $search . '%';

        $stmt = $conn->prepare(
            'SELECT
                id,
                examination_date,
                height_cm,
                weight_kg,
                temperature,
                blood_pressure,
                findings,
                remarks,
                recorded_by
             FROM health_documentation
             WHERE student_id_no = ?
               AND (
                    CAST(examination_date AS CHAR) LIKE ?
                    OR CAST(findings AS CHAR) LIKE ?
                    OR CAST(remarks AS CHAR) LIKE ?
                    OR CAST(recorded_by AS CHAR) LIKE ?
               )
             ORDER BY examination_date DESC, id DESC'
        );

        if ($stmt) {
            $stmt->bind_param(
                'sssss',
                $studentIdNo,
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm
            );

            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }

            $stmt->close();
        } else {
            $errorMessage = 'Unable to retrieve health records.';
        }
    } else {
        $stmt = $conn->prepare(
            'SELECT
                id,
                examination_date,
                height_cm,
                weight_kg,
                temperature,
                blood_pressure,
                findings,
                remarks,
                recorded_by
             FROM health_documentation
             WHERE student_id_no = ?
             ORDER BY examination_date DESC, id DESC'
        );

        if ($stmt) {
            $stmt->bind_param('s', $studentIdNo);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }

            $stmt->close();
        } else {
            $errorMessage = 'Unable to retrieve health records.';
        }
    }
} else {
    $errorMessage =
        'Your student account is not linked to a student health profile. '
        . 'Please contact the clinic staff or administrator.';
}

$totalRecords = count($records);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Health Records | SHRCMS</title>

    <link rel="stylesheet" href="../../css/student-dashboard.css?v=20261114-icons">


    <link rel="stylesheet" href="../../css/student-health-records.css">
</head>

<body class="student-dashboard-body">

    <div class="student-layout">

        <aside class="student-sidebar">

            <div class="student-brand">
                <img src="../../image/logo2.png" alt="SHRCMS Logo">

                <div class="student-brand-text">
                    <strong>SHRCMS</strong>
                    <span>Student Health Record &amp; Clinic Management System</span>
                </div>
            </div>

            <nav class="student-navigation">
                <p class="student-nav-label">STUDENT MENU</p>

                <a href="student_dashboard.php" class="student-nav-link">
                    <span class="nav-icon"><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"></span>
                    <span>Dashboard</span>
                </a>

                <a href="student_health_records.php"
                    class="student-nav-link active">
                    <span class="nav-icon"><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span>
                    <span>My Health Records</span>
                </a>

                <a href="student_visit_history.php" class="student-nav-link">
                    <span class="nav-icon"><img class="ui-icon" src="../../image/icon-clock.svg" alt="" aria-hidden="true"></span>
                    <span>Clinic Visit History</span>
                </a>

                <a href="student_medical_certificate.php" class="student-nav-link">
                    <span class="nav-icon"><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"></span>
                    <span>Medical Certificate</span>
                </a>

                <a href="student_profile.php" class="student-nav-link">
                    <span class="nav-icon"><img class="ui-icon" src="../../image/icon-profile.svg" alt="" aria-hidden="true"></span>
                    <span>My Profile</span>
                </a>
            </nav>

            <div class="student-sidebar-bottom">
                <a href="../logout.php"
                    class="student-nav-link student-logout"
                    onclick="return confirm('Are you sure you want to log out?');">
                    <span class="nav-icon"><img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"></span>
                    <span>Log Out</span>
                </a>
            </div>

        </aside>

        <main class="student-main">

            <header class="student-header">
                <div class="student-header-title">
                    <h1>My Health Records</h1>
                    <p>View your recorded physical examinations and health information.</p>
                </div>

                <div class="student-profile">
                    <div class="student-avatar"><img class="ui-icon" src="../../image/icon-profile.svg" alt="" aria-hidden="true"></div>

                    <div class="student-profile-info">
                        <strong><?= e($username) ?></strong>
                        <span>Student</span>
                    </div>
                </div>
            </header>

            <section class="student-content">

                <?php if ($errorMessage !== ''): ?>
                    <div class="health-record-notice" role="alert">
                        <?= e($errorMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if ($student): ?>

                    <section class="student-welcome">
                        <div class="student-welcome-text">
                            <span class="student-eyebrow">STUDENT PORTAL</span>
                            <h2>Your Health Information</h2>
                            <p>
                                Review the examination records saved by your
                                campus clinic.
                            </p>

                            <div class="student-id-badge">
                                Student ID:
                                <strong><?= e($student['student_id_no']) ?></strong>
                            </div>
                        </div>

                        <div class="student-welcome-icon">
                            <span><img class="ui-icon" src="../../image/icon-heart.svg" alt="" aria-hidden="true"></span>
                        </div>
                    </section>

                    <div class="student-section-heading">
                        <div>
                            <h2>Health Records Overview</h2>
                            <p>Your recorded physical examinations.</p>
                        </div>
                    </div>

                    <div class="health-records-summary">
                        <span>Total health records</span>
                        <strong><?= number_format($totalRecords) ?></strong>
                    </div>

                    <div class="health-records-toolbar">
                        <form method="GET"
                            action="student_health_records.php"
                            class="health-records-search">

                            <input
                                type="search"
                                name="search"
                                placeholder="Search date, findings, remarks..."
                                value="<?= e($search) ?>"
                                aria-label="Search health records">

                            <button type="submit">Search</button>

                            <?php if ($search !== ''): ?>
                                <a href="student_health_records.php">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if ($totalRecords > 0): ?>

                        <?php foreach ($records as $record): ?>

                            <article class="health-record-card">

                                <div class="health-record-card-header">
                                    <h3>
                                        Physical Examination
                                        #<?= (int)$record['id'] ?>
                                    </h3>

                                    <span>
                                        <?= !empty($record['examination_date'])
                                            ? e(date(
                                                'F d, Y',
                                                strtotime($record['examination_date'])
                                            ))
                                            : 'Date not recorded' ?>
                                    </span>
                                </div>

                                <div class="health-measurements">

                                    <div class="health-measurement">
                                        <span>Height</span>
                                        <strong>
                                            <?= $record['height_cm'] !== null
                                                && $record['height_cm'] !== ''
                                                ? e($record['height_cm']) . ' cm'
                                                : 'Not recorded' ?>
                                        </strong>
                                    </div>

                                    <div class="health-measurement">
                                        <span>Weight</span>
                                        <strong>
                                            <?= $record['weight_kg'] !== null
                                                && $record['weight_kg'] !== ''
                                                ? e($record['weight_kg']) . ' kg'
                                                : 'Not recorded' ?>
                                        </strong>
                                    </div>

                                    <div class="health-measurement">
                                        <span>Temperature</span>
                                        <strong>
                                            <?= $record['temperature'] !== null
                                                && $record['temperature'] !== ''
                                                ? e($record['temperature']) . ' °C'
                                                : 'Not recorded' ?>
                                        </strong>
                                    </div>

                                    <div class="health-measurement">
                                        <span>Blood Pressure</span>
                                        <strong>
                                            <?= e($record['blood_pressure'] ?: 'Not recorded') ?>
                                        </strong>
                                    </div>

                                </div>

                                <div class="health-record-details">

                                    <div class="health-record-detail">
                                        <span>Examination Findings</span>
                                        <p><?= e($record['findings'] ?: 'No findings recorded.') ?></p>
                                    </div>

                                    <div class="health-record-detail">
                                        <span>Remarks</span>
                                        <p><?= e($record['remarks'] ?: 'No remarks recorded.') ?></p>
                                    </div>

                                    <div class="health-record-detail">
                                        <span>Recorded By</span>
                                        <p><?= e($record['recorded_by'] ?: 'Clinic Staff') ?></p>
                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="health-record-empty">
                            <h3>
                                <?= $search !== ''
                                    ? 'No matching health records'
                                    : 'No health records yet' ?>
                            </h3>

                            <p>
                                <?= $search !== ''
                                    ? 'Try another search term or clear your search.'
                                    : 'Your physical examination records will appear here after clinic staff record them.' ?>
                            </p>
                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </section>

        </main>

    </div>

</body>

</html>

<?php
$conn->close();
?>