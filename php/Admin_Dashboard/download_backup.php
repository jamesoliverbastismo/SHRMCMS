<?php
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    ($_SESSION['user_role'] ?? '') !== 'Administrator'
) {
    header('Location: ../login.php');
    exit();
}

$submittedToken = $_POST['csrf_token'] ?? '';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !is_string($submittedToken) ||
    empty($_SESSION['admin_dashboard_csrf']) ||
    !hash_equals($_SESSION['admin_dashboard_csrf'], $submittedToken)
) {
    http_response_code(400);
    exit('Invalid backup request. Return to the admin dashboard and try again.');
}

require_once __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

function quoteBackupIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function formatBackupValue(mysqli $conn, $value, string $columnType): string
{
    if ($value === null) {
        return 'NULL';
    }

    $type = strtolower($columnType);
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|real|year)\b/', $type)) {
        return (string)$value;
    }

    if (preg_match('/^bit\b/', $type)) {
        return '0x' . bin2hex((string)$value);
    }

    if (
        preg_match('/^(binary|varbinary|tinyblob|blob|mediumblob|longblob|geometry|point|linestring|polygon)\b/', $type)
    ) {
        return '0x' . bin2hex((string)$value);
    }

    return "'" . $conn->real_escape_string((string)$value) . "'";
}

try {
    $databaseResult = $conn->query('SELECT DATABASE() AS database_name');
    $databaseName = (string)($databaseResult->fetch_assoc()['database_name'] ?? '');
    $databaseResult->free();

    if ($databaseName === '') {
        throw new RuntimeException('No database is selected.');
    }

    $tablesResult = $conn->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
    $tableNames = [];

    while ($tableRow = $tablesResult->fetch_row()) {
        $tableNames[] = (string)$tableRow[0];
    }
    $tablesResult->free();

    if (!$tableNames) {
        throw new RuntimeException('No database tables were found.');
    }

    $backup = fopen('php://temp/maxmemory:5242880', 'w+b');
    if ($backup === false) {
        throw new RuntimeException('Unable to prepare the backup stream.');
    }

    $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $databaseName)
        . '-backup-' . date('Y-m-d-His') . '.sql';

    fwrite($backup, "-- SHRCMS database backup\n");
    fwrite($backup, '-- Database: ' . str_replace(["\r", "\n"], '', $databaseName) . "\n");
    fwrite($backup, '-- Created: ' . date(DATE_ATOM) . "\n\n");
    fwrite($backup, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    foreach ($tableNames as $tableName) {
        $quotedTable = quoteBackupIdentifier($tableName);
        $createResult = $conn->query('SHOW CREATE TABLE ' . $quotedTable);
        $createRow = $createResult->fetch_assoc();
        $createResult->free();

        if (!$createRow || !isset($createRow['Create Table'])) {
            throw new RuntimeException('Unable to export a table definition.');
        }

        fwrite($backup, 'DROP TABLE IF EXISTS ' . $quotedTable . ";\n");
        fwrite($backup, $createRow['Create Table'] . ";\n\n");

        $columnsResult = $conn->query('SHOW COLUMNS FROM ' . $quotedTable);
        $columns = [];
        $columnTypes = [];

        while ($column = $columnsResult->fetch_assoc()) {
            $columns[] = (string)$column['Field'];
            $columnTypes[] = (string)$column['Type'];
        }
        $columnsResult->free();

        $dataResult = $conn->query('SELECT * FROM ' . $quotedTable, MYSQLI_USE_RESULT);
        $columnList = implode(', ', array_map('quoteBackupIdentifier', $columns));

        while ($row = $dataResult->fetch_row()) {
            $values = [];
            foreach ($row as $index => $value) {
                $values[] = formatBackupValue($conn, $value, $columnTypes[$index]);
            }
            fwrite(
                $backup,
                'INSERT INTO ' . $quotedTable . ' (' . $columnList . ') VALUES ('
                . implode(', ', $values) . ");\n"
            );
        }
        $dataResult->free();
        fwrite($backup, "\n");
    }

    fwrite($backup, "SET FOREIGN_KEY_CHECKS=1;\n");
    rewind($backup);

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    fpassthru($backup);
    fclose($backup);
} catch (Throwable $exception) {
    error_log('Database backup download failed: ' . $exception->getMessage());

    if (isset($backup) && is_resource($backup)) {
        fclose($backup);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, private');
    }

    exit('Unable to generate the database backup. Check the server error log.');
}
