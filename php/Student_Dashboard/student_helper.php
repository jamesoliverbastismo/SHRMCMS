<?php
declare(strict_types=1);

function requireStudentSession(): void
{
    if (!empty($_SESSION['must_change_password'])) {
        header('Location: ../change_initial_password.php');
        exit();
    }

    if (
        !isset($_SESSION['admin_logged_in']) ||
        $_SESSION['admin_logged_in'] !== true ||
        ($_SESSION['user_role'] ?? '') !== 'Student'
    ) {
        header('Location: ../login.php');
        exit();
    }
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function findLinkedStudent(mysqli $conn, int $userId, string $studentIdNo, string $email = '', string $columns = 'id, student_id_no, fullname, age, gender, course_year'): ?array
{
    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT {$columns} FROM students WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }
    }

    if ($studentIdNo !== '') {
        $stmt = $conn->prepare("SELECT {$columns} FROM students WHERE student_id_no = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $studentIdNo);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }
    }

    if ($email !== '') {
        $stmt = $conn->prepare("SELECT {$columns} FROM students WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }
    }

    return null;
}