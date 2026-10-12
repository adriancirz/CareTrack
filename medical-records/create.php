<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'clinician']);

$pageTitle = 'Create Medical Record';
$currentPage = 'medical-records';
$role = $_SESSION['role'] ?? '';
$errors = [];
$patientId = '';
$clinicianId = $role === 'clinician' ? (string) current_user_id() : '';
$appointmentId = '';
$visitDate = (new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Manila')
))->format('Y-m-d');
$chiefComplaint = '';
$clinicalNotes = '';
$treatmentNotes = '';

$patients = $pdo->query(
    'SELECT patient_id, patient_code, first_name, last_name
     FROM patients
     WHERE deleted_at IS NULL
     ORDER BY last_name, first_name'
)->fetchAll();

if ($role === 'admin') {
    $clinicians = $pdo->query(
        "SELECT user_id, full_name
         FROM users
         WHERE role = 'clinician' AND status = 'active'
         ORDER BY full_name"
    )->fetchAll();
} else {
    $clinicianStatement = $pdo->prepare(
        "SELECT user_id, full_name
         FROM users
         WHERE user_id = ? AND role = 'clinician' AND status = 'active'"
    );
    $clinicianStatement->execute([current_user_id()]);
    $clinicians = $clinicianStatement->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
        $errors[] = 'Invalid security token. Refresh the page and try again.';
    }

    $patientInput = $_POST['patient_id'] ?? '';
    $appointmentInput = $_POST['appointment_id'] ?? '';
    $visitDateInput = $_POST['visit_date'] ?? '';
    $chiefComplaintInput = $_POST['chief_complaint'] ?? '';
    $clinicalNotesInput = $_POST['clinical_notes'] ?? '';
    $treatmentNotesInput = $_POST['treatment_notes'] ?? '';

    $patientId = is_string($patientInput) ? trim($patientInput) : '';
    $appointmentId = is_string($appointmentInput) ? trim($appointmentInput) : '';
    $visitDate = is_string($visitDateInput) ? trim($visitDateInput) : '';
    $chiefComplaint = is_string($chiefComplaintInput) ? trim($chiefComplaintInput) : '';
    $clinicalNotes = is_string($clinicalNotesInput) ? trim($clinicalNotesInput) : '';
    $treatmentNotes = is_string($treatmentNotesInput) ? trim($treatmentNotesInput) : '';

    if ($role === 'admin') {
        $clinicianInput = $_POST['clinician_id'] ?? '';
        $clinicianId = is_string($clinicianInput) ? trim($clinicianInput) : '';
    }

    $patientIdValue = filter_var($patientId, FILTER_VALIDATE_INT);
    $clinicianIdValue = filter_var($clinicianId, FILTER_VALIDATE_INT);
    $appointmentIdValue = $appointmentId === ''
        ? null
        : filter_var($appointmentId, FILTER_VALIDATE_INT);

    if ($patientIdValue === false || $patientIdValue < 1) {
        $errors[] = 'Select a valid patient.';
    }
    if ($clinicianIdValue === false || $clinicianIdValue < 1) {
        $errors[] = 'Select a valid clinician.';
    }

    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validDate = $dateObject !== false
        && $dateObject->format('Y-m-d') === $visitDate
        && ($dateErrors === false
            || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0));
    $today = (new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Manila')
    ))->format('Y-m-d');

    if (!$validDate || $visitDate > $today) {
        $errors[] = 'Enter a valid visit date that is not in the future.';
    }
    if ($chiefComplaint === '' || mb_strlen($chiefComplaint) > 10000) {
        $errors[] = 'Enter a chief complaint of up to 10,000 characters.';
    }
    if (mb_strlen($clinicalNotes) > 20000) {
        $errors[] = 'Clinical notes must be 20,000 characters or fewer.';
    }
    if (mb_strlen($treatmentNotes) > 20000) {
        $errors[] = 'Treatment notes must be 20,000 characters or fewer.';
    }
    if ($appointmentIdValue === false || ($appointmentIdValue !== null && $appointmentIdValue < 1)) {
        $errors[] = 'Enter a valid appointment ID or leave the field blank.';
    }

    if ($role === 'clinician' && $clinicianIdValue !== current_user_id()) {
        $errors[] = 'Clinicians may create records only under their own account.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            $patientCheck = $pdo->prepare(
                'SELECT patient_id FROM patients
                 WHERE patient_id = ? AND deleted_at IS NULL
                 FOR UPDATE'
            );
            $patientCheck->execute([$patientIdValue]);
            if ($patientCheck->fetchColumn() === false) {
                throw new DomainException('The selected patient does not exist or is archived.');
            }

            $clinicianCheck = $pdo->prepare(
                "SELECT user_id FROM users
                 WHERE user_id = ? AND role = 'clinician' AND status = 'active'
                 FOR UPDATE"
            );
            $clinicianCheck->execute([$clinicianIdValue]);
            if ($clinicianCheck->fetchColumn() === false) {
                throw new DomainException('The selected clinician is not active.');
            }

            if ($appointmentIdValue !== null) {
                $appointmentCheck = $pdo->prepare(
                    'SELECT appointment_id FROM appointments
                     WHERE appointment_id = ?
                       AND patient_id = ?
                       AND clinician_id = ?
                     FOR UPDATE'
                );
                $appointmentCheck->execute([
                    $appointmentIdValue,
                    $patientIdValue,
                    $clinicianIdValue,
                ]);
                if ($appointmentCheck->fetchColumn() === false) {
                    throw new DomainException(
                        'The appointment must belong to the selected patient and clinician.'
                    );
                }
            }

            $insert = $pdo->prepare(
                'INSERT INTO medical_records
                    (patient_id, clinician_id, appointment_id, visit_date,
                     chief_complaint, clinical_notes, treatment_notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $patientIdValue,
                $clinicianIdValue,
                $appointmentIdValue,
                $visitDate,
                $chiefComplaint,
                $clinicalNotes !== '' ? $clinicalNotes : null,
                $treatmentNotes !== '' ? $treatmentNotes : null,
            ]);

            $recordId = (int) $pdo->lastInsertId();
            log_activity($pdo, 'medical_record.created', 'medical_record', $recordId);
            $pdo->commit();
            redirect('/caretrack/medical-records/view.php?id=' . $recordId);
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('CareTrack medical record creation failed: ' . $exception->getMessage());
            $errors[] = 'The medical record could not be saved. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="/caretrack/medical-records/index.php" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to Medical Records
    </a>
    <h1 class="h3 mt-3 mb-1">Create Medical Record</h1>
    <p class="text-secondary mb-0">Enter accurate consultation information.</p>
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

<?php if ($patients === []): ?>
    <div class="alert alert-warning">
        Add an active patient before creating a medical record.
    </div>
<?php elseif ($clinicians === []): ?>
    <div class="alert alert-warning">
        No active clinician account is available.
    </div>
<?php else: ?>
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
                            <?= e($patient['patient_code'] . ' — ' . $patient['last_name'] . ', ' . $patient['first_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($role === 'admin'): ?>
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
            <?php else: ?>
                <div class="col-md-6">
                    <label class="form-label" for="clinician-display">Clinician</label>
                    <input id="clinician-display" class="form-control"
                           value="<?= e($clinicians[0]['full_name']) ?>" disabled>
                </div>
            <?php endif; ?>

            <div class="col-md-6">
                <label for="visit_date" class="form-label">Visit Date *</label>
                <input type="date" id="visit_date" name="visit_date"
                       max="<?= e($today) ?>" class="form-control" required
                       value="<?= e($visitDate) ?>">
            </div>

            <div class="col-md-6">
                <label for="appointment_id" class="form-label">
                    Related Appointment ID (optional)
                </label>
                <input
                    type="number"
                    id="appointment_id"
                    name="appointment_id"
                    min="1"
                    class="form-control"
                    value="<?= e($appointmentId) ?>"
                    placeholder="Enter an appointment ID"
                >
                <div class="form-text">
                    Leave blank if this visit is not linked to an appointment.
                    You can find the appointment ID on its details page.
                </div>
            </div>

            <div class="col-12">
                <label for="chief_complaint" class="form-label">Chief Complaint *</label>
                <textarea id="chief_complaint" name="chief_complaint" rows="3"
                          maxlength="10000" class="form-control"
                          required><?= e($chiefComplaint) ?></textarea>
            </div>

            <div class="col-12">
                <label for="clinical_notes" class="form-label">Clinical Notes</label>
                <textarea id="clinical_notes" name="clinical_notes" rows="5"
                          maxlength="20000" class="form-control"><?= e($clinicalNotes) ?></textarea>
            </div>

            <div class="col-12">
                <label for="treatment_notes" class="form-label">Treatment Notes</label>
                <textarea id="treatment_notes" name="treatment_notes" rows="4"
                          maxlength="20000" class="form-control"><?= e($treatmentNotes) ?></textarea>
            </div>
        </div>

        <div class="alert alert-info mt-4 mb-0">
            Enter only accurate information documented by an authorized clinician.
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Save Medical Record</button>
            <a href="/caretrack/medical-records/index.php"
               class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
