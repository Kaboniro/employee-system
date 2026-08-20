<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$token = (string) ($_GET['token'] ?? '');

if ($token === '') {
    header('Location: /index.html?error=' . urlencode('Invalid or expired link.'));
    exit;
}

$hash = hash('sha256', $token);
$db = getDb();

$stmt = $db->prepare(
    'SELECT lt.id, lt.user_id, u.role
     FROM login_tokens lt
     JOIN users u ON u.id = lt.user_id
     WHERE lt.token_hash = :hash AND lt.used = false AND lt.expires_at > now()'
);
$stmt->execute(['hash' => $hash]);
$row = $stmt->fetch();

if (!$row) {
    header('Location: /index.html?error=' . urlencode('This link is invalid or has expired.'));
    exit;
}

$db->prepare('UPDATE login_tokens SET used = true WHERE id = :id')->execute(['id' => $row['id']]);
$db->prepare('UPDATE users SET logged_in_at = now() WHERE id = :id')->execute(['id' => $row['user_id']]);

$_SESSION = [];
$_SESSION['user_id'] = $row['user_id'];
$_SESSION['role'] = $row['role'];
session_regenerate_id(true);

header('Location: /dashboard.html');
exit;
