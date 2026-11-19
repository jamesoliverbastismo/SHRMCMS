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
require_once __DIR__ . '/../rbac_audit.php';
rbacRequireRole(['Staff', 'Administrator'], '../login.php');

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !isset($_SESSION['user_role']) ||
    !in_array($_SESSION['user_role'], ['Staff', 'Administrator'], true)
) {
    header('Location: ../login.php');
    exit();
}

$conn->set_charset('utf8mb4');

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function validDate($date)
{
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    $errors = DateTime::getLastErrors();

    return $d !== false &&
        ($errors === false ||
            ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) &&
        $d->format('Y-m-d') === $date;
}

if (empty($_SESSION['consultation_csrf'])) {
    $_SESSION['consultation_csrf'] = bin2hex(random_bytes(32));
}

$success_msg = '';
$error_msg = '';

$edit_id = 0;
$student_id = '';
$consultation_date = date('Y-m-d');
$reason = 'General Check-up';
$remarks = '';

$reasons = [
    'General Check-up',
    'Fever / Headache',
    'Injury / Wound',
    'Stomachache',
    'Cough / Cold',
    'Dizziness',
    'Physical Examination',
    'Referral / Follow-up',
    'Others'
];

/* LOAD RECORD FOR TRACEABLE UPDATE */
if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $editCandidate = (int)$_GET['edit'];
    if ($editCandidate > 0) {
        $editStmt = $conn->prepare(
            "SELECT id, student_id, consultation_date, reason, remarks
             FROM consultations
             WHERE id = ? LIMIT 1"
        );
        $editStmt->bind_param('i', $editCandidate);
        $editStmt->execute();
        $foundEdit = $editStmt->get_result()->fetch_assoc();
        $editStmt->close();

        if ($foundEdit) {
            $edit_id = (int)$foundEdit['id'];
            $student_id = (string)$foundEdit['student_id'];
            $consultation_date = (string)$foundEdit['consultation_date'];
            $reason = in_array($foundEdit['reason'], $reasons, true) ? $foundEdit['reason'] : 'Others';
            $remarks = (string)($foundEdit['remarks'] ?? '');
        }
    }
}

/* SAVE OR UPDATE CONSULTATION */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_consultation'])
) {
    $csrf = $_POST['csrf_token'] ?? '';

    $edit_id = isset($_POST['edit_id']) && ctype_digit((string)$_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $student_id = trim($_POST['student_id'] ?? '');
    $consultation_date = trim($_POST['consultation_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $staff_name = $_SESSION['admin_username'] ?? 'Staff';

    if (!hash_equals($_SESSION['consultation_csrf'], $csrf)) {
        $error_msg = 'Your session token is invalid. Please refresh the page and try again.';
    } elseif (
        $student_id === '' ||
        !ctype_digit($student_id) ||
        !validDate($consultation_date) ||
        $consultation_date > date('Y-m-d') ||
        !in_array($reason, $reasons, true)
    ) {
        $error_msg = 'Please select a student, enter a valid date, and choose a reason.';
    } elseif (strlen($remarks) > 5000) {
        $error_msg = 'Remarks must not exceed 5,000 characters.';
    } else {
        try {
            $check = $conn->prepare(
                "SELECT id FROM students WHERE id = ? LIMIT 1"
            );
            $check->bind_param('i', $student_id);
            $check->execute();
            $studentResult = $check->get_result();
            $studentExists = $studentResult->num_rows > 0;
            $check->close();

            if (!$studentExists) {
                $error_msg = 'The selected student could not be found. Please select a registered student.';
            } else {
                $studentIdInt = (int)$student_id;

                if ($edit_id > 0) {
                    $auditStamp = "\n[Updated on " . date('Y-m-d H:i') . " by " . $staff_name . "]";
                    $cleanRemarks = preg_replace('/\n\[Updated on [^\]]+\]$/', '', $remarks) ?? $remarks;
                    $tracedRemarks = mb_substr($cleanRemarks . $auditStamp, 0, 5000);

                    $stmt = $conn->prepare(
                        "UPDATE consultations
                         SET student_id = ?, consultation_date = ?, reason = ?, remarks = ?, staff_name = ?
                         WHERE id = ?"
                    );
                    $stmt->bind_param(
                        'issssi',
                        $studentIdInt,
                        $consultation_date,
                        $reason,
                        $tracedRemarks,
                        $staff_name,
                        $edit_id
                    );
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['consultation_flash'] =
                        'Clinic visit entry updated with traceable revision note.';
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO consultations
                        (student_id, consultation_date, reason, remarks, staff_name)
                        VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param(
                        'issss',
                        $studentIdInt,
                        $consultation_date,
                        $reason,
                        $remarks,
                        $staff_name
                    );
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['consultation_flash'] =
                        'Clinic visit documentation saved successfully.';
                }

                header('Location: clinic_consultation.php?student_filter=' . $studentIdInt);
                exit();
            }
        } catch (Throwable $ex) {
            error_log('Consultation save error: ' . $ex->getMessage());
            $error_msg = 'Unable to save the consultation record. Please check your database structure and try again.';
        }
    }
}

/* ONE-TIME SUCCESS MESSAGE */
if (!empty($_SESSION['consultation_flash'])) {
    $success_msg = $_SESSION['consultation_flash'];
    unset($_SESSION['consultation_flash']);
}

/* STUDENT DROPDOWN & SAFELY DETECT OPTIONAL ALLERGY/CONDITION COLUMNS */
$students = [];
$studentAlerts = [];

try {
    $colsRes = $conn->query("SHOW COLUMNS FROM students");
    $studentCols = [];
    if ($colsRes) {
        while ($colRow = $colsRes->fetch_assoc()) {
            $studentCols[] = $colRow['Field'];
        }
    }

    $selectFields = ['id', 'student_id_no', 'fullname', 'course_year'];
    foreach (['allergies', 'known_conditions', 'medical_history'] as $optCol) {
        if (in_array($optCol, $studentCols, true)) {
            $selectFields[] = $optCol;
        }
    }

    $studentQuery = $conn->query(
        "SELECT " . implode(', ', $selectFields) . "
         FROM students
         ORDER BY fullname ASC"
    );

    while ($row = $studentQuery->fetch_assoc()) {
        $students[] = $row;
        $studentAlerts[(string)$row['id']] = [
            'allergies' => $row['allergies'] ?? 'Not recorded in profile',
            'conditions' => $row['known_conditions'] ?? ($row['medical_history'] ?? 'Not recorded in profile'),
        ];
    }
} catch (Throwable $ex) {
    error_log('Student list error: ' . $ex->getMessage());
    $error_msg = $error_msg ?: 'Unable to load the student list.';
}

/* SEARCH, STUDENT, AND DATE FILTER */
$search = trim($_GET['search'] ?? '');
$filter_date = trim($_GET['date'] ?? '');
$filter_student = (isset($_GET['student_filter']) && ctype_digit((string)$_GET['student_filter']))
    ? (string)(int)$_GET['student_filter']
    : ($student_id !== '' ? $student_id : '');

if ($filter_date !== '' && !validDate($filter_date)) {
    $filter_date = '';
}

$conditions = [];
$params = [];
$types = '';

if ($filter_student !== '') {
    $conditions[] = "c.student_id = ?";
    $params[] = (int)$filter_student;
    $types .= 'i';
}

if ($search !== '') {
    $conditions[] = "(s.fullname LIKE ? OR s.student_id_no LIKE ?
                      OR c.reason LIKE ? OR c.staff_name LIKE ? OR c.remarks LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
    $types .= 'sssss';
}

if ($filter_date !== '') {
    $conditions[] = "c.consultation_date = ?";
    $params[] = $filter_date;
    $types .= 's';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

/* CONSULTATION STATISTICS */
$totalConsultations = 0;
$todayConsultations = 0;
$studentCount = count($students);

try {
    $result = $conn->query("SELECT COUNT(*) AS total FROM consultations");
    $totalConsultations = (int)$result->fetch_assoc()['total'];

    $today = date('Y-m-d');
    $stat = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM consultations
         WHERE consultation_date = ?"
    );
    $stat->bind_param('s', $today);
    $stat->execute();
    $todayConsultations = (int)$stat->get_result()->fetch_assoc()['total'];
    $stat->close();
} catch (Throwable $ex) {
    error_log('Consultation statistics error: ' . $ex->getMessage());
}

/* RECENT CONSULTATION RECORDS */
$consultations = [];

try {
    $sql = "SELECT c.id, c.student_id, c.consultation_date,
                   c.reason, c.remarks, c.staff_name,
                   s.student_id_no, s.fullname
            FROM consultations c
            INNER JOIN students s ON s.id = c.student_id
            $where
            ORDER BY c.consultation_date DESC, c.id DESC
            LIMIT 100";

    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $records = $stmt->get_result();

    while ($row = $records->fetch_assoc()) {
        $consultations[] = $row;
    }

    $stmt->close();
} catch (Throwable $ex) {
    error_log('Consultation list error: ' . $ex->getMessage());
    $error_msg = $error_msg ?: 'Unable to load consultation records.';
}

$username = e($_SESSION['admin_username'] ?? 'Staff');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Consultation - SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/staff-dashboard.css?v=20261115-staff-admin-alignment">
    <link rel="stylesheet" href="../../css/consultation.css?v=20261114-icons">
</head>

<body class="staff-dashboard-body">

    <aside class="staff-sidebar">
        <a href="staff_dashboard.php" class="staff-brand">
            <img src="../../image/logo2.png" alt="SHRCMS Logo">
            <div>
                <h2>SHRCMS</h2>
                <p>Clinic Management</p>
            </div>
        </a>

        <p class="sidebar-label">MAIN MENU</p>

        <nav class="staff-nav">
            <a href="staff_dashboard.php"><span><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"></span> Dashboard</a>
            <a href="clinic_consultation.php" class="active"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Clinic Consultation</a>
            <a href="clinic_operations.php?view=passes"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Clinic Passes</a>
            <a href="clinic_operations.php?view=referrals"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Referrals</a>
            <a href="clinic_operations.php?view=incidents"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Incidents</a>
            <a href="health_documentation.php"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Health Documentation</a>
            <a href="medical_examinations.php"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medical Examinations</a>
            <a href="scan_medical_certificate.php"><span><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"></span> Verify Certificate</a>
            <a href="medicine_inventory.php">
                <span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medicine Inventory
            </a>

            <p class="sidebar-label">REPORTS</p>
            <a href="clinic_reports.php"><span><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"></span> Clinic Reports</a>
            <a href="../logout.php" class="logout-link"><span><img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"></span> Logout</a>
        </nav>

        <div class="sidebar-bottom">
            <p>Student Health Record</p>
            <small>Clinic Management System</small>
        </div>
    </aside>

    <main class="staff-main">

        <header class="staff-header">
            <div>
                <p class="page-eyebrow">STUDENT HEALTH SERVICES</p>
                <h1>Clinic Consultation &amp; Visit Documentation</h1>
                <p class="header-subtitle">
                    Document student clinic visits, reported concerns, first-aid/referral notes, and follow-up details. This module supports clinic documentation only and does not perform independent diagnosis or automated prescribing.
                </p>
            </div>

            <div class="staff-user">
                <img src="../../image/logo2.png" alt="Staff profile">
                <div>
                    <strong><?= $username ?></strong>
                    <span>Clinic Staff</span>
                </div>
            </div>
        </header>

        <?php if ($success_msg !== ''): ?>
            <div class="consultation-message success-message" role="status">
                <strong><img class="ui-icon" src="../../image/icon-check.svg" alt="" aria-hidden="true"> Success</strong>
                <p><?= e($success_msg) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error_msg !== ''): ?>
            <div class="consultation-message error-message" role="alert">
                <strong>! Notice</strong>
                <p><?= e($error_msg) ?></p>
            </div>
        <?php endif; ?>

        <section class="consultation-stats">
            <article class="consultation-stat">
                <span class="stat-icon"><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span>
                <div>
                    <p>Total Recorded Visits</p>
                    <h2><?= number_format($totalConsultations) ?></h2>
                    <small>All documented clinic visits</small>
                </div>
            </article>

            <article class="consultation-stat">
                <span class="stat-icon"><img class="ui-icon" src="../../image/icon-clock.svg" alt="" aria-hidden="true"></span>
                <div>
                    <p>Today's Clinic Visits</p>
                    <h2><?= number_format($todayConsultations) ?></h2>
                    <small><?= e(date('F j, Y')) ?></small>
                </div>
            </article>

            <article class="consultation-stat">
                <span class="stat-icon"><img class="ui-icon" src="../../image/icon-profile.svg" alt="" aria-hidden="true"></span>
                <div>
                    <p>Registered Students</p>
                    <h2><?= number_format($studentCount) ?></h2>
                    <small>Available for visit documentation</small>
                </div>
            </article>
        </section>

        <section class="consultation-panel">
            <div class="consultation-panel-heading">
                <div class="consultation-heading-icon"><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></div>
                <div>
                    <h2><?= $edit_id > 0 ? 'Update Clinic Visit Entry (Traceable)' : 'Record Clinic Visit' ?></h2>
                    <p>Enter factual visit details, reported concern, and authorized clinic actions/follow-up notes.</p>
                </div>
            </div>

            <div class="consultation-divider"></div>

            <form action="clinic_consultation.php" method="POST"
                class="consultation-form">

                <input type="hidden" name="csrf_token"
                    value="<?= e($_SESSION['consultation_csrf']) ?>">
                <input type="hidden" name="edit_id" value="<?= (int)$edit_id ?>">

                <div class="consultation-form-grid">

                    <div class="consultation-field full-width">
                        <label for="student_id">Select Student <span class="required">*</span></label>

                        <select id="student_id" name="student_id" required>
                            <option value="">Choose a registered student</option>
                            <?php foreach ($students as $student): ?>
                                <option
                                    value="<?= (int)$student['id'] ?>"
                                    <?= $student_id === (string)$student['id'] ? 'selected' : '' ?>>
                                    <?= e($student['student_id_no']) ?> —
                                    <?= e($student['fullname']) ?>
                                    <?= $student['course_year'] ? ' (' . e($student['course_year']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div id="student-health-alert" style="margin-top:8px; padding:10px 12px; border-radius:6px; background:#f8fafc; border:1px solid #cbd5e1; font-size:0.9rem;">
                            <strong>Profile Alert:</strong> <span id="alert-allergy">Select a student to view allergies and known conditions.</span>
                        </div>
                    </div>

                    <div class="consultation-field">
                        <label for="consultation_date">Visit Date <span class="required">*</span></label>
                        <input type="date" id="consultation_date"
                            name="consultation_date"
                            value="<?= e($consultation_date) ?>"
                            max="<?= e(date('Y-m-d')) ?>" required>
                    </div>

                    <div class="consultation-field">
                        <label for="reason">Reported Concern / Visit Category <span class="required">*</span></label>
                        <select id="reason" name="reason" required>
                            <?php foreach ($reasons as $option): ?>
                                <option value="<?= e($option) ?>"
                                    <?= $reason === $option ? 'selected' : '' ?>>
                                    <?= e($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="consultation-field full-width">
                        <label for="remarks">Documentation Notes (Observations, Care / First-Aid, Referral, Follow-up)</label>
                        <textarea id="remarks" name="remarks" rows="5"
                            maxlength="5000"
                            placeholder="Document reported symptoms, factual observations, first-aid/care provided, referral destination/status, or follow-up notes..."><?= e($remarks) ?></textarea>
                        <div class="remarks-footer">
                            <small>Record factual clinic documentation only; avoid unsupported diagnostic or treatment claims.</small>
                            <small><span id="remarks-count">0</span>/5000</small>
                        </div>
                    </div>
                </div>

                <div class="consultation-form-footer">
                    <p><span class="required">*</span> Required fields</p>
                    <?php if ($edit_id > 0): ?>
                        <a href="clinic_consultation.php" class="consultation-reset">Cancel Edit</a>
                    <?php else: ?>
                        <button type="reset" class="consultation-reset">Clear Form</button>
                    <?php endif; ?>
                    <button type="submit" name="save_consultation"
                        class="consultation-submit">
                        <span><img class="ui-icon" src="../../image/icon-add.svg" alt="" aria-hidden="true"></span>
                        <?= $edit_id > 0 ? 'Save Traceable Update' : 'Save Visit Record' ?>
                    </button>
                </div>
            </form>
        </section>

        <section class="consultation-panel history-panel">
            <div class="consultation-panel-heading history-heading">
                <div>
                    <h2>Clinic Visit &amp; Consultation History</h2>
                    <p>Search and review prior visits for continuity of documentation.</p>
                </div>
                <span class="record-count"><?= count($consultations) ?> records shown</span>
            </div>

            <form action="clinic_consultation.php" method="GET"
                class="consultation-search-form">
                <div class="search-input-wrap">
                    <span><img class="ui-icon" src="../../image/icon-search.svg" alt="" aria-hidden="true"></span>
                    <input type="search" name="search"
                        value="<?= e($search) ?>"
                        placeholder="Search student, ID, reason, notes, or staff...">
                </div>

                <select name="student_filter" aria-label="Filter by student">
                    <option value="">All Students</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= (int)$student['id'] ?>" <?= $filter_student === (string)$student['id'] ? 'selected' : '' ?>>
                            <?= e($student['student_id_no']) ?> — <?= e($student['fullname']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="date" value="<?= e($filter_date) ?>"
                    aria-label="Filter by visit date">

                <button type="submit" class="consultation-search-button">Search</button>
                <a href="clinic_consultation.php" class="consultation-reset-link">Reset</a>
            </form>

            <div class="consultation-table-wrap">
                <table class="consultation-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Visit Date</th>
                            <th>Reported Concern / Category</th>
                            <th>Documentation Notes</th>
                            <th>Recorded By</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($consultations)): ?>
                            <tr>
                                <td colspan="6" class="consultation-empty">
                                    <span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span>
                                    <strong>No consultation records found</strong>
                                    <p>Try another search or record a new clinic visit.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($consultations as $record): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($record['fullname']) ?></strong>
                                        <small class="student-number">
                                            <?= e($record['student_id_no']) ?>
                                        </small>
                                    </td>
                                    <td><?= e(date('M j, Y', strtotime($record['consultation_date']))) ?></td>
                                    <td><span class="reason-tag"><?= e($record['reason']) ?></span></td>
                                    <td class="remarks-cell">
                                        <?= $record['remarks'] !== ''
                                            ? nl2br(e($record['remarks']))
                                            : '<span class="no-remarks">No documentation notes provided</span>' ?>
                                    </td>
                                    <td><?= e($record['staff_name']) ?></td>
                                    <td>
                                        <a href="clinic_consultation.php?edit=<?= (int)$record['id'] ?>">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="consultation-table-footer">
                <span>Showing up to 100 records, newest first.</span>
                <span>Confidential student health record — authorized access only.</span>
            </div>
        </section>

    </main>

    <script>
        const studentAlerts = <?= json_encode($studentAlerts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const studentSelect = document.getElementById('student_id');
        const alertSpan = document.getElementById('alert-allergy');
        const remarks = document.getElementById('remarks');
        const counter = document.getElementById('remarks-count');
        const form = document.querySelector('.consultation-form');

        function updateStudentAlert() {
            const val = studentSelect.value;
            if (!val || !studentAlerts[val]) {
                alertSpan.textContent = 'Select a student to view allergies and known conditions.';
                return;
            }
            alertSpan.textContent = 'Allergies: ' + studentAlerts[val].allergies + ' | Known Conditions / History: ' + studentAlerts[val].conditions;
        }

        function updateRemarksCount() {
            counter.textContent = remarks.value.length;
        }

        studentSelect.addEventListener('change', updateStudentAlert);
        remarks.addEventListener('input', updateRemarksCount);

        form.addEventListener('reset', function() {
            setTimeout(function() {
                updateRemarksCount();
                updateStudentAlert();
            }, 0);
        });

        updateStudentAlert();
        updateRemarksCount();
    </script>

</body>

</html>