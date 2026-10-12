<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role(['admin']);

$pageTitle = 'Activity Logs';
$currentPage = 'activity-logs';
$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$params = [];

$sql = 'SELECT
            al.log_id,
            al.user_id,
            al.action,
            al.entity_type,
            al.entity_id,
            al.created_at,
            u.full_name AS actor_name
        FROM activity_logs al
        LEFT JOIN users u ON u.user_id = al.user_id';

if ($search !== '') {
    $sql .= ' WHERE al.action LIKE ? ESCAPE \'!\'
              OR al.entity_type LIKE ? ESCAPE \'!\'
              OR al.entity_id LIKE ? ESCAPE \'!\'
              OR u.full_name LIKE ? ESCAPE \'!\'';
    $term = '%' . escape_like_search($search) . '%';
    $params = [$term, $term, $term, $term];
}

$sql .= ' ORDER BY al.created_at DESC, al.log_id DESC LIMIT 200';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$logs = $statement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <p class="page-eyebrow">Administration</p>
    <h1 class="h3 mb-1">Activity Logs</h1>
    <p class="text-secondary mb-0">
        Review recent staff actions. Sensitive patient and clinical details are not stored here.
    </p>
</div>

<section class="dashboard-section content-fit-section p-3 p-md-4 mb-4">
    <form method="get" class="row g-2">
        <div class="col-md-9">
            <label for="search" class="form-label">Search activity</label>
            <input
                type="search"
                name="search"
                id="search"
                class="form-control"
                value="<?= e($search) ?>"
                placeholder="Staff member, action, record type, or record ID"
            >
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-search"></i> Search
            </button>
            <a href="/caretrack/activity-logs/index.php"
               class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</section>

<section class="dashboard-section overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Date and Time</th>
                    <th>Staff Member</th>
                    <th>Action</th>
                    <th>Record</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs === []): ?>
                <tr>
                    <td colspan="4" class="text-center text-secondary py-4">
                        No activity logs found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['created_at']) ?></td>
                        <td><?= e($log['actor_name'] ?? 'System / public registration') ?></td>
                        <td><code><?= e($log['action']) ?></code></td>
                        <td>
                            <?php if ($log['entity_type'] !== null): ?>
                                <?= e(ucwords(str_replace('_', ' ', $log['entity_type']))) ?>
                            <?php endif; ?>
                            <?= $log['entity_id'] !== null ? '#' . e($log['entity_id']) : '' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-3 border-top text-secondary small">
        Showing up to 200 entries, newest first.
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
