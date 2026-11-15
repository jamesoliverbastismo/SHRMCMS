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
$requests = [];
$message = '';
$messageType = 'success';

// Pangitaa ang student profile
if ($userId > 0) {
    $stmt = $conn->prepare('SELECT id, student_id_no, fullname, course_year, age, gender FROM students WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$student && $studentIdNo !== '') {
    $stmt = $conn->prepare('SELECT id, student_id_no, fullname, course_year, age, gender FROM students WHERE student_id_no = ? LIMIT 1');
    $stmt->bind_param('s', $studentIdNo);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle Form Submission (Requesting Med Cert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request') {
    $purpose = trim($_POST['purpose'] ?? '');

    if ($purpose === '') {
        $message = 'Please provide a purpose for your request.';
        $messageType = 'error';
    } elseif ($student) {
        $stmt = $conn->prepare('INSERT INTO med_cert_requests (student_id, student_id_no, purpose) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $student['id'], $student['student_id_no'], $purpose);
        $stmt->execute();
        $stmt->close();

        $message = 'Medical certificate request submitted successfully. Please wait for clinic approval.';
    }
}

// Load Student Requests
if ($student) {
    $username = $student['fullname'];
    $stmt = $conn->prepare('SELECT * FROM med_cert_requests WHERE student_id_no = ? ORDER BY id DESC');
    $stmt->bind_param('s', $student['student_id_no']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Certificate Request | SHRCMS</title>
    <link rel="stylesheet" href="../../css/student-dashboard.css?v=20261114-icons">
    <style>
        @media print {
            body * { visibility: hidden; }
            .printable-cert, .printable-cert * { visibility: visible; }
            .printable-cert { position: absolute; left: 0; top: 0; width: 100%; padding: 30px; background: white; }
            .no-print { display: none !important; }
        }
        .status-badge { padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .status-Pending { background: #fef08a; color: #854d0e; }
        .status-Approved { background: #bfdbfe; color: #1e40af; }
        .status-Completed { background: #bbf7d0; color: #166534; }
        .status-Rejected { background: #fecaca; color: #991b1b; }
    </style>
</head>
<body class="student-dashboard-body">
<div class="student-layout">

    <aside class="student-sidebar no-print">
        <div class="student-brand">
            <img src="../../image/logo2.png" alt="SHRCMS Logo">
            <div class="student-brand-text">
                <strong>SHRCMS</strong>
                <span>Student Health Record</span>
            </div>
        </div>
        <nav class="student-navigation">
            <p class="student-nav-label">STUDENT MENU</p>
            <a href="student_dashboard.php" class="student-nav-link"><span class="nav-icon"><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"></span><span>Dashboard</span></a>
            <a href="student_health_records.php" class="student-nav-link"><span class="nav-icon"><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span><span>My Health Records</span></a>
            <a href="student_visit_history.php" class="student-nav-link"><span class="nav-icon"><img class="ui-icon" src="../../image/icon-clock.svg" alt="" aria-hidden="true"></span><span>Clinic Visit History</span></a>
            <a href="student_medical_certificate.php" class="student-nav-link active"><span class="nav-icon"><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"></span><span>Medical Certificate</span></a>
            <a href="student_profile.php" class="student-nav-link"><span class="nav-icon"><img class="ui-icon" src="../../image/icon-profile.svg" alt="" aria-hidden="true"></span><span>My Profile</span></a>
        </nav>
        <div class="student-sidebar-bottom">
            <a href="../logout.php" class="student-nav-link student-logout" onclick="return confirm('Log out?');"><span class="nav-icon"><img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"></span><span>Log Out</span></a>
        </div>
    </aside>

    <main class="student-main">
        <header class="student-header no-print">
            <div class="student-header-title">
                <h1>Medical Certificate</h1>
                <p>Request and print your verified medical certificates.</p>
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
            <?php if ($message !== ''): ?>
                <div class="no-print" style="padding: 12px; margin-bottom: 20px; border-radius: 6px; background: <?= $messageType === 'error' ? '#fee2e2' : '#dcfce7' ?>; color: <?= $messageType === 'error' ? '#dc2626' : '#166534' ?>;">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <!-- REQUEST FORM -->
            <section class="student-panel no-print" style="margin-bottom: 25px;">
                <div class="student-panel-heading">
                    <div>
                        <h2>Request Medical Certificate</h2>
                        <p>Fill out the form to request a medical certificate from the clinic.</p>
                    </div>
                </div>
                <form method="POST" action="student_medical_certificate.php" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="request">
                    <div style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom: 5px; font-weight: 600;">Purpose of Request *</label>
                        <textarea name="purpose" rows="3" required placeholder="e.g. Excused absence due to illness, Sports clearance, Internship requirements..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;"></textarea>
                    </div>
                    <button type="submit" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Submit Request</button>
                </form>
            </section>

            <!-- REQUEST HISTORY TABLE -->
            <section class="student-panel no-print" style="margin-bottom: 25px;">
                <div class="student-panel-heading">
                    <h2>My Requests</h2>
                </div>
                <table class="student-table" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Requested Date</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Action / Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($requests) > 0): ?>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
                                    <td><?= e($r['purpose']) ?></td>
                                    <td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                                    <td>
                                        <?php if ($r['status'] === 'Approved'): ?>
                                            <span style="color:#1e40af;">Please visit clinic for vital signs.</span>
                                        <?php elseif ($r['status'] === 'Completed'): ?>
                                            <a href="#cert-<?= $r['id'] ?>" style="color: #2563eb; font-weight: bold; text-decoration: underline;">View & Print Cert</a>
                                        <?php elseif ($r['status'] === 'Pending'): ?>
                                            <span>Waiting for clinic staff approval.</span>
                                        <?php else: ?>
                                            <span style="color:#dc2626;">Request Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="student-empty">No requests submitted yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <!-- COMPLETED CERTIFICATES FOR PRINTING -->
            <?php foreach ($requests as $r): ?>
                <?php if ($r['status'] === 'Completed'): ?>
                    <section class="student-panel printable-cert" id="cert-<?= $r['id'] ?>" style="background:#fff; padding:40px; border-radius:8px; margin-bottom:30px; border:2px solid #cbd5e1;">
                        <div style="text-align:center; border-bottom:2px solid #2563eb; padding-bottom:15px; margin-bottom:20px;">
                            <h2 style="margin:0; font-size: 22px;">STUDENT HEALTH RECORD & CLINIC MANAGEMENT SYSTEM</h2>
                            <p style="margin:5px 0 0 0; color:#475569;">OFFICIAL MEDICAL CERTIFICATE</p>
                        </div>
                        
                        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                            <p><strong>Date Issued:</strong> <?= e(date('F d, Y', strtotime($r['issued_date']))) ?></p>
                            <p><strong>Cert Code:</strong> <?= e($r['qr_code_hash']) ?></p>
                        </div>

                        <p style="margin-bottom:15px;">To Whom It May Concern,</p>
                        
                        <p style="margin-bottom:20px; line-height:1.8;">
                            This is to certify that <strong><?= e($student['fullname']) ?></strong>, 
                            (Student ID: <strong><?= e($student['student_id_no']) ?></strong>, 
                            Course/Year: <strong><?= e($student['course_year'] ?: 'N/A') ?></strong>, 
                            Age: <strong><?= e($student['age'] ?: 'N/A') ?></strong>, 
                            Gender: <strong><?= e($student['gender'] ?: 'N/A') ?></strong>) 
                            has been examined and attended to at the School Clinic.
                        </p>

                        <div style="margin-bottom:15px; background: #f8fafc; padding: 15px; border-radius: 6px;">
                            <strong>Diagnosis / Medical Findings:</strong>
                            <p style="margin:5px 0 0 0;"><?= nl2br(e($r['diagnosis'])) ?></p>
                        </div>

                        <div style="margin-bottom:25px; background: #f8fafc; padding: 15px; border-radius: 6px;">
                            <strong>Recommendations / Remarks:</strong>
                            <p style="margin:5px 0 0 0;"><?= nl2br(e($r['recommendation'])) ?></p>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:40px;">
                            <!-- QR CODE -->
                            <div style="text-align:center;">
                                <?php 
                                    $qrData = "VERIFIED MED CERT\nCode: " . $r['qr_code_hash'] . "\nStudent: " . $student['fullname'] . "\nIssued: " . $r['issued_date'];
                                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=" . urlencode($qrData);
                                ?>
                                <img src="<?= $qrUrl ?>" alt="QR Code Verification" style="border:1px solid #ccc; padding:3px; background:#fff;">
                                <br><small style="font-size:10px; color:#64748b;">Scan to Verify</small>
                            </div>

                            <!-- SIGNATURE -->
                            <div style="text-align:center;">
                                <div style="border-bottom:1px solid #000; width:220px; margin-bottom:5px;"></div>
                                <strong><?= e($r['approved_by'] ?: 'Clinic Staff') ?></strong><br>
                                <small>Attending Clinic Personnel</small>
                            </div>
                        </div>

                        <div class="no-print" style="margin-top:25px; text-align:right;">
                            <button onclick="window.print()" style="padding:8px 18px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;"><img class="ui-icon" src="../../image/icon-print.svg" alt="" aria-hidden="true"> Print Certificate</button>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>

        </section>
    </main>
</div>
</body>
</html>
<?php $conn->close(); ?>