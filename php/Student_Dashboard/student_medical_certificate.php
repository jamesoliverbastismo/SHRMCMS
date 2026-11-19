<?php
session_start();
require_once __DIR__ . '/student_helper.php';
requireStudentSession();

require_once __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

function postedString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function redirectCertificateRequest(string $message, string $type = 'success'): void
{
    header(
        'Location: student_medical_certificate.php?message=' .
        urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

if (empty($_SESSION['medical_certificate_csrf'])) {
    $_SESSION['medical_certificate_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['medical_certificate_csrf'];
$userId = (int)($_SESSION['user_id'] ?? 0);
$studentIdNo = trim((string)($_SESSION['student_id_no'] ?? ''));
$sessionEmail = trim((string)($_SESSION['email'] ?? ''));
$username = $_SESSION['admin_username'] ?? 'Student';

$student = findLinkedStudent(
    $conn,
    $userId,
    $studentIdNo,
    $sessionEmail,
    'id, student_id_no, fullname, course_year, age, gender, contact, email'
);
$requests = [];
$message = trim(is_string($_GET['message'] ?? null) ? $_GET['message'] : '');
$messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';

$form = [
    'student_name' => (string)($student['fullname'] ?? ''),
    'course_year' => (string)($student['course_year'] ?? ''),
    'age' => (string)($student['age'] ?? ''),
    'gender' => (string)($student['gender'] ?? ''),
    'contact' => (string)($student['contact'] ?? ''),
    'email' => (string)($student['email'] ?? ''),
    'purpose' => '',
    'allergies' => '',
    'current_medications' => '',
    'medical_history' => '',
    'menstrual_history' => 'Not asked',
    'alcohol_use' => 'Not asked',
    'tobacco_use' => 'Not asked',
    'family_history_tuberculosis' => 'Unknown',
    'family_history_hypertension' => 'Unknown',
    'family_history_diabetes' => 'Unknown',
    'family_history_heart_disease' => 'Unknown',
    'family_history_other' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student) {
    $message = '';
    $messageType = 'success';

    foreach ($form as $key => $default) {
        $form[$key] = postedString($key);
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        http_response_code(400);
        $message = 'Your form expired. Refresh the page and try again.';
        $messageType = 'error';
    } elseif (($_POST['action'] ?? '') !== 'request') {
        http_response_code(400);
        $message = 'Invalid medical certificate request.';
        $messageType = 'error';
    } else {
        $age = filter_var($form['age'], FILTER_VALIDATE_INT);
        $genderOptions = ['Female', 'Male', 'Non-binary', 'Other', 'Prefer not to say'];
        $choiceFields = [
            'menstrual_history' => ['Not applicable', 'Regular', 'Irregular', 'Not asked', 'Prefer not to answer'],
            'alcohol_use' => ['No', 'Yes', 'Not asked', 'Prefer not to answer'],
            'tobacco_use' => ['No', 'Yes', 'Not asked', 'Prefer not to answer'],
            'family_history_tuberculosis' => ['No', 'Yes', 'Unknown', 'Prefer not to answer'],
            'family_history_hypertension' => ['No', 'Yes', 'Unknown', 'Prefer not to answer'],
            'family_history_diabetes' => ['No', 'Yes', 'Unknown', 'Prefer not to answer'],
            'family_history_heart_disease' => ['No', 'Yes', 'Unknown', 'Prefer not to answer']
        ];

        if (
            $form['student_name'] === '' ||
            mb_strlen($form['student_name']) > 100 ||
            $form['course_year'] === '' ||
            mb_strlen($form['course_year']) > 50 ||
            $age === false ||
            $age < 1 ||
            $age > 120 ||
            !in_array($form['gender'], $genderOptions, true)
        ) {
            $message = 'Enter valid personal information, including name, course/year, age, and gender.';
        } elseif ($form['purpose'] === '' || mb_strlen($form['purpose']) > 255) {
            $message = 'Enter the certificate purpose (255 characters or fewer).';
        } elseif (($_POST['consent'] ?? '') !== 'yes') {
            $message = 'Confirm the information-sharing statement before submitting.';
        } elseif (
            mb_strlen($form['contact']) > 50 ||
            ($form['email'] !== '' && (
                mb_strlen($form['email']) > 100 ||
                !filter_var($form['email'], FILTER_VALIDATE_EMAIL)
            ))
        ) {
            $message = 'Enter a valid contact number and email address.';
        } else {
            foreach ($choiceFields as $key => $allowedValues) {
                if (!in_array($form[$key], $allowedValues, true)) {
                    $message = 'Choose a valid response for each medical history question.';
                    break;
                }
            }
        }

        $limits = [
            'allergies' => 2000,
            'current_medications' => 2000,
            'medical_history' => 3000,
            'family_history_other' => 2000
        ];
        foreach ($limits as $key => $limit) {
            if (mb_strlen($form[$key]) > $limit) {
                $message = 'One or more medical history answers exceed the allowed length.';
                break;
            }
        }

        if ($message === '') {
            $requestStatus = 'Pending';
            $studentId = (int)$student['id'];
            $verifiedStudentId = (string)$student['student_id_no'];
            $requestAge = (int)$age;
            $email = $form['email'] === '' ? null : $form['email'];

            try {
                $stmt = $conn->prepare(
                    'INSERT INTO med_cert_requests (
                        student_id, student_id_no, purpose, status,
                        student_name, course_year, age, gender, contact, email,
                        allergies, current_medications, medical_history,
                        menstrual_history, alcohol_use, tobacco_use,
                        family_history_tuberculosis, family_history_hypertension,
                        family_history_diabetes, family_history_heart_disease,
                        family_history_other
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'isssssis' . str_repeat('s', 13),
                    $studentId,
                    $verifiedStudentId,
                    $form['purpose'],
                    $requestStatus,
                    $form['student_name'],
                    $form['course_year'],
                    $requestAge,
                    $form['gender'],
                    $form['contact'],
                    $email,
                    $form['allergies'],
                    $form['current_medications'],
                    $form['medical_history'],
                    $form['menstrual_history'],
                    $form['alcohol_use'],
                    $form['tobacco_use'],
                    $form['family_history_tuberculosis'],
                    $form['family_history_hypertension'],
                    $form['family_history_diabetes'],
                    $form['family_history_heart_disease'],
                    $form['family_history_other']
                );
                $stmt->execute();
                $stmt->close();

                // Rotate CSRF token after successful request
                $_SESSION['medical_certificate_csrf'] = bin2hex(random_bytes(32));

                redirectCertificateRequest(
                    'Your request was submitted. Clinic staff will review your information and complete the physical examination when you visit.'
                );
            } catch (mysqli_sql_exception $exception) {
                error_log('Student medical certificate request failed: ' . $exception->getMessage());
                $message = 'The request could not be saved. Please try again or contact the clinic.';
                $messageType = 'error';
            }
        }

        if ($message !== '') {
            $messageType = 'error';
        }
    }
}

if ($student) {
    $username = $student['fullname'];
    $studentId = (int)$student['id'];
    $stmt = $conn->prepare(
        'SELECT * FROM med_cert_requests
         WHERE student_id = ? ORDER BY id DESC'
    );
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
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
    <title>Medical Certificate | SHRCMS</title>
    <link rel="stylesheet" href="../../css/student-dashboard.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/student-medical-certificate.css">
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
                <p>Submit your health history, visit the clinic for examination, then print your approved certificate.</p>
            </div>
            <div class="student-profile">
                <div class="student-avatar"><img class="ui-icon" src="../../image/icon-profile.svg" alt="" aria-hidden="true"></div>
                <div class="student-profile-info"><strong><?= e($username) ?></strong><span>Student</span></div>
            </div>
        </header>

        <section class="student-content student-medical-content">
            <?php if ($message !== ''): ?>
                <div class="student-medical-alert <?= $messageType === 'error' ? 'is-error' : 'is-success' ?>" role="<?= $messageType === 'error' ? 'alert' : 'status' ?>">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!$student): ?>
                <section class="student-panel student-medical-panel">
                    <h2>Student profile unavailable</h2>
                    <p>Your student profile is not linked to this account. Contact clinic staff before submitting a request.</p>
                </section>
            <?php else: ?>
                <section class="student-panel student-medical-panel no-print">
                    <div class="student-medical-heading">
                        <div>
                            <h2>Request a Medical Certificate</h2>
                            <p>Complete your personal and interview information. Clinic staff will record all physical examination findings during your clinic visit.</p>
                        </div>
                        <span class="student-medical-id">Student ID: <?= e($student['student_id_no']) ?></span>
                    </div>

                    <form method="POST" action="student_medical_certificate.php" class="student-medical-form">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="request">

                        <fieldset>
                            <legend>Personal information</legend>
                            <div class="student-medical-grid">
                                <label>Full name
                                    <input type="text" name="student_name" maxlength="100" required value="<?= e($form['student_name']) ?>">
                                </label>
                                <label>Student ID
                                    <input type="text" value="<?= e($student['student_id_no']) ?>" readonly>
                                </label>
                                <label>Course / year
                                    <input type="text" name="course_year" maxlength="50" required value="<?= e($form['course_year']) ?>">
                                </label>
                                <label>Age
                                    <input type="number" name="age" min="1" max="120" required value="<?= e($form['age']) ?>">
                                </label>
                                <label>Gender
                                    <select name="gender" required>
                                        <option value="">Select</option>
                                        <?php foreach (['Female', 'Male', 'Non-binary', 'Other', 'Prefer not to say'] as $option): ?>
                                            <option value="<?= e($option) ?>" <?= $form['gender'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Contact number
                                    <input type="text" name="contact" maxlength="50" value="<?= e($form['contact']) ?>">
                                </label>
                                <label>Email address
                                    <input type="email" name="email" maxlength="100" value="<?= e($form['email']) ?>">
                                </label>
                                <label class="student-medical-wide">Purpose of certificate
                                    <input type="text" name="purpose" maxlength="255" required value="<?= e($form['purpose']) ?>" placeholder="What do you need the certificate for?">
                                </label>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend>Medical interview</legend>
                            <p class="student-medical-help">Your answers are reviewed by clinic staff and are not printed on the certificate.</p>
                            <div class="student-medical-grid">
                                <label class="student-medical-wide">Allergies
                                    <textarea name="allergies" rows="3" maxlength="2000" placeholder="List known allergies or enter none."><?= e($form['allergies']) ?></textarea>
                                </label>
                                <label class="student-medical-wide">Current medications
                                    <textarea name="current_medications" rows="3" maxlength="2000" placeholder="List current medications or enter none."><?= e($form['current_medications']) ?></textarea>
                                </label>
                                <label class="student-medical-wide">Past or current medical history
                                    <textarea name="medical_history" rows="3" maxlength="3000" placeholder="Include relevant past or current conditions."><?= e($form['medical_history']) ?></textarea>
                                </label>
                                <label>Menstrual history
                                    <select name="menstrual_history" required>
                                        <?php foreach (['Not asked', 'Not applicable', 'Regular', 'Irregular', 'Prefer not to answer'] as $option): ?>
                                            <option value="<?= e($option) ?>" <?= $form['menstrual_history'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Alcohol use
                                    <select name="alcohol_use" required>
                                        <?php foreach (['Not asked', 'No', 'Yes', 'Prefer not to answer'] as $option): ?>
                                            <option value="<?= e($option) ?>" <?= $form['alcohol_use'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Tobacco use
                                    <select name="tobacco_use" required>
                                        <?php foreach (['Not asked', 'No', 'Yes', 'Prefer not to answer'] as $option): ?>
                                            <option value="<?= e($option) ?>" <?= $form['tobacco_use'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <?php
                                $familyHistoryLabels = [
                                    'family_history_tuberculosis' => 'Family history of tuberculosis',
                                    'family_history_hypertension' => 'Family history of high blood pressure',
                                    'family_history_diabetes' => 'Family history of diabetes',
                                    'family_history_heart_disease' => 'Family history of heart disease'
                                ];
                                foreach ($familyHistoryLabels as $key => $label):
                                ?>
                                    <label><?= e($label) ?>
                                        <select name="<?= e($key) ?>" required>
                                            <?php foreach (['Unknown', 'No', 'Yes', 'Prefer not to answer'] as $option): ?>
                                                <option value="<?= e($option) ?>" <?= $form[$key] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                <?php endforeach; ?>
                                <label class="student-medical-wide">Other family health history
                                    <textarea name="family_history_other" rows="3" maxlength="2000"><?= e($form['family_history_other']) ?></textarea>
                                </label>
                            </div>
                        </fieldset>

                        <label class="student-medical-consent">
                            <input type="checkbox" name="consent" value="yes" required>
                            <span>I confirm that the information above is accurate to the best of my knowledge and may be reviewed by authorized clinic staff for this request.</span>
                        </label>
                        <div class="student-medical-actions">
                            <button type="submit" class="student-medical-button">Submit request</button>
                        </div>
                    </form>
                </section>

                <section class="student-panel student-medical-panel no-print">
                    <div class="student-medical-heading">
                        <div><h2>My Requests</h2><p>After staff completes your physical examination and approves the request, print your certificate here.</p></div>
                    </div>
                    <div class="student-medical-table-wrap">
                        <table class="student-table student-medical-table">
                            <thead><tr><th>Requested</th><th>Purpose</th><th>Status</th><th>Next step</th></tr></thead>
                            <tbody>
                            <?php if ($requests): ?>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?= e(date('M d, Y', strtotime($request['created_at']))) ?></td>
                                        <td><?= e($request['purpose']) ?></td>
                                        <td><span class="student-medical-status status-<?= e($request['status']) ?>"><?= e($request['status']) ?></span></td>
                                        <td>
                                            <?php if (in_array($request['status'], ['Approved', 'Completed'], true) && !empty($request['qr_code_hash'])): ?>
                                                <a class="student-medical-link" href="../print_medical_certificate_request.php?id=<?= (int)$request['id'] ?>">View / print certificate</a>
                                            <?php elseif ($request['status'] === 'Pending' || ($request['status'] === 'Approved' && empty($request['qr_code_hash']))): ?>
                                                Please visit the clinic for your physical examination.
                                            <?php else: ?>
                                                Contact the clinic if you need more information.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="student-empty">No medical certificate requests yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
<?php $conn->close(); ?>