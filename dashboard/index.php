<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$searchInput = $_GET['search'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$today = (new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Manila')
))->format('Y-m-d');

$patientCount = (int) $pdo->query(
    'SELECT COUNT(*)
     FROM patients
     WHERE deleted_at IS NULL'
)->fetchColumn();

$statement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM appointments
     WHERE appointment_date = ?'
);
$statement->execute([$today]);
$todayCount = (int) $statement->fetchColumn();

$statement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM appointments
     WHERE status = 'pending'
       AND appointment_date >= ?"
);
$statement->execute([$today]);
$pendingCount = (int) $statement->fetchColumn();

$statement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM appointments
     WHERE appointment_date = ?
       AND status = 'completed'"
);
$statement->execute([$today]);
$completedCount = (int) $statement->fetchColumn();

$statement = $pdo->prepare(
    "SELECT
        a.appointment_id,
        a.appointment_date,
        a.appointment_time,
        a.reason,
        a.status,
        p.patient_code,
        p.first_name,
        p.last_name,
        u.full_name AS clinician_name
     FROM appointments a
     INNER JOIN patients p
        ON p.patient_id = a.patient_id
     LEFT JOIN users u
        ON u.user_id = a.clinician_id
     WHERE a.appointment_date >= ?
       AND a.status IN ('pending', 'confirmed')
       AND p.deleted_at IS NULL
       AND (
            ? = ''
            OR p.patient_code LIKE ? ESCAPE '!'
            OR p.first_name LIKE ? ESCAPE '!'
            OR p.last_name LIKE ? ESCAPE '!'
            OR u.full_name LIKE ? ESCAPE '!'
            OR a.reason LIKE ? ESCAPE '!'
            OR a.status LIKE ? ESCAPE '!'
       )
     ORDER BY a.appointment_date ASC, a.appointment_time ASC
     LIMIT 8"
);
$searchTerm = '%' . escape_like_search($search) . '%';
$statement->execute([
    $today,
    $search,
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $searchTerm,
    $searchTerm,
]);
$upcomingAppointments = $statement->fetchAll();

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
        <p class="page-eyebrow">Clinic overview</p>
        <h1 class="h3 fw-bold mb-1">Dashboard</h1>
        <p class="text-secondary mb-0">
            Welcome back, <?= e($_SESSION['full_name'] ?? 'Staff') ?>.
            Here's your clinic overview.
        </p>
    </div>

    <span class="badge text-bg-light border p-2">
        <i class="bi bi-calendar3 me-1"></i>
        <?= e(date('F j, Y', strtotime($today))) ?>
    </span>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card dashboard-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary small">Active Patients</span>
                    <span class="stat-icon stat-blue">
                        <i class="bi bi-people"></i>
                    </span>
                </div>
                <div class="stat-number"><?= number_format($patientCount) ?></div>
                <p class="small text-secondary mb-0 mt-1">
                    Registered patient records
                </p>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card dashboard-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary small">Today's Appointments</span>
                    <span class="stat-icon stat-green">
                        <i class="bi bi-calendar-check"></i>
                    </span>
                </div>
                <div class="stat-number"><?= number_format($todayCount) ?></div>
                <p class="small text-secondary mb-0 mt-1">
                    Scheduled for today
                </p>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card dashboard-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary small">Pending</span>
                    <span class="stat-icon stat-amber">
                        <i class="bi bi-clock-history"></i>
                    </span>
                </div>
                <div class="stat-number"><?= number_format($pendingCount) ?></div>
                <p class="small text-secondary mb-0 mt-1">
                    Pending today or later
                </p>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card dashboard-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary small">Completed Today</span>
                    <span class="stat-icon stat-purple">
                        <i class="bi bi-clipboard-check"></i>
                    </span>
                </div>
                <div class="stat-number"><?= number_format($completedCount) ?></div>
                <p class="small text-secondary mb-0 mt-1">
                    Completed appointments
                </p>
            </div>
        </div>
    </div>
</div>

<section class="dashboard-section content-fit-section p-3 p-md-4 mb-4">
    <h2 class="h5 fw-semibold mb-3">Quick Actions</h2>

    <div class="d-flex flex-wrap gap-2">
        <a href="/caretrack/patients/create.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>
            Register Patient
        </a>

        <a href="/caretrack/appointments/create.php" class="btn btn-outline-primary">
            <i class="bi bi-calendar-plus me-1"></i>
            Schedule Appointment
        </a>

        <a href="/caretrack/patients/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-search me-1"></i>
            Find Patient
        </a>
    </div>
</section>

<section class="dashboard-section overflow-hidden">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 p-md-4 border-bottom">
        <div>
            <h2 class="h5 fw-semibold mb-1">Upcoming Appointments</h2>
            <p class="text-secondary small mb-0">
                Pending and confirmed appointments
            </p>
        </div>

        <a href="/caretrack/appointments/index.php"
           class="btn btn-sm btn-outline-primary">
            View All
        </a>
    </div>

    <form method="get" class="row g-2 p-3 p-md-4 border-bottom">
        <div class="col-md-9">
            <label for="dashboard-search" class="form-label">Search upcoming appointments</label>
            <input
                type="search"
                id="dashboard-search"
                name="search"
                class="form-control"
                value="<?= e($search) ?>"
                placeholder="Patient name or code, clinician, reason, or status"
            >
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-search"></i> Search
            </button>
            <a href="/caretrack/dashboard/index.php"
               class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Date</th>
                    <th scope="col">Time</th>
                    <th scope="col">Clinician</th>
                    <th scope="col">Reason</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$upcomingAppointments): ?>
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="bi bi-calendar2-x fs-2 text-secondary"></i>
                        <p class="mt-2 mb-1 fw-semibold">
                            No upcoming appointments
                        </p>
                        <p class="small text-secondary mb-0">
                            Appointments will appear here once scheduled.
                        </p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($upcomingAppointments as $appointment): ?>
                    <?php
                    $status = $appointment['status'];
                    $statusClass = $statusClasses[$status] ?? 'text-bg-secondary';
                    $patientName = $appointment['first_name']
                        . ' ' . $appointment['last_name'];
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($patientName) ?></div>
                            <div class="small text-secondary">
                                <?= e($appointment['patient_code']) ?>
                            </div>
                        </td>
                        <td>
                            <?= e(date(
                                'M j, Y',
                                strtotime($appointment['appointment_date'])
                            )) ?>
                        </td>
                        <td>
                            <?= e(date(
                                'g:i A',
                                strtotime($appointment['appointment_time'])
                            )) ?>
                        </td>
                        <td><?= e($appointment['clinician_name'] ?? 'Unassigned') ?></td>
                        <td><?= e($appointment['reason']) ?></td>
                        <td>
                            <span class="badge <?= e($statusClass) ?>">
                                <?= e(ucfirst($status)) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
