<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rbac_audit.php';
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    rbacLog($conn, 'logout', 'Authentication', 'Session', session_id(), 'User logged out');
}
session_unset();
session_destroy();
// Mogawas sa php/ folder ug mosulod sa html/ folder paingon sa index.html
header("Location: ../html/index.html");
exit();
?>