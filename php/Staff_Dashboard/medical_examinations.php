<?php
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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
$username = $_SESSION['admin_username'] ?? 'Clinic Staff';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['examination_csrf'])) {
    $_SESSION['examination_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['examination_csrf'];
$success = trim((string)($_GET['success'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));
$editing = null;
$search = trim((string)($_GET['search'] ?? ''));
$filterDate = trim((string)($_GET['date'] ?? ''));
$filterYear = trim((string)($_GET['academic_year'] ?? ''));

$columns = $conn->query('SHOW COLUMNS FROM medical_examinations');
$hasAcademicYear = false;
if ($columns) {
    while ($column = $columns->fetch_assoc()) {
        if ($column['Field'] === 'academic_year') {
            $hasAcademicYear = true;
        }
    }
}
if (!$hasAcademicYear) {
    @$conn->query("ALTER TABLE medical_examinations ADD COLUMN academic_year VARCHAR(20) NULL AFTER examination_date");
    $hasAcademicYear = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        header('Location: medical_examinations.php?error=' . urlencode('Invalid form submission.'));
        exit();
    }

    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id && $id > 0) {
                $stmt = $conn->prepare('DELETE FROM medical_examinations WHERE id=?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
            header('Location: medical_examinations.php?success=' . urlencode('Examination record deleted.'));
            exit();
        }

        if ($action === 'save') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $id = $id === false ? 0 : (int)$id;
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            $examDate = trim((string)($_POST['examination_date'] ?? ''));
            $academicYear = trim((string)($_POST['academic_year'] ?? ''));
            $height = trim((string)($_POST['height_cm'] ?? ''));
            $weight = trim((string)($_POST['weight_kg'] ?? ''));
            $temperature = trim((string)($_POST['temperature'] ?? ''));
            $bloodPressure = trim((string)($_POST['blood_pressure'] ?? ''));
            $physical = trim((string)($_POST['physical_examination'] ?? ''));
            $assessment = trim((string)($_POST['assessment'] ?? ''));
            $recommendations = trim((string)($_POST['recommendations'] ?? ''));
            $allergies = trim((string)($_POST['allergies'] ?? ''));
            $medications = trim((string)($_POST['current_medications'] ?? ''));
            $history = trim((string)($_POST['medical_history'] ?? ''));

            $studentLookup = $conn->prepare('SELECT id, student_id_no FROM students WHERE id=? LIMIT 1');
            $studentLookup->bind_param('i', $studentId);
            $studentLookup->execute();
            $student = $studentLookup->get_result()->fetch_assoc();
            $studentLookup->close();

            if (!$studentId || !$student) {
                throw new InvalidArgumentException('Select a valid student.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate)) {
                throw new InvalidArgumentException('Enter a valid examination date.');
            }
            if ($academicYear === '' || mb_strlen($academicYear) > 20) {
                throw new InvalidArgumentException('Enter a valid academic year.');
            }
            if ($physical === '' || mb_strlen($physical) > 5000) {
                throw new InvalidArgumentException('Describe the physical examination conducted.');
            }

            foreach ([['Height', $height, 30, 250], ['Weight', $weight, 1, 500], ['Temperature', $temperature, 25, 45]] as [$label, $value, $min, $max]) {
                if ($value !== '' && (!is_numeric($value) || (float)$value < $min || (float)$value > $max)) {
                    throw new InvalidArgumentException($label . ' is outside the accepted range.');
                }
            }

            $studentIdNo = (string)$student['student_id_no'];
            $recordedBy = mb_substr($username, 0, 100);
            $measureHeight = $height === '' ? null : $height;
            $measureWeight = $weight === '' ? null : $weight;
            $measureTemperature = $temperature === '' ? null : $temperature;

            if ($id > 0) {
                $stmt = $conn->prepare(
                    'UPDATE medical_examinations SET student_id=?, student_id_no=?, examination_date=?, academic_year=?, height_cm=?, weight_kg=?, temperature=?, blood_pressure=?, allergies=?, current_medications=?, medical_history=?, physical_examination=?, assessment=?, recommendations=?, recorded_by=? WHERE id=?'
                );
                $stmt->bind_param(
                    'issssssssssssssi',
                    $studentId,
                    $studentIdNo,
                    $examDate,
                    $academicYear,
                    $measureHeight,
                    $measureWeight,
                    $measureTemperature,
                    $bloodPressure,
                    $allergies,
                    $medications,
                    $history,
                    $physical,
                    $assessment,
                    $recommendations,
                    $recordedBy,
                    $id
                );
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO medical_examinations (student_id, student_id_no, examination_date, academic_year, height_cm, weight_kg, temperature, blood_pressure, allergies, current_medications, medical_history, physical_examination, assessment, recommendations, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'issssssssssssss',
                    $studentId,
                    $studentIdNo,
                    $examDate,
                    $academicYear,
                    $measureHeight,
                    $measureWeight,
                    $measureTemperature,
                    $bloodPressure,
                    $allergies,
                    $medications,
                    $history,
                    $physical,
                    $assessment,
                    $recommendations,
                    $recordedBy
                );
            }
            $stmt->execute();
            $stmt->close();
            header('Location: medical_examinations.php?success=' . urlencode($id > 0 ? 'Examination record updated.' : 'Examination record added.'));
            exit();
        }
    } catch (Throwable $ex) {
        error_log('Medical examination save error: ' . $ex->getMessage());
        $message = $ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Unable to save the examination record.';
        header('Location: medical_examinations.php?error=' . urlencode($message));
        exit();
    }
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId && $editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM medical_examinations WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$students = [];
$studentResult = $conn->query('SELECT id, student_id_no, fullname, course_year FROM students ORDER BY fullname ASC');
while ($student = $studentResult->fetch_assoc()) {
    $students[] = $student;
}

$conditions = [];
$params = [];
$types = '';
if ($search !== '') {
    $conditions[] = '(me.student_id_no LIKE ? OR s.fullname LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= 'ss';
}
if ($filterDate !== '') {
    $conditions[] = 'me.examination_date=?';
    $params[] = $filterDate;
    $types .= 's';
}
if ($filterYear !== '') {
    $conditions[] = 'me.academic_year=?';
    $params[] = $filterYear;
    $types .= 's';
}
$sql = 'SELECT me.*, s.fullname, s.course_year FROM medical_examinations me LEFT JOIN students s ON s.id=me.student_id';
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY me.examination_date DESC, me.id DESC';
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$records = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Examinations - SHRCMS</title>
    <link rel="stylesheet" href="../../css/Shared/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/Staff/staff-dashboard.css?v=20261119-examinations">
    <link rel="stylesheet" href="../../css/Staff/consultation.css?v=20261114-icons">
</head>
<body class="staff-dashboard-body">
<aside class="staff-sidebar">
    <a href="staff_dashboard.php" class="staff-brand"><img src="../../image/logo2.png" alt="SHRCMS Logo"><div><h2>SHRCMS</h2><p>Clinic Management</p></div></a>
      <nav class="staff-nav">
            <a href="staff_dashboard.php"><span><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"></span> Dashboard</a>
            <a href="clinic_consultation.php"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Clinic Consultation</a>
            <a href="clinic_operations.php?view=passes"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Clinic Passes</a>
            <a href="clinic_operations.php?view=referrals"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Referrals</a>
            <a href="clinic_operations.php?view=incidents"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Incidents</a>
            <a href="health_documentation.php"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Health Documentation</a>
            <a href="medical_examinations.php" class="active"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medical Examinations</a>
            <a href="scan_medical_certificate.php"><span><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"></span> Verify Certificate</a>
            <a href="medicine_inventory.php" >
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
        <div><p class="page-eyebrow">STAFF PORTAL</p><h1>Medical Examinations</h1><p class="header-subtitle">Record school-required assessments actually conducted by the clinic.</p></div>
        <div class="staff-user"><img src="../../image/logo2.png" alt="Staff profile"><div><strong><?= e($username) ?></strong><span>Clinic Staff</span></div></div>
    </header>

    <?php if ($success !== ''): ?><div class="sp-alert sp-alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="sp-alert sp-alert-error"><?= e($error) ?></div><?php endif; ?>

    <section class="sp-panel" id="examination-form">
        <div class="sp-panel-heading"><div><h2><?= $editing ? 'Edit Examination Record' : 'Add Examination Record' ?></h2><p>Only record measurements and assessments completed by clinic personnel.</p></div><?php if ($editing): ?><a class="sp-button sp-button-light" href="medical_examinations.php">Cancel Edit</a><?php endif; ?></div>
        <form method="POST" action="medical_examinations.php" class="sp-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
            <div class="sp-form-grid">
                <div class="sp-field"><label for="student_id">Student <span>*</span></label><select id="student_id" name="student_id" required><option value="">Select student</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>" <?= ((int)($editing['student_id'] ?? 0) === (int)$student['id']) ? 'selected' : '' ?>><?= e($student['fullname']) ?> — <?= e($student['student_id_no']) ?></option><?php endforeach; ?></select></div>
                <div class="sp-field"><label for="examination_date">Examination Date <span>*</span></label><input id="examination_date" name="examination_date" type="date" required value="<?= e($editing['examination_date'] ?? date('Y-m-d')) ?>"></div>
                <div class="sp-field"><label for="academic_year">Academic Year <span>*</span></label><input id="academic_year" name="academic_year" maxlength="20" required placeholder="e.g. 2026-2027" value="<?= e($editing['academic_year'] ?? '') ?>"></div>
                <div class="sp-field"><label for="height_cm">Height (cm)</label><input id="height_cm" name="height_cm" type="number" min="30" max="250" step="0.01" value="<?= e($editing['height_cm'] ?? '') ?>" placeholder="If measured"></div>
                <div class="sp-field"><label for="weight_kg">Weight (kg)</label><input id="weight_kg" name="weight_kg" type="number" min="1" max="500" step="0.01" value="<?= e($editing['weight_kg'] ?? '') ?>" placeholder="If measured"></div>
                <div class="sp-field"><label for="temperature">Temperature (°C)</label><input id="temperature" name="temperature" type="number" min="25" max="45" step="0.1" value="<?= e($editing['temperature'] ?? '') ?>" placeholder="If measured"></div>
                <div class="sp-field"><label for="blood_pressure">Blood Pressure</label><input id="blood_pressure" name="blood_pressure" maxlength="20" value="<?= e($editing['blood_pressure'] ?? '') ?>" placeholder="If measured"></div>
                <div class="sp-field sp-field-wide"><label for="physical_examination">Physical Examination / Findings <span>*</span></label><textarea id="physical_examination" name="physical_examination" rows="4" maxlength="5000" required placeholder="Document only the assessment actually performed"><?= e($editing['physical_examination'] ?? '') ?></textarea></div>
                <div class="sp-field sp-field-wide"><label for="assessment">Health Assessment</label><textarea id="assessment" name="assessment" rows="3" maxlength="3000" placeholder="Clinic assessment summary"><?= e($editing['assessment'] ?? '') ?></textarea></div>
                <div class="sp-field sp-field-wide"><label for="recommendations">Recommendations / Follow-Up</label><textarea id="recommendations" name="recommendations" rows="3" maxlength="3000" placeholder="Relevant recommendations, if any"><?= e($editing['recommendations'] ?? '') ?></textarea></div>
                <div class="sp-field sp-field-wide"><label for="allergies">Known Allergies</label><textarea id="allergies" name="allergies" rows="2" maxlength="2000"><?= e($editing['allergies'] ?? '') ?></textarea></div>
                <div class="sp-field sp-field-wide"><label for="current_medications">Current Medications</label><textarea id="current_medications" name="current_medications" rows="2" maxlength="3000"><?= e($editing['current_medications'] ?? '') ?></textarea></div>
                <div class="sp-field sp-field-wide"><label for="medical_history">Relevant Medical History</label><textarea id="medical_history" name="medical_history" rows="3" maxlength="5000"><?= e($editing['medical_history'] ?? '') ?></textarea></div>
            </div>
            <div class="sp-form-actions"><button type="submit" class="sp-button sp-button-primary"><?= $editing ? 'Save Changes' : '+ Add Examination' ?></button><?php if (!$editing): ?><button type="reset" class="sp-button sp-button-light">Clear Form</button><?php endif; ?></div>
        </form>
    </section>

    <section class="sp-panel">
        <div class="sp-panel-heading sp-list-heading">
            <div><h2>Examination History</h2><p>Filter records by student, date, or academic year.</p></div>
            <form class="sp-search" method="GET" action="medical_examinations.php">
                <input type="search" name="search" value="<?= e($search) ?>" placeholder="Student ID or name">
                <input type="date" name="date" value="<?= e($filterDate) ?>" aria-label="Examination date">
                <input type="text" name="academic_year" value="<?= e($filterYear) ?>" placeholder="Academic year">
                <button type="submit" class="sp-button sp-button-primary">Filter</button>
                <?php if ($search !== '' || $filterDate !== '' || $filterYear !== ''): ?><a href="medical_examinations.php" class="sp-button sp-button-light">Clear</a><?php endif; ?>
            </form>
        </div>
        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead><tr><th>Date</th><th>Academic Year</th><th>Student</th><th>Measurements</th><th>Physical Examination / Findings</th><th>Assessment</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ($records->num_rows > 0): ?>
                    <?php while ($record = $records->fetch_assoc()):
                        $measurements = [];
                        if ($record['height_cm'] !== null) $measurements[] = 'Height: ' . e($record['height_cm']) . ' cm';
                        if ($record['weight_kg'] !== null) $measurements[] = 'Weight: ' . e($record['weight_kg']) . ' kg';
                        if ($record['temperature'] !== null) $measurements[] = 'Temp: ' . e($record['temperature']) . ' °C';
                        if ($record['blood_pressure'] !== null && $record['blood_pressure'] !== '') $measurements[] = 'BP: ' . e($record['blood_pressure']);
                    ?>
                    <tr>
                        <td><?= e(date('M d, Y', strtotime($record['examination_date']))) ?></td>
                        <td><?= e($record['academic_year'] ?: '—') ?></td>
                        <td><strong><?= e($record['fullname'] ?: $record['student_id_no']) ?></strong><br><small><?= e($record['student_id_no']) ?></small></td>
                        <td><?= $measurements ? implode('<br>', $measurements) : 'Not recorded' ?></td>
                        <td><?= nl2br(e($record['physical_examination'])) ?></td>
                        <td><?= e($record['assessment'] ?: '—') ?></td>
                        <td><div class="sp-row-actions"><a class="sp-edit" href="medical_examinations.php?edit=<?= (int)$record['id'] ?>#examination-form">Edit</a><form method="POST" action="medical_examinations.php" onsubmit="return confirm('Delete this examination record?');"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$record['id'] ?>"><button type="submit" class="sp-delete">Delete</button></form></div></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="sp-empty">No examination records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>
