<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';

requireLogin(true);

$stmt = getDb()->prepare(
    "SELECT name FROM users
     WHERE role = 'employee' AND last_active_at > now() - interval '5 minutes'
     ORDER BY name"
);
$stmt->execute();

jsonResponse(['online' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
