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
$search = trim((string)($_GET['search'] ?? ''));

$student = null;
$visits = [];

// Pangitaa ang student profile
if ($userId > 0) {
    $stmt = $conn->prepare('SELECT id, student_id_no, fullname FROM students WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$student && $studentIdNo !== '') {
    $stmt = $conn->prepare('SELECT id, student_id_no, fullname FROM students WHERE student_id_no = ? LIMIT 1');
    $stmt->bind_param('s', $studentIdNo);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($student) {
    $studentDatabaseId = (int)$student['id'];
    $username = $student['fullname'];

    $sql = 'SELECT consultation_date, reason, remarks, staff_name 
            FROM consultations 
            WHERE student_id = ?';

    if ($search !== '') {
        $sql .= ' AND (reason LIKE ? OR remarks LIKE ? OR staff_name LIKE ?)';
        $term = '%' . $search . '%';
        $stmt = $conn->prepare($sql . ' ORDER BY consultation_date DESC');
        $stmt->bind_param('isss', $studentDatabaseId, $term, $term, $term);
    } else {
        $stmt = $conn->prepare($sql . ' ORDER BY consultation_date DESC');
        $stmt->bind_param('i', $studentDatabaseId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $visits[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Visit History | SHRCMS</title>
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

                <a href="student_visit_history.php" class="student-nav-link active">
                    <span class="nav-icon">◷</span>
                    <span>Clinic Visit History</span>
                </a>

                <a href="student_medical_certificate.php" class="student-nav-link">
                    <span class="nav-icon">📜</span>
                    <span>Medical Certificate</span>
                </a>

                <a href="student_profile.php" class="student-nav-link">
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
                    <h1>Clinic Visit History</h1>
                    <p>View all your recorded clinic visits and consultations.</p>
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
                <section class="student-panel">
                    <div class="student-panel-heading">
                        <div>
                            <h2>Visit Logs</h2>
                            <p>Search and review your past clinic consultations.</p>
                        </div>

                        <form class="sr-search" method="GET" action="student_visit_history.php" style="display:flex; gap:10px;">
                            <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search reason, staff..." style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px;">
                            <button type="submit" style="padding:6px 12px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer;">Search</button>
                            <?php if ($search !== ''): ?>
                                <a href="student_visit_history.php" style="padding:6px 12px; background:#e2e8f0; color:#1e293b; text-decoration:none; border-radius:6px; display:flex; align-items:center;">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="student-table-container">
                        <table class="student-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reason for Visit</th>
                                    <th>Clinic Staff</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$student): ?>
                                    <tr>
                                        <td colspan="4" class="student-empty">Your student record is not linked to this account.</td>
                                    </tr>
                                <?php elseif (count($visits) > 0): ?>
                                    <?php foreach ($visits as $v): ?>
                                        <tr>
                                            <td><?= !empty($v['consultation_date']) ? e(date('M d, Y', strtotime($v['consultation_date']))) : '—' ?></td>
                                            <td><strong><?= e($v['reason'] ?: 'Not specified') ?></strong></td>
                                            <td><?= e($v['staff_name'] ?: 'Clinic Staff') ?></td>
                                            <td><?= e($v['remarks'] ?: 'No remarks recorded') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="student-empty">
                                            <span class="student-empty-icon">◷</span>
                                            <strong>No visit history found</strong>
                                            <p><?= $search !== '' ? 'Try a different search term.' : 'You have no recorded clinic visits yet.' ?></p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="student-table-footer">
                        Showing <?= number_format(count($visits)) ?> visit(s)
                    </div>
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