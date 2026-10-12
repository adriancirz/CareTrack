<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$submittedToken = $_POST['csrf_token'] ?? null;

if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
    http_response_code(403);
    exit('Invalid request token. Refresh the page and try again.');
}

try {
    log_activity($pdo, 'user.logged_out', 'user', current_user_id());
} catch (PDOException $exception) {
    error_log('CareTrack logout activity logging failed: ' . $exception->getMessage());
}

destroy_user_session();

redirect('/caretrack/login.php');
