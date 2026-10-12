<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role(['admin']);

$pageTitle = 'User Management';
$currentPage = 'users';

$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$params = [];

$sql = 'SELECT
            user_id,
            full_name,
            username,
            email,
            role,
            status,
            created_at
        FROM users';

if ($search !== '') {
    $sql .= ' WHERE full_name LIKE ? ESCAPE \'!\'
              OR username LIKE ? ESCAPE \'!\'
              OR email LIKE ? ESCAPE \'!\'
              OR role LIKE ? ESCAPE \'!\'
              OR status LIKE ? ESCAPE \'!\'';

    $term = '%' . escape_like_search($search) . '%';
    $params = [$term, $term, $term, $term, $term];
}

$sql .= " ORDER BY
            CASE status
                WHEN 'inactive' THEN 0
                ELSE 1
            END,
            full_name ASC
          LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$noticeInput = $_GET['notice'] ?? '';
$errorInput = $_GET['error'] ?? '';
$notice = is_string($noticeInput) ? $noticeInput : '';
$error = is_string($errorInput) ? $errorInput : '';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <p class="page-eyebrow">Administration</p>
        <h1 class="h3 mb-1">User Management</h1>
        <p class="text-secondary mb-0">
            Manage staff accounts, roles, and access.
        </p>
    </div>

    <span class="badge text-bg-primary">
        <?= count($users) ?> accounts shown
    </span>
</div>

<?php if ($notice === 'updated'): ?>
    <div class="alert alert-success" role="alert">
        User account updated successfully.
    </div>
<?php elseif ($notice === 'registered'): ?>
    <div class="alert alert-success" role="alert">
        User account created successfully.
    </div>
<?php endif; ?>

<?php if ($error === 'invalid'): ?>
    <div class="alert alert-danger" role="alert">
        Invalid request or account details.
    </div>
<?php elseif ($error === 'last_admin'): ?>
    <div class="alert alert-warning" role="alert">
        You cannot deactivate or demote the last active administrator.
    </div>
<?php elseif ($error === 'self'): ?>
    <div class="alert alert-warning" role="alert">
        You cannot change your own role or deactivate your own account.
    </div>
<?php elseif ($error === 'not_found'): ?>
    <div class="alert alert-danger" role="alert">
        The selected user account was not found.
    </div>
<?php endif; ?>

<div class="dashboard-section content-fit-section p-3 p-md-4 mb-4">
    <form method="get" class="row g-2">
        <div class="col-md-9">
            <label for="search" class="form-label">Search users</label>
            <input
                type="search"
                name="search"
                id="search"
                class="form-control"
                value="<?= e($search) ?>"
                placeholder="Name, username, email, role, or status"
            >
        </div>

        <div class="col-md-3 d-flex align-items-end gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit">
                <i class="bi bi-search"></i> Search
            </button>
            <a href="/caretrack/users/index.php" class="btn btn-outline-secondary">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="dashboard-section overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Manage</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr>
                    <td colspan="6" class="text-center text-secondary py-4">
                        No user accounts found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <?php $isSelf = (int) $user['user_id'] === current_user_id(); ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($user['full_name']) ?></div>
                            <small class="text-secondary"><?= e($user['email']) ?></small>
                        </td>
                        <td><?= e($user['username']) ?></td>
                        <td>
                            <span class="badge text-bg-light border">
                                <?= e(ucfirst($user['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['status'] === 'active'): ?>
                                <span class="badge text-bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge text-bg-warning">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e(date('M d, Y', strtotime($user['created_at']))) ?>
                        </td>
                        <td>
                            <?php if ($isSelf): ?>
                                <span class="text-secondary small">Current account</span>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-2" style="min-width: 190px;">
                                    <form
                                        method="post"
                                        action="/caretrack/users/update.php"
                                        class="d-flex gap-2"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(csrf_token()) ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $user['user_id'] ?>"
                                        >
                                        <input type="hidden" name="action" value="role">
                                        <select
                                            name="role"
                                            class="form-select form-select-sm"
                                            aria-label="User role"
                                        >
                                            <option value="receptionist"
                                                <?= $user['role'] === 'receptionist' ? 'selected' : '' ?>>
                                                Receptionist
                                            </option>
                                            <option value="clinician"
                                                <?= $user['role'] === 'clinician' ? 'selected' : '' ?>>
                                                Clinician
                                            </option>
                                            <option value="admin"
                                                <?= $user['role'] === 'admin' ? 'selected' : '' ?>>
                                                Administrator
                                            </option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            Save
                                        </button>
                                    </form>

                                    <form method="post" action="/caretrack/users/update.php">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(csrf_token()) ?>"
                                        >
                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $user['user_id'] ?>"
                                        >
                                        <input type="hidden" name="action" value="status">
                                        <input
                                            type="hidden"
                                            name="status"
                                            value="<?= $user['status'] === 'active' ? 'inactive' : 'active' ?>"
                                        >
                                        <button
                                            type="submit"
                                            class="btn btn-sm w-100 <?= $user['status'] === 'active'
                                                ? 'btn-outline-danger'
                                                : 'btn-outline-success' ?>"
                                            onclick="return confirm('Change this account status?')"
                                        >
                                            <?= $user['status'] === 'active'
                                                ? 'Deactivate account'
                                                : 'Activate account' ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="p-3 border-top text-secondary small">
        Showing up to 200 accounts. Search to find a specific user.
        You cannot change your own account from this page.
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
