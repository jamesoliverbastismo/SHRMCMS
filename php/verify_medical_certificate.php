<?php
require_once __DIR__ . '/db.php';
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$code = trim(is_string($_GET['code'] ?? null) ? $_GET['code'] : '');
$certificate = null;

if ($code !== '' && preg_match('/^MC-[A-Z0-9]{4,64}$/', $code)) {
    $stmt = $conn->prepare(
        "SELECT r.qr_code_hash, r.status, r.purpose, r.issued_date,
                r.approved_by,
                COALESCE(NULLIF(r.student_name, ''), s.fullname) AS student_name,
                r.student_id_no
         FROM med_cert_requests r
         LEFT JOIN students s ON s.id = r.student_id
         WHERE r.qr_code_hash = ?
           AND r.status IN ('Approved', 'Completed')
         LIMIT 1"
    );
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $certificate = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$conn->close();
$isValid = $certificate !== null;
if (!$isValid) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification | SHRCMS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/medical-certificate.css">
</head>
<body class="staff-dashboard-body">
<main class="certificate-page certificate-verification-page">
    <section class="certificate-verification-card <?= $isValid ? 'is-valid' : 'is-invalid' ?>">
        <img src="../image/logo2.png" alt="SHRCMS Logo">
        <p class="certificate-verification-eyebrow">SHRCMS CERTIFICATE VERIFICATION</p>
        <?php if ($isValid): ?>
            <h1>Certificate verified</h1>
            <p class="certificate-verification-status">This certificate is valid and approved by the school clinic.</p>
            <dl>
                <div><dt>Certificate number</dt><dd><?= e($certificate['qr_code_hash']) ?></dd></div>
                <div><dt>Student</dt><dd><?= e($certificate['student_name']) ?></dd></div>
                <div><dt>Student ID</dt><dd><?= e($certificate['student_id_no']) ?></dd></div>
                <div><dt>Purpose</dt><dd><?= e($certificate['purpose']) ?></dd></div>
                <div><dt>Date issued</dt><dd><?= e($certificate['issued_date'] ? date('F d, Y', strtotime($certificate['issued_date'])) : 'Not recorded') ?></dd></div>
                <div><dt>Approved by</dt><dd><?= e($certificate['approved_by'] ?: 'Clinic Staff') ?></dd></div>
            </dl>
        <?php else: ?>
            <h1>Certificate not verified</h1>
            <p class="certificate-verification-status">No approved certificate matches the scanned code. Check the code or contact the school clinic.</p>
            <?php if ($code !== ''): ?><p><strong>Scanned code:</strong> <?= e($code) ?></p><?php endif; ?>
        <?php endif; ?>
        <a class="medical-button medical-button-light" href="Staff_Dashboard/scan_medical_certificate.php">Staff verification scanner</a>
    </section>
</main>
</body>
</html>
