<?php
session_start();

if (!empty($_SESSION['must_change_password'])) {
    header('Location: change_initial_password.php');
    exit();
}

$role = $_SESSION['user_role'] ?? '';
if (
    ($_SESSION['admin_logged_in'] ?? false) !== true ||
    !in_array($role, ['Student', 'Staff', 'Administrator'], true)
) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/db.php';
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$requestId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    http_response_code(400);
    exit('A valid certificate request is required.');
}

$stmt = $conn->prepare(
    "SELECT r.*,
            COALESCE(NULLIF(r.student_name, ''), s.fullname) AS certificate_student_name,
            COALESCE(NULLIF(r.course_year, ''), s.course_year) AS certificate_course_year,
            COALESCE(r.age, s.age) AS certificate_age,
            COALESCE(NULLIF(r.gender, ''), s.gender) AS certificate_gender
     FROM med_cert_requests r
     LEFT JOIN students s ON s.id = r.student_id
     WHERE r.id = ?
       AND r.status IN ('Approved', 'Completed')
       AND r.qr_code_hash IS NOT NULL
       AND r.qr_code_hash <> ''
     LIMIT 1"
);
$stmt->bind_param('i', $requestId);
$stmt->execute();
$certificate = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$certificate) {
    http_response_code(404);
    exit('The certificate is not available or has not been approved.');
}

if ($role === 'Student') {
    $sessionStudentIdNo = trim((string)($_SESSION['student_id_no'] ?? ''));
    if (
        $sessionStudentIdNo === '' ||
        strcasecmp($sessionStudentIdNo, (string)$certificate['student_id_no']) !== 0
    ) {
        http_response_code(403);
        exit('You are not authorized to view this certificate.');
    }
}

$scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/php/print_medical_certificate_request.php'));
$applicationPath = rtrim(dirname(dirname($scriptPath)), '/');
$verifyUrl = $applicationPath . '/php/verify_medical_certificate.php?code=' .
    rawurlencode((string)$certificate['qr_code_hash']);
$issuedDate = $certificate['issued_date']
    ? date('F d, Y', strtotime($certificate['issued_date']))
    : 'Not recorded';
$examinationDate = $certificate['examination_date']
    ? date('F d, Y', strtotime($certificate['examination_date']))
    : $issuedDate;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Certificate <?= e($certificate['qr_code_hash']) ?> | SHRCMS</title>
    <link rel="stylesheet" href="../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../css/medical-certificate.css">
    <script src="../js/qrcode-generator.js" defer></script>
    <script src="../js/medical-certificate-qr.js" defer></script>
</head>
<body class="staff-dashboard-body">
<main class="certificate-page">
    <div class="certificate-controls no-print">
        <a class="medical-button medical-button-light" href="<?= $role === 'Student' ? 'Student_Dashboard/student_medical_certificate.php' : 'Staff_Dashboard/health_documentation.php' ?>">Back</a>
        <button class="medical-button medical-button-primary" type="button" onclick="window.print()">Print certificate</button>
    </div>

    <article class="certificate-sheet">
        <header class="certificate-heading">
            <img src="../image/logo2.png" alt="SHRCMS Logo">
            <div>
                <p>STUDENT HEALTH RECORD &amp; CLINIC MANAGEMENT SYSTEM</p>
                <h1>Medical Certificate</h1>
            </div>
        </header>

        <div class="certificate-meta">
            <p><strong>Certificate No.:</strong> <?= e($certificate['qr_code_hash']) ?></p>
            <p><strong>Date issued:</strong> <?= e($issuedDate) ?></p>
        </div>

        <p class="certificate-to-whom">To Whom It May Concern:</p>
        <p class="certificate-statement">
            This is to certify that <strong><?= e($certificate['certificate_student_name']) ?></strong>
            (Student ID: <strong><?= e($certificate['student_id_no']) ?></strong>,
            Course / Year: <strong><?= e($certificate['certificate_course_year'] ?: 'Not recorded') ?></strong>,
            Age: <strong><?= e($certificate['certificate_age'] ?: 'Not recorded') ?></strong>,
            Gender: <strong><?= e($certificate['certificate_gender'] ?: 'Not recorded') ?></strong>)
            visited the school clinic for the stated purpose and completed a physical examination on
            <strong><?= e($examinationDate) ?></strong>.
        </p>

        <section class="certificate-section">
            <h2>Purpose</h2>
            <p><?= nl2br(e($certificate['purpose'])) ?></p>
        </section>

        <section class="certificate-section">
            <h2>Physical examination</h2>
            <p>
                Height: <?= $certificate['height_cm'] !== null ? e($certificate['height_cm']) . ' cm' : 'Not recorded' ?>;
                Weight: <?= $certificate['weight_kg'] !== null ? e($certificate['weight_kg']) . ' kg' : 'Not recorded' ?>;
                Temperature: <?= $certificate['temperature'] !== null ? e($certificate['temperature']) . ' &deg;C' : 'Not recorded' ?>;
                Blood pressure: <?= e($certificate['blood_pressure'] ?: 'Not recorded') ?>
            </p>
            <?php if (trim((string)$certificate['physical_findings']) !== ''): ?>
                <p><?= nl2br(e($certificate['physical_findings'])) ?></p>
            <?php endif; ?>
        </section>

        <?php if (trim((string)$certificate['diagnosis']) !== ''): ?>
            <section class="certificate-section">
                <h2>Staff assessment</h2>
                <p><?= nl2br(e($certificate['diagnosis'])) ?></p>
            </section>
        <?php endif; ?>

        <?php if (trim((string)$certificate['recommendation']) !== ''): ?>
            <section class="certificate-section">
                <h2>Recommendations / remarks</h2>
                <p><?= nl2br(e($certificate['recommendation'])) ?></p>
            </section>
        <?php endif; ?>

        <p class="certificate-note">
            This certificate reflects the physical examination and findings recorded by the issuing clinic staff.
            Interview answers are confidential and are not printed on this certificate.
        </p>

        <footer class="certificate-approval-footer">
            <div class="certificate-qr-block">
                <div id="medical-certificate-qr" data-qr-url="<?= e($verifyUrl) ?>" aria-label="Certificate verification QR code">
                    QR code could not be generated. Use the certificate number to verify.
                </div>
                <small>Scan to verify certificate status</small>
            </div>
            <div class="certificate-signature">
                <div>
                    <span class="signature-line"></span>
                    <strong><?= e($certificate['approved_by'] ?: 'Clinic Staff') ?></strong>
                    <small>Approving clinic staff</small>
                </div>
            </div>
        </footer>
    </article>
</main>
</body>
</html>
