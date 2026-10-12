<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();

$pageTitle = 'Appointment Details';
$currentPage = 'appointments';
$appointmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$appointmentId || $appointmentId < 1) {
    http_response_code(400);
    exit('Invalid appointment ID.');
}

$statement = $pdo->prepare(
    'SELECT a.*, p.patient_code, p.first_name, p.last_name,
            p.contact_number,
            u.full_name AS clinician_name, creator.full_name AS creator_name
     FROM appointments a
     INNER JOIN patients p ON p.patient_id = a.patient_id
     LEFT JOIN users u ON u.user_id = a.clinician_id
     LEFT JOIN users creator ON creator.user_id = a.created_by
     WHERE a.appointment_id = ?
     LIMIT 1'
);
$statement->execute([$appointmentId]);
$appointment = $statement->fetch();

if (!$appointment) {
    http_response_code(404);
    exit('Appointment not found.');
}

$pageTitle = 'Appointment Details';
$statusClasses = [
    'pending' => 'text-bg-warning',
    'confirmed' => 'text-bg-primary',
    'completed' => 'text-bg-success',
    'cancelled' => 'text-bg-secondary',
    'missed' => 'text-bg-danger',
];
$role = $_SESSION['role'] ?? '';
$canEdit = in_array($role, ['admin', 'receptionist'], true);
$canUpdateStatus = $canEdit
    || ($role === 'clinician'
        && (int) $appointment['clinician_id'] === current_user_id());
$appointmentIsOpen = in_array($appointment['status'], ['pending', 'confirmed'], true);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <a href="/caretrack/appointments/index.php" class="text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Appointments
        </a>
        <h1 class="h3 mt-3 mb-1">Appointment Details</h1>
        <p class="text-secondary mb-0">
            Appointment #<?= (int) $appointment['appointment_id'] ?>
        </p>
    </div>
    <?php if ($canEdit && $appointmentIsOpen): ?>
        <a href="/caretrack/appointments/edit.php?id=<?= (int) $appointmentId ?>"
           class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit Appointment
        </a>
    <?php endif; ?>
</div>

<section class="dashboard-section p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h2 class="h5 mb-0">Booking Information</h2>
        <span class="badge <?= e($statusClasses[$appointment['status']] ?? 'text-bg-secondary') ?>">
            <?= e(ucfirst($appointment['status'])) ?>
        </span>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="text-secondary small">Patient</div>
            <div class="fw-semibold">
                <?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?>
            </div>
            <div class="text-secondary small"><?= e($appointment['patient_code']) ?></div>
            <div class="small mt-1">
                Contact: <?= e($appointment['contact_number'] ?: 'Not provided') ?>
            </div>
            <a class="btn btn-sm btn-outline-secondary mt-2"
               href="/caretrack/patients/view.php?id=<?= (int) $appointment['patient_id'] ?>">
                View Patient
            </a>
        </div>
        <div class="col-md-6">
            <div class="text-secondary small">Clinician</div>
            <div><?= e($appointment['clinician_name'] ?? 'Unassigned') ?></div>
        </div>
        <div class="col-md-6">
            <div class="text-secondary small">Date and time</div>
            <div>
                <?= e(date('F j, Y', strtotime($appointment['appointment_date']))) ?>
                at <?= e(date('g:i A', strtotime($appointment['appointment_time']))) ?>
            </div>
        </div>
        <div class="col-12">
            <div class="text-secondary small">Reason for Visit</div>
            <div><?= nl2br(e($appointment['reason'] ?: 'Not provided')) ?></div>
        </div>
        <div class="col-md-6">
            <div class="text-secondary small">Created By</div>
            <div><?= e($appointment['creator_name'] ?? 'Unavailable') ?></div>
        </div>
        <div class="col-md-6">
            <div class="text-secondary small">Created At</div>
            <div><?= e($appointment['created_at']) ?></div>
        </div>
    </div>

    <?php if ($canUpdateStatus && $appointmentIsOpen): ?>
        <hr class="my-4">
        <h2 class="h5 mb-3">Update Appointment Status</h2>

        <form method="post" action="/caretrack/appointments/update-status.php"
              class="row g-2 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="appointment_id"
                   value="<?= (int) $appointment['appointment_id'] ?>">

            <div class="col-sm-6 col-md-4">
                <label for="new_status" class="form-label">New Status</label>
                <select id="new_status" name="status" class="form-select" required>
                    <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'missed'] as $option): ?>
                        <option value="<?= e($option) ?>"
                            <?= $appointment['status'] === $option ? 'selected' : '' ?>>
                            <?= e(ucfirst($option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary">Update Status</button>
            </div>
        </form>
        <p class="text-secondary small mt-2 mb-0">
            Completed, cancelled, and missed appointments cannot be edited or reopened.
        </p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
