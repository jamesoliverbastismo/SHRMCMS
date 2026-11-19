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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function validInventoryDate(string $value): bool
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

function redirectInventory(string $message, string $type = 'success'): void
{
    header(
        'Location: medicine_inventory.php?message=' .
            urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

if (empty($_SESSION['medicine_csrf'])) {
    $_SESSION['medicine_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['medicine_csrf'];
$username = $_SESSION['admin_username'] ?? 'Clinic Staff';

$message = trim((string)($_GET['message'] ?? ''));
$messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';

/* ---------------------------------
   POST ACTIONS: ADD, STOCK ADJUST, EDIT, DELETE
--------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        redirectInventory('Invalid form submission. Please refresh and try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_medicine') {
        $name = trim((string)($_POST['medicine_name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
        $unit = trim((string)($_POST['unit'] ?? 'pcs'));
        $reorderLevel = filter_var($_POST['reorder_level'] ?? null, FILTER_VALIDATE_INT);
        $expirationRaw = trim((string)($_POST['expiration_date'] ?? ''));
        $supplier = trim((string)($_POST['supplier'] ?? ''));
        $storage = trim((string)($_POST['storage_location'] ?? ''));
        $remarks = trim((string)($_POST['remarks'] ?? ''));

        if ($name === '' || mb_strlen($name) > 150) {
            redirectInventory('Enter a valid medicine or supply name (up to 150 characters).', 'error');
        }
        if ($quantity === false || $quantity < 0) {
            redirectInventory('Quantity must be a whole number of 0 or greater.', 'error');
        }
        if ($unit === '' || mb_strlen($unit) > 50) {
            $unit = 'pcs';
        }
        if ($reorderLevel === false || $reorderLevel < 0) {
            $reorderLevel = 10;
        }
        $expirationDate = null;
        if ($expirationRaw !== '') {
            if (!validInventoryDate($expirationRaw)) {
                redirectInventory('Enter a valid expiration date (YYYY-MM-DD).', 'error');
            }
            $expirationDate = $expirationRaw;
        }

        $categoryVal = $category !== '' ? mb_substr($category, 0, 100) : null;
        $supplierVal = $supplier !== '' ? mb_substr($supplier, 0, 150) : null;
        $storageVal = $storage !== '' ? mb_substr($storage, 0, 150) : null;
        $remarksVal = $remarks !== '' ? mb_substr($remarks, 0, 2000) : null;

        try {
            $stmt = $conn->prepare(
                'INSERT INTO medicine_inventory
                    (medicine_name, category, quantity, unit, reorder_level, expiration_date, supplier, storage_location, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'ssisissss',
                $name,
                $categoryVal,
                $quantity,
                $unit,
                $reorderLevel,
                $expirationDate,
                $supplierVal,
                $storageVal,
                $remarksVal
            );
            $stmt->execute();
            $stmt->close();

            redirectInventory('Medicine / medical supply item added to inventory.');
        } catch (Throwable $ex) {
            error_log('Medicine add error: ' . $ex->getMessage());
            redirectInventory('Unable to save medicine inventory item.', 'error');
        }
    }

    if ($action === 'adjust_stock') {
        $medId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $movement = $_POST['movement'] ?? '';
        $adjustQty = filter_var($_POST['adjust_qty'] ?? null, FILTER_VALIDATE_INT);
        $note = trim((string)($_POST['note'] ?? ''));

        if (!$medId || $medId < 1 || $adjustQty === false || $adjustQty < 1 || !in_array($movement, ['in', 'out'], true)) {
            redirectInventory('Invalid stock adjustment details.', 'error');
        }

        try {
            $conn->begin_transaction();

            $selectStmt = $conn->prepare(
                'SELECT quantity, remarks FROM medicine_inventory WHERE id = ? FOR UPDATE'
            );
            $selectStmt->bind_param('i', $medId);
            $selectStmt->execute();
            $currentRow = $selectStmt->get_result()->fetch_assoc();
            $selectStmt->close();

            if (!$currentRow) {
                $conn->rollback();
                redirectInventory('Inventory item not found.', 'error');
            }

            $currentQty = (int)$currentRow['quantity'];
            $newQty = $movement === 'in'
                ? $currentQty + $adjustQty
                : $currentQty - $adjustQty;

            if ($newQty < 0) {
                $conn->rollback();
                redirectInventory('Stock-out quantity cannot exceed current available stock (' . $currentQty . ').', 'error');
            }

            $logStamp = ($movement === 'in' ? '[Stock In +' : '[Stock Out -')
                . $adjustQty . ' on ' . date('Y-m-d') . ' by ' . $username
                . ($note !== '' ? ': ' . $note : '') . ']';

            $existingRemarks = trim((string)($currentRow['remarks'] ?? ''));
            $updatedRemarks = $existingRemarks !== ''
                ? mb_substr($existingRemarks . ' | ' . $logStamp, 0, 2000)
                : mb_substr($logStamp, 0, 2000);

            $updateStmt = $conn->prepare(
                'UPDATE medicine_inventory SET quantity = ?, remarks = ? WHERE id = ?'
            );
            $updateStmt->bind_param('isi', $newQty, $updatedRemarks, $medId);
            $updateStmt->execute();
            $updateStmt->close();

            $conn->commit();
            redirectInventory(
                $movement === 'in'
                    ? 'Stock-in recorded. New quantity: ' . $newQty . '.'
                    : 'Stock-out recorded. Remaining quantity: ' . $newQty . '.'
            );
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log('Medicine stock adjust error: ' . $ex->getMessage());
            redirectInventory('Unable to update stock quantity.', 'error');
        }
    }

    if ($action === 'delete_medicine') {
        $medId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$medId || $medId < 1) {
            redirectInventory('Invalid inventory item ID.', 'error');
        }

        try {
            $stmt = $conn->prepare('DELETE FROM medicine_inventory WHERE id = ?');
            $stmt->bind_param('i', $medId);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();

            redirectInventory(
                $deleted ? 'Inventory item removed.' : 'Inventory item could not be found.',
                $deleted ? 'success' : 'error'
            );
        } catch (Throwable $ex) {
            error_log('Medicine delete error: ' . $ex->getMessage());
            redirectInventory('Unable to remove inventory item.', 'error');
        }
    }
}

/* ---------------------------------
   LOAD SUMMARY & INVENTORY LIST
--------------------------------- */
$search = trim((string)($_GET['search'] ?? ''));
$filterStatus = trim((string)($_GET['status'] ?? 'all'));

$stats = [
    'total_items' => 0,
    'total_units' => 0,
    'low_stock' => 0,
    'expired' => 0,
    'expiring_soon' => 0
];

$medicines = [];

try {
    $statRes = $conn->query(
        'SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(quantity), 0) AS total_units,
            COALESCE(SUM(CASE WHEN quantity <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock,
            COALESCE(SUM(CASE WHEN expiration_date IS NOT NULL AND expiration_date < CURDATE() THEN 1 ELSE 0 END), 0) AS expired,
            COALESCE(SUM(CASE WHEN expiration_date IS NOT NULL AND expiration_date >= CURDATE() AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS expiring_soon
         FROM medicine_inventory'
    );
    $statRow = $statRes->fetch_assoc();
    $stats['total_items'] = (int)($statRow['total_items'] ?? 0);
    $stats['total_units'] = (int)($statRow['total_units'] ?? 0);
    $stats['low_stock'] = (int)($statRow['low_stock'] ?? 0);
    $stats['expired'] = (int)($statRow['expired'] ?? 0);
    $stats['expiring_soon'] = (int)($statRow['expiring_soon'] ?? 0);

    $sql = 'SELECT * FROM medicine_inventory WHERE 1=1';
    $params = [];
    $types = '';

    if ($search !== '') {
        $sql .= ' AND (medicine_name LIKE ? OR category LIKE ? OR supplier LIKE ? OR storage_location LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term);
        $types .= 'ssss';
    }

    if ($filterStatus === 'low_stock') {
        $sql .= ' AND quantity <= reorder_level';
    } elseif ($filterStatus === 'expired') {
        $sql .= ' AND expiration_date IS NOT NULL AND expiration_date < CURDATE()';
    } elseif ($filterStatus === 'expiring_soon') {
        $sql .= ' AND expiration_date IS NOT NULL AND expiration_date >= CURDATE() AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
    }

    $sql .= ' ORDER BY medicine_name ASC, id DESC';

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $medicines[] = $row;
    }
    $stmt->close();
} catch (Throwable $ex) {
    error_log('Medicine load error: ' . $ex->getMessage());
    $message = $message ?: 'Unable to load medicine and supply inventory.';
    $messageType = 'error';
}

$todayStr = date('Y-m-d');
$soonLimitStr = date('Y-m-d', strtotime('+30 days'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine &amp; Medical Supply Inventory - SHRCMS</title>

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
            <a href="clinic_consultation.php"><span><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span> Clinic Consultation</a>
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
                <p class="page-eyebrow">CLINIC INVENTORY &amp; SUPPLIES</p>
                <h1>Medicine &amp; Medical Supply Inventory</h1>
                <p class="header-subtitle">
                    Monitor medicine and medical supply stock levels, stock-in/stock-out adjustments, and expiration dates. For inventory tracking only—does not automate medication prescribing.
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

        <?php if ($message !== ''): ?>
            <div class="consultation-message <?= $messageType === 'error' ? 'error-message' : 'success-message' ?>" role="<?= $messageType === 'error' ? 'alert' : 'status' ?>">
                <strong><?= $messageType === 'error' ? '! Notice' : 'Success' ?></strong>
                <p><?= e($message) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($stats['low_stock'] > 0 || $stats['expiring_soon'] > 0 || $stats['expired'] > 0): ?>
            <section class="inventory-alert-panel" role="alert" aria-label="Inventory alerts">
                <div>
                    <strong>Inventory Attention Required</strong>
                    <p>Review the affected medicine and supply items before dispensing or restocking.</p>
                </div>
                <div class="inventory-alert-actions">
                    <?php if ($stats['low_stock'] > 0): ?>
                        <a href="medicine_inventory.php?status=low_stock"><?= number_format($stats['low_stock']) ?> Low Stock</a>
                    <?php endif; ?>
                    <?php if ($stats['expiring_soon'] > 0): ?>
                        <a href="medicine_inventory.php?status=expiring_soon"><?= number_format($stats['expiring_soon']) ?> Expiring in 30 Days</a>
                    <?php endif; ?>
                    <?php if ($stats['expired'] > 0): ?>
                        <a href="medicine_inventory.php?status=expired"><?= number_format($stats['expired']) ?> Expired</a>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="consultation-stats">
            <article class="consultation-stat">
                <span class="stat-icon"><img class="ui-icon" src="../../image/icon-document.svg" alt="" aria-hidden="true"></span>
                <div>
                    <p>Registered Items</p>
                    <h2><?= number_format($stats['total_items']) ?></h2>
                    <small>Medicine &amp; supply entries</small>
                </div>
            </article>

            <article class="consultation-stat">
                <span class="stat-icon"><img class="ui-icon" src="../../image/icon-medical.svg" alt="" aria-hidden="true"></span>
                <div>
                    <p>Total Stock Units</p>
                    <h2><?= number_format($stats['total_units']) ?></h2>
                    <small>Current recorded quantity</small>
                </div>
            </article>

            <article class="consultation-stat">
                <span class="stat-icon"><img class="ui-icon" src="../../image/icon-clock.svg" alt="" aria-hidden="true"></span>
                <div>
                    <p>Low Stock / Expiring Soon</p>
                    <h2><?= number_format($stats['low_stock']) ?> / <?= number_format($stats['expiring_soon']) ?></h2>
                    <small>At or below reorder level / within 30 days</small>
                </div>
            </article>

            <article class="consultation-stat">
                <span class="stat-icon"><img class="ui-icon" src="../../image/icon-clock.svg" alt="" aria-hidden="true"></span>
                <div>
                    <p>Expired Items</p>
                    <h2><?= number_format($stats['expired']) ?></h2>
                    <small>Past expiration date</small>
                </div>
            </article>
        </section>

        <section class="consultation-panel">
            <div class="consultation-panel-heading">
                <div class="consultation-heading-icon"><img class="ui-icon" src="../../image/icon-add.svg" alt="" aria-hidden="true"></div>
                <div>
                    <h2>Add Medicine or Medical Supply</h2>
                    <p>Record item details, initial quantity, reorder threshold, expiration date, and storage location.</p>
                </div>
            </div>

            <div class="consultation-divider"></div>

            <form method="POST" action="medicine_inventory.php" class="consultation-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="add_medicine">

                <div class="consultation-form-grid">
                    <div class="consultation-field">
                        <label for="medicine_name">Medicine / Supply Name <span class="required">*</span></label>
                        <input type="text" id="medicine_name" name="medicine_name" maxlength="150" required placeholder="e.g., Biogesic 500mg, Gauze Pad">
                    </div>

                    <div class="consultation-field">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" maxlength="100" placeholder="e.g., Analgesic, First-Aid Supply">
                    </div>

                    <div class="consultation-field">
                        <label for="quantity">Initial Quantity <span class="required">*</span></label>
                        <input type="number" id="quantity" name="quantity" min="0" value="0" required>
                    </div>

                    <div class="consultation-field">
                        <label for="unit">Unit <span class="required">*</span></label>
                        <input type="text" id="unit" name="unit" maxlength="50" value="pcs" required placeholder="pcs, box, bottle, pack">
                    </div>

                    <div class="consultation-field">
                        <label for="reorder_level">Low-Stock Reorder Level <span class="required">*</span></label>
                        <input type="number" id="reorder_level" name="reorder_level" min="0" value="10" required>
                    </div>

                    <div class="consultation-field">
                        <label for="expiration_date">Expiration Date</label>
                        <input type="date" id="expiration_date" name="expiration_date">
                    </div>

                    <div class="consultation-field">
                        <label for="supplier">Supplier</label>
                        <input type="text" id="supplier" name="supplier" maxlength="150" placeholder="Optional supplier name">
                    </div>

                    <div class="consultation-field">
                        <label for="storage_location">Storage Location</label>
                        <input type="text" id="storage_location" name="storage_location" maxlength="150" placeholder="e.g., Cabinet A, First-Aid Kit">
                    </div>

                    <div class="consultation-field full-width">
                        <label for="remarks">Remarks / Transaction Notes</label>
                        <input type="text" id="remarks" name="remarks" maxlength="2000" placeholder="Optional notes on batch, packaging, or stock intake">
                    </div>
                </div>

                <div class="consultation-form-footer">
                    <p><span class="required">*</span> Required fields</p>
                    <button type="reset" class="consultation-reset">Clear Form</button>
                    <button type="submit" class="consultation-submit">Save Inventory Item</button>
                </div>
            </form>
        </section>

        <section class="consultation-panel history-panel">
            <div class="consultation-panel-heading history-heading">
                <div>
                    <h2>Inventory Stock List &amp; Adjustments</h2>
                    <p>Filter items and record stock-in or stock-out adjustments.</p>
                </div>
                <span class="record-count"><?= count($medicines) ?> items shown</span>
            </div>

            <form method="GET" action="medicine_inventory.php" class="consultation-search-form">
                <div class="search-input-wrap">
                    <span><img class="ui-icon" src="../../image/icon-search.svg" alt="" aria-hidden="true"></span>
                    <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search name, category, supplier, or location...">
                </div>

                <select name="status" aria-label="Filter by stock or expiration status">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Items</option>
                    <option value="low_stock" <?= $filterStatus === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="expiring_soon" <?= $filterStatus === 'expiring_soon' ? 'selected' : '' ?>>Expiring in 30 Days</option>
                    <option value="expired" <?= $filterStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                </select>

                <button type="submit" class="consultation-search-button">Filter</button>
                <a href="medicine_inventory.php" class="consultation-reset-link">Reset</a>
            </form>

            <div class="consultation-table-wrap">
                <table class="consultation-table">
                    <thead>
                        <tr>
                            <th>Item Details</th>
                            <th>Stock &amp; Reorder</th>
                            <th>Expiration &amp; Status</th>
                            <th>Stock Adjustment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicines)): ?>
                            <tr>
                                <td colspan="5" class="consultation-empty">
                                    <strong>No inventory items found</strong>
                                    <p>Add a new medicine or supply above or adjust your filter.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($medicines as $item): ?>
                                <?php
                                $qty = (int)$item['quantity'];
                                $reorder = (int)$item['reorder_level'];
                                $exp = $item['expiration_date'] ?? '';
                                $isExpired = ($exp !== '' && $exp < $todayStr);
                                $isExpiringSoon = (!$isExpired && $exp !== '' && $exp <= $soonLimitStr);
                                $isLowStock = ($qty <= $reorder);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($item['medicine_name']) ?></strong><br>
                                        <small class="student-number">
                                            <?= e($item['category'] ?: 'Uncategorized') ?>
                                            <?= !empty($item['storage_location']) ? ' • ' . e($item['storage_location']) : '' ?>
                                        </small>
                                        <?php if (!empty($item['remarks'])): ?>
                                            <div class="remarks-cell"><small><?= e($item['remarks']) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= number_format($qty) ?> <?= e($item['unit'] ?: 'pcs') ?></strong><br>
                                        <small class="student-number">Reorder at: <?= number_format($reorder) ?></small>
                                    </td>
                                    <td>
                                        <div><?= $exp !== '' ? e(date('M d, Y', strtotime($exp))) : 'No expiry date' ?></div>
                                        <?php if ($isExpired): ?>
                                            <span class="reason-tag inventory-status-expired">Expired</span>
                                        <?php elseif ($isExpiringSoon): ?>
                                            <span class="reason-tag inventory-status-soon">Expiring Soon</span>
                                        <?php elseif ($isLowStock): ?>
                                            <span class="reason-tag inventory-status-low">Low Stock</span>
                                        <?php else: ?>
                                            <small class="student-number">Normal</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="medicine_inventory.php" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="adjust_stock">
                                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                            <select name="movement" aria-label="Stock movement">
                                                <option value="in">Stock In (+)</option>
                                                <option value="out">Stock Out (-)</option>
                                            </select>
                                            <input type="number" name="adjust_qty" min="1" value="1" style="width:70px;" aria-label="Adjustment quantity">
                                            <input type="text" name="note" maxlength="120" placeholder="Note" style="width:110px;" aria-label="Adjustment note">
                                            <button type="submit" class="consultation-search-button">Apply</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" action="medicine_inventory.php" onsubmit="return confirm('Remove this inventory item?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete_medicine">
                                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                            <button type="submit" class="consultation-reset">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

</body>

</html>
<?php $conn->close(); ?>