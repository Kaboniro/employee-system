<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function issueLoginLink(int $userId): string
{
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $expiresAt = (new DateTimeImmutable())->modify('+' . LOGIN_LINK_TTL_MINUTES . ' minutes');

    $stmt = getDb()->prepare(
        'INSERT INTO login_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
    );
    $stmt->execute([
        'user_id'    => $userId,
        'token_hash' => $hash,
        'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
    ]);

    return APP_BASE_URL . '/php/magic-login.php?token=' . $raw;
}
