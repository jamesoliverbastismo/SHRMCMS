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
    !in_array($_SESSION['user_role'], ['Staff', 'Administrator'], true)
) {
    header('Location: ../login.php');
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirectOps(string $msg, string $type = 'success'): void
{
    header('Location: clinic_operations.php?message=' . urlencode($msg) . '&type=' . urlencode($type));
    exit();
}

if (empty($_SESSION['ops_csrf'])) {
    $_SESSION['ops_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['ops_csrf'];
$staffId = (int)($_SESSION['staff_id'] ?? $_SESSION['user_id'] ?? 0);
$staffName = $_SESSION['admin_username'] ?? 'Clinic Staff';

$message = trim((string)($_GET['message'] ?? ''));
$messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
        redirectOps('Invalid session token. Refresh and try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    try {
        // 1. CLINIC PASS ISSUE & RETURN-TO-CLASS
        if ($action === 'issue_pass') {
            $studentNo = trim((string)($_POST['student_id_no'] ?? ''));
            $passDate = trim((string)($_POST['pass_date'] ?? ''));
            $passStatus = trim((string)($_POST['pass_status'] ?? 'Issued'));
            $authStatus = trim((string)($_POST['authorization_status'] ?? 'Authorized'));

            if ($studentNo === '' || $passDate === '') {
                redirectOps('Student ID and pass time are required.', 'error');
            }

            $stmt = $conn->prepare(
                "INSERT INTO clinic_passes
                 (student_id_no, pass_date, pass_status, authorization_status, return_status, issued_by_staff_id)
                 VALUES (?, ?, ?, ?, 'Pending', ?)"
            );
            $stmt->bind_param('ssssi', $studentNo, $passDate, $passStatus, $authStatus, $staffId);
            $stmt->execute();
            $stmt->close();

            redirectOps('Clinic pass issued for attendance and return-to-class tracking.');
        }

        if ($action === 'mark_returned') {
            $passId = filter_var($_POST['pass_id'] ?? null, FILTER_VALIDATE_INT);
            if ($passId && $passId > 0) {
                $stmt = $conn->prepare(
                    "UPDATE clinic_passes
                     SET return_status = 'Returned', pass_status = 'Completed', returned_at = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param('i', $passId);
                $stmt->execute();
                $stmt->close();
            }
            redirectOps('Return-to-class time recorded.');
        }

        // 2. REFERRAL CREATION & FOLLOW-UP
        if ($action === 'add_referral') {
            $studentNo = trim((string)($_POST['student_id_no'] ?? ''));
            $refDate = trim((string)($_POST['referral_date'] ?? ''));
            $destination = trim((string)($_POST['destination'] ?? ''));
            $reason = trim((string)($_POST['reason'] ?? ''));
            $reqFollowUp = isset($_POST['action_required']) ? 1 : 0;

            if ($studentNo === '' || $refDate === '' || $destination === '' || $reason === '') {
                redirectOps('All referral fields are required.', 'error');
            }

            $status = $reqFollowUp ? 'Pending Follow-Up' : 'No Further Action Needed';
            $stmt = $conn->prepare(
                "INSERT INTO clinic_referrals
                 (student_id_no, referral_date, reason, destination, follow_up_status, action_required, recorded_by_staff_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssssii', $studentNo, $refDate, $reason, $destination, $status, $reqFollowUp, $staffId);
            $stmt->execute();
            $stmt->close();

            redirectOps('Referral record saved.');
        }

        if ($action === 'update_referral') {
            $refId = filter_var($_POST['ref_id'] ?? null, FILTER_VALIDATE_INT);
            $status = trim((string)($_POST['follow_up_status'] ?? 'Follow-Up Logged'));
            $remarks = trim((string)($_POST['follow_up_remarks'] ?? ''));
            $reqFollowUp = ($status === 'Pending Follow-Up') ? 1 : 0;

            if ($refId && $refId > 0) {
                $stmt = $conn->prepare(
                    "UPDATE clinic_referrals
                     SET follow_up_status = ?, action_required = ?, follow_up_remarks = ?
                     WHERE id = ?"
                );
                $stmt->bind_param('sisi', $status, $reqFollowUp, $remarks, $refId);
                $stmt->execute();
                $stmt->close();
            }
            redirectOps('Referral follow-up status updated.');
        }

        // 3. INCIDENT & FIRST-AID DOCUMENTATION
        if ($action === 'add_incident') {
            $studentNo = trim((string)($_POST['student_id_no'] ?? ''));
            $incDate = trim((string)($_POST['incident_datetime'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $circumstances = trim((string)($_POST['circumstances'] ?? ''));
            $firstAid = trim((string)($_POST['first_aid_action'] ?? ''));
            $refNeeded = isset($_POST['referral_needed']) ? 1 : 0;
            $status = trim((string)($_POST['follow_up_status'] ?? 'Open'));

            if ($studentNo === '' || $incDate === '' || $location === '' || $circumstances === '' || $firstAid === '') {
                redirectOps('All incident and first-aid fields are required.', 'error');
            }

            $stmt = $conn->prepare(
                "INSERT INTO incident_records
                 (student_id_no, incident_datetime, location, circumstances, first_aid_action, referral_needed, follow_up_status, recorded_by_staff_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssssisi', $studentNo, $incDate, $location, $circumstances, $firstAid, $refNeeded, $status, $staffId);
            $stmt->execute();
            $stmt->close();

            redirectOps('Incident and first-aid record saved.');
        }

        if ($action === 'update_incident') {
            $incId = filter_var($_POST['inc_id'] ?? null, FILTER_VALIDATE_INT);
            $status = trim((string)($_POST['follow_up_status'] ?? 'Resolved'));
            $outcome = trim((string)($_POST['outcome_notes'] ?? ''));

            if ($incId && $incId > 0) {
                $stmt = $conn->prepare(
                    "UPDATE incident_records SET follow_up_status = ?, outcome_notes = ? WHERE id = ?"
                );
                $stmt->bind_param('ssi', $status, $outcome, $incId);
                $stmt->execute();
                $stmt->close();
            }
            redirectOps('Incident follow-up outcome updated.');
        }
    } catch (Throwable $ex) {
        error_log('Clinic operations error: ' . $ex->getMessage());
        redirectOps('Unable to save operation record. Check that the required database tables exist.', 'error');
    }
}

$passes = [];
$referrals = [];
$incidents = [];

try {
    $pRes = $conn->query(
        "SELECT p.*, s.fullname, s.course_year
         FROM clinic_passes p
         LEFT JOIN students s ON s.student_id_no = p.student_id_no
         ORDER BY p.pass_date DESC, p.id DESC LIMIT 30"
    );
    $passes = $pRes->fetch_all(MYSQLI_ASSOC);

    $rRes = $conn->query(
        "SELECT r.*, s.fullname, s.course_year
         FROM clinic_referrals r
         LEFT JOIN students s ON s.student_id_no = r.student_id_no
         ORDER BY r.referral_date DESC, r.id DESC LIMIT 30"
    );
    $referrals = $rRes->fetch_all(MYSQLI_ASSOC);

    $iRes = $conn->query(
        "SELECT i.*, s.fullname, s.course_year
         FROM incident_records i
         LEFT JOIN students s ON s.student_id_no = i.student_id_no
         ORDER BY i.incident_datetime DESC, i.id DESC LIMIT 30"
    );
    $incidents = $iRes->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $ex) {
    error_log('Clinic operations fetch error: ' . $ex->getMessage());
    $message = $message ?: 'Some operational tables are not yet initialized in the database.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passes, Referrals &amp; Incidents - SHRCMS</title>
    <link rel="stylesheet" href="../../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/staff-dashboard.css?v=20261115-staff-admin-alignment">
    <link rel="stylesheet" href="../../css/consultation.css?v=20261114-icons">
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
            <a href="clinic_operations.php" class="active"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Passes, Referrals &amp; Incidents</a>
            <a href="health_documentation.php"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Health Documentation</a>
            <a href="scan_medical_certificate.php"><span><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"></span> Verify Certificate</a>
            <a href="medicine_inventory.php"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medicine Inventory</a>
            <p class="sidebar-label">REPORTS</p>
            <a href="clinic_reports.php"><span><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"></span> Clinic Reports</a>
            <a href="../logout.php" class="logout-link"><span><img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"></span> Logout</a>
        </nav>
    </aside>

    <main class="staff-main">
        <header class="staff-header">
            <div>
                <p class="page-eyebrow">CLINIC OPERATIONS</p>
                <h1>Clinic Passes, Referrals &amp; First-Aid Incidents</h1>
                <p class="header-subtitle">Manage student clinic passes/return-to-class tracking, referrals, and campus first-aid incident logs.</p>
            </div>
            <div class="staff-user">
                <img src="../../image/logo2.png" alt="Staff profile">
                <div><strong><?= e($staffName) ?></strong><span>Clinic Staff</span></div>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="consultation-message <?= $messageType === 'error' ? 'error-message' : 'success-message' ?>">
                <strong><?= $messageType === 'error' ? '! Notice' : 'Success' ?></strong>
                <p><?= e($message) ?></p>
            </div>
        <?php endif; ?>

        <!-- CLINIC PASSES -->
        <section class="consultation-panel">
            <h2>Clinic Pass &amp; Return-to-Class</h2>
            <form method="POST" action="clinic_operations.php" class="consultation-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="issue_pass">
                <div class="consultation-form-grid">
                    <div class="consultation-field">
                        <label>Student ID No. *</label>
                        <input type="text" name="student_id_no" required placeholder="e.g., 2026-0001">
                    </div>
                    <div class="consultation-field">
                        <label>Pass Date/Time *</label>
                        <input type="datetime-local" name="pass_date" required value="<?= e(date('Y-m-d\TH:i')) ?>">
                    </div>
                    <div class="consultation-field">
                        <label>Pass Status</label>
                        <select name="pass_status">
                            <option value="Issued">Issued</option>
                            <option value="Sent Home">Sent Home</option>
                            <option value="Referred">Referred</option>
                        </select>
                    </div>
                    <div class="consultation-field">
                        <label>Authorization Status</label>
                        <select name="authorization_status">
                            <option value="Authorized">Authorized</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="consultation-form-footer">
                    <button type="submit" class="consultation-submit">Issue Clinic Pass</button>
                </div>
            </form>

            <div class="consultation-table-wrap" style="margin-top:16px;">
                <table class="consultation-table">
                    <thead>
                        <tr><th>Student</th><th>Pass Time</th><th>Status</th><th>Auth</th><th>Return-to-Class</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($passes): foreach ($passes as $p): ?>
                            <tr>
                                <td><strong><?= e($p['fullname'] ?: $p['student_id_no']) ?></strong><br><small><?= e($p['student_id_no']) ?></small></td>
                                <td><?= e(date('M j, Y g:i A', strtotime($p['pass_date']))) ?></td>
                                <td><?= e($p['pass_status']) ?></td>
                                <td><?= e($p['authorization_status']) ?></td>
                                <td><?= e($p['return_status']) ?><?= !empty($p['returned_at']) ? '<br><small>' . e(date('M j, Y g:i A', strtotime($p['returned_at']))) . '</small>' : '' ?></td>
                                <td>
                                    <?php if ($p['return_status'] === 'Pending'): ?>
                                        <form method="POST" action="clinic_operations.php">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="mark_returned">
                                            <input type="hidden" name="pass_id" value="<?= (int)$p['id'] ?>">
                                            <button type="submit" class="consultation-search-button">Mark Returned</button>
                                        </form>
                                    <?php else: ?>
                                        <span>Done</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6">No clinic passes recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- REFERRALS -->
        <section class="consultation-panel">
            <h2>Referral Management &amp; Follow-Up</h2>
            <form method="POST" action="clinic_operations.php" class="consultation-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="add_referral">
                <div class="consultation-form-grid">
                    <div class="consultation-field">
                        <label>Student ID No. *</label>
                        <input type="text" name="student_id_no" required placeholder="e.g., 2026-0001">
                    </div>
                    <div class="consultation-field">
                        <label>Referral Date *</label>
                        <input type="date" name="referral_date" required value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="consultation-field full-width">
                        <label>Destination *</label>
                        <input type="text" name="destination" required placeholder="e.g., Partner Hospital / City Health Unit / Guardian">
                    </div>
                    <div class="consultation-field full-width">
                        <label>Referral Reason *</label>
                        <textarea name="reason" rows="2" required placeholder="Reason for external referral"></textarea>
                    </div>
                    <div class="consultation-field full-width">
                        <label><input type="checkbox" name="action_required" value="1" checked> Follow-up action required</label>
                    </div>
                </div>
                <div class="consultation-form-footer">
                    <button type="submit" class="consultation-submit">Save Referral</button>
                </div>
            </form>

            <div class="consultation-table-wrap" style="margin-top:16px;">
                <table class="consultation-table">
                    <thead>
                        <tr><th>Date</th><th>Student</th><th>Destination &amp; Reason</th><th>Follow-Up Status</th><th>Update</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($referrals): foreach ($referrals as $r): ?>
                            <tr>
                                <td><?= e(date('M j, Y', strtotime($r['referral_date']))) ?></td>
                                <td><strong><?= e($r['fullname'] ?: $r['student_id_no']) ?></strong><br><small><?= e($r['student_id_no']) ?></small></td>
                                <td><strong><?= e($r['destination']) ?>:</strong> <?= e($r['reason']) ?></td>
                                <td><?= e($r['follow_up_status']) ?><?= !empty($r['follow_up_remarks']) ? '<br><small>' . e($r['follow_up_remarks']) . '</small>' : '' ?></td>
                                <td>
                                    <form method="POST" action="clinic_operations.php" style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="update_referral">
                                        <input type="hidden" name="ref_id" value="<?= (int)$r['id'] ?>">
                                        <select name="follow_up_status">
                                            <option value="Pending Follow-Up" <?= $r['follow_up_status'] === 'Pending Follow-Up' ? 'selected' : '' ?>>Pending Follow-Up</option>
                                            <option value="Student Checked In" <?= $r['follow_up_status'] === 'Student Checked In' ? 'selected' : '' ?>>Student Checked In</option>
                                            <option value="Closed" <?= $r['follow_up_status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                        </select>
                                        <input type="text" name="follow_up_remarks" placeholder="Follow-up note" value="<?= e($r['follow_up_remarks'] ?? '') ?>">
                                        <button type="submit" class="consultation-search-button">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5">No referrals recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- INCIDENT & FIRST-AID -->
        <section class="consultation-panel">
            <h2>Incident &amp; First-Aid Documentation</h2>
            <form method="POST" action="clinic_operations.php" class="consultation-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="add_incident">
                <div class="consultation-form-grid">
                    <div class="consultation-field">
                        <label>Student ID No. *</label>
                        <input type="text" name="student_id_no" required placeholder="e.g., 2026-0001">
                    </div>
                    <div class="consultation-field">
                        <label>Incident Date/Time *</label>
                        <input type="datetime-local" name="incident_datetime" required value="<?= e(date('Y-m-d\TH:i')) ?>">
                    </div>
                    <div class="consultation-field">
                        <label>Location *</label>
                        <input type="text" name="location" required placeholder="e.g., Campus Gym / Corridor">
                    </div>
                    <div class="consultation-field">
                        <label>Follow-Up Status</label>
                        <select name="follow_up_status">
                            <option value="Open">Open</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                    <div class="consultation-field full-width">
                        <label>Reported Circumstances *</label>
                        <textarea name="circumstances" rows="2" required placeholder="Describe incident details"></textarea>
                    </div>
                    <div class="consultation-field full-width">
                        <label>First-Aid Actions Provided *</label>
                        <textarea name="first_aid_action" rows="2" required placeholder="Describe first-aid care provided"></textarea>
                    </div>
                    <div class="consultation-field full-width">
                        <label><input type="checkbox" name="referral_needed" value="1"> Referral needed</label>
                    </div>
                </div>
                <div class="consultation-form-footer">
                    <button type="submit" class="consultation-submit">Save Incident Record</button>
                </div>
            </form>

            <div class="consultation-table-wrap" style="margin-top:16px;">
                <table class="consultation-table">
                    <thead>
                        <tr><th>Date/Time</th><th>Student</th><th>Location &amp; Circumstances</th><th>First-Aid</th><th>Status / Outcome</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($incidents): foreach ($incidents as $inc): ?>
                            <tr>
                                <td><?= e(date('M j, Y g:i A', strtotime($inc['incident_datetime']))) ?></td>
                                <td><strong><?= e($inc['fullname'] ?: $inc['student_id_no']) ?></strong><br><small><?= e($inc['student_id_no']) ?></small></td>
                                <td><strong><?= e($inc['location']) ?>:</strong> <?= e($inc['circumstances']) ?></td>
                                <td><?= e($inc['first_aid_action']) ?></td>
                                <td>
                                    <form method="POST" action="clinic_operations.php" style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="update_incident">
                                        <input type="hidden" name="inc_id" value="<?= (int)$inc['id'] ?>">
                                        <select name="follow_up_status">
                                            <option value="Open" <?= $inc['follow_up_status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                                            <option value="Follow-Up Scheduled" <?= $inc['follow_up_status'] === 'Follow-Up Scheduled' ? 'selected' : '' ?>>Follow-Up Scheduled</option>
                                            <option value="Resolved" <?= $inc['follow_up_status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                        </select>
                                        <input type="text" name="outcome_notes" placeholder="Outcome notes" value="<?= e($inc['outcome_notes'] ?? '') ?>">
                                        <button type="submit" class="consultation-search-button">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5">No incidents logged yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>