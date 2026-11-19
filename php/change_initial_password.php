<?php
session_start();

$accountType = $_SESSION['auth_account_type'] ?? '';
$accountId = (int)($_SESSION['auth_account_id'] ?? 0);
$role = $_SESSION['user_role'] ?? '';
$validAccountRole = ($accountType === 'student' && $role === 'Student') ||
    ($accountType === 'staff' && in_array($role, ['Staff', 'Teacher'], true)) ||
    ($accountType === 'teacher' && $role === 'Teacher');

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !$validAccountRole ||
    $accountId < 1
) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/db.php';
$conn->set_charset('utf8mb4');

if (empty($_SESSION['initial_password_csrf'])) {
    $_SESSION['initial_password_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['initial_password_csrf'];
$errorMessage = '';
$successTarget = $role === 'Student'
    ? 'Student_Dashboard/student_dashboard.php'
    : ($role === 'Teacher'
        ? 'Teacher_Dashboard/teacher_dashboard.php'
        : 'Staff_Dashboard/staff_dashboard.php');

try {
    $requiredStmt = $conn->prepare(
        'SELECT 1 FROM initial_password_changes
         WHERE account_type = ? AND account_id = ? LIMIT 1'
    );
    $requiredStmt->bind_param('si', $accountType, $accountId);
    $requiredStmt->execute();
    $mustChange = $requiredStmt->get_result()->num_rows > 0;
    $requiredStmt->close();

    if (!$mustChange) {
        unset(
            $_SESSION['must_change_password'],
            $_SESSION['auth_account_type'],
            $_SESSION['auth_account_id']
        );
        header('Location: ' . $successTarget);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (
            !is_string($submittedToken) ||
            !hash_equals($csrfToken, $submittedToken)
        ) {
            $errorMessage = 'Your form expired. Refresh the page and try again.';
        } elseif (!is_string($newPassword) || strlen($newPassword) < 12) {
            $errorMessage = 'Choose a new password with at least 12 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'The new passwords do not match.';
        } else {
            if ($accountType === 'student') {
                $currentStmt = $conn->prepare(
                    'SELECT password FROM users WHERE id = ? LIMIT 1'
                );
            } else {
                $currentStmt = $conn->prepare(
                    'SELECT password FROM staff WHERE id = ? LIMIT 1'
                );
            }

            $currentStmt->bind_param('i', $accountId);
            $currentStmt->execute();
            $currentPassword = (string)(
                $currentStmt->get_result()->fetch_assoc()['password'] ?? ''
            );
            $currentStmt->close();

            if ($currentPassword === '') {
                $errorMessage = 'Unable to find your account password. Contact an administrator.';
            } elseif (password_verify($newPassword, $currentPassword)) {
                $errorMessage = 'Your new password must be different from your birthday password.';
            } else {
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                try {
                    $conn->begin_transaction();

                    if ($accountType === 'student') {
                        $updateStmt = $conn->prepare(
                            'UPDATE users SET password = ? WHERE id = ?'
                        );
                        $updateStmt->bind_param('si', $newPasswordHash, $accountId);
                        $updateStmt->execute();
                        $updateStmt->close();

                        $studentStmt = $conn->prepare(
                            'UPDATE students SET password = ?
                             WHERE user_id = ?'
                        );
                        $studentStmt->bind_param('si', $newPasswordHash, $accountId);
                        $studentStmt->execute();
                        $studentStmt->close();
                    } else {
                        $updateStmt = $conn->prepare(
                            'UPDATE staff SET password = ? WHERE id = ?'
                        );
                        $updateStmt->bind_param('si', $newPasswordHash, $accountId);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }

                    $removeFlagStmt = $conn->prepare(
                        'DELETE FROM initial_password_changes
                         WHERE account_type = ? AND account_id = ?'
                    );
                    $removeFlagStmt->bind_param('si', $accountType, $accountId);
                    $removeFlagStmt->execute();
                    $removedFlag = $removeFlagStmt->affected_rows === 1;
                    $removeFlagStmt->close();

                    if (!$removedFlag) {
                        throw new RuntimeException('Initial password flag was not found.');
                    }

                    $conn->commit();
                    session_regenerate_id(true);
                    unset(
                        $_SESSION['must_change_password'],
                        $_SESSION['auth_account_type'],
                        $_SESSION['auth_account_id']
                    );
                    header('Location: ' . $successTarget);
                    exit();
                } catch (Throwable $exception) {
                    $conn->rollback();
                    error_log('Initial password update failed.');
                    $errorMessage = 'Unable to update your password. Please try again.';
                }
            }
        }
    }
} catch (mysqli_sql_exception $exception) {
    error_log('Initial password page could not verify the account.');
    http_response_code(500);
    exit('Password setup is unavailable. Contact an administrator.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set a New Password | SHRCMS</title>
    <link rel="stylesheet" href="../css/style.css?v=20261114-login-height">

    <link rel="stylesheet" href="../css/change-initial-password.css">
</head>

<body class="login-wrapper">
    <main class="initial-password-card">
        <h1>Set a new password</h1>
        <p>Your birthday was used as a temporary password. For your security, create a new password to continue.</p>

        <?php if ($errorMessage !== ''): ?>
            <div class="initial-password-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="change_initial_password.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="new-password">New password</label>
            <input id="new-password" name="new_password" type="password"
                   autocomplete="new-password" minlength="12" required>
            <label for="confirm-password">Confirm new password</label>
            <input id="confirm-password" name="confirm_password" type="password"
                   autocomplete="new-password" minlength="12" required>
            <button type="submit">Save new password</button>
        </form>
    </main>
</body>

</html>
