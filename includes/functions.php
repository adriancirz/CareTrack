<?php

declare(strict_types=1);

/**
 * Safely escape untrusted values for HTML output.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Redirect to a local application path.
 */
function redirect(string $path): never
{
    if (
        $path === ''
        || $path[0] !== '/'
        || str_starts_with($path, '//')
        || str_contains($path, "\r")
        || str_contains($path, "\n")
    ) {
        throw new InvalidArgumentException('Invalid redirect path.');
    }

    header('Location: ' . $path);
    exit;
}

function log_activity(
    PDO $pdo,
    string $action,
    string $entityType,
    int|string|null $entityId = null,
    ?int $actorId = null
): void {
    $actorId ??= isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    $statement = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, action, entity_type, entity_id)
         VALUES (?, ?, ?, ?)'
    );
    $statement->execute([
        $actorId,
        $action,
        $entityType,
        $entityId === null ? null : (string) $entityId,
    ]);
}

function escape_like_search(string $search): string
{
    return strtr($search, [
        '!' => '!!',
        '%' => '!%',
        '_' => '!_',
    ]);
}
