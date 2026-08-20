<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/tokens.php';

function redirect(string $path, array $params = []): never
{
    $qs = $params ? ('?' . http_build_query($params)) : '';
    header('Location: ' . $path . $qs);
    exit;
}

function generateNumericCode(int $length): string
{
    $min = (int) str_pad('1', $length, '0');
    $max = (int) str_pad('', $length, '9');
    return (string) random_int($min, $max);
}

function issueMfaCode(int $userId, string $email): void
{
    $code = generateNumericCode(MFA_CODE_LENGTH);
    $codeHash = hash('sha256', $code);
    $expiresAt = (new DateTimeImmutable())->modify('+' . MFA_CODE_TTL_MINUTES . ' minutes');

    $stmt = getDb()->prepare(
        'INSERT INTO mfa_codes (user_id, code_hash, expires_at) VALUES (:user_id, :code_hash, :expires_at)'
    );
    $stmt->execute([
        'user_id'    => $userId,
        'code_hash'  => $codeHash,
        'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
    ]);

    sendVerificationCode($email, $code);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    case 'register': {
        requireValidCsrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || $department === '' || $phone === '') {
            redirect('/register.html', ['error' => 'Please fill in your name, department, and phone number.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('/register.html', ['error' => 'Please enter a valid email address.']);
        }
        if (strlen($password) < 8) {
            redirect('/register.html', ['error' => 'Password must be at least 8 characters.']);
        }

        $db = getDb();
        $existing = $db->prepare('SELECT id FROM users WHERE email = :email');
        $existing->execute(['email' => $email]);
        if ($existing->fetch()) {
            redirect('/register.html', ['error' => 'An account with that email already exists.']);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $insert = $db->prepare(
            'INSERT INTO users (name, department, phone, email, password_hash)
             VALUES (:name, :department, :phone, :email, :password_hash)'
        );
        $insert->execute([
            'name'          => $name,
            'department'    => $department,
            'phone'         => $phone,
            'email'         => $email,
            'password_hash' => $hash,
        ]);

        redirect('/index.html', ['success' => 'Account created. Please log in.']);
    }

    case 'login': {
        requireValidCsrf();

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $genericError = ['error' => 'Invalid email or password.'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            redirect('/index.html', $genericError);
        }

        $db = getDb();
        $stmt = $db->prepare('SELECT id, email, password_hash, role, failed_login_attempts, locked_until FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            redirect('/index.html', $genericError);
        }

        if ($user['locked_until'] !== null && new DateTimeImmutable($user['locked_until']) > new DateTimeImmutable()) {
            redirect('/index.html', ['error' => 'Account temporarily locked due to failed attempts. Try again later.']);
        }

        if (!password_verify($password, $user['password_hash'])) {
            $attempts = (int) $user['failed_login_attempts'] + 1;
            $lockedUntil = null;
            if ($attempts >= LOGIN_MAX_ATTEMPTS) {
                $lockedUntil = (new DateTimeImmutable())->modify('+' . LOGIN_LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:sP');
                $attempts = 0;
            }
            $update = $db->prepare('UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked_until WHERE id = :id');
            $update->execute(['attempts' => $attempts, 'locked_until' => $lockedUntil, 'id' => $user['id']]);
            redirect('/index.html', $genericError);
        }

        $reset = $db->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
        $reset->execute(['id' => $user['id']]);

        issueMfaCode((int) $user['id'], $user['email']);

        $_SESSION['pending_user_id'] = $user['id'];
        $_SESSION['pending_user_email'] = $user['email'];
        $_SESSION['pending_role'] = $user['role'];
        unset($_SESSION['user_id'], $_SESSION['role']);

        redirect('/verify.html');
    }

    case 'verify-code': {
        requireValidCsrf();

        $pendingUserId = $_SESSION['pending_user_id'] ?? null;
        if ($pendingUserId === null) {
            redirect('/index.html', ['error' => 'Please log in again.']);
        }

        $submitted = trim((string) ($_POST['code'] ?? ''));
        $db = getDb();

        $stmt = $db->prepare(
            'SELECT id, code_hash, attempts FROM mfa_codes
             WHERE user_id = :user_id AND used = false AND expires_at > now()
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $pendingUserId]);
        $mfa = $stmt->fetch();

        if (!$mfa) {
            redirect('/verify.html', ['error' => 'Code expired. Please request a new one.']);
        }

        if ((int) $mfa['attempts'] >= MFA_MAX_ATTEMPTS) {
            $db->prepare('UPDATE mfa_codes SET used = true WHERE id = :id')->execute(['id' => $mfa['id']]);
            redirect('/verify.html', ['error' => 'Too many incorrect attempts. Please request a new code.']);
        }

        if (!hash_equals($mfa['code_hash'], hash('sha256', $submitted))) {
            $db->prepare('UPDATE mfa_codes SET attempts = attempts + 1 WHERE id = :id')->execute(['id' => $mfa['id']]);
            redirect('/verify.html', ['error' => 'Incorrect code. Please try again.']);
        }

        $db->prepare('UPDATE mfa_codes SET used = true WHERE id = :id')->execute(['id' => $mfa['id']]);
        $db->prepare('UPDATE users SET logged_in_at = now() WHERE id = :id')->execute(['id' => $pendingUserId]);

        $_SESSION['user_id'] = $pendingUserId;
        $_SESSION['role'] = $_SESSION['pending_role'] ?? 'employee';
        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_email'], $_SESSION['pending_role']);
        session_regenerate_id(true);

        redirect('/dashboard.html');
    }

    case 'resend-code': {
        requireValidCsrf();

        $pendingUserId = $_SESSION['pending_user_id'] ?? null;
        $pendingEmail = $_SESSION['pending_user_email'] ?? null;
        if ($pendingUserId === null || $pendingEmail === null) {
            redirect('/index.html', ['error' => 'Please log in again.']);
        }

        $db = getDb();
        $stmt = $db->prepare(
            'SELECT created_at FROM mfa_codes WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $pendingUserId]);
        $last = $stmt->fetch();

        if ($last && new DateTimeImmutable($last['created_at']) > (new DateTimeImmutable())->modify('-60 seconds')) {
            redirect('/verify.html', ['error' => 'Please wait a minute before requesting another code.']);
        }

        issueMfaCode((int) $pendingUserId, $pendingEmail);
        redirect('/verify.html', ['success' => 'A new code has been sent.']);
    }

    case 'forgot-password': {
        requireValidCsrf();

        $email = trim((string) ($_POST['email'] ?? ''));
        $genericSuccess = ['success' => 'If that email is registered, a login link has been sent.'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('/forgot-password.html', $genericSuccess);
        }

        $stmt = getDb()->prepare('SELECT id, email FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $link = issueLoginLink((int) $user['id']);
            sendMagicLoginLink($user['email'], $link);
        }

        redirect('/forgot-password.html', $genericSuccess);
    }

    case 'logout': {
        $_SESSION = [];
        session_destroy();
        redirect('/index.html');
    }

    default:
        redirect('/index.html');
}
