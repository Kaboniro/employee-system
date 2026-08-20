<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/db.php';

requireAdmin(true);

$stmt = getDb()->query(
    'SELECT id, name, department, phone, email, role, logged_in_at, created_at
     FROM users ORDER BY created_at DESC'
);
$employees = $stmt->fetchAll();

jsonResponse(['employees' => $employees]);
