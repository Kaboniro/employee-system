<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function requireLogin(bool $json = false): int
{
    if (empty($_SESSION['user_id'])) {
        if ($json) {
            jsonResponse(['error' => 'Not authenticated'], 401);
        }
        header('Location: /index.html');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    // Every authenticated request passes through here, so this doubles as a
    // presence heartbeat: "online" is just "active in the last N minutes."
    getDb()->prepare('UPDATE users SET last_active_at = now() WHERE id = :id')->execute(['id' => $userId]);

    return $userId;
}

function requireAdmin(bool $json = false): int
{
    $userId = requireLogin($json);
    if (($_SESSION['role'] ?? '') !== 'admin') {
        if ($json) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }
        header('Location: /dashboard.html');
        exit;
    }
    return $userId;
}

function requireValidCsrf(string $failurePath = '/index.html', bool $json = false): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        if ($json) {
            jsonResponse(['error' => 'Invalid request, please try again.'], 403);
        }
        header('Location: ' . $failurePath . '?error=' . urlencode('Invalid request, please try again.'));
        exit;
    }
}
