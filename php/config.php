<?php
declare(strict_types=1);

// ---- PostgreSQL connection ----
// Reads from environment variables when present (set these on your host),
// otherwise falls back to local dev defaults so nothing breaks on your
// machine. Supports a single DATABASE_URL (Railway/Render/Heroku-style
// postgres:// connection string) or individual PG*/DB_* vars.
$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl !== false && $databaseUrl !== '') {
    $parts = parse_url($databaseUrl);
    define('DB_HOST', $parts['host'] ?? 'localhost');
    define('DB_PORT', (string) ($parts['port'] ?? '5432'));
    define('DB_NAME', ltrim($parts['path'] ?? '', '/'));
    define('DB_USER', $parts['user'] ?? '');
    define('DB_PASS', $parts['pass'] ?? '');
} else {
    define('DB_HOST', getenv('PGHOST') ?: getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('PGPORT') ?: getenv('DB_PORT') ?: '5432');
    define('DB_NAME', getenv('PGDATABASE') ?: getenv('DB_NAME') ?: 'employee_demo');
    define('DB_USER', getenv('PGUSER') ?: getenv('DB_USER') ?: 'postgres');
    define('DB_PASS', getenv('PGPASSWORD') ?: getenv('DB_PASS') ?: 'emma');
}

// ---- SMTP (PHPMailer) ----
// Falls back to the local Mailpit catcher (localhost:8025 web UI) for dev.
// Set these env vars on your host to use a real provider (or a remote
// Mailtrap sandbox) once deployed.
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'localhost');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 1025));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'no-reply@example.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Employee System');

// ---- MFA settings ----
define('MFA_CODE_LENGTH', 6);
define('MFA_CODE_TTL_MINUTES', 10);
define('MFA_MAX_ATTEMPTS', 5);

// ---- Login lockout settings ----
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ---- Magic login link settings ----
define('LOGIN_LINK_TTL_MINUTES', 15);

// ---- App URL ----
// Base URL this app is served from (no trailing slash). Used to build links
// inside emails. Set APP_BASE_URL on your host to its public URL.
define('APP_BASE_URL', getenv('APP_BASE_URL') ?: 'http://localhost:8000');

// ---- Sessions ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (bool) getenv('APP_HTTPS'), // set APP_HTTPS=1 once deployed behind HTTPS
    ]);
    session_start();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
