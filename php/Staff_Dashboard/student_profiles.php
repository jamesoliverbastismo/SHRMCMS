<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
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
$username = $_SESSION['admin_username'] ?? 'Clinic Staff';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['student_profile_csrf'])) {
    $_SESSION['student_profile_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['student_profile_csrf'];
$successMsg = '';
$errorMsg = '';
$search = trim((string)($_GET['search'] ?? ''));
$editing = null;

function ensureStudentProfileSchema(mysqli $conn): void
{
    $columns = [];
    $result = $conn->query('SHOW COLUMNS FROM students');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }

    $definitions = [
        'medical_history' => 'TEXT NULL',
        'current_medications' => 'TEXT NULL',
        'immunization_information' => 'TEXT NULL',
        'emergency_contact_name' => 'VARCHAR(100) NULL',
        'emergency_contact_relationship' => 'VARCHAR(50) NULL',
        'emergency_contact_number' => 'VARCHAR(20) NULL',
    ];
    foreach ($definitions as $column => $definition) {
        if (!isset($columns[$column])) {
            @$conn->query("ALTER TABLE students ADD COLUMN `{$column}` {$definition}");
        }
    }

    $conn->query(
        'CREATE TABLE IF NOT EXISTS student_profile_history (
            id INT NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            student_id_no VARCHAR(50) NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            changed_by VARCHAR(100) DEFAULT NULL,
            change_note VARCHAR(255) DEFAULT NULL,
            action_type VARCHAR(20) NOT NULL DEFAULT "updated",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_profile_history_student (student_id),
            INDEX idx_profile_history_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $conn->query(
        'CREATE TABLE IF NOT EXISTS student_profile_documents (
            id INT NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            uploaded_by VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_profile_documents_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

ensureStudentProfileSchema($conn);

function uploadStudentId(array $file, string &$error): ?string
{
    $error = '';
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        $error = 'Failed to upload the student ID file.';
        return null;
    }
    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'ID file must be 5 MB or smaller.';
        return null;
    }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
        $error = 'Only JPG, JPEG, PNG, or PDF files are allowed.';
        return null;
    }
    $targetDir = __DIR__ . '/../../uploads/student_ids';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        $error = 'Unable to create the student ID upload folder.';
        return null;
    }
    $fileName = 'sid_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $fileName)) {
        $error = 'Unable to store the uploaded ID file.';
        return null;
    }
    return 'uploads/student_ids/' . $fileName;
}

function uploadSupportingDocument(array $file, string &$error): ?string
{
    $error = '';
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        $error = 'Failed to upload the supporting document.';
        return null;
    }
    if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) {
        $error = 'Supporting document must be 10 MB or smaller.';
        return null;
    }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'], true)) {
        $error = 'Supporting document must be JPG, PNG, PDF, DOC, or DOCX.';
        return null;
    }
    $targetDir = __DIR__ . '/../../uploads/student_documents';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        $error = 'Unable to create the supporting document folder.';
        return null;
    }
    $fileName = 'sdoc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $fileName)) {
        $error = 'Unable to store the supporting document.';
        return null;
    }
    return 'uploads/student_documents/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $token)) {
        $errorMsg = 'Invalid form submission. Please refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $id = $id === false ? 0 : (int)$id;
            $studentIdNo = trim((string)($_POST['student_id_no'] ?? ''));
            $fullname = trim((string)($_POST['fullname'] ?? ''));
            $age = trim((string)($_POST['age'] ?? ''));
            $gender = trim((string)($_POST['gender'] ?? ''));
            $courseYear = trim((string)($_POST['course_year'] ?? ''));
            $contact = trim((string)($_POST['contact'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $allergies = trim((string)($_POST['allergies'] ?? ''));
            $knownConditions = trim((string)($_POST['known_conditions'] ?? ''));
            $medicalHistory = trim((string)($_POST['medical_history'] ?? ''));
            $currentMedications = trim((string)($_POST['current_medications'] ?? ''));
            $immunizationInformation = trim((string)($_POST['immunization_information'] ?? ''));
            $emergencyContactName = trim((string)($_POST['emergency_contact_name'] ?? ''));
            $emergencyContactRelationship = trim((string)($_POST['emergency_contact_relationship'] ?? ''));
            $emergencyContactNumber = trim((string)($_POST['emergency_contact_number'] ?? ''));
            $changeNote = trim((string)($_POST['change_note'] ?? ''));
            $ageValue = $age === '' ? null : filter_var($age, FILTER_VALIDATE_INT);

            if ($id < 0 || $studentIdNo === '' || $fullname === '') {
                $errorMsg = 'Student ID number and full name are required.';
            } elseif (strlen($studentIdNo) > 50 || strlen($fullname) > 100) {
                $errorMsg = 'Student ID number or full name exceeds the allowed length.';
            } elseif ($age !== '' && ($ageValue === false || $ageValue < 1 || $ageValue > 150)) {
                $errorMsg = 'Please enter a valid age.';
            } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100)) {
                $errorMsg = 'Please enter a valid email address.';
            } else {
                $uploadError = '';
                $uploadedDoc = uploadStudentId($_FILES['id_document'] ?? [], $uploadError);
                if ($uploadError !== '') {
                    $errorMsg = $uploadError;
                } else {
                    $supportUploadError = '';
                    $supportingDoc = uploadSupportingDocument($_FILES['supporting_document'] ?? [], $supportUploadError);
                    if ($supportUploadError !== '') {
                        $errorMsg = $supportUploadError;
                    } else {
                        $checkSql = $id > 0
                            ? 'SELECT id FROM students WHERE (student_id_no = ? OR (email IS NOT NULL AND email = ?)) AND id <> ? LIMIT 1'
                            : 'SELECT id FROM students WHERE student_id_no = ? OR (email IS NOT NULL AND email = ?) LIMIT 1';
                        $check = $conn->prepare($checkSql);
                        if ($id > 0) {
                            $check->bind_param('ssi', $studentIdNo, $email, $id);
                        } else {
                            $check->bind_param('ss', $studentIdNo, $email);
                        }
                        $check->execute();
                        $duplicate = $check->get_result()->fetch_assoc();
                        $check->close();

                        if ($duplicate) {
                            $errorMsg = 'This student ID or email is already registered.';
                        } else {
                            if ($id > 0) {
                                $oldStmt = $conn->prepare('SELECT * FROM students WHERE id=? LIMIT 1');
                                $oldStmt->bind_param('i', $id);
                                $oldStmt->execute();
                                $oldStudent = $oldStmt->get_result()->fetch_assoc();
                                $oldStmt->close();
                                if (!$oldStudent) {
                                    $errorMsg = 'Student profile not found.';
                                } else {
                                    $stmt = $uploadedDoc === null
                                        ? $conn->prepare('UPDATE students SET student_id_no=?, fullname=?, age=?, gender=?, course_year=?, contact=?, email=?, allergies=?, known_conditions=?, medical_history=?, current_medications=?, immunization_information=?, emergency_contact_name=?, emergency_contact_relationship=?, emergency_contact_number=? WHERE id=?')
                                        : $conn->prepare('UPDATE students SET student_id_no=?, fullname=?, age=?, gender=?, course_year=?, contact=?, email=?, allergies=?, known_conditions=?, medical_history=?, current_medications=?, immunization_information=?, emergency_contact_name=?, emergency_contact_relationship=?, emergency_contact_number=?, id_document=? WHERE id=?');
                                    if ($uploadedDoc === null) {
                                        $stmt->bind_param('ssissssssssssssi', $studentIdNo, $fullname, $ageValue, $gender, $courseYear, $contact, $email, $allergies, $knownConditions, $medicalHistory, $currentMedications, $immunizationInformation, $emergencyContactName, $emergencyContactRelationship, $emergencyContactNumber, $id);
                                    } else {
                                        $stmt->bind_param('ssissssssssssssis', $studentIdNo, $fullname, $ageValue, $gender, $courseYear, $contact, $email, $allergies, $knownConditions, $medicalHistory, $currentMedications, $immunizationInformation, $emergencyContactName, $emergencyContactRelationship, $emergencyContactNumber, $uploadedDoc, $id);
                                    }
                                }
                            } else {
                                $stmt = $conn->prepare('INSERT INTO students (student_id_no, fullname, age, gender, course_year, contact, email, allergies, known_conditions, medical_history, current_medications, immunization_information, emergency_contact_name, emergency_contact_relationship, emergency_contact_number, id_document) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                                $stmt->bind_param('ssisssssssssssss', $studentIdNo, $fullname, $ageValue, $gender, $courseYear, $contact, $email, $allergies, $knownConditions, $medicalHistory, $currentMedications, $immunizationInformation, $emergencyContactName, $emergencyContactRelationship, $emergencyContactNumber, $uploadedDoc);
                            }

                            if ($errorMsg === '' && $stmt->execute()) {
                                $savedId = $id > 0 ? $id : (int)$conn->insert_id;
                                $sync = $conn->prepare('UPDATE users SET name=?, email=?, student_id_no=? WHERE id=(SELECT user_id FROM students WHERE id=? LIMIT 1)');
                                $sync->bind_param('sssi', $fullname, $email, $studentIdNo, $savedId);
                                $sync->execute();
                                $sync->close();

                                $snapshot = $id > 0 ? $oldStudent : [];
                                $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
                                $history = $conn->prepare('INSERT INTO student_profile_history (student_id, student_id_no, snapshot_json, changed_by, change_note, action_type) VALUES (?, ?, ?, ?, ?, ?)');
                                $historyAction = $id > 0 ? 'updated' : 'created';
                                $changedBy = (string)($_SESSION['admin_username'] ?? 'Clinic Staff');
                                $history->bind_param('isssss', $savedId, $studentIdNo, $snapshotJson, $changedBy, $changeNote, $historyAction);
                                $history->execute();
                                $history->close();

                                if ($supportingDoc !== null) {
                                    $documentName = basename((string)($_FILES['supporting_document']['name'] ?? 'Supporting document'));
                                    $documentStmt = $conn->prepare('INSERT INTO student_profile_documents (student_id, document_name, file_path, uploaded_by) VALUES (?, ?, ?, ?)');
                                    $documentStmt->bind_param('isss', $savedId, $documentName, $supportingDoc, $changedBy);
                                    $documentStmt->execute();
                                    $documentStmt->close();
                                }

                                $successMsg = $id > 0 ? 'Student profile updated successfully. A history snapshot was preserved.' : 'Student profile added successfully.';
                                rbacLog($conn, $id > 0 ? 'update' : 'create', 'Student Profiles', 'Student', (string)$savedId, 'Student profile record saved');
                            } elseif ($errorMsg === '') {
                                $errorMsg = 'Unable to save the student profile.';
                            }
                            if (isset($stmt)) {
                                $stmt->close();
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                $errorMsg = 'Invalid student profile.';
            } else {
                $find = $conn->prepare('SELECT user_id, id_document FROM students WHERE id=? LIMIT 1');
                $find->bind_param('i', $id);
                $find->execute();
                $studentRow = $find->get_result()->fetch_assoc();
                $find->close();
                if (!$studentRow) {
                    $errorMsg = 'Student profile not found.';
                } else {
                    $userId = (int)($studentRow['user_id'] ?? 0);
                    if ($userId > 0) {
                        $unlink = $conn->prepare('UPDATE users SET student_id_no=NULL WHERE id=?');
                        $unlink->bind_param('i', $userId);
                        $unlink->execute();
                        $unlink->close();
                    }
                    $delete = $conn->prepare('DELETE FROM students WHERE id=?');
                    $delete->bind_param('i', $id);
                    if ($delete->execute() && $delete->affected_rows > 0) {
                        $successMsg = 'Student profile deleted successfully.';
                        rbacLog($conn, 'delete', 'Student Profiles', 'Student', (string)$id, 'Student profile record deleted');
                    } else {
                        $errorMsg = 'Unable to delete the student profile.';
                    }
                    $delete->close();
                }
            }
        }
    }
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
if ($editId !== false && (int)$editId > 0) {
    $editStmt = $conn->prepare('SELECT * FROM students WHERE id=? LIMIT 1');
    $editStmt->bind_param('i', $editId);
    $editStmt->execute();
    $editing = $editStmt->get_result()->fetch_assoc();
    $editStmt->close();
}

$profileHistory = [];
$profileDocuments = [];
if ($editing) {
    $historyStmt = $conn->prepare('SELECT snapshot_json, changed_by, change_note, action_type, created_at FROM student_profile_history WHERE student_id=? ORDER BY created_at DESC, id DESC LIMIT 20');
    $historyStudentId = (int)$editing['id'];
    $historyStmt->bind_param('i', $historyStudentId);
    $historyStmt->execute();
    $profileHistory = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $historyStmt->close();

    $documentStmt = $conn->prepare('SELECT id, document_name, file_path, uploaded_by, created_at FROM student_profile_documents WHERE student_id=? ORDER BY created_at DESC, id DESC');
    $documentStmt->bind_param('i', $historyStudentId);
    $documentStmt->execute();
    $profileDocuments = $documentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $documentStmt->close();
}

$sql = 'SELECT id, student_id_no, fullname, age, gender, course_year, contact, email, allergies, known_conditions, id_document, created_at FROM students';
$params = [];
$types = '';
if ($search !== '') {
    $sql .= ' WHERE student_id_no LIKE ? OR fullname LIKE ? OR course_year LIKE ? OR contact LIKE ? OR email LIKE ?';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term, $term];
    $types = 'sssss';
}
$sql .= ' ORDER BY fullname ASC';
$listStmt = $conn->prepare($sql);
if ($types !== '') {
    $listStmt->bind_param($types, ...$params);
}
$listStmt->execute();
$studentResult = $listStmt->get_result();
$countResult = $conn->query('SELECT COUNT(*) AS total FROM students');
$totalStudents = (int)($countResult->fetch_assoc()['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profiles - SHRCMS</title>
    <link rel="stylesheet" href="../../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/staff-dashboard.css?v=20261119-student-profiles">
</head>
<body class="staff-dashboard-body">
    <aside class="staff-sidebar">
        <a href="staff_dashboard.php" class="staff-brand">
            <img src="../../image/logo2.png" alt="SHRCMS Logo">
            <div><h2>SHRCMS</h2><p>Clinic Management</p></div>
        </a>
        <p class="sidebar-label">MAIN MENU</p>
        <nav class="staff-nav">
            <a href="staff_dashboard.php"><span><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"></span> Dashboard</a>
            <a href="clinic_consultation.php"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Clinic Consultation</a>
            <a href="student_profiles.php" class="active"><span><img class="ui-icon" src="../../image/icon-student.svg" alt="" aria-hidden="true"></span> Student Profiles</a>
            <a href="clinic_operations.php?view=passes"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Clinic Passes</a>
            <a href="clinic_operations.php?view=referrals"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Referrals</a>
            <a href="clinic_operations.php?view=incidents"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Incidents</a>
            <a href="health_documentation.php"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Health Documentation</a>
            <a href="medical_examinations.php"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medical Examinations</a>
            <a href="scan_medical_certificate.php"><span><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"></span> Verify Certificate</a>
            <a href="medicine_inventory.php"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medicine Inventory</a>
            <p class="sidebar-label">REPORTS</p>
            <a href="clinic_reports.php"><span><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"></span> Clinic Reports</a>
            <a href="../logout.php" class="logout-link"><span><img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"></span> Logout</a>
        </nav>
        <div class="sidebar-bottom"><p>Student Health Record</p><small>Clinic Management System</small></div>
    </aside>

    <main class="staff-main">
        <header class="staff-header">
            <div>
                <p class="page-eyebrow">STAFF PORTAL</p>
                <h1>Student Profiles</h1>
                <p class="header-subtitle">View and maintain student identity and health profile information.</p>
            </div>
            <div class="staff-user">
                <img src="../../image/logo2.png" alt="Staff profile">
                <div><strong><?= e($username) ?></strong><span>Clinic Staff</span></div>
            </div>
        </header>

        <section class="sp-summary">
            <div class="module-icon blue"><img class="ui-icon" src="../../image/icon-student.svg" alt="" aria-hidden="true"></div>
            <div><p>Total Registered Students</p><h2><?= number_format($totalStudents) ?></h2></div>
        </section>

        <?php if ($successMsg !== ''): ?><div class="sp-alert sp-alert-success"><?= e($successMsg) ?></div><?php endif; ?>
        <?php if ($errorMsg !== ''): ?><div class="sp-alert sp-alert-error"><?= e($errorMsg) ?></div><?php endif; ?>

        <section class="sp-panel" id="student-profile-form">
            <div class="sp-panel-heading">
                <div><h2><?= $editing ? 'Edit Student Profile' : 'Add Student Profile' ?></h2><p><?= $editing ? 'Update the selected student record.' : 'Register a student in the clinic management system.' ?></p></div>
                <?php if ($editing): ?><a class="sp-button sp-button-light" href="student_profiles.php">Cancel Edit</a><?php endif; ?>
            </div>
            <form method="POST" action="student_profiles.php" enctype="multipart/form-data" class="sp-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
                <div class="sp-form-grid">
                    <div class="sp-field"><label for="student_id_no">Student ID Number <span>*</span></label><input id="student_id_no" name="student_id_no" maxlength="50" required value="<?= e($editing['student_id_no'] ?? '') ?>" placeholder="e.g. SCC-22-00022156"></div>
                    <div class="sp-field"><label for="fullname">Full Name <span>*</span></label><input id="fullname" name="fullname" maxlength="100" required value="<?= e($editing['fullname'] ?? '') ?>" placeholder="Student full name"></div>
                    <div class="sp-field"><label for="age">Age</label><input id="age" name="age" type="number" min="1" max="150" value="<?= e($editing['age'] ?? '') ?>"></div>
                    <div class="sp-field"><label for="gender">Gender</label><select id="gender" name="gender"><option value="">Select gender</option><?php foreach (['Female', 'Male', 'Other'] as $gender): ?><option value="<?= e($gender) ?>" <?= (($editing['gender'] ?? '') === $gender) ? 'selected' : '' ?>><?= e($gender) ?></option><?php endforeach; ?></select></div>
                    <div class="sp-field"><label for="course_year">Course / Year / Grade</label><input id="course_year" name="course_year" maxlength="50" value="<?= e($editing['course_year'] ?? '') ?>" placeholder="e.g. BSIT - 2nd Year"></div>
                    <div class="sp-field"><label for="contact">Contact Number</label><input id="contact" name="contact" maxlength="20" value="<?= e($editing['contact'] ?? '') ?>" placeholder="e.g. 09123456789"></div>
                    <div class="sp-field"><label for="email">Email Address</label><input id="email" name="email" type="email" maxlength="100" value="<?= e($editing['email'] ?? '') ?>" placeholder="student@example.com"></div>
                    <div class="sp-field"><label for="id_document">ID Document (JPG, PNG, PDF)</label><input id="id_document" name="id_document" type="file" accept=".jpg,.jpeg,.png,.pdf"><?php if (!empty($editing['id_document'])): ?><small><a href="../../<?= e($editing['id_document']) ?>" target="_blank">View current ID file</a></small><?php endif; ?></div>
                    <div class="sp-field sp-field-wide"><label for="allergies">Allergies</label><textarea id="allergies" name="allergies" rows="3" maxlength="2000" placeholder="Known allergies or 'None'"><?= e($editing['allergies'] ?? '') ?></textarea></div>
                    <div class="sp-field sp-field-wide"><label for="known_conditions">Known Medical Conditions</label><textarea id="known_conditions" name="known_conditions" rows="3" maxlength="3000" placeholder="Known conditions or 'None'"><?= e($editing['known_conditions'] ?? '') ?></textarea></div>
                    <div class="sp-field sp-field-wide"><label for="medical_history">Relevant Medical History</label><textarea id="medical_history" name="medical_history" rows="4" maxlength="5000" placeholder="Past diagnoses, surgeries, ongoing concerns..."><?= e($editing['medical_history'] ?? '') ?></textarea></div>
                    <div class="sp-field sp-field-wide"><label for="current_medications">Current Medications</label><textarea id="current_medications" name="current_medications" rows="3" maxlength="3000" placeholder="Medication name, dose, and frequency"><?= e($editing['current_medications'] ?? '') ?></textarea></div>
                    <div class="sp-field sp-field-wide"><label for="immunization_information">Immunization Information</label><textarea id="immunization_information" name="immunization_information" rows="3" maxlength="3000" placeholder="Vaccines, dates, or source of records"><?= e($editing['immunization_information'] ?? '') ?></textarea></div>
                    <div class="sp-field"><label for="emergency_contact_name">Emergency Contact Name</label><input id="emergency_contact_name" name="emergency_contact_name" maxlength="100" value="<?= e($editing['emergency_contact_name'] ?? '') ?>" placeholder="Contact full name"></div>
                    <div class="sp-field"><label for="emergency_contact_relationship">Relationship</label><input id="emergency_contact_relationship" name="emergency_contact_relationship" maxlength="50" value="<?= e($editing['emergency_contact_relationship'] ?? '') ?>" placeholder="e.g. Parent, Guardian"></div>
                    <div class="sp-field"><label for="emergency_contact_number">Emergency Contact Number</label><input id="emergency_contact_number" name="emergency_contact_number" maxlength="20" value="<?= e($editing['emergency_contact_number'] ?? '') ?>" placeholder="e.g. 09123456789"></div>
                    <div class="sp-field sp-field-wide"><label for="supporting_document">Supporting Document (JPG, PNG, PDF, DOC, DOCX)</label><input id="supporting_document" name="supporting_document" type="file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"></div>
                    <div class="sp-field sp-field-wide"><label for="change_note">Update Note</label><input id="change_note" name="change_note" maxlength="255" value="" placeholder="Reason or summary of this authorized update"></div>
                </div>
                <div class="sp-form-actions"><button type="submit" class="sp-button sp-button-primary"><?= $editing ? 'Save Changes' : '+ Add Student Profile' ?></button><?php if (!$editing): ?><button type="reset" class="sp-button sp-button-light">Clear Form</button><?php endif; ?></div>
            </form>
        </section>

        <?php if ($editing): ?>
        <section class="sp-panel">
            <div class="sp-panel-heading"><div><h2>Authorized Supporting Documents</h2><p>Documents uploaded with this student profile.</p></div></div>
            <div class="sp-history">
                <?php if ($profileDocuments): ?>
                    <?php foreach ($profileDocuments as $document): ?>
                        <div class="sp-history-item">
                            <div>
                                <strong><?= e($document['document_name']) ?></strong>
                                <p>Uploaded by <?= e($document['uploaded_by'] ?: 'Clinic Staff') ?> · <?= e(date('M d, Y h:i A', strtotime($document['created_at']))) ?></p>
                            </div>
                            <a class="sp-button sp-button-light" href="../../<?= e($document['file_path']) ?>" target="_blank">View Document</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="sp-empty">No supporting documents uploaded.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="sp-panel">
            <div class="sp-panel-heading"><div><h2>Profile Update History</h2><p>Previous profile snapshots are retained after authorized changes.</p></div></div>
            <div class="sp-history">
                <?php if ($profileHistory): ?>
                    <?php foreach ($profileHistory as $history): ?>
                        <div class="sp-history-item">
                            <div>
                                <strong><?= ucfirst(e($history['action_type'])) ?></strong>
                                <p><?= e($history['change_note'] ?: 'No update note provided') ?></p>
                                <small>By <?= e($history['changed_by'] ?: 'Clinic Staff') ?> · <?= e(date('M d, Y h:i A', strtotime($history['created_at']))) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="sp-empty">No update history available.</p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="sp-panel">
            <div class="sp-panel-heading sp-list-heading">
                <div><h2>Student Profile List</h2><p>Search and manage registered student profiles.</p></div>
                <form class="sp-search" method="GET" action="student_profiles.php">
                    <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search ID, name, course, contact..." aria-label="Search student profiles">
                    <button type="submit" class="sp-button sp-button-primary">Search</button>
                    <?php if ($search !== ''): ?><a href="student_profiles.php" class="sp-button sp-button-light">Clear</a><?php endif; ?>
                </form>
            </div>
            <div class="sp-table-wrap">
                <table class="sp-table">
                    <thead><tr><th>Student ID</th><th>Full Name</th><th>Course / Year</th><th>Contact</th><th>Email</th><th>ID File</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($studentResult->num_rows > 0): ?>
                        <?php while ($student = $studentResult->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= e($student['student_id_no']) ?></strong></td>
                                <td><?= e($student['fullname']) ?></td>
                                <td><?= e($student['course_year'] ?: '—') ?></td>
                                <td><?= e($student['contact'] ?: '—') ?></td>
                                <td><?= e($student['email'] ?: '—') ?></td>
                                <td><?php if (!empty($student['id_document'])): ?><a href="../../<?= e($student['id_document']) ?>" target="_blank">View File</a><?php else: ?>—<?php endif; ?></td>
                                <td><div class="sp-row-actions"><a class="sp-edit" href="student_profiles.php?edit=<?= (int)$student['id'] ?>#student-profile-form">Edit</a><form method="POST" action="student_profiles.php" onsubmit="return confirm('Delete this student profile? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$student['id'] ?>"><button type="submit" class="sp-delete">Delete</button></form></div></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="sp-empty">No student profiles found<?= $search !== '' ? ' for this search.' : '.' ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
<?php $listStmt->close(); $conn->close(); ?>
