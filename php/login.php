<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';
require_once __DIR__ . '/rbac_audit.php';

function needsInitialPasswordChange(mysqli $conn, string $accountType, int $accountId): ?bool
{
    try {
        $tables = $conn->query('SHOW TABLES');
        if (!$tables) {
            error_log('Initial password table check failed: ' . $conn->error);
            return null;
        }

        $tableExists = false;

        while ($table = $tables->fetch_row()) {
            if ($table[0] === 'initial_password_changes') {
                $tableExists = true;
                break;
            }
        }
        $tables->free();

        if (!$tableExists) {
            return false;
        }

        $stmt = $conn->prepare(
            'SELECT 1 FROM initial_password_changes
             WHERE account_type = ? AND account_id = ? LIMIT 1'
        );

        if (!$stmt) {
            error_log('Initial password status query prepare failed: ' . $conn->error);
            return null;
        }

        $stmt->bind_param('si', $accountType, $accountId);

        if (!$stmt->execute()) {
            error_log('Initial password status query failed: ' . $stmt->error);
            $stmt->close();
            return null;
        }

        $required = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $required;
    } catch (mysqli_sql_exception $exception) {
        error_log('Initial password status lookup failed: ' . $exception->getMessage());
        return null;
    }
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $school_id = trim($_POST['school_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($school_id === '' || $password === '') {
        $error_msg = 'Please enter your School ID, Staff ID, Teacher ID, or Admin ID and password.';
    } elseif (!isset($conn) || !$conn) {
        $error_msg = 'Database connection failed.';
    } else {
        $account_found = false;

        // ==========================================
        // 1. CHECK ADMIN ACCOUNT USING ADMIN ID
        // ==========================================
        $stmt = $conn->prepare(
            "SELECT * FROM admin WHERE admin_id_no = ? LIMIT 2"
        );

        if (!$stmt) {
            error_log('Admin login query failed: ' . $conn->error);
            $error_msg = 'Unable to process your login. Please try again.';
        } else {
            $stmt->bind_param('s', $school_id);
            if (!$stmt->execute()) {
                error_log('Admin login execution failed: ' . $stmt->error);
                $error_msg = 'Unable to process your login. Please try again.';
            } else {
                $result = $stmt->get_result();

                if (!$result) {
                    error_log('Admin login result failed: ' . $stmt->error);
                    $error_msg = 'Unable to process your login. Please try again.';
                } elseif ($result->num_rows > 1) {
                    $account_found = true;
                    $error_msg = 'This admin login ID matches multiple accounts. Contact an administrator.';
                } elseif ($result->num_rows === 1) {
                    $account_found = true;
                    $admin = $result->fetch_assoc();
                    $stored_password = (string) $admin['password'];
                    $password_valid = password_verify($password, $stored_password);

                    if (
                        !$password_valid &&
                        $stored_password !== '' &&
                        hash_equals($stored_password, $password)
                    ) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $rehash_stmt = $conn->prepare(
                            'UPDATE admin SET password = ? WHERE id = ?'
                        );

                        if (!$rehash_stmt) {
                            error_log('Admin password rehash prepare failed: ' . $conn->error);
                            $error_msg = 'Unable to process your login. Please try again.';
                        } else {
                            $admin_id = (int) $admin['id'];
                            $rehash_stmt->bind_param('si', $hashed_password, $admin_id);

                            if ($rehash_stmt->execute()) {
                                $password_valid = true;
                            } else {
                                error_log('Admin password rehash failed: ' . $rehash_stmt->error);
                                $error_msg = 'Unable to process your login. Please try again.';
                            }

                            $rehash_stmt->close();
                        }
                    }

                    if ($password_valid) {
                        session_regenerate_id(true);

                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_username'] = $admin['username'] ?? $admin['email'] ?? 'Administrator';
                        $_SESSION['admin_id_no'] = $admin['admin_id_no'];
                        $_SESSION['user_role'] = 'Administrator';
                        $_SESSION['user_id'] = (int)$admin['id'];
                        rbacLog($conn, 'login', 'Authentication', 'Session', session_id(), 'Administrator logged in');

                        header('Location: Admin_Dashboard/admin_dashboard.php');
                        exit;
                    } elseif ($error_msg === '') {
                        $error_msg = 'Invalid ID or password.';
                    }
                }
            }

            $stmt->close();
        }

        // ==========================================
        // 2A. CHECK TEACHER ACCOUNT USING TEACHER ID
        // ==========================================
        if (!$account_found && $error_msg === '') {
            $teachers_table = $conn->query("SHOW TABLES LIKE 'teachers'");

            if (!$teachers_table) {
                error_log('Teachers table check failed: ' . $conn->error);
                $error_msg = 'Unable to process your login. Please try again.';
            } elseif ($teachers_table->num_rows > 0) {
                $stmt_teacher = $conn->prepare(
                    "SELECT id, teacher_id_no, fullname, password, status
                     FROM teachers
                     WHERE teacher_id_no = ?
                     LIMIT 2"
                );

                if (!$stmt_teacher) {
                    error_log('Teacher ID login query failed: ' . $conn->error);
                    $error_msg = 'Unable to process your login. Please try again.';
                } else {
                    $stmt_teacher->bind_param('s', $school_id);
                    if (!$stmt_teacher->execute()) {
                        error_log('Teacher ID login execution failed: ' . $stmt_teacher->error);
                        $error_msg = 'Unable to process your login. Please try again.';
                    } else {
                        $result_teacher = $stmt_teacher->get_result();

                        if (!$result_teacher) {
                            error_log('Teacher ID login result failed: ' . $stmt_teacher->error);
                            $error_msg = 'Unable to process your login. Please try again.';
                        } elseif ($result_teacher->num_rows > 1) {
                            $account_found = true;
                            $error_msg = 'This Teacher ID matches multiple accounts. Contact an administrator.';
                        } elseif ($result_teacher->num_rows === 1) {
                            $account_found = true;
                            $teacher_user = $result_teacher->fetch_assoc();
                            $stored_password = (string) ($teacher_user['password'] ?? '');
                            $password_valid = password_verify($password, $stored_password);

                            if (
                                !$password_valid &&
                                $stored_password !== '' &&
                                hash_equals($stored_password, $password)
                            ) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $rehash_stmt = $conn->prepare(
                                    'UPDATE teachers SET password = ? WHERE id = ?'
                                );

                                if (!$rehash_stmt) {
                                    error_log('Teacher password rehash prepare failed: ' . $conn->error);
                                    $error_msg = 'Unable to process your login. Please try again.';
                                } else {
                                    $teacher_record_id = (int) $teacher_user['id'];
                                    $rehash_stmt->bind_param('si', $hashed_password, $teacher_record_id);

                                    if ($rehash_stmt->execute()) {
                                        $password_valid = true;
                                    } else {
                                        error_log('Teacher password rehash failed: ' . $rehash_stmt->error);
                                        $error_msg = 'Unable to process your login. Please try again.';
                                    }

                                    $rehash_stmt->close();
                                }
                            }

                            if (strtolower((string)($teacher_user['status'] ?? 'Active')) !== 'active') {
                                $error_msg = 'Invalid ID or password.';
                            } elseif ($password_valid) {
                                $teacherId = (int) $teacher_user['id'];
                                $mustChangePassword = needsInitialPasswordChange(
                                    $conn,
                                    'teacher',
                                    $teacherId
                                );

                                if ($mustChangePassword === null) {
                                    $error_msg = 'Unable to process your login. Please try again.';
                                } else {
                                    session_regenerate_id(true);

                                    $_SESSION['admin_logged_in'] = true;
                                    $_SESSION['admin_username'] = $teacher_user['fullname'] ?? 'Teacher';
                                    $_SESSION['user_role'] = 'Teacher';
                                    $_SESSION['user_id'] = $teacherId;
                                    $_SESSION['staff_id'] = $teacherId;
                                    $_SESSION['auth_account_type'] = 'teacher';
                                    $_SESSION['auth_account_id'] = $teacherId;
                                    $_SESSION['must_change_password'] = $mustChangePassword;
                                    rbacLog($conn, 'login', 'Authentication', 'Session', session_id(), 'Teacher logged in');

                                    if ($mustChangePassword) {
                                        header('Location: change_initial_password.php');
                                        exit;
                                    }

                                    header('Location: Teacher_Dashboard/teacher_dashboard.php');
                                    exit;
                                }
                            } elseif ($error_msg === '') {
                                $error_msg = 'Invalid ID or password.';
                            }
                        }
                    }

                    $stmt_teacher->close();
                }
            }

            if ($teachers_table) {
                $teachers_table->free();
            }
        }

        // ==========================================
        // 2B. CHECK STAFF ACCOUNT USING STAFF ID
        // ==========================================
        if (!$account_found && $error_msg === '') {
            $staff_columns = $conn->query(
                "SHOW COLUMNS FROM `staff`
                 WHERE Field IN ('staff_id_no', 'password')"
            );

            if (!$staff_columns) {
                error_log('Staff login columns check failed: ' . $conn->error);
                $error_msg = 'Unable to process your login. Please try again.';
            } elseif ($staff_columns->num_rows !== 2) {
                $error_msg = 'Staff ID or password is not configured in the database.';
            } else {
                $stmt_staff = $conn->prepare(
                    "SELECT id, fullname, password, role FROM staff
                     WHERE staff_id_no = ?
                     LIMIT 2"
                );

                if (!$stmt_staff) {
                    error_log('Staff ID login query failed: ' . $conn->error);
                    $error_msg = 'Unable to process your login. Please try again.';
                } else {
                    $stmt_staff->bind_param('s', $school_id);
                    if (!$stmt_staff->execute()) {
                        error_log('Staff ID login execution failed: ' . $stmt_staff->error);
                        $error_msg = 'Unable to process your login. Please try again.';
                    } else {
                        $result_staff = $stmt_staff->get_result();

                        if (!$result_staff) {
                            error_log('Staff ID login result failed: ' . $stmt_staff->error);
                            $error_msg = 'Unable to process your login. Please try again.';
                        } elseif ($result_staff->num_rows === 1) {
                            $account_found = true;
                            $staff_user = $result_staff->fetch_assoc();
                            $stored_password = (string) ($staff_user['password'] ?? '');
                            $password_valid = password_verify($password, $stored_password);

                            if (
                                !$password_valid &&
                                $stored_password !== '' &&
                                hash_equals($stored_password, $password)
                            ) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $rehash_stmt = $conn->prepare(
                                    'UPDATE staff SET password = ? WHERE id = ?'
                                );

                                if (!$rehash_stmt) {
                                    error_log('Staff password rehash prepare failed: ' . $conn->error);
                                    $error_msg = 'Unable to process your login. Please try again.';
                                } else {
                                    $staff_id = (int) $staff_user['id'];
                                    $rehash_stmt->bind_param(
                                        'si',
                                        $hashed_password,
                                        $staff_id
                                    );

                                    if ($rehash_stmt->execute()) {
                                        $password_valid = true;
                                    } else {
                                        error_log('Staff password rehash failed: ' . $rehash_stmt->error);
                                        $error_msg = 'Unable to process your login. Please try again.';
                                    }

                                    $rehash_stmt->close();
                                }
                            }

                            if ($password_valid) {
                                $staffRole = strtolower(trim(
                                    (string) ($staff_user['role'] ?? '')
                                ));
                                $isTeacher = $staffRole === 'teacher';
                                $staffId = (int) $staff_user['id'];
                                $mustChangePassword = needsInitialPasswordChange(
                                    $conn,
                                    'staff',
                                    $staffId
                                );

                                if ($mustChangePassword === null) {
                                    $error_msg = 'Unable to process your login. Please try again.';
                                } else {
                                    session_regenerate_id(true);

                                    $_SESSION['admin_logged_in'] = true;
                                    $_SESSION['admin_username'] = $staff_user['fullname'] ?? ($isTeacher ? 'Teacher' : 'Staff');
                                    $_SESSION['user_role'] = $isTeacher ? 'Teacher' : 'Staff';
                                    $_SESSION['user_id'] = $staffId;
                                    $_SESSION['staff_id'] = $staffId;
                                    $_SESSION['auth_account_type'] = 'staff';
                                    $_SESSION['auth_account_id'] = $staffId;
                                    $_SESSION['must_change_password'] = $mustChangePassword;
                                    rbacLog($conn, 'login', 'Authentication', 'Session', session_id(), 'Staff or Teacher logged in');

                                    if ($mustChangePassword) {
                                        header('Location: change_initial_password.php');
                                        exit;
                                    }

                                    header(
                                        'Location: ' . (
                                            $isTeacher
                                            ? 'Teacher_Dashboard/teacher_dashboard.php'
                                            : 'Staff_Dashboard/staff_dashboard.php'
                                        )
                                    );
                                    exit;
                                }
                            } elseif ($error_msg === '') {
                                $error_msg = 'Invalid ID or password.';
                            }
                        } elseif ($result_staff->num_rows > 1) {
                            $account_found = true;
                            $error_msg = 'This Staff ID is assigned to multiple accounts. Contact an administrator.';
                        }
                    }

                    $stmt_staff->close();
                }
            }

            if ($staff_columns) {
                $staff_columns->free();
            }
        }

        // ==========================================
        // 3. CHECK USER / STUDENT ACCOUNT USING SCHOOL ID
        // ==========================================
        if (!$account_found && $error_msg === '') {
            $users_table = $conn->query("SHOW TABLES LIKE 'users'");

            if (!$users_table) {
                error_log('Users table check failed: ' . $conn->error);
                $error_msg = 'Unable to process your login. Please try again.';
            } elseif ($users_table->num_rows > 0) {
                $stmt_user = $conn->prepare(
                    "SELECT * FROM users
                     WHERE student_id_no = ?
                     LIMIT 2"
                );

                if (!$stmt_user) {
                    error_log('Student user login query failed: ' . $conn->error);
                    $error_msg = 'Unable to process your login. Please try again.';
                } else {
                    $stmt_user->bind_param('s', $school_id);

                    if (!$stmt_user->execute()) {
                        error_log('Student user login execution failed: ' . $stmt_user->error);
                        $error_msg = 'Unable to process your login. Please try again.';
                    } else {
                        $result_user = $stmt_user->get_result();

                        if (!$result_user) {
                            error_log('Student user login result failed: ' . $stmt_user->error);
                            $error_msg = 'Unable to process your login. Please try again.';
                        } elseif ($result_user->num_rows > 1) {
                            $account_found = true;
                            $error_msg = 'This Student ID is assigned to multiple accounts. Contact an administrator.';
                        } elseif ($result_user->num_rows > 0) {
                            $account_found = true;
                            $user = $result_user->fetch_assoc();
                            $stored_password = (string) $user['password'];
                            $password_valid = password_verify($password, $stored_password);

                            if (
                                !$password_valid &&
                                $stored_password !== '' &&
                                hash_equals($stored_password, $password)
                            ) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $rehash_stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                                if ($rehash_stmt) {
                                    $uid = (int)$user['id'];
                                    $rehash_stmt->bind_param('si', $hashed_password, $uid);
                                    if ($rehash_stmt->execute()) {
                                        $password_valid = true;
                                    }
                                    $rehash_stmt->close();
                                }
                            }

                            if (strtolower((string)($user['status'] ?? 'Active')) !== 'active') {
                                $error_msg = 'Invalid ID or password.';
                            } elseif ($password_valid) {
                                $role = strtolower(trim($user['role'] ?? ''));

                                if ($role === '') {
                                    $role = 'student';
                                }

                                $userId = (int)$user['id'];
                                $accountType = $role === 'student' ? 'student' : null;
                                $mustChangePassword = $accountType !== null
                                    ? needsInitialPasswordChange($conn, $accountType, $userId)
                                    : false;

                                if ($mustChangePassword === null) {
                                    $error_msg = 'Unable to process your login. Please try again.';
                                } else {
                                    session_regenerate_id(true);

                                    $_SESSION['admin_logged_in'] = true;
                                    $_SESSION['admin_username'] = $user['name'] ?? 'User';
                                    $_SESSION['user_role'] = ucfirst($role);
                                    $_SESSION['user_id'] = $userId;
                                    $_SESSION['student_id_no'] = $user['student_id_no'] ?? '';
                                    $_SESSION['email'] = $user['email'] ?? '';

                                    if ($accountType !== null) {
                                        $_SESSION['auth_account_type'] = $accountType;
                                        $_SESSION['auth_account_id'] = $userId;
                                        $_SESSION['must_change_password'] = $mustChangePassword;

                                        if ($mustChangePassword) {
                                            header('Location: change_initial_password.php');
                                            exit;
                                        }
                                    }

                                    if ($role === 'staff') {
                                        header('Location: Staff_Dashboard/staff_dashboard.php');
                                        exit;
                                    } elseif ($role === 'teacher') {
                                        header('Location: Teacher_Dashboard/teacher_dashboard.php');
                                        exit;
                                    } elseif ($role === 'administrator' || $role === 'admin') {
                                        header('Location: Admin_Dashboard/admin_dashboard.php');
                                        exit;
                                    } else {
                                        header('Location: Student_Dashboard/student_dashboard.php');
                                        exit;
                                    }
                                }
                            } else {
                                $error_msg = 'Invalid ID or password.';
                            }
                        }
                    }

                    $stmt_user->close();
                }
            }

            if ($users_table) {
                $users_table->free();
            }
        }

        // ==========================================
        // 4. CHECK STUDENT RECORD USING SCHOOL ID
        // ==========================================
        if (!$account_found && $error_msg === '') {
            $students_table = $conn->query("SHOW TABLES LIKE 'students'");

            if (!$students_table) {
                error_log('Students table check failed: ' . $conn->error);
                $error_msg = 'Unable to process your login. Please try again.';
            } elseif ($students_table->num_rows === 0) {
                $error_msg = 'Invalid ID or password.';
            } else {
                $stmt_student = $conn->prepare(
                    "SELECT id, user_id, student_id_no, fullname, email, password
                     FROM students
                     WHERE student_id_no = ?
                     LIMIT 2"
                );

                if (!$stmt_student) {
                    error_log('Student record login query failed: ' . $conn->error);
                    $error_msg = 'Unable to process your login. Please try again.';
                } else {
                    $stmt_student->bind_param('s', $school_id);

                    if (!$stmt_student->execute()) {
                        error_log('Student record login execution failed: ' . $stmt_student->error);
                        $error_msg = 'Unable to process your login. Please try again.';
                    } else {
                        $result_student = $stmt_student->get_result();

                        if (!$result_student) {
                            error_log('Student record login result failed: ' . $stmt_student->error);
                            $error_msg = 'Unable to process your login. Please try again.';
                        } elseif ($result_student->num_rows > 1) {
                            $account_found = true;
                            $error_msg = 'This School ID is assigned to multiple student records. Contact an administrator.';
                        } elseif ($result_student->num_rows === 1) {
                            $account_found = true;
                            $student = $result_student->fetch_assoc();
                            $stored_password = (string) ($student['password'] ?? '');
                            $password_valid = password_verify($password, $stored_password);

                            if (
                                !$password_valid &&
                                $stored_password !== '' &&
                                hash_equals($stored_password, $password)
                            ) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $rehash_stmt = $conn->prepare(
                                    'UPDATE students SET password = ? WHERE id = ?'
                                );

                                if (!$rehash_stmt) {
                                    error_log('Student password rehash prepare failed: ' . $conn->error);
                                    $error_msg = 'Unable to process your login. Please try again.';
                                } else {
                                    $student_record_id = (int) $student['id'];
                                    $rehash_stmt->bind_param(
                                        'si',
                                        $hashed_password,
                                        $student_record_id
                                    );

                                    if ($rehash_stmt->execute()) {
                                        $password_valid = true;
                                    } else {
                                        error_log('Student password rehash failed: ' . $rehash_stmt->error);
                                        $error_msg = 'Unable to process your login. Please try again.';
                                    }

                                    $rehash_stmt->close();
                                }
                            }

                            if ($password_valid) {
                                $studentRecordId = (int) $student['id'];
                                $studentLookupId = (int) ($student['user_id'] ?? 0);
                                $passwordCheckId = $studentLookupId > 0 ? $studentLookupId : $studentRecordId;
                                $mustChangePassword = needsInitialPasswordChange(
                                    $conn,
                                    'student',
                                    $passwordCheckId
                                );

                                if ($mustChangePassword === null) {
                                    $error_msg = 'Unable to process your login. Please try again.';
                                } else {
                                    session_regenerate_id(true);

                                    $_SESSION['admin_logged_in'] = true;
                                    $_SESSION['admin_username'] = $student['fullname'] ?? 'Student';
                                    $_SESSION['user_role'] = 'Student';
                                    $_SESSION['user_id'] = $studentLookupId;
                                    $_SESSION['student_id_no'] = $student['student_id_no'];
                                    $_SESSION['email'] = $student['email'] ?? '';
                                    $_SESSION['auth_account_type'] = 'student';
                                    $_SESSION['auth_account_id'] = $passwordCheckId;
                                    $_SESSION['must_change_password'] = $mustChangePassword;
                                    rbacLog($conn, 'login', 'Authentication', 'Session', session_id(), 'Student logged in');

                                    if ($mustChangePassword) {
                                        header('Location: change_initial_password.php');
                                        exit;
                                    }

                                    header('Location: Student_Dashboard/student_dashboard.php');
                                    exit;
                                }
                            } elseif ($error_msg === '') {
                                $error_msg = 'Invalid ID or password.';
                            }
                        } else {
                            $error_msg = 'Invalid ID or password.';
                        }
                    }

                    $stmt_student->close();
                }
            }

            if ($students_table) {
                $students_table->free();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SHRCMS</title>
    <link rel="stylesheet" href="../css/style.css?v=20261114-login-height">
</head>

<body class="login-wrapper">

    <main class="login-card">
        <section class="login-right-section" aria-labelledby="login-title">

            <h2 id="login-title">Log In</h2>

            <?php if ($error_msg !== ''): ?>
                <div
                    class="login-error"
                    role="alert">
                    <?= htmlspecialchars(
                        $error_msg,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">

                <div class="login-input-group">
                    <img
                        class="input-icon"
                        src="../image/login-id-icon.svg"
                        alt=""
                        aria-hidden="true">
                    <input
                        type="text"
                        name="school_id"
                        required
                        maxlength="50"
                        autocomplete="username"
                        aria-label="School ID, Staff ID, Teacher ID, or Admin ID"
                        placeholder="e.g. STAFF-0001 or ADMIN-0001"
                        value="<?= htmlspecialchars(
                                    $_POST['school_id'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>">
                </div>

                <div class="login-input-group">
                    <img
                        class="input-icon"
                        src="../image/login-lock-icon.svg"
                        alt=""
                        aria-hidden="true">
                    <input
                        type="password"
                        name="password"
                        id="loginPassword"
                        required
                        autocomplete="current-password"
                        aria-label="Password"
                        placeholder="Enter Password">

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                        aria-label="Show password"
                        aria-pressed="false"
                        data-show-icon="../image/login-eye-icon.svg"
                        data-hide-icon="../image/login-eye-off-icon.svg">
                        <img
                            src="../image/login-eye-icon.svg"
                            alt=""
                            aria-hidden="true">
                    </button>
                </div>

                <div class="login-options">
                    <label>
                        <input
                            type="checkbox"
                            name="remember_me"
                            value="1">
                        Remember me
                    </label>

                    <a href="forgot_password.php" class="forgot-link">
                        Forgot password?
                    </a>
                </div>

                <button
                    type="submit"
                    class="login-submit-btn">
                    Login
                </button>

            </form>

        </section>
    </main>

    <script src="../js/login.js?v=20261114-login"></script>

</body>

</html>