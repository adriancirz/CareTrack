<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Require an authenticated user.
 */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('/caretrack/login.php');
    }
}

/**
 * Return the current user ID, or null if unauthenticated.
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;
}

/**
 * Require one of the specified roles.
 *
 * @param list<string> $allowedRoles
 */
function require_role(array $allowedRoles): void
{
    require_login();

    $role = $_SESSION['role'] ?? null;

    if (!is_string($role) || !in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        exit('Access denied. You do not have permission to access this page.');
    }
}

/**
 * Regenerate the session ID after authentication.
 *
 * @param array{user_id: int|string, full_name: string, role: string} $user
 */
function establish_user_session(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_regeneration'] = time();
}

/**
 * Clear and destroy the current session.
 */
function destroy_user_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}
