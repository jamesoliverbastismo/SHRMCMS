<?php
session_start();
if (!empty($_SESSION['must_change_password'])) {
    header('Location: ../change_initial_password.php');
    exit();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../rbac_audit.php';
rbacRequireRole(['Staff', 'Administrator'], '../login.php');

// Check if the user is logged in and has the correct role
if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !isset($_SESSION['user_role']) ||
    !in_array($_SESSION['user_role'], ['Staff', 'Administrator'], true)
) {
    header('Location: ../login.php');
    exit();
}

// Get the username from the session
$username = $_SESSION['admin_username'] ?? 'Clinic Staff';
rbacLog($conn, 'view', 'Staff Dashboard', 'Page', 'staff_dashboard', 'Staff dashboard accessed');
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Staff Dashboard - SHRCMS</title>

    <link rel="stylesheet" href="../../css/style.css?v=20261114-icons">
    <link rel="stylesheet" href="../../css/staff-dashboard.css?v=20261115-staff-admin-alignment">
</head>

<body class="staff-dashboard-body">

    <!-- SIDEBAR -->
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
            <a href="staff_dashboard.php" class="active">
                <span><img class="ui-icon" src="../../image/icon-dashboard.svg" alt="" aria-hidden="true"></span> Dashboard
            </a>

            <a href="clinic_consultation.php">
                <span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Clinic Consultation
            </a>

            <a href="student_profiles.php">
                <span><img class="ui-icon" src="../../image/icon-student.svg" alt="" aria-hidden="true"></span> Student Profiles
            </a>

            <a href="clinic_operations.php?view=passes"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Clinic Passes</a>
            <a href="clinic_operations.php?view=referrals"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Referrals</a>
            <a href="clinic_operations.php?view=incidents"><span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Incidents</a>
            
            <a href="health_documentation.php">
                <span><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span> Health Documentation
            </a>

            <a href="medical_examinations.php">
                <span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medical Examinations
            </a>

            <a href="scan_medical_certificate.php">
                <span><img class="ui-icon" src="../../image/icon-certificate.svg" alt="" aria-hidden="true"></span> Verify Certificate
            </a>

            <a href="medicine_inventory.php">
                <span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Medicine Inventory
            </a>

            <p class="sidebar-label">REPORTS</p>

            <a href="clinic_reports.php">
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

    <!-- MAIN CONTENT -->
    <main class="staff-main">

        <!-- HEADER -->
        <header class="staff-header">
            <div>
                <p class="page-eyebrow">STAFF PORTAL</p>
                <h1>Dashboard</h1>
                <p class="header-subtitle">
                    Manage student health services and clinic resources.
                </p>
                <nav class="dashboard-header-actions" aria-label="Clinic operations">
                    <a href="clinic_operations.php?view=passes">Clinic Passes</a>
                    <a href="clinic_operations.php?view=referrals">Referrals</a>
                    <a href="clinic_operations.php?view=incidents">Incidents</a>
                </nav>
            </div>

            <div class="staff-user">
                <img src="../../image/logo2.png" alt="Staff profile">

                <div>
                    <strong><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span>Clinic Staff</span>
                </div>
            </div>
        </header>

        <!-- WELCOME -->
        <section class="welcome-panel">
            <div>
                <span class="welcome-tag">SHRCMS STAFF PORTAL</span>

                <h2>Welcome back, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</h2>
                <p>
                    Manage clinic consultations, certificate requests, and health reports.
                </p>

                <a href="clinic_consultation.php" class="welcome-button">
                    Open Clinic Consultation →
                </a>
            </div>

            <div class="welcome-symbol" aria-hidden="true"><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></div>
        </section>

        <!-- QUICK ACCESS -->
        <section class="dashboard-section">
            <div class="section-heading">
                <div>
                    <h2>Clinic Management</h2>
                    <p>Choose a module to get started.</p>
                </div>
            </div>

            <div class="module-grid">

                <a href="clinic_consultation.php" class="module-card">
                    <div class="module-icon green"><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></div>
                    <h3>Clinic Consultation</h3>
                    <p>Record and manage student consultations.</p>
                    <span class="module-link">Open consultations →</span>
                </a>

                <a href="student_profiles.php" class="module-card">
                    <div class="module-icon blue"><img class="ui-icon" src="../../image/icon-student.svg" alt="" aria-hidden="true"></div>
                    <h3>Student Profiles</h3>
                    <p>View, add, edit, and maintain student profile information.</p>
                    <span class="module-link">Manage profiles →</span>
                </a>

                <a href="clinic_operations.php?view=passes" class="module-card">
                    <div class="module-icon purple"><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></div>
                    <h3>Clinic Passes</h3>
                    <p>Issue passes and track return-to-class status.</p>
                    <span class="module-link">Manage passes →</span>
                </a>

                <a href="clinic_operations.php?view=referrals" class="module-card">
                    <div class="module-icon teal"><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></div>
                    <h3>Referrals</h3>
                    <p>Create referrals and manage follow-up actions.</p>
                    <span class="module-link">Manage referrals →</span>
                </a>

                <a href="clinic_operations.php?view=incidents" class="module-card">
                    <div class="module-icon red"><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></div>
                    <h3>Incidents</h3>
                    <p>Document incidents and first-aid care.</p>
                    <span class="module-link">Manage incidents →</span>
                </a>

                <a href="health_documentation.php" class="module-card">
                    <div class="module-icon orange"><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></div>
                    <h3>Health Documentation</h3>
                    <p>Review certificate requests and generate QR-verified certificates.</p>
                    <span class="module-link">View documents →</span>
                </a>

                <a href="medical_examinations.php" class="module-card">
                    <div class="module-icon teal"><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></div>
                    <h3>Medical Examinations</h3>
                    <p>Record clinic-conducted assessments and review history by date or academic year.</p>
                    <span class="module-link">Manage examinations →</span>
                </a>

                <a href="medicine_inventory.php" class="module-card">
                    <div class="module-icon orange"><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></div>
                    <h3>Medicine Inventory</h3>
                    <p>Monitor stock levels and receive low-stock and expiration alerts.</p>
                    <span class="module-link">View inventory alerts →</span>
                </a>

                <a href="clinic_reports.php" class="module-card">
                    <div class="module-icon navy"><img class="ui-icon" src="../../image/icon-reports.svg" alt="" aria-hidden="true"></div>
                    <h3>Clinic Reports</h3>
                    <p>View clinic activity, health trends, and inventory reports.</p>
                    <span class="module-link">View summaries and trends →</span>
                </a>

            </div>
        </section>

    </main>

</body>

</html>