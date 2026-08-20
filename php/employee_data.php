<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/db.php';

$userId = requireLogin(true);
$db = getDb();

$stmt = $db->prepare('SELECT name, email, department, phone FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

// Shared board: every employee sees every task. Only the creator may edit
// or delete their own task (flagged below as is_owner); anyone may complete one.
$stmt = $db->prepare(
    'SELECT t.id, t.title, t.status, t.due_date, t.supervisor_comment, t.user_id,
            creator.name AS created_by_name,
            t.completed_by, completer.name AS completed_by_name
     FROM tasks t
     JOIN users creator ON creator.id = t.user_id
     LEFT JOIN users completer ON completer.id = t.completed_by
     ORDER BY t.due_date ASC NULLS LAST'
);
$stmt->execute();
$tasks = $stmt->fetchAll();

foreach ($tasks as &$task) {
    $task['is_owner'] = ((int) $task['user_id'] === $userId);
}
unset($task);

$pending = array_values(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$completed = array_values(array_filter($tasks, fn($t) => $t['status'] === 'completed'));

jsonResponse([
    'user'  => $user,
    'tasks' => [
        'pending'   => $pending,
        'completed' => $completed,
    ],
]);
