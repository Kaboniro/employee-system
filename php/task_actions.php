<?php
declare(strict_types=1);

require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/db.php';

$userId = requireLogin(true);
requireValidCsrf('/employee-dashboard.html', true);

$action = $_POST['action'] ?? '';
$db = getDb();

switch ($action) {

    case 'add-task': {
        $title = trim((string) ($_POST['title'] ?? ''));
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));

        if ($title === '') {
            jsonResponse(['error' => 'Title is required.'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO tasks (user_id, title, due_date) VALUES (:user_id, :title, :due_date)
             RETURNING id, title, status, due_date, supervisor_comment'
        );
        $stmt->execute([
            'user_id'  => $userId,
            'title'    => $title,
            'due_date' => $dueDate !== '' ? $dueDate : null,
        ]);

        jsonResponse(['task' => $stmt->fetch()]);
    }

    case 'edit-task': {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));

        if ($title === '') {
            jsonResponse(['error' => 'Title is required.'], 422);
        }

        $stmt = $db->prepare(
            "UPDATE tasks SET title = :title, due_date = :due_date
             WHERE id = :id AND user_id = :user_id AND status = 'pending'
             RETURNING id, title, status, due_date, supervisor_comment"
        );
        $stmt->execute([
            'title'    => $title,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'id'       => $id,
            'user_id'  => $userId,
        ]);
        $task = $stmt->fetch();

        if (!$task) {
            jsonResponse(['error' => 'Task not found or not editable.'], 404);
        }

        jsonResponse(['task' => $task]);
    }

    case 'update-status': {
        // Any employee may complete or reopen any task on the shared board.
        // completed_by is only ever set here (never cleared on reopen), so
        // it stays a record of whoever last finished the task.
        $id = (int) ($_POST['id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'completed' ? 'completed' : 'pending';

        if ($status === 'completed') {
            $stmt = $db->prepare(
                'UPDATE tasks SET status = :status, completed_by = :completed_by
                 WHERE id = :id
                 RETURNING id, title, status, due_date, supervisor_comment, user_id, completed_by'
            );
            $stmt->execute(['status' => $status, 'completed_by' => $userId, 'id' => $id]);
        } else {
            $stmt = $db->prepare(
                'UPDATE tasks SET status = :status
                 WHERE id = :id
                 RETURNING id, title, status, due_date, supervisor_comment, user_id, completed_by'
            );
            $stmt->execute(['status' => $status, 'id' => $id]);
        }
        $task = $stmt->fetch();

        if (!$task) {
            jsonResponse(['error' => 'Task not found.'], 404);
        }

        jsonResponse(['task' => $task]);
    }

    case 'delete-task': {
        $id = (int) ($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :user_id')
            ->execute(['id' => $id, 'user_id' => $userId]);

        jsonResponse(['deleted' => true]);
    }

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}
