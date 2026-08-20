<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    jsonResponse(['authenticated' => false]);
}

$stmt = getDb()->prepare('SELECT name FROM users WHERE id = :id');
$stmt->execute(['id' => (int) $_SESSION['user_id']]);
$name = $stmt->fetchColumn();

jsonResponse([
    'authenticated' => true,
    'role'          => $_SESSION['role'] ?? 'employee',
    'name'          => $name,
]);
