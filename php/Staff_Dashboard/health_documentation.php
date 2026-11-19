<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!empty($_SESSION['must_change_password'])) {
    header('Location: ../change_initial_password.php');
    exit();
}

require_once __DIR__ . '/../db.php';

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !isset($_SESSION['user_role']) ||
    !in_array(
        $_SESSION['user_role'],
        ['Staff', 'Administrator'],
        true
    )
) {
    header('Location: ../login.php');
    exit();
}

$username = $_SESSION['admin_username'] ?? 'Clinic Staff';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error. Check your db.php configuration.');
}

$conn->set_charset('utf8mb4');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function goBack(string $message, string $type = 'success'): void
{
    header(
        'Location: health_documentation.php?message=' .
            urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

function postedString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

$message = trim($_GET['message'] ?? '');
$messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';

/*
|--------------------------------------------------------------------------
| POST ACTIONS: SAVE/UPDATE/DELETE EXAM & MED CERT APPROVALS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        goBack('Invalid form submission. Refresh and try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Complete the physical exam and approve the certificate
    if ($action === 'approve_request') {
        $reqId = filter_var($_POST['req_id'] ?? null, FILTER_VALIDATE_INT);
        $heightInput = postedString('height_cm');
        $weightInput = postedString('weight_kg');
        $temperatureInput = postedString('temperature');
        $bloodPressure = postedString('blood_pressure');
        $physicalFindings = postedString('physical_findings');
        $diagnosis = postedString('diagnosis');
        $recommendation = postedString('recommendation');

        if ($reqId === false || !$reqId || $reqId < 1) {
            goBack('Invalid request ID.', 'error');
        }

        $measurements = [
            'Height' => [$heightInput, 30, 250],
            'Weight' => [$weightInput, 1, 500],
            'Temperature' => [$temperatureInput, 25, 45]
        ];
        foreach ($measurements as $label => [$value, $minimum, $maximum]) {
            if (
                $value !== '' &&
                (!is_numeric($value) || (float)$value < $minimum || (float)$value > $maximum)
            ) {
                goBack($label . ' is outside the accepted input range.', 'error');
            }
        }

        if ($bloodPressure !== '' && mb_strlen($bloodPressure) > 20) {
            goBack('Blood pressure must not exceed 20 characters.', 'error');
        }
        if ($physicalFindings === '' || mb_strlen($physicalFindings) > 5000) {
            goBack('Enter physical examination findings (up to 5,000 characters).', 'error');
        }
        if (mb_strlen($diagnosis) > 3000 || mb_strlen($recommendation) > 3000) {
            goBack('Staff findings and recommendations must be 3,000 characters or fewer.', 'error');
        }

        $height = $heightInput === '' ? null : $heightInput;
        $weight = $weightInput === '' ? null : $weightInput;
        $temperature = $temperatureInput === '' ? null : $temperatureInput;
        $approvedBy = mb_substr(
            trim((string)($_SESSION['admin_username'] ?? 'Clinic Staff')),
            0,
            100
        );
        $issuedDate = date('Y-m-d');
        $certificateCode = 'MC-' . strtoupper(bin2hex(random_bytes(16)));
        $examDate = $issuedDate;

        $stmt = $conn->prepare(
            "UPDATE med_cert_requests
             SET status = 'Approved', height_cm = ?, weight_kg = ?, temperature = ?,
                 blood_pressure = ?, physical_findings = ?, diagnosis = ?,
                 recommendation = ?, qr_code_hash = ?, approved_by = ?,
                 issued_date = ?, examination_date = ?
             WHERE id = ?
               AND (status = 'Pending'
                    OR (status = 'Approved' AND (qr_code_hash IS NULL OR qr_code_hash = '')))"
        );
        $stmt->bind_param(
            str_repeat('s', 11) . 'i',
            $height,
            $weight,
            $temperature,
            $bloodPressure,
            $physicalFindings,
            $diagnosis,
            $recommendation,
            $certificateCode,
            $approvedBy,
            $issuedDate,
            $examDate,
            $reqId
        );
        $stmt->execute();
        $approved = $stmt->affected_rows;
        $stmt->close();

        goBack(
            $approved
                ? 'Physical examination recorded and medical certificate approved.'
                : 'This request was already processed or could not be found.',
            $approved ? 'success' : 'error'
        );
    }

    // Action 2: Reject Request
    if ($action === 'reject_request') {
        $reqId = filter_var($_POST['req_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$reqId || $reqId < 1) {
            goBack('Invalid request ID.', 'error');
        }

        $stmt = $conn->prepare(
            "UPDATE med_cert_requests
             SET status = 'Rejected'
             WHERE id = ? AND qr_code_hash IS NULL
               AND status IN ('Pending', 'Approved')"
        );
        $stmt->bind_param('i', $reqId);
        $stmt->execute();
        $rejected = $stmt->affected_rows;
        $stmt->close();

        goBack(
            $rejected
                ? 'Medical Certificate request has been rejected.'
                : 'The request could not be rejected because it was already processed.',
            $rejected ? 'success' : 'error'
        );
    }

    // Legacy form action retained only to prevent bypassing the physical exam.
    if ($action === 'generate_cert') {
        goBack('Complete and record the physical examination before approving the certificate.', 'error');
    }

    if ($action === 'create_walkin_certificate') {
        $studentName = postedString('student_name');
        $studentIdNo = postedString('student_id_no');
        $courseYear = postedString('course_year');
        $ageInput = postedString('age');
        $gender = postedString('gender');
        $contact = postedString('contact');
        $emailInput = postedString('email');
        $purpose = postedString('purpose');
        $allergies = postedString('allergies');
        $currentMedications = postedString('current_medications');
        $medicalHistory = postedString('medical_history');
        $menstrualHistory = postedString('menstrual_history');
        $alcoholUse = postedString('alcohol_use');
        $tobaccoUse = postedString('tobacco_use');
        $familyTb = postedString('family_history_tuberculosis');
        $familyHypertension = postedString('family_history_hypertension');
        $familyDiabetes = postedString('family_history_diabetes');
        $familyHeartDisease = postedString('family_history_heart_disease');
        $familyOther = postedString('family_history_other');
        $heightInput = postedString('height_cm');
        $weightInput = postedString('weight_kg');
        $temperatureInput = postedString('temperature');
        $bloodPressure = postedString('blood_pressure');
        $physicalFindings = postedString('physical_findings');
        $diagnosis = postedString('diagnosis');
        $recommendation = postedString('recommendation');
        $email = $emailInput === '' ? null : $emailInput;

        if ($studentName === '' || mb_strlen($studentName) > 100) {
            goBack('Enter the student name (100 characters or fewer).', 'error');
        }
        if ($studentIdNo === '' || mb_strlen($studentIdNo) > 50) {
            goBack('Enter the student ID (50 characters or fewer).', 'error');
        }
        if (mb_strlen($courseYear) > 50 || mb_strlen($contact) > 50) {
            goBack('Course/year or contact information exceeds the allowed length.', 'error');
        }
        $age = null;
        if ($ageInput !== '') {
            $ageValue = filter_var($ageInput, FILTER_VALIDATE_INT);
            if ($ageValue === false || $ageValue < 1 || $ageValue > 120) {
                goBack('Age must be a whole number between 1 and 120.', 'error');
            }
            $age = (string)$ageValue;
        }
        $genderOptions = ['Female', 'Male', 'Non-binary', 'Other', 'Prefer not to say'];
        if ($gender !== '' && !in_array($gender, $genderOptions, true)) {
            goBack('Select a valid gender response.', 'error');
        }
        if ($email !== null && (
            mb_strlen($email) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        )) {
            goBack('Enter a valid email address or leave it blank.', 'error');
        }
        if ($purpose === '' || mb_strlen($purpose) > 255) {
            goBack('Enter the certificate purpose (255 characters or fewer).', 'error');
        }
        if (($_POST['confirmed_with_student'] ?? '') !== 'yes') {
            goBack('Confirm that the personal and interview information was checked with the student.', 'error');
        }

        $choiceFields = [
            [$menstrualHistory, ['Not applicable', 'Regular', 'Irregular', 'Not asked', 'Prefer not to answer']],
            [$alcoholUse, ['No', 'Yes', 'Not asked', 'Prefer not to answer']],
            [$tobaccoUse, ['No', 'Yes', 'Not asked', 'Prefer not to answer']],
            [$familyTb, ['No', 'Yes', 'Unknown', 'Prefer not to answer']],
            [$familyHypertension, ['No', 'Yes', 'Unknown', 'Prefer not to answer']],
            [$familyDiabetes, ['No', 'Yes', 'Unknown', 'Prefer not to answer']],
            [$familyHeartDisease, ['No', 'Yes', 'Unknown', 'Prefer not to answer']]
        ];
        foreach ($choiceFields as [$value, $allowedValues]) {
            if (!in_array($value, $allowedValues, true)) {
                goBack('Choose valid responses for the student interview questions.', 'error');
            }
        }

        foreach (
            [
                'Allergies' => [$allergies, 2000],
                'Current medications' => [$currentMedications, 2000],
                'Medical history' => [$medicalHistory, 3000],
                'Other family history' => [$familyOther, 2000],
                'Physical findings' => [$physicalFindings, 5000],
                'Staff assessment' => [$diagnosis, 3000],
                'Recommendations' => [$recommendation, 3000]
            ] as $label => [$value, $limit]
        ) {
            if (mb_strlen($value) > $limit) {
                goBack($label . ' exceeds the allowed length.', 'error');
            }
        }

        if ($physicalFindings === '') {
            goBack('Enter the physical examination findings before generating the certificate.', 'error');
        }

        foreach (
            [
                'Height' => [$heightInput, 30, 250],
                'Weight' => [$weightInput, 1, 500],
                'Temperature' => [$temperatureInput, 25, 45]
            ] as $label => [$value, $minimum, $maximum]
        ) {
            if (
                $value !== '' &&
                (!is_numeric($value) || (float)$value < $minimum || (float)$value > $maximum)
            ) {
                goBack($label . ' is outside the accepted input range.', 'error');
            }
        }
        if (mb_strlen($bloodPressure) > 20) {
            goBack('Blood pressure must not exceed 20 characters.', 'error');
        }

        $height = $heightInput === '' ? null : $heightInput;
        $weight = $weightInput === '' ? null : $weightInput;
        $temperature = $temperatureInput === '' ? null : $temperatureInput;
        $approvedBy = mb_substr(
            trim((string)($_SESSION['admin_username'] ?? 'Clinic Staff')),
            0,
            100
        );
        $issuedDate = date('Y-m-d');
        $certificateCode = 'MC-' . strtoupper(bin2hex(random_bytes(16)));
        $requestStatus = 'Approved';

        try {
            $stmt = $conn->prepare(
                'INSERT INTO med_cert_requests (
                    student_id, student_id_no, purpose, status, student_name,
                    course_year, age, gender, contact, email, allergies,
                    current_medications, medical_history, menstrual_history,
                    alcohol_use, tobacco_use, family_history_tuberculosis,
                    family_history_hypertension, family_history_diabetes,
                    family_history_heart_disease, family_history_other, height_cm,
                    weight_kg, temperature, blood_pressure, physical_findings,
                    diagnosis, recommendation, qr_code_hash, approved_by,
                    issued_date, examination_date
                 ) VALUES (
                    NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                 )'
            );
            $stmt->bind_param(
                str_repeat('s', 31),
                $studentIdNo,
                $purpose,
                $requestStatus,
                $studentName,
                $courseYear,
                $age,
                $gender,
                $contact,
                $email,
                $allergies,
                $currentMedications,
                $medicalHistory,
                $menstrualHistory,
                $alcoholUse,
                $tobaccoUse,
                $familyTb,
                $familyHypertension,
                $familyDiabetes,
                $familyHeartDisease,
                $familyOther,
                $height,
                $weight,
                $temperature,
                $bloodPressure,
                $physicalFindings,
                $diagnosis,
                $recommendation,
                $certificateCode,
                $approvedBy,
                $issuedDate,
                $issuedDate
            );
            $stmt->execute();
            $requestId = (int)$stmt->insert_id;
            $stmt->close();
            header('Location: ../print_medical_certificate_request.php?id=' . $requestId);
            exit();
        } catch (mysqli_sql_exception $exception) {
            error_log('Walk-in medical certificate creation failed: ' . $exception->getMessage());
            goBack('The walk-in certificate could not be saved. Check the student details and try again.', 'error');
        }
    }
}

$searchTerm = isset($_GET['search']) && is_string($_GET['search'])
    ? trim($_GET['search'])
    : '';

$requestSql = "SELECT r.*,
                      COALESCE(NULLIF(r.student_name, ''), s.fullname) AS request_student_name,
                      COALESCE(NULLIF(r.course_year, ''), s.course_year) AS request_course_year
               FROM med_cert_requests r
               LEFT JOIN students s ON s.id = r.student_id";

if ($searchTerm === '') {
    $medCertRequests = $conn->query($requestSql . ' ORDER BY r.id DESC');
} else {
    $searchPattern = '%' . $searchTerm . '%';
    $requestStmt = $conn->prepare(
        $requestSql . "
        WHERE COALESCE(NULLIF(r.student_name, ''), s.fullname) LIKE ?
           OR r.student_id_no LIKE ?
           OR r.purpose LIKE ?
           OR r.status LIKE ?
        ORDER BY r.id DESC"
    );
    $requestStmt->bind_param(
        'ssss',
        $searchPattern,
        $searchPattern,
        $searchPattern,
        $searchPattern
    );
    $requestStmt->execute();
    $medCertRequests = $requestStmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Documentation | SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/staff-dashboard.css?v=20261115-staff-brand-alignment">
    <link rel="stylesheet" href="../../css/health-documentation.css?v=20261114-icons">

</head>

<body class="staff-dashboard-body">

    <aside class="staff-sidebar">
        <div class="staff-brand">
            <img src="../../image/logo2.png" alt="SHRCMS Logo">
            <div>
                <strong>SHRCMS</strong>
                <span>Clinic Management</span>
            </div>
        </div>

        <nav class="staff-nav">
            <p class="staff-nav-label">MAIN MENU</p>
            <a href="staff_dashboard.php"><span><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"></span> Dashboard</a>
            <a href="clinic_consultation.php"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Clinic Consultation</a>
            <a href="clinic_operations.php" class="active"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Passes, Referrals &amp; Incidents</a>
            <a href="health_documentation.php" class="active"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Health Documentation</a>
            <a href="scan_medical_certificate.php"><span><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"></span> Verify Certificate</a>
            <a href="medicine_inventory.php">
                <span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medicine Inventory
            </a>

            <p class="staff-nav-label">REPORTS</p>
            <a href="clinic_reports.php"><span><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"></span> Clinic Reports</a>
        </nav>

        <a href="../logout.php" class="staff-logout" onclick="return confirm('Are you sure you want to log out?');">
            <img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"> Log Out
        </a>
    </aside>

    <main class="staff-main">
        <header class="staff-header">
            <div>
                <p class="staff-eyebrow">CLINIC MANAGEMENT</p>
                <h1>Health Documentation</h1>
                <p class="staff-header-subtitle">
                    Review certificate requests, complete physical exams, and generate QR-verified certificates.
                </p>
                <a class="hd-button hd-button-primary" href="#walk-in-certificate">Add Med Cert</a>
            </div>

            <div class="staff-user">
                <img src="../../image/logo2.png" alt="Staff profile">
                <div>
                    <strong><?= e($username) ?></strong>
                    <small>Clinic Staff</small>
                </div>
            </div>
        </header>

        <section class="hd-content">

            <?php if ($message !== ''): ?>
                <div class="hd-alert <?= $messageType === 'error' ? 'hd-alert-error' : 'hd-alert-success' ?>">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <!-- SECTION 1: MEDICAL CERTIFICATE REQUESTS FROM STUDENTS -->
            <section class="hd-panel" style="margin-bottom: 30px;">
                <div class="hd-panel-heading">
                    <div>
                        <h2><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"> Student Medical Certificate Requests</h2>
                        <p>Review student-submitted health information, complete the physical examination during the clinic visit, and approve printable QR-verified certificates.</p>
                    </div>
                    <form method="GET" action="health_documentation.php" class="hd-search">
                        <input
                            type="search"
                            name="search"
                            value="<?= e($searchTerm) ?>"
                            placeholder="Search name, ID, purpose, or status"
                            aria-label="Search medical certificate requests">
                        <button type="submit" class="hd-button hd-button-primary">Search</button>
                        <?php if ($searchTerm !== ''): ?>
                            <a class="hd-button hd-button-light" href="health_documentation.php">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="hd-table-wrap">
                    <table class="hd-table">
                        <thead>
                            <tr>
                                <th>Student Details</th>
                                <th>Purpose</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th>Action / Management</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($medCertRequests && $medCertRequests->num_rows > 0): ?>
                                <?php while ($r = $medCertRequests->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($r['request_student_name'] ?: 'Student') ?></strong><br>
                                            <small><?= e($r['student_id_no']) ?> | <?= e($r['request_course_year'] ?: 'N/A') ?></small>
                                            <details class="hd-request-details" open>
                                                <summary>Student-submitted information (click to collapse)</summary>
                                                <dl>
                                                    <dt>Age / gender</dt>
                                                    <dd><?= e($r['age'] ?: 'Not provided') ?> / <?= e($r['gender'] ?: 'Not provided') ?></dd>
                                                    <dt>Contact</dt>
                                                    <dd><?= e($r['contact'] ?: 'Not provided') ?></dd>
                                                    <dt>Email</dt>
                                                    <dd><?= e($r['email'] ?: 'Not provided') ?></dd>
                                                    <dt>Allergies</dt>
                                                    <dd><?= nl2br(e($r['allergies'] ?: 'Not provided')) ?></dd>
                                                    <dt>Current medications</dt>
                                                    <dd><?= nl2br(e($r['current_medications'] ?: 'Not provided')) ?></dd>
                                                    <dt>Medical history</dt>
                                                    <dd><?= nl2br(e($r['medical_history'] ?: 'Not provided')) ?></dd>
                                                    <dt>Menstrual history</dt>
                                                    <dd><?= e($r['menstrual_history'] ?: 'Not provided') ?></dd>
                                                    <dt>Alcohol / tobacco</dt>
                                                    <dd><?= e($r['alcohol_use'] ?: 'Not provided') ?> / <?= e($r['tobacco_use'] ?: 'Not provided') ?></dd>
                                                    <dt>Family history</dt>
                                                    <dd>TB: <?= e($r['family_history_tuberculosis'] ?: 'Not provided') ?>; high blood pressure: <?= e($r['family_history_hypertension'] ?: 'Not provided') ?>; diabetes: <?= e($r['family_history_diabetes'] ?: 'Not provided') ?>; heart disease: <?= e($r['family_history_heart_disease'] ?: 'Not provided') ?>; other: <?= nl2br(e($r['family_history_other'] ?: 'Not provided')) ?></dd>
                                                </dl>
                                            </details>
                                        </td>
                                        <td><?= e($r['purpose']) ?></td>
                                        <td><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if (
                                                $r['status'] === 'Pending' ||
                                                ($r['status'] === 'Approved' && empty($r['qr_code_hash']))
                                            ): ?>
                                                <form method="POST" action="health_documentation.php" class="hd-approval-form">
                                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="action" value="approve_request">
                                                    <input type="hidden" name="req_id" value="<?= (int)$r['id'] ?>">
                                                    <strong>Complete physical examination</strong>
                                                    <div class="hd-approval-grid">
                                                        <label>Height (cm)<input type="number" name="height_cm" min="30" max="250" step="0.01"></label>
                                                        <label>Weight (kg)<input type="number" name="weight_kg" min="1" max="500" step="0.01"></label>
                                                        <label>Temperature (°C)<input type="number" name="temperature" min="25" max="45" step="0.1"></label>
                                                        <label>Blood pressure<input type="text" name="blood_pressure" maxlength="20" placeholder="120/80"></label>
                                                        <label class="hd-approval-wide">Physical examination findings<textarea name="physical_findings" rows="3" maxlength="5000" required></textarea></label>
                                                        <label class="hd-approval-wide">Staff assessment / diagnosis<textarea name="diagnosis" rows="2" maxlength="3000"></textarea></label>
                                                        <label class="hd-approval-wide">Recommendations<textarea name="recommendation" rows="2" maxlength="3000"></textarea></label>
                                                    </div>
                                                    <button type="submit" class="hd-button hd-button-primary">Save physical exam &amp; approve certificate</button>
                                                </form>
                                                <?php if ($r['status'] === 'Pending'): ?>
                                                    <form method="POST" action="health_documentation.php" class="hd-request-reject" onsubmit="return confirm('Reject this request?');">
                                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="reject_request">
                                                        <input type="hidden" name="req_id" value="<?= (int)$r['id'] ?>">
                                                        <button type="submit" class="hd-delete">Reject request</button>
                                                    </form>
                                                <?php endif; ?>

                                            <?php elseif (in_array($r['status'], ['Approved', 'Completed'], true) && !empty($r['qr_code_hash'])): ?>
                                                <div class="hd-certificate-issued">
                                                    <strong>Certificate approved</strong><br>
                                                    Code: <code><?= e($r['qr_code_hash']) ?></code><br>
                                                    Issued: <?= e($r['issued_date'] ? date('M d, Y', strtotime($r['issued_date'])) : 'Not recorded') ?>
                                                    <a class="hd-button hd-button-light" href="../print_medical_certificate_request.php?id=<?= (int)$r['id'] ?>">View / print certificate</a>
                                                    <a class="hd-button hd-button-light" href="scan_medical_certificate.php">Scan to verify</a>
                                                </div>
                                            <?php elseif ($r['status'] === 'Completed'): ?>
                                                <div style="font-size:12px;">
                                                    <strong style="color:#166534;">Verified Certificate Issued</strong><br>
                                                    Code: <code><?= e($r['qr_code_hash']) ?></code><br>
                                                    Issued: <?= e($r['issued_date'] ? date('M d, Y', strtotime($r['issued_date'])) : 'Not recorded') ?>
                                                    <a class="hd-button hd-button-light" href="../print_medical_certificate_request.php?id=<?= (int)$r['id'] ?>">View / print certificate</a>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#dc2626; font-size:12px;">Request Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="hd-empty">No student medical certificate requests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="hd-panel" id="walk-in-certificate">
                <div class="hd-panel-heading">
                    <div>
                        <h2>Walk-in Medical Certificate</h2>
                        <p>For students who do not use the system. Enter their personal details and interview answers, complete the physical examination, then generate the certificate with QR verification.</p>
                    </div>
                </div>

                <form method="POST" action="health_documentation.php" class="hd-form">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="create_walkin_certificate">

                    <div class="hd-form-grid">
                        <div class="hd-field hd-field-wide"><strong>Student personal information</strong></div>
                        <div class="hd-field">
                            <label for="walkin_student_name">Full name <span>*</span></label>
                            <input type="text" name="student_name" id="walkin_student_name" maxlength="100" required>
                        </div>
                        <div class="hd-field">
                            <label for="walkin_student_id_no">Student ID <span>*</span></label>
                            <input type="text" name="student_id_no" id="walkin_student_id_no" maxlength="50" required>
                        </div>
                        <div class="hd-field">
                            <label for="walkin_course_year">Course / year</label>
                            <input type="text" name="course_year" id="walkin_course_year" maxlength="50">
                        </div>
                        <div class="hd-field">
                            <label for="walkin_age">Age</label>
                            <input type="number" name="age" id="walkin_age" min="1" max="120">
                        </div>
                        <div class="hd-field">
                            <label for="walkin_gender">Gender</label>
                            <select name="gender" id="walkin_gender">
                                <option value="">Not provided</option>
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                                <option value="Non-binary">Non-binary</option>
                                <option value="Other">Other</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                        </div>
                        <div class="hd-field">
                            <label for="walkin_contact">Contact number</label>
                            <input type="text" name="contact" id="walkin_contact" maxlength="50">
                        </div>
                        <div class="hd-field">
                            <label for="walkin_email">Email address</label>
                            <input type="email" name="email" id="walkin_email" maxlength="100">
                        </div>
                        <div class="hd-field hd-field-wide">
                            <label for="walkin_purpose">Purpose of certificate <span>*</span></label>
                            <input type="text" name="purpose" id="walkin_purpose" maxlength="255" required>
                        </div>

                        <div class="hd-field hd-field-wide"><strong>Student interview</strong></div>
                        <div class="hd-field hd-field-wide">
                            <label for="walkin_allergies">Allergies</label>
                            <textarea name="allergies" id="walkin_allergies" rows="3" maxlength="2000"></textarea>
                        </div>
                        <div class="hd-field hd-field-wide">
                            <label for="walkin_current_medications">Current medications</label>
                            <textarea name="current_medications" id="walkin_current_medications" rows="3" maxlength="2000"></textarea>
                        </div>
                        <div class="hd-field hd-field-wide">
                            <label for="walkin_medical_history">Past or current medical history</label>
                            <textarea name="medical_history" id="walkin_medical_history" rows="3" maxlength="3000"></textarea>
                        </div>
                        <div class="hd-field">
                            <label for="walkin_menstrual_history">Menstrual history</label>
                            <select name="menstrual_history" id="walkin_menstrual_history">
                                <option value="Not asked">Not asked</option>
                                <option value="Not applicable">Not applicable</option>
                                <option value="Regular">Regular</option>
                                <option value="Irregular">Irregular</option>
                                <option value="Prefer not to answer">Prefer not to answer</option>
                            </select>
                        </div>
                        <div class="hd-field">
                            <label for="walkin_alcohol_use">Alcohol use</label>
                            <select name="alcohol_use" id="walkin_alcohol_use">
                                <option value="Not asked">Not asked</option>
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                                <option value="Prefer not to answer">Prefer not to answer</option>
                            </select>
                        </div>
                        <div class="hd-field">
                            <label for="walkin_tobacco_use">Tobacco use</label>
                            <select name="tobacco_use" id="walkin_tobacco_use">
                                <option value="Not asked">Not asked</option>
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                                <option value="Prefer not to answer">Prefer not to answer</option>
                            </select>
                        </div>
                        <?php
                        $walkInFamilyHistoryFields = [
                            'family_history_tuberculosis' => 'Family history of tuberculosis',
                            'family_history_hypertension' => 'Family history of high blood pressure',
                            'family_history_diabetes' => 'Family history of diabetes',
                            'family_history_heart_disease' => 'Family history of heart disease'
                        ];
                        foreach ($walkInFamilyHistoryFields as $fieldName => $fieldLabel):
                        ?>
                            <div class="hd-field">
                                <label for="walkin_<?= e($fieldName) ?>"><?= e($fieldLabel) ?></label>
                                <select name="<?= e($fieldName) ?>" id="walkin_<?= e($fieldName) ?>">
                                    <option value="Unknown">Unknown</option>
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                    <option value="Prefer not to answer">Prefer not to answer</option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                        <div class="hd-field hd-field-wide">
                            <label for="walkin_family_history_other">Other family health history</label>
                            <textarea name="family_history_other" id="walkin_family_history_other" rows="3" maxlength="2000"></textarea>
                        </div>

                        <div class="hd-field hd-field-wide"><strong>Physical examination and certificate</strong></div>
                        <div class="hd-field">
                            <label for="walkin_height_cm">Height (cm)</label>
                            <input type="number" name="height_cm" id="walkin_height_cm" min="30" max="250" step="0.01">
                        </div>
                        <div class="hd-field">
                            <label for="walkin_weight_kg">Weight (kg)</label>
                            <input type="number" name="weight_kg" id="walkin_weight_kg" min="1" max="500" step="0.01">
                        </div>
                        <div class="hd-field">
                            <label for="walkin_temperature">Temperature (°C)</label>
                            <input type="number" name="temperature" id="walkin_temperature" min="25" max="45" step="0.1">
                        </div>
                        <div class="hd-field">
                            <label for="walkin_blood_pressure">Blood pressure</label>
                            <input type="text" name="blood_pressure" id="walkin_blood_pressure" maxlength="20" placeholder="120/80">
                        </div>
                        <div class="hd-field hd-field-wide">
                            <label for="walkin_physical_findings">Physical examination findings <span>*</span></label>
                            <textarea name="physical_findings" id="walkin_physical_findings" rows="4" maxlength="5000" required></textarea>
                        </div>
                        <div class="hd-field hd-field-wide">
                            <label for="walkin_diagnosis">Staff assessment / diagnosis</label>
                            <textarea name="diagnosis" id="walkin_diagnosis" rows="3" maxlength="3000"></textarea>
                        </div>
                        <div class="hd-field hd-field-wide">
                            <label for="walkin_recommendation">Recommendations / remarks</label>
                            <textarea name="recommendation" id="walkin_recommendation" rows="3" maxlength="3000"></textarea>
                        </div>
                    </div>

                    <label class="hd-walkin-confirmation">
                        <input type="checkbox" name="confirmed_with_student" value="yes" required>
                        <span>I confirmed the personal and interview information with the walk-in student.</span>
                    </label>
                    <p class="hd-privacy-note">Interview answers are available to authorized clinic staff and are not printed on the certificate. The certificate is generated immediately after saving.</p>
                    <div class="hd-form-actions">
                        <button type="submit" class="hd-button hd-button-primary">Save &amp; generate walk-in certificate</button>
                        <button type="reset" class="hd-button hd-button-light">Clear form</button>
                    </div>
                </form>
            </section>

        </section>

    </main>

</body>

</html>
<?php $conn->close(); ?>