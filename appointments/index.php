<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();

$pageTitle = 'Appointments';
$currentPage = 'appointments';
$rawSearch = $_GET['search'] ?? '';
$search = is_string($rawSearch) ? trim($rawSearch) : '';
$rawStatus = $_GET['status'] ?? '';
$statusFilter = is_string($rawStatus) ? $rawStatus : '';
$allowedStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'missed'];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(p.patient_code LIKE ? OR p.first_name LIKE ?
        OR p.last_name LIKE ? OR u.full_name LIKE ? OR a.reason LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}

if (in_array($statusFilter, $allowedStatuses, true)) {
    $conditions[] = 'a.status = ?';
    $params[] = $statusFilter;
} else {
    $statusFilter = '';
}

$where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

$countStatement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM appointments a
     INNER JOIN patients p ON p.patient_id = a.patient_id
     {$where}"
);
$countStatement->execute($params);
$totalAppointments = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalAppointments / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$statement = $pdo->prepare(
    "SELECT a.appointment_id, a.appointment_date, a.appointment_time,
            a.reason, a.status, p.patient_code, p.first_name, p.last_name,
            u.full_name AS clinician_name
     FROM appointments a
     INNER JOIN patients p ON p.patient_id = a.patient_id
     LEFT JOIN users u ON u.user_id = a.clinician_id
     {$where}
     ORDER BY a.appointment_date DESC, a.appointment_time DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$statement->execute($params);
$appointments = $statement->fetchAll();

require_once __DIR__ . '/../includes/header.php';

$statusClasses = [
    'pending' => 'text-bg-warning',
    'confirmed' => 'text-bg-primary',
    'completed' => 'text-bg-success',
    'cancelled' => 'text-bg-secondary',
    'missed' => 'text-bg-danger',
];
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <p class="page-eyebrow">Scheduling</p>
        <h1 class="h3 mb-1">Appointments</h1>
        <p class="text-secondary mb-0">Search and manage clinic appointments.</p>
    </div>
    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'receptionist'], true)): ?>
        <a href="/caretrack/appointments/create.php" class="btn btn-primary">
            <i class="bi bi-calendar-plus me-1"></i>Schedule Appointment
        </a>
    <?php endif; ?>
</div>

<section class="dashboard-section p-3 p-md-4">
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-6 col-lg-5">
            <label class="visually-hidden" for="appointment-search">Search appointments</label>
            <input type="search" id="appointment-search" name="search"
                   class="form-control" value="<?= e($search) ?>"
                   placeholder="Search patient, clinician, code, or reason">
        </div>
        <div class="col-md-3 col-lg-2">
            <label class="visually-hidden" for="status-filter">Filter by status</label>
            <select id="status-filter" name="status" class="form-select">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                        <?= e(ucfirst($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Search</button>
            <a href="/caretrack/appointments/index.php"
               class="btn btn-outline-secondary">Clear</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Date &amp; Time</th>
                    <th scope="col">Clinician</th>
                    <th scope="col">Reason</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($appointments === []): ?>
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">
                            No appointments found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?>
                                </div>
                                <div class="small text-secondary">
                                    <?= e($appointment['patient_code']) ?>
                                </div>
                            </td>
                            <td>
                                <?= e(date('M j, Y', strtotime($appointment['appointment_date']))) ?>
                                <span class="d-block small text-secondary">
                                    <?= e(date('g:i A', strtotime($appointment['appointment_time']))) ?>
                                </span>
                            </td>
                            <td><?= e($appointment['clinician_name'] ?? 'Unassigned') ?></td>
                            <td><?= e($appointment['reason']) ?></td>
                            <td>
                                <span class="badge <?= e($statusClasses[$appointment['status']] ?? 'text-bg-secondary') ?>">
                                    <?= e(ucfirst($appointment['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="/caretrack/appointments/view.php?id=<?= (int) $appointment['appointment_id'] ?>">
                                    View
                                </a>
                                <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'receptionist'], true)): ?>
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="/caretrack/appointments/edit.php?id=<?= (int) $appointment['appointment_id'] ?>">
                                        Edit
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <small class="text-secondary"><?= number_format($totalAppointments) ?> appointment(s)</small>
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Appointment pages">
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                            <a class="page-link"
                               href="?search=<?= urlencode($search) ?>&amp;status=<?= urlencode($statusFilter) ?>&amp;page=<?= $pageNumber ?>"
                               <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>>
                                <?= $pageNumber ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
