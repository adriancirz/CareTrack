<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $sessionStorage = getenv('CARETRACK_SESSION_STORAGE') ?: 'files';

    if (getenv('VERCEL') === '1' && $sessionStorage !== 'database') {
        throw new RuntimeException(
            'CARETRACK_SESSION_STORAGE=database is required on Vercel.'
        );
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        ),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if ($sessionStorage === 'database') {
        require_once __DIR__ . '/database-session-handler.php';
        session_set_save_handler(new DatabaseSessionHandler($pdo), true);
    } elseif ($sessionStorage !== 'files') {
        throw new RuntimeException(
            'CARETRACK_SESSION_STORAGE must be "files" or "database".'
        );
    }

    if (!session_start()) {
        throw new RuntimeException('The session could not be started.');
    }
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
