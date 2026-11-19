<?php
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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function validReportDate(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    return $date !== false
        && (
            $errors === false ||
            (
                $errors['warning_count'] === 0 &&
                $errors['error_count'] === 0
            )
        )
        && $date->format('Y-m-d') === $value;
}

function fetchRows(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): array {
    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $rows;
}

$username = $_SESSION['admin_username'] ?? 'Clinic Staff';

$today = date('Y-m-d');
$firstDay = date('Y-m-01');

$startDate = $_GET['start_date'] ?? $firstDay;
$endDate = $_GET['end_date'] ?? $today;
$reportType = $_GET['report'] ?? 'overview';

$allowedReports = [
    'overview',
    'visits',
    'examinations',
    'students',
    'medicines',
    'equipment'
];

if (
    !is_string($startDate) ||
    !is_string($endDate) ||
    !validReportDate($startDate) ||
    !validReportDate($endDate) ||
    $startDate > $endDate
) {
    $startDate = $firstDay;
    $endDate = $today;
}

if (
    !is_string($reportType) ||
    !in_array($reportType, $allowedReports, true)
) {
    $reportType = 'overview';
}

$reportTitles = [
    'overview' => 'Clinic Visit & Operations Summary Report',
    'visits' => 'Clinic Visit Report',
    'examinations' => 'Student Health Examination Report',
    'students' => 'Student Registration Report',
    'medicines' => 'Medicine & Medical Supply Inventory Report',
    'equipment' => 'Equipment Inventory Report'
];

$reportTitle = $reportTitles[$reportType];

$summary = [
    'students' => 0,
    'visits' => 0,
    'examinations' => 0,
    'medicine_types' => 0,
    'medicine_units' => 0,
    'medicine_low_stock' => 0,
    'medicine_expired' => 0,
    'equipment_types' => 0,
    'equipment_units' => 0
];

$reportRows = [];
$reportError = '';

try {
    $rows = fetchRows(
        $conn,
        "SELECT COUNT(*) AS total
         FROM students
         WHERE created_at >= ?
           AND created_at < DATE_ADD(?, INTERVAL 1 DAY)",
        'ss',
        [$startDate, $endDate]
    );
    $summary['students'] = (int)$rows[0]['total'];

    $rows = fetchRows(
        $conn,
        "SELECT COUNT(*) AS total
         FROM consultations
         WHERE consultation_date >= ?
           AND consultation_date < DATE_ADD(?, INTERVAL 1 DAY)",
        'ss',
        [$startDate, $endDate]
    );
    $summary['visits'] = (int)$rows[0]['total'];

    $rows = fetchRows(
        $conn,
        "SELECT COUNT(*) AS total
         FROM health_documentation
         WHERE examination_date BETWEEN ? AND ?",
        'ss',
        [$startDate, $endDate]
    );
    $summary['examinations'] = (int)$rows[0]['total'];

    $rows = fetchRows(
        $conn,
        "SELECT
            COUNT(*) AS total_types,
            COALESCE(SUM(quantity), 0) AS total_units,
            COALESCE(SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock_count,
            COALESCE(SUM(CASE WHEN expiration_date IS NOT NULL AND expiration_date < CURDATE() THEN 1 ELSE 0 END), 0) AS expired_count
         FROM medicine_inventory"
    );
    $summary['medicine_types'] = (int)$rows[0]['total_types'];
    $summary['medicine_units'] = (int)$rows[0]['total_units'];
    $summary['medicine_low_stock'] = (int)$rows[0]['low_stock_count'];
    $summary['medicine_expired'] = (int)$rows[0]['expired_count'];

    $rows = fetchRows(
        $conn,
        "SELECT
            COUNT(*) AS total_types,
            COALESCE(SUM(quantity), 0) AS total_units
         FROM equipment_inventory"
    );
    $summary['equipment_types'] = (int)$rows[0]['total_types'];
    $summary['equipment_units'] = (int)$rows[0]['total_units'];

    if ($reportType === 'overview' || $reportType === 'visits') {
        $reportRows = fetchRows(
            $conn,
            "SELECT
                c.id,
                c.consultation_date,
                s.student_id_no,
                s.fullname,
                s.course_year,
                c.reason,
                c.remarks,
                c.staff_name
             FROM consultations c
             LEFT JOIN students s ON s.id = c.student_id
             WHERE c.consultation_date >= ?
               AND c.consultation_date < DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY c.consultation_date DESC, c.id DESC",
            'ss',
            [$startDate, $endDate]
        );
    } elseif ($reportType === 'examinations') {
        $reportRows = fetchRows(
            $conn,
            "SELECT
                h.id,
                h.student_id_no,
                s.fullname,
                s.course_year,
                h.examination_date,
                h.height_cm,
                h.weight_kg,
                h.temperature,
                h.blood_pressure,
                h.findings,
                h.remarks,
                h.recorded_by
             FROM health_documentation h
             LEFT JOIN students s
                ON s.student_id_no = h.student_id_no
             WHERE h.examination_date BETWEEN ? AND ?
             ORDER BY h.examination_date DESC, h.id DESC",
            'ss',
            [$startDate, $endDate]
        );
    } elseif ($reportType === 'students') {
        $reportRows = fetchRows(
            $conn,
            "SELECT
                id,
                student_id_no,
                fullname,
                age,
                gender,
                course_year,
                contact,
                created_at
             FROM students
             WHERE created_at >= ?
               AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY fullname ASC",
            'ss',
            [$startDate, $endDate]
        );
    } elseif ($reportType === 'medicines') {
        $reportRows = fetchRows(
            $conn,
            "SELECT
                id,
                medicine_name,
                category,
                quantity,
                unit,
                reorder_level,
                expiration_date,
                supplier,
                storage_location,
                remarks
             FROM medicine_inventory
             ORDER BY medicine_name ASC"
        );
    } elseif ($reportType === 'equipment') {
        $reportRows = fetchRows(
            $conn,
            "SELECT
                id,
                equipment_name,
                category,
                quantity,
                equipment_condition,
                date_acquired,
                last_maintenance,
                next_maintenance,
                location,
                remarks
             FROM equipment_inventory
             ORDER BY equipment_name ASC"
        );
    }

    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $fileNames = [
            'overview' => 'clinic-visits',
            'visits' => 'clinic-visits',
            'examinations' => 'health-examinations',
            'students' => 'student-records',
            'medicines' => 'medicine-inventory',
            'equipment' => 'equipment-inventory'
        ];

        $filename = $fileNames[$reportType]
            . '-' . $startDate
            . '-to-' . $endDate . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        if (!empty($reportRows)) {
            fputcsv($output, array_keys($reportRows[0]));
            foreach ($reportRows as $row) {
                fputcsv($output, array_values($row));
            }
        } else {
            fputcsv($output, ['No records found']);
        }

        fclose($output);
        exit();
    }
} catch (Throwable $exception) {
    error_log('Clinic reports error: ' . $exception->getMessage());
    $reportError = $exception->getMessage();
    $reportRows = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Reports - SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/staff-dashboard.css?v=20261115-staff-admin-alignment">
    <link rel="stylesheet" href="../../css/clinic-reports.css?v=20261114-icons">
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
            <a href="clinic_consultation.php"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Clinic Consultation</a>
            <a href="clinic_operations.php" class="active"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Passes, Referrals &amp; Incidents</a>
            <a href="health_documentation.php"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Health Documentation</a>
            <a href="scan_medical_certificate.php"><span><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"></span> Verify Certificate</a>
            <a href="medicine_inventory.php">
                <span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medicine Inventory
            </a>

            <p class="sidebar-label">REPORTS</p>

            <a href="clinic_reports.php" class="active">
                <span><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"></span> Clinic Reports
            </a>

            <a href="../logout.php" class="logout-link">
                <span><img class="ui-icon" src="../../image/icon-logout.svg" alt="" aria-hidden="true"></span> Logout
            </a>
        </nav>

        <div class="sidebar-bottom">
            <p>Student Health Record</p>
            <small>Clinic Management System</small>
        </div>
    </aside>

    <main class="staff-main cr-main">

        <header class="staff-header">
            <div>
                <p class="page-eyebrow">DESCRIPTIVE REPORTS &amp; INVENTORY SUMMARY</p>
                <h1>Clinic Reports</h1>
                <p class="header-subtitle">
                    Descriptive summaries of recorded clinic visits, examinations, student profiles, medicine stock status, and equipment inventory.
                </p>
            </div>

            <div class="staff-user">
                <img src="../../image/logo2.png" alt="Staff profile">
                <div>
                    <strong><?= e($username) ?></strong>
                    <span>Clinic Staff</span>
                </div>
            </div>
        </header>

        <?php if ($reportError !== ''): ?>
            <div class="cr-alert" role="alert">
                <strong>Unable to load the report.</strong>
                <p><?= e($reportError) ?></p>
                <p>Check the PHP error log and verify your database column names.</p>
            </div>
        <?php endif; ?>

        <section class="cr-stats">
            <article class="cr-stat">
                <span>New Students</span>
                <strong><?= $summary['students'] ?></strong>
                <small>Registered during selected dates</small>
            </article>

            <article class="cr-stat">
                <span>Clinic Visits</span>
                <strong><?= $summary['visits'] ?></strong>
                <small>Documented visits during selected dates</small>
            </article>

            <article class="cr-stat">
                <span>Health Examinations</span>
                <strong><?= $summary['examinations'] ?></strong>
                <small>Examinations recorded during selected dates</small>
            </article>

            <article class="cr-stat">
                <span>Medicine Stock Units</span>
                <strong><?= $summary['medicine_units'] ?></strong>
                <small>
                    <?= $summary['medicine_types'] ?> items |
                    <?= $summary['medicine_low_stock'] ?> low stock |
                    <?= $summary['medicine_expired'] ?> expired
                </small>
            </article>

            <article class="cr-stat">
                <span>Equipment Units</span>
                <strong><?= $summary['equipment_units'] ?></strong>
                <small>
                    <?= $summary['equipment_types'] ?> equipment types registered
                </small>
            </article>
        </section>

        <section class="cr-panel">
            <div class="cr-heading">
                <div>
                    <h2>Generate a Report</h2>
                    <p>Select a report type and date range.</p>
                </div>
            </div>

            <form method="GET"
                action="clinic_reports.php"
                class="cr-filter"
                id="reportForm">

                <div class="cr-field">
                    <label for="report">Report Type</label>

                    <select name="report" id="report">
                        <option value="overview" <?= $reportType === 'overview' ? 'selected' : '' ?>>Overview / Clinic Visits</option>
                        <option value="visits" <?= $reportType === 'visits' ? 'selected' : '' ?>>Clinic Visits</option>
                        <option value="examinations" <?= $reportType === 'examinations' ? 'selected' : '' ?>>Health Examinations</option>
                        <option value="students" <?= $reportType === 'students' ? 'selected' : '' ?>>Student Registrations</option>
                        <option value="medicines" <?= $reportType === 'medicines' ? 'selected' : '' ?>>Medicine &amp; Supply Inventory</option>
                        <option value="equipment" <?= $reportType === 'equipment' ? 'selected' : '' ?>>Equipment Inventory</option>
                    </select>
                </div>

                <div class="cr-field">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date"
                        value="<?= e($startDate) ?>" max="<?= e($endDate) ?>" required>
                </div>

                <div class="cr-field">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date"
                        value="<?= e($endDate) ?>" min="<?= e($startDate) ?>" required>
                </div>

                <div class="cr-filter-actions">
                    <button type="submit" class="cr-primary-btn">Generate Report</button>
                </div>
            </form>
        </section>

        <section class="cr-panel">
            <div class="cr-heading cr-report-heading">
                <div>
                    <h2><?= e($reportTitle) ?></h2>
                    <p>
                        Report period:
                        <?= e(date('M d, Y', strtotime($startDate))) ?>
                        to
                        <?= e(date('M d, Y', strtotime($endDate))) ?>
                        <?php if (in_array($reportType, ['medicines', 'equipment'], true)): ?>
                            — current inventory snapshot
                        <?php endif; ?>
                    </p>
                </div>

                <div class="cr-actions">
                    <a class="cr-secondary-btn"
                        href="clinic_reports.php?report=<?= urlencode($reportType) ?>&amp;start_date=<?= urlencode($startDate) ?>&amp;end_date=<?= urlencode($endDate) ?>&amp;export=csv">
                        Export CSV
                    </a>

                    <button type="button" class="cr-secondary-btn" id="printReport">
                        Print Report
                    </button>
                </div>
            </div>

            <div class="cr-table-wrap">
                <table class="cr-table">
                    <thead>
                        <tr>
                            <?php if ($reportType === 'overview' || $reportType === 'visits'): ?>
                                <th>Visit Date</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Course / Year</th>
                                <th>Reported Concern</th>
                                <th>Documentation Notes</th>
                                <th>Recorded By</th>
                            <?php elseif ($reportType === 'examinations'): ?>
                                <th>Examination Date</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Course / Year</th>
                                <th>Height (cm)</th>
                                <th>Weight (kg)</th>
                                <th>Temperature</th>
                                <th>Blood Pressure</th>
                                <th>Findings</th>
                                <th>Remarks</th>
                                <th>Recorded By</th>
                            <?php elseif ($reportType === 'students'): ?>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Course / Year</th>
                                <th>Contact</th>
                                <th>Date Registered</th>
                            <?php elseif ($reportType === 'medicines'): ?>
                                <th>Medicine / Supply</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Reorder Level</th>
                                <th>Expiration Date</th>
                                <th>Supplier</th>
                                <th>Storage Location</th>
                                <th>Remarks</th>
                            <?php elseif ($reportType === 'equipment'): ?>
                                <th>Equipment</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Condition</th>
                                <th>Date Acquired</th>
                                <th>Last Maintenance</th>
                                <th>Next Maintenance</th>
                                <th>Location</th>
                                <th>Remarks</th>
                            <?php endif; ?>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($reportRows)): ?>
                            <tr>
                                <td colspan="11" class="cr-empty">
                                    <?= $reportError !== ''
                                        ? 'Report could not be loaded.'
                                        : 'No records found for the selected report and dates.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reportRows as $row): ?>
                                <tr>
                                    <?php if ($reportType === 'overview' || $reportType === 'visits'): ?>
                                        <td><?= e($row['consultation_date']) ?></td>
                                        <td><?= e($row['student_id_no'] ?? '—') ?></td>
                                        <td><?= e($row['fullname'] ?? 'Student not found') ?></td>
                                        <td><?= e($row['course_year'] ?? '—') ?></td>
                                        <td><?= e($row['reason'] ?? '—') ?></td>
                                        <td><?= e($row['remarks'] ?? '—') ?></td>
                                        <td><?= e($row['staff_name'] ?? '—') ?></td>
                                    <?php elseif ($reportType === 'examinations'): ?>
                                        <td><?= e($row['examination_date']) ?></td>
                                        <td><?= e($row['student_id_no']) ?></td>
                                        <td><?= e($row['fullname'] ?? 'Student not found') ?></td>
                                        <td><?= e($row['course_year'] ?? '—') ?></td>
                                        <td><?= e($row['height_cm'] ?? '—') ?></td>
                                        <td><?= e($row['weight_kg'] ?? '—') ?></td>
                                        <td><?= e($row['temperature'] ?? '—') ?></td>
                                        <td><?= e($row['blood_pressure'] ?? '—') ?></td>
                                        <td><?= e($row['findings'] ?? '—') ?></td>
                                        <td><?= e($row['remarks'] ?? '—') ?></td>
                                        <td><?= e($row['recorded_by'] ?? '—') ?></td>
                                    <?php elseif ($reportType === 'students'): ?>
                                        <td><?= e($row['student_id_no']) ?></td>
                                        <td><?= e($row['fullname']) ?></td>
                                        <td><?= e($row['age'] ?? '—') ?></td>
                                        <td><?= e($row['gender'] ?? '—') ?></td>
                                        <td><?= e($row['course_year'] ?? '—') ?></td>
                                        <td><?= e($row['contact'] ?? '—') ?></td>
                                        <td><?= e($row['created_at']) ?></td>
                                    <?php elseif ($reportType === 'medicines'): ?>
                                        <td><?= e($row['medicine_name']) ?></td>
                                        <td><?= e($row['category'] ?? '—') ?></td>
                                        <td><?= (int)$row['quantity'] ?></td>
                                        <td><?= e($row['unit'] ?? 'pcs') ?></td>
                                        <td><?= (int)($row['reorder_level'] ?? 0) ?></td>
                                        <td><?= e($row['expiration_date'] ?? '—') ?></td>
                                        <td><?= e($row['supplier'] ?? '—') ?></td>
                                        <td><?= e($row['storage_location'] ?? '—') ?></td>
                                        <td><?= e($row['remarks'] ?? '—') ?></td>
                                    <?php elseif ($reportType === 'equipment'): ?>
                                        <td><?= e($row['equipment_name']) ?></td>
                                        <td><?= e($row['category'] ?? '—') ?></td>
                                        <td><?= (int)$row['quantity'] ?></td>
                                        <td><?= e($row['equipment_condition']) ?></td>
                                        <td><?= e($row['date_acquired'] ?? '—') ?></td>
                                        <td><?= e($row['last_maintenance'] ?? '—') ?></td>
                                        <td><?= e($row['next_maintenance'] ?? '—') ?></td>
                                        <td><?= e($row['location'] ?? '—') ?></td>
                                        <td><?= e($row['remarks'] ?? '—') ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="cr-record-count">
                Total records:
                <strong><?= count($reportRows) ?></strong>
            </p>
        </section>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('reportForm');
            const printButton = document.getElementById('printReport');

            if (form) {
                form.addEventListener('submit', function() {
                    const button = form.querySelector('button[type="submit"]');
                    if (button) {
                        button.disabled = true;
                        button.textContent = 'Generating...';
                    }
                });
            }

            if (printButton) {
                printButton.addEventListener('click', function() {
                    window.print();
                });
            }
        });
    </script>

</body>

</html>