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

require_once __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

function redirectRoster(string $message, string $type = 'success'): void
{
    header(
        'Location: user_management.php?message=' .
        urlencode($message) . '&type=' . urlencode($type)
    );
    exit();
}

function rosterRowError(int $rowNumber, string $message): RuntimeException
{
    return new RuntimeException('CSV row ' . $rowNumber . ': ' . $message);
}

function findRosterStudent(mysqli $conn, string $studentId): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, user_id FROM students
         WHERE student_id_no COLLATE utf8mb4_unicode_ci =
               ? COLLATE utf8mb4_unicode_ci
         LIMIT 2'
    );
    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 1) {
        $stmt->close();
        throw new RuntimeException('A student ID matches multiple student records.');
    }

    $student = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $student;
}

function findRosterStudentUser(mysqli $conn, string $studentId): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, role, status, student_id_no
         FROM users
         WHERE student_id_no COLLATE utf8mb4_unicode_ci =
               ? COLLATE utf8mb4_unicode_ci
         LIMIT 2'
    );
    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 1) {
        $stmt->close();
        throw new RuntimeException('A student ID is linked to multiple user accounts.');
    }

    $user = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $user;
}

function findRosterStaff(mysqli $conn, string $staffId): ?array
{
    $stmt = $conn->prepare(
        'SELECT id FROM staff
         WHERE staff_id_no COLLATE utf8mb4_unicode_ci =
               ? COLLATE utf8mb4_unicode_ci
         LIMIT 2'
    );
    $stmt->bind_param('s', $staffId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 1) {
        $stmt->close();
        throw new RuntimeException('A staff ID matches multiple staff records.');
    }

    $staff = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $staff;
}

function ensureUniqueUserEmail(
    mysqli $conn,
    ?string $email,
    int $userId
): void {
    if ($email === null) {
        return;
    }

    if ($userId > 0) {
        $stmt = $conn->prepare(
            'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1'
        );
        $stmt->bind_param('si', $email, $userId);
    } else {
        $stmt = $conn->prepare(
            'SELECT id FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
    }

    $stmt->execute();
    $duplicate = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($duplicate) {
        throw new RuntimeException('An email address is already assigned to another user account.');
    }
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_SESSION['user_management_csrf']) ||
    !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals($_SESSION['user_management_csrf'], $_POST['csrf_token'])
) {
    redirectRoster('Invalid form submission. Refresh and try again.', 'error');
}

$upload = $_FILES['roster_csv'] ?? null;

if (
    !is_array($upload) ||
    ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
    !is_string($upload['tmp_name'] ?? null) ||
    !is_uploaded_file($upload['tmp_name'])
) {
    redirectRoster('Choose a valid CSV file to upload.', 'error');
}

if (
    strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION)) !== 'csv' ||
    (int)($upload['size'] ?? 0) > 5 * 1024 * 1024
) {
    redirectRoster('Upload a CSV file no larger than 5 MB.', 'error');
}

$handle = fopen($upload['tmp_name'], 'rb');

if ($handle === false) {
    redirectRoster('The CSV file could not be read.', 'error');
}

$headers = fgetcsv($handle, null, ',', '"', '');
$expectedHeaders = [
    'role',
    'id_number',
    'full_name',
    'email',
    'birthday',
    'course_year',
];

if (!$headers) {
    fclose($handle);
    redirectRoster('The CSV file is empty or missing its header row.', 'error');
}

$headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
$headers = array_map(
    static function ($header): string {
        return strtolower(trim((string)$header));
    },
    $headers
);

if (
    count($headers) !== count(array_unique($headers)) ||
    array_diff($expectedHeaders, $headers)
) {
    fclose($handle);
    redirectRoster(
        'CSV header must include: role, id_number, full_name, email, birthday, course_year.',
        'error'
    );
}

$columnIndexes = array_flip($headers);
$rows = [];
$errors = [];
$seenIds = [];
$seenEmails = [];
$rowNumber = 1;

while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
    $rowNumber++;

    if (count(array_filter($values, static function ($value): bool {
        return trim((string)$value) !== '';
    })) === 0) {
        continue;
    }

    if (count($rows) >= 500) {
        $errors[] = 'The CSV file exceeds the 500-account upload limit.';
        break;
    }

    $values = array_pad($values, count($headers), '');
    $row = [];

    foreach ($expectedHeaders as $header) {
        $row[$header] = trim((string)($values[$columnIndexes[$header]] ?? ''));
    }

    $role = strtolower($row['role']);
    $row['role'] = $role === 'teacher'
        ? 'Teacher'
        : ($role === 'staff' ? 'Staff' : ($role === 'student' ? 'Student' : ''));
    $birthday = DateTimeImmutable::createFromFormat('!Y-m-d', $row['birthday']);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validBirthday = $birthday !== false &&
        $birthday->format('Y-m-d') === $row['birthday'] &&
        ($dateErrors === false || (
            $dateErrors['warning_count'] === 0 &&
            $dateErrors['error_count'] === 0
        )) &&
        $birthday <= new DateTimeImmutable('today');

    $rowError = '';

    if ($row['role'] === '') {
        $rowError = 'role must be Student, Staff, or Teacher.';
    } elseif ($row['id_number'] === '' || strlen($row['id_number']) > 50) {
        $rowError = 'id_number is required and must be 50 characters or fewer.';
    } elseif (
        $row['full_name'] === '' ||
        mb_strlen($row['full_name']) > 100
    ) {
        $rowError = 'full_name is required and must be 100 characters or fewer.';
    } elseif (
        ($row['role'] !== 'Student' && $row['email'] === '') ||
        ($row['email'] !== '' && (
            strlen($row['email']) > 100 ||
            !filter_var($row['email'], FILTER_VALIDATE_EMAIL)
        ))
    ) {
        $rowError = 'email must be valid and 100 characters or fewer; it is required for staff and teachers.';
    } elseif (!$validBirthday) {
        $rowError = 'birthday must be a valid, non-future date in YYYY-MM-DD format.';
    } elseif (strlen($row['course_year']) > 50) {
        $rowError = 'course_year must be 50 characters or fewer.';
    }

    $idKey = strtoupper($row['id_number']);
    $emailKey = strtolower($row['email']);

    if ($rowError === '' && isset($seenIds[$idKey])) {
        $rowError = 'id_number is duplicated in the CSV file.';
    } elseif ($rowError === '' && $emailKey !== '' && isset($seenEmails[$emailKey])) {
        $rowError = 'email is duplicated in the CSV file.';
    }

    if ($rowError !== '') {
        $errors[] = 'Row ' . $rowNumber . ': ' . $rowError;
        if (count($errors) >= 10) {
            $errors[] = 'Additional row errors were omitted.';
            break;
        }
        continue;
    }

    $seenIds[$idKey] = true;
    if ($emailKey !== '') {
        $seenEmails[$emailKey] = true;
    }
    $row['row_number'] = $rowNumber;
    $row['age'] = $birthday->diff(new DateTimeImmutable('today'))->y;
    $rows[] = $row;
}

fclose($handle);

if ($errors) {
    redirectRoster('CSV was not imported. ' . implode(' ', $errors), 'error');
}

if (!$rows) {
    redirectRoster('The CSV file does not contain any account rows.', 'error');
}

$tablesResult = $conn->query('SHOW TABLES');
$initialPasswordTableExists = false;

while ($table = $tablesResult->fetch_row()) {
    if ($table[0] === 'initial_password_changes') {
        $initialPasswordTableExists = true;
        break;
    }
}
$tablesResult->free();

if (!$initialPasswordTableExists) {
    redirectRoster(
        'Password onboarding is not installed. Apply the initial_password_changes table from the project SQL file first.',
        'error'
    );
}

$created = 0;
$updated = 0;
$currentRowNumber = 0;

try {
    $conn->begin_transaction();

    foreach ($rows as $row) {
        $currentRowNumber = (int)$row['row_number'];
        $idNumber = $row['id_number'];
        $name = $row['full_name'];
        $email = $row['email'] === '' ? null : $row['email'];
        $courseYear = $row['course_year'];
        $role = $row['role'];
        $passwordHash = password_hash($row['birthday'], PASSWORD_DEFAULT);

        if ($role === 'Student') {
            if (findRosterStaff($conn, $idNumber)) {
                throw rosterRowError($currentRowNumber, 'this ID already belongs to a staff or teacher account.');
            }

            $student = findRosterStudent($conn, $idNumber);
            $user = findRosterStudentUser($conn, $idNumber);

            if ($student && (int)($student['user_id'] ?? 0) > 0) {
                $linkedUserStmt = $conn->prepare(
                    'SELECT id, role, status, student_id_no
                     FROM users WHERE id = ? LIMIT 1'
                );
                $linkedUserId = (int)$student['user_id'];
                $linkedUserStmt->bind_param('i', $linkedUserId);
                $linkedUserStmt->execute();
                $linkedUser = $linkedUserStmt->get_result()->fetch_assoc();
                $linkedUserStmt->close();

                if (
                    $linkedUser &&
                    $linkedUser['student_id_no'] !== null &&
                    $linkedUser['student_id_no'] !== '' &&
                    strcasecmp($linkedUser['student_id_no'], $idNumber) !== 0
                ) {
                    throw rosterRowError($currentRowNumber, 'the student profile is linked to a different account ID.');
                }

                if ($linkedUser && $user && (int)$linkedUser['id'] !== (int)$user['id']) {
                    throw rosterRowError($currentRowNumber, 'the student ID is linked to conflicting accounts.');
                }

                if ($linkedUser) {
                    $user = $linkedUser;
                }
            }

            if ($user && strtolower((string)$user['role']) !== 'student') {
                throw rosterRowError($currentRowNumber, 'this ID is already assigned to a non-student account.');
            }

            $userId = (int)($user['id'] ?? 0);
            ensureUniqueUserEmail($conn, $email, $userId);

            if ($userId > 0) {
                $userStmt = $conn->prepare(
                    'UPDATE users
                     SET name = ?, email = ?, role = \'Student\', password = ?,
                         student_id_no = ?
                     WHERE id = ?'
                );
                $userStmt->bind_param('ssssi', $name, $email, $passwordHash, $idNumber, $userId);
                $userStmt->execute();
                $userStmt->close();
                $updated++;
            } else {
                $status = 'Active';
                $userStmt = $conn->prepare(
                    'INSERT INTO users (name, email, role, password, status, student_id_no)
                     VALUES (?, ?, \'Student\', ?, ?, ?)'
                );
                $userStmt->bind_param('sssss', $name, $email, $passwordHash, $status, $idNumber);
                $userStmt->execute();
                $userId = (int)$conn->insert_id;
                $userStmt->close();
                $created++;
            }

            if ($student) {
                if ((int)($student['user_id'] ?? 0) > 0 &&
                    (int)$student['user_id'] !== $userId) {
                    throw rosterRowError($currentRowNumber, 'the student profile is linked to a different account.');
                }

                $studentStmt = $conn->prepare(
                    'UPDATE students
                     SET fullname = ?, age = ?,
                         course_year = CASE WHEN ? = \'\' THEN course_year ELSE ? END,
                         user_id = ?, email = ?, password = ?
                     WHERE id = ?'
                );
                $studentStmt->bind_param(
                    'sississi',
                    $name,
                    $row['age'],
                    $courseYear,
                    $courseYear,
                    $userId,
                    $email,
                    $passwordHash,
                    $student['id']
                );
                $studentStmt->execute();
                $studentStmt->close();
            } else {
                $studentStmt = $conn->prepare(
                    'INSERT INTO students
                        (student_id_no, fullname, age, course_year, user_id, email, password)
                     VALUES (?, ?, ?, NULLIF(?, \'\'), ?, ?, ?)'
                );
                $studentStmt->bind_param(
                    'ssisiss',
                    $idNumber,
                    $name,
                    $row['age'],
                    $courseYear,
                    $userId,
                    $email,
                    $passwordHash
                );
                $studentStmt->execute();
                $studentStmt->close();
            }

            $accountType = 'student';
            $accountId = $userId;
        } else {
            $studentCollision = findRosterStudent($conn, $idNumber);
            $studentUserCollision = findRosterStudentUser($conn, $idNumber);

            if ($studentCollision || $studentUserCollision) {
                throw rosterRowError($currentRowNumber, 'this ID already belongs to a student account.');
            }

            $staff = findRosterStaff($conn, $idNumber);
            $staffId = (int)($staff['id'] ?? 0);

            if ($staffId > 0) {
                $staffStmt = $conn->prepare(
                    'UPDATE staff SET fullname = ?, role = ?, email = ?, password = ?
                     WHERE id = ?'
                );
                $staffStmt->bind_param('ssssi', $name, $role, $email, $passwordHash, $staffId);
                $staffStmt->execute();
                $staffStmt->close();
                $updated++;
            } else {
                $staffStmt = $conn->prepare(
                    'INSERT INTO staff (staff_id_no, password, fullname, role, email)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $staffStmt->bind_param('sssss', $idNumber, $passwordHash, $name, $role, $email);
                $staffStmt->execute();
                $staffId = (int)$conn->insert_id;
                $staffStmt->close();
                $created++;
            }

            $accountType = 'staff';
            $accountId = $staffId;
        }

        $initialPasswordStmt = $conn->prepare(
            'INSERT INTO initial_password_changes (account_type, account_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP'
        );
        $initialPasswordStmt->bind_param('si', $accountType, $accountId);
        $initialPasswordStmt->execute();
        $initialPasswordStmt->close();
    }

    $conn->commit();
    redirectRoster(
        'CSV import complete: ' . $created . ' account(s) created and ' .
        $updated . ' updated. Users must change their birthday password at first login.'
    );
} catch (Throwable $exception) {
    $conn->rollback();
    error_log(
        'Roster CSV import failed at row ' . $currentRowNumber .
        '. The transaction was rolled back.'
    );

    if ($exception instanceof RuntimeException) {
        redirectRoster('CSV import failed. No records were changed. ' . $exception->getMessage(), 'error');
    }

    redirectRoster(
        'CSV import failed at row ' . $currentRowNumber .
        '. No records were changed; check the CSV and database setup.',
        'error'
    );
}
