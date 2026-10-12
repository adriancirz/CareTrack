<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointments.php';

require_role(['admin', 'receptionist']);

$pageTitle = 'Schedule Appointment';
$currentPage = 'appointments';
$errors = [];
$patientId = '';
$clinicianId = '';
$date = '';
$time = '';
$reason = '';

$patients = $pdo->query(
    'SELECT patient_id, patient_code, first_name, last_name
     FROM patients WHERE deleted_at IS NULL
     ORDER BY last_name, first_name'
)->fetchAll();
$clinicians = active_clinicians($pdo);
$today = (new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Manila')
))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    }

    $rawPatientId = $_POST['patient_id'] ?? '';
    $rawClinicianId = $_POST['clinician_id'] ?? '';
    $rawDate = $_POST['appointment_date'] ?? '';
    $rawTime = $_POST['appointment_time'] ?? '';
    $rawReason = $_POST['reason'] ?? '';

    $patientId = is_string($rawPatientId) ? $rawPatientId : '';
    $clinicianId = is_string($rawClinicianId) ? $rawClinicianId : '';
    $date = is_string($rawDate) ? $rawDate : '';
    $time = is_string($rawTime) ? $rawTime : '';
    $reason = is_string($rawReason) ? trim($rawReason) : '';

    $patientIdValue = filter_var($patientId, FILTER_VALIDATE_INT);
    $clinicianIdValue = filter_var($clinicianId, FILTER_VALIDATE_INT);

    if ($patientIdValue === false || $patientIdValue < 1) {
        $errors[] = 'Select a valid patient.';
    }
    if ($clinicianIdValue === false || $clinicianIdValue < 1) {
        $errors[] = 'Select a valid clinician.';
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
        $errors[] = 'Enter an appointment reason of up to 255 characters.';
    }
    if ($patientIdValue !== false && $patientIdValue > 0
        && !active_patient_exists($pdo, $patientIdValue)) {
        $errors[] = 'The selected patient is not active.';
    }
    if ($clinicianIdValue !== false && $clinicianIdValue > 0) {
        $clinicianIsActive = false;
        foreach ($clinicians as $clinician) {
            if ((int) $clinician['user_id'] === $clinicianIdValue) {
                $clinicianIsActive = true;
                break;
            }
        }
        if (!$clinicianIsActive) {
            $errors[] = 'Select an active clinician.';
        }
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            if (!lock_active_clinician($pdo, $clinicianIdValue)) {
                throw new DomainException('The selected clinician is no longer active.');
            }

            if (!appointment_slot_available($pdo, $date, $time, $clinicianIdValue)) {
                throw new DomainException('That clinician already has an appointment at this date and time.');
            }

            $insert = $pdo->prepare(
                "INSERT INTO appointments
                    (patient_id, clinician_id, appointment_date, appointment_time,
                     reason, status, created_by)
                 VALUES (?, ?, ?, ?, ?, 'pending', ?)"
            );
            $insert->execute([
                $patientIdValue,
                $clinicianIdValue,
                $date,
                $time . ':00',
                $reason,
                current_user_id(),
            ]);

            $appointmentId = (int) $pdo->lastInsertId();
            log_activity($pdo, 'appointment.created', 'appointment', $appointmentId);
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
            error_log('CareTrack appointment creation failed: ' . $exception->getMessage());
            $errors[] = 'The appointment could not be saved. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="/caretrack/appointments/index.php" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to Appointments
    </a>
    <h1 class="h3 mt-3 mb-1">Schedule Appointment</h1>
</div>

<?php if ($patients === []): ?>
    <div class="alert alert-warning">
        There are no active patients. Add a patient before scheduling an appointment.
    </div>
<?php elseif ($clinicians === []): ?>
    <div class="alert alert-warning">
        There are no active clinician accounts. An administrator must activate a clinician account first.
    </div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($patients !== [] && $clinicians !== []): ?>
<form method="post" class="dashboard-section p-3 p-md-4">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="patient_id" class="form-label">Patient *</label>
            <select id="patient_id" name="patient_id" class="form-select" required>
                <option value="">Select a patient</option>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= (int) $patient['patient_id'] ?>"
                        <?= (string) $patient['patient_id'] === $patientId ? 'selected' : '' ?>>
                        <?= e($patient['patient_code'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="clinician_id" class="form-label">Clinician *</label>
            <select id="clinician_id" name="clinician_id" class="form-select" required>
                <option value="">Select a clinician</option>
                <?php foreach ($clinicians as $clinician): ?>
                    <option value="<?= (int) $clinician['user_id'] ?>"
                        <?= (string) $clinician['user_id'] === $clinicianId ? 'selected' : '' ?>>
                        <?= e($clinician['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="appointment_date" class="form-label">Date *</label>
            <input type="date" id="appointment_date" name="appointment_date"
                   min="<?= e($today) ?>" class="form-control" required value="<?= e($date) ?>">
        </div>
        <div class="col-md-6">
            <label for="appointment_time" class="form-label">Time *</label>
            <input type="time" id="appointment_time" name="appointment_time"
                   class="form-control" required value="<?= e($time) ?>">
        </div>
        <div class="col-12">
            <label for="reason" class="form-label">Reason *</label>
            <textarea id="reason" name="reason" class="form-control"
                      maxlength="255" rows="3" required><?= e($reason) ?></textarea>
        </div>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Save Appointment</button>
        <a href="/caretrack/appointments/index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
