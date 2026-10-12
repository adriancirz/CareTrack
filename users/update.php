<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$submittedToken = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
    http_response_code(403);
    exit('Invalid security token. Please return and try again.');
}

$userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (
    $userId === false
    || $userId === null
    || $userId < 1
    || !is_string($action)
    || !in_array($action, ['role', 'status'], true)
) {
    redirect('/caretrack/users/index.php?error=invalid');
}

if ($userId === current_user_id()) {
    redirect('/caretrack/users/index.php?error=self');
}

try {
    $pdo->beginTransaction();

    // Serialize administrator changes so concurrent requests cannot remove the final active admin.
    $pdo->query(
        "SELECT user_id
         FROM users
         WHERE role = 'admin' AND status = 'active'
         ORDER BY user_id
         FOR UPDATE"
    )->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT user_id, role, status
         FROM users
         WHERE user_id = ?
         FOR UPDATE'
    );
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        $pdo->rollBack();
        redirect('/caretrack/users/index.php?error=not_found');
    }

    if ($action === 'role') {
        $newRole = $_POST['role'] ?? '';
        if (
            !is_string($newRole)
            || !in_array($newRole, ['admin', 'receptionist', 'clinician'], true)
        ) {
            $pdo->rollBack();
            redirect('/caretrack/users/index.php?error=invalid');
        }

        $removingAdmin = $target['role'] === 'admin'
            && $target['status'] === 'active'
            && $newRole !== 'admin';

        if ($removingAdmin && !has_another_active_admin($pdo, $userId)) {
            $pdo->rollBack();
            redirect('/caretrack/users/index.php?error=last_admin');
        }

        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
        $stmt->execute([$newRole, $userId]);
        if ($target['role'] !== $newRole) {
            log_activity($pdo, 'user.role_changed_to_' . $newRole, 'user', $userId);
        }
    } else {
        $newStatus = $_POST['status'] ?? '';
        if (!is_string($newStatus) || !in_array($newStatus, ['active', 'inactive'], true)) {
            $pdo->rollBack();
            redirect('/caretrack/users/index.php?error=invalid');
        }

        $removingAdmin = $target['role'] === 'admin'
            && $target['status'] === 'active'
            && $newStatus === 'inactive';

        if ($removingAdmin && !has_another_active_admin($pdo, $userId)) {
            $pdo->rollBack();
            redirect('/caretrack/users/index.php?error=last_admin');
        }

        $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?');
        $stmt->execute([$newStatus, $userId]);
        if ($target['status'] !== $newStatus) {
            $actionName = $newStatus === 'active' ? 'user.activated' : 'user.deactivated';
            log_activity($pdo, $actionName, 'user', $userId);
        }
    }

    $pdo->commit();
    redirect('/caretrack/users/index.php?notice=updated');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('CareTrack user management error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to update the user account. Please try again.');
}

function has_another_active_admin(PDO $pdo, int $excludedUserId): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM users
         WHERE role = 'admin'
           AND status = 'active'
           AND user_id <> ?"
    );
    $stmt->execute([$excludedUserId]);

    return (int) $stmt->fetchColumn() > 0;
}
