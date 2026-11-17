<?php
session_start();

if (!empty($_SESSION['must_change_password'])) {
    header('Location: ../change_initial_password.php');
    exit();
}

if (
    ($_SESSION['admin_logged_in'] ?? false) !== true ||
    !in_array($_SESSION['user_role'] ?? '', ['Staff', 'Administrator'], true)
) {
    header('Location: ../login.php');
    exit();
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Medical Certificate | SHRCMS</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/staff-dashboard.css">
    <link rel="stylesheet" href="../../css/medical-certificate.css">
    <script src="../../js/medical-certificate-scanner.js" defer></script>
</head>
<body class="staff-dashboard-body">
<main class="certificate-page certificate-scanner-page">
    <header class="certificate-scanner-heading">
        <div>
            <p class="staff-eyebrow">CLINIC MANAGEMENT</p>
            <h1>Verify Medical Certificate</h1>
            <p>Scan the QR code printed on the certificate, or enter its certificate number.</p>
        </div>
        <a class="medical-button medical-button-light" href="health_documentation.php">Back to requests</a>
    </header>

    <section class="certificate-scanner-card">
        <div class="certificate-scanner-video-wrap">
            <video id="certificate-camera" playsinline muted hidden></video>
            <div id="certificate-camera-placeholder">Camera preview will appear here.</div>
        </div>
        <p id="certificate-scanner-status" role="status">Use the camera on a secure or localhost connection, or enter the code manually.</p>
        <button class="medical-button medical-button-primary" id="start-certificate-scan" type="button">Start camera scanner</button>

        <form action="../verify_medical_certificate.php" method="GET" class="certificate-code-form">
            <label for="certificate-code">Certificate number</label>
            <div>
                <input type="text" name="code" id="certificate-code" maxlength="67" pattern="MC-[A-Za-z0-9]{4,64}" required placeholder="MC-...">
                <button class="medical-button medical-button-primary" type="submit">Verify code</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
