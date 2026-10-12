<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php';

require_role(['admin', 'receptionist']);

$pageTitle = 'Edit Appointment';
$currentPage = 'appointments';
$appointmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$appointmentId || $appointmentId < 1) {
    http_response_code(400);
    exit('Invalid appointment ID.');
}

$statement = $pdo->prepare(
    "SELECT * FROM appointments
     WHERE appointment_id = ?
       AND status IN ('pending', 'confirmed')
     LIMIT 1"
);
$statement->execute([$appointmentId]);
$appointment = $statement->fetch();

if (!$appointment) {
    http_response_code(404);
    exit('Appointment not found or cannot be edited.');
}

$patientsStatement = $pdo->prepare(
    'SELECT patient_id, patient_code, first_name, last_name
     FROM patients
     WHERE deleted_at IS NULL OR patient_id = ?
     ORDER BY last_name, first_name'
);
$patientsStatement->execute([$appointment['patient_id']]);
$patients = $patientsStatement->fetchAll();
$clinicians = active_clinicians($pdo);
$activeClinicianIds = array_map(
    static fn (array $clinician): int => (int) $clinician['user_id'],
    $clinicians
);
$currentClinicianIsActive = in_array(
    (int) $appointment['clinician_id'],
    $activeClinicianIds,
    true
);

$errors = [];
$patientId = (string) $appointment['patient_id'];
$clinicianId = (string) $appointment['clinician_id'];
$date = $appointment['appointment_date'];
$time = substr((string) $appointment['appointment_time'], 0, 5);
$reason = $appointment['reason'];
$today = (new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Manila')
))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
        $errors[] = 'Invalid security token. Refresh the page and try again.';
    }

    $patientInput = $_POST['patient_id'] ?? '';
    $clinicianInput = $_POST['clinician_id'] ?? '';
    $dateInput = $_POST['appointment_date'] ?? '';
    $timeInput = $_POST['appointment_time'] ?? '';
    $reasonInput = $_POST['reason'] ?? '';

    $patientId = is_string($patientInput) ? trim($patientInput) : '';
    $clinicianId = is_string($clinicianInput) ? trim($clinicianInput) : '';
    $date = is_string($dateInput) ? trim($dateInput) : '';
    $time = is_string($timeInput) ? trim($timeInput) : '';
    $reason = is_string($reasonInput) ? trim($reasonInput) : '';

    $patientIdValue = filter_var($patientId, FILTER_VALIDATE_INT);
    $clinicianIdValue = filter_var($clinicianId, FILTER_VALIDATE_INT);

    if ($patientIdValue === false || $patientIdValue < 1) {
        $errors[] = 'Select a valid patient.';
    } elseif (!active_patient_exists($pdo, $patientIdValue)) {
        $errors[] = 'The selected patient is not active.';
    }
    if ($clinicianIdValue === false || $clinicianIdValue < 1) {
        $errors[] = 'Select a valid clinician.';
    } elseif (!in_array($clinicianIdValue, $activeClinicianIds, true)) {
        $errors[] = 'Select an active clinician.';
    }
    if (!valid_appointment_date($date)) {
        $errors[] = 'Enter a valid appointment date.';
    } elseif ($date < $today) {
        $errors[] = 'Appointment date cannot be in the past.';
    }
    if (!valid_appointment_time($time)) {
        $errors[] = 'Enter a valid appointment time.';
    }
    if ($reason === '' || mb_strlen($reason) > 255) {
        $errors[] = 'Enter a reason of up to 255 characters.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            $currentStatement = $pdo->prepare(
                "SELECT appointment_id, status
                 FROM appointments
                 WHERE appointment_id = ?
                 FOR UPDATE"
            );
            $currentStatement->execute([$appointmentId]);
            $currentAppointment = $currentStatement->fetch();

            if (!$currentAppointment
                || !in_array($currentAppointment['status'], ['pending', 'confirmed'], true)) {
                throw new DomainException('This appointment is no longer editable.');
            }

            if (!lock_active_clinician($pdo, $clinicianIdValue)) {
                throw new DomainException('The selected clinician is not active.');
            }

            if (!appointment_slot_available(
                $pdo,
                $date,
                $time,
                $clinicianIdValue,
                $appointmentId
            )) {
                throw new DomainException(
                    'This clinician already has a pending or confirmed appointment at that time.'
                );
            }

            $update = $pdo->prepare(
                "UPDATE appointments
                 SET patient_id = ?, clinician_id = ?, appointment_date = ?,
                     appointment_time = ?, reason = ?
                 WHERE appointment_id = ?
                   AND status IN ('pending', 'confirmed')"
            );
            $update->execute([
                $patientIdValue,
                $clinicianIdValue,
                $date,
                $time . ':00',
                $reason,
                $appointmentId,
            ]);

            log_activity($pdo, 'appointment.updated', 'appointment', $appointmentId);
            $pdo->commit();
            redirect('/caretrack/appointments/view.php?id=' . $appointmentId);
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('CareTrack appointment update failed: ' . $exception->getMessage());
            $errors[] = 'The appointment could not be updated. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="/caretrack/appointments/view.php?id=<?= (int) $appointmentId ?>"
       class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to Appointment
    </a>
    <h1 class="h3 mt-3 mb-1">Edit Appointment</h1>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="dashboard-section p-3 p-md-4">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="patient_id" class="form-label">Patient *</label>
            <select id="patient_id" name="patient_id" class="form-select" required>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= (int) $patient['patient_id'] ?>"
                        <?= (string) $patient['patient_id'] === $patientId ? 'selected' : '' ?>>
                        <?= e($patient['patient_code'] . ' — ' . $patient['last_name'] . ', ' . $patient['first_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="clinician_id" class="form-label">Clinician *</label>
            <select id="clinician_id" name="clinician_id" class="form-select" required>
                <?php if (!$currentClinicianIsActive): ?>
                    <option value="" selected>
                        Previously assigned clinician is inactive — select an active clinician
                    </option>
                <?php else: ?>
                    <option value="">Select a clinician</option>
                <?php endif; ?>
                <?php foreach ($clinicians as $clinician): ?>
                    <option value="<?= (int) $clinician['user_id'] ?>"
                        <?= (string) $clinician['user_id'] === $clinicianId ? 'selected' : '' ?>>
                        <?= e($clinician['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="appointment_date" class="form-label">Appointment Date *</label>
            <input type="date" id="appointment_date" name="appointment_date"
                   min="<?= e($today) ?>" class="form-control" required
                   value="<?= e($date) ?>">
        </div>
        <div class="col-md-6">
            <label for="appointment_time" class="form-label">Appointment Time *</label>
            <input type="time" id="appointment_time" name="appointment_time"
                   class="form-control" required value="<?= e($time) ?>">
        </div>
        <div class="col-12">
            <label for="reason" class="form-label">Reason for Visit *</label>
            <textarea id="reason" name="reason" rows="3" maxlength="255"
                      class="form-control" required><?= e($reason) ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="/caretrack/appointments/view.php?id=<?= (int) $appointmentId ?>"
           class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
