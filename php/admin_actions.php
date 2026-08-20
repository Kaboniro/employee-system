<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/tokens.php';

$adminId = requireAdmin(true);
requireValidCsrf('/admin.html', true);

$action = $_POST['action'] ?? '';
$db = getDb();

switch ($action) {

    case 'add-employee': {
        $name = trim((string) ($_POST['name'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'employee';

        if ($name === '' || $department === '' || $phone === '') {
            jsonResponse(['error' => 'Please fill in name, department, and phone.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Please enter a valid email address.'], 422);
        }

        $existing = $db->prepare('SELECT id FROM users WHERE email = :email');
        $existing->execute(['email' => $email]);
        if ($existing->fetch()) {
            jsonResponse(['error' => 'An account with that email already exists.'], 409);
        }

        // No password is set at creation time; the employee gets in via the
        // one-time login link below and can set a real password afterwards.
        $unusableHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);

        $insert = $db->prepare(
            'INSERT INTO users (name, department, phone, email, password_hash, role)
             VALUES (:name, :department, :phone, :email, :password_hash, :role)
             RETURNING id'
        );
        $insert->execute([
            'name'          => $name,
            'department'    => $department,
            'phone'         => $phone,
            'email'         => $email,
            'password_hash' => $unusableHash,
            'role'          => $role,
        ]);
        $newUserId = (int) $insert->fetchColumn();

        $link = issueLoginLink($newUserId);
        sendMagicLoginLink($email, $link);

        jsonResponse(['success' => true]);
    }

    case 'edit-employee': {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'employee';

        if ($id <= 0 || $name === '' || $department === '' || $phone === '') {
            jsonResponse(['error' => 'Please fill in all fields.'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Please enter a valid email address.'], 422);
        }

        $existing = $db->prepare('SELECT id FROM users WHERE email = :email AND id != :id');
        $existing->execute(['email' => $email, 'id' => $id]);
        if ($existing->fetch()) {
            jsonResponse(['error' => 'Another account already uses that email.'], 409);
        }

        $stmt = $db->prepare(
            'UPDATE users SET name = :name, department = :department, phone = :phone,
             email = :email, role = :role, edited_at = now()
             WHERE id = :id
             RETURNING id, name, department, phone, email, role, logged_in_at, created_at'
        );
        $stmt->execute([
            'name'       => $name,
            'department' => $department,
            'phone'      => $phone,
            'email'      => $email,
            'role'       => $role,
            'id'         => $id,
        ]);
        $employee = $stmt->fetch();

        if (!$employee) {
            jsonResponse(['error' => 'Employee not found.'], 404);
        }

        jsonResponse(['employee' => $employee]);
    }

    case 'delete-employee': {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === $adminId) {
            jsonResponse(['error' => 'You cannot delete your own account.'], 422);
        }

        $db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);

        jsonResponse(['deleted' => true]);
    }

    case 'send-login-link': {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT email FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $employee = $stmt->fetch();

        if (!$employee) {
            jsonResponse(['error' => 'Employee not found.'], 404);
        }

        $link = issueLoginLink($id);
        sendMagicLoginLink($employee['email'], $link);

        jsonResponse(['success' => true, 'email' => $employee['email']]);
    }

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}
