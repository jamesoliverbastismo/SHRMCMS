<?php
session_start();

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
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['admin_username'] ?? 'Student';
$userId = (int)($_SESSION['user_id'] ?? 0);
$studentIdNo = trim((string)($_SESSION['student_id_no'] ?? ''));

$student = null;
$message = trim((string)($_GET['message'] ?? ''));
$messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';

// Pangitaa ang student profile base sa user_id o student_id_no
if ($userId > 0) {
    $stmt = $conn->prepare('SELECT id, student_id_no, fullname, age, gender, course_year, contact, email, created_at FROM students WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$student && $studentIdNo !== '') {
    $stmt = $conn->prepare('SELECT id, student_id_no, fullname, age, gender, course_year, contact, email, created_at FROM students WHERE student_id_no = ? LIMIT 1');
    $stmt->bind_param('s', $studentIdNo);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($student) {
    $username = $student['fullname'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | SHRCMS</title>
    <link rel="stylesheet" href="../../css/student-dashboard.css">
</head>

<body class="student-dashboard-body">
    <div class="student-layout">

        <!-- SIDEBAR -->
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
                    <span class="nav-icon">▦</span>
                    <span>Dashboard</span>
                </a>

                <a href="student_health_records.php" class="student-nav-link">
                    <span class="nav-icon">▤</span>
                    <span>My Health Records</span>
                </a>

                <a href="student_visit_history.php" class="student-nav-link">
                    <span class="nav-icon">◷</span>
                    <span>Clinic Visit History</span>
                </a>

                <a href="student_medical_certificate.php" class="student-nav-link">
                    <span class="nav-icon">📜</span>
                    <span>Medical Certificate</span>
                </a>

                <a href="student_profile.php" class="student-nav-link active">
                    <span class="nav-icon">♙</span>
                    <span>My Profile</span>
                </a>
            </nav>

            <div class="student-sidebar-bottom">
                <a href="../logout.php" class="student-nav-link student-logout" onclick="return confirm('Are you sure you want to log out?');">
                    <span class="nav-icon">↪</span>
                    <span>Log Out</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="student-main">
            <header class="student-header">
                <div class="student-header-title">
                    <h1>My Profile</h1>
                    <p>View your personal student information and account details.</p>
                </div>

                <div class="student-profile">
                    <div class="student-avatar">♙</div>
                    <div class="student-profile-info">
                        <strong><?= e($username) ?></strong>
                        <span>Student</span>
                    </div>
                </div>
            </header>

            <section class="student-content">

                <?php if ($message !== ''): ?>
                    <div style="padding: 12px; border-radius: 6px; margin-bottom: 20px; background: <?= $messageType === 'error' ? '#fee2e2' : '#dcfce7' ?>; color: <?= $messageType === 'error' ? '#dc2626' : '#166534' ?>;">
                        <?= e($message) ?>
                    </div>
                <?php endif; ?>

                <section class="student-panel">
                    <div class="student-panel-heading">
                        <div>
                            <h2>Student Profile Information</h2>
                            <p>Your official records stored in the clinic management system.</p>
                        </div>
                        <span class="student-details-symbol" style="font-size: 2rem;">♙</span>
                    </div>

                    <?php if ($student): ?>
                        <div class="student-details-grid" style="margin-top: 20px;">
                            <div class="student-detail">
                                <span>Student ID Number</span>
                                <strong><?= e($student['student_id_no']) ?></strong>
                            </div>

                            <div class="student-detail">
                                <span>Full Name</span>
                                <strong><?= e($student['fullname']) ?></strong>
                            </div>

                            <div class="student-detail">
                                <span>Email Address</span>
                                <strong><?= e($student['email'] ?: 'Not provided') ?></strong>
                            </div>

                            <div class="student-detail">
                                <span>Age</span>
                                <strong><?= $student['age'] !== null && $student['age'] !== '' ? e($student['age']) : 'Not provided' ?></strong>
                            </div>

                            <div class="student-detail">
                                <span>Gender</span>
                                <strong><?= e($student['gender'] ?: 'Not provided') ?></strong>
                            </div>

                            <div class="student-detail">
                                <span>Course / Year / Grade</span>
                                <strong><?= e($student['course_year'] ?: 'Not provided') ?></strong>
                            </div>

                            <div class="student-detail">
                                <span>Contact Number</span>
                                <strong><?= e($student['contact'] ?: 'Not provided') ?></strong>
                            </div>

                            <div class="student-detail">
                                <span>Record Registered</span>
                                <strong><?= !empty($student['created_at']) ? e(date('M d, Y', strtotime($student['created_at']))) : '—' ?></strong>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="student-empty" style="padding: 40px; text-align: center;">
                            <span class="student-empty-icon" style="font-size: 3rem;">♙</span>
                            <strong>Student profile not found</strong>
                            <p>Your user account is not yet linked to a student record. Please contact the clinic administrator.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <footer class="student-footer">
                    Student Health Record and Clinic Management System
                    <span>SHRCMS</span>
                </footer>
            </section>
        </main>

    </div>
</body>

</html>
<?php
$conn->close();
?>