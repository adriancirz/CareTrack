<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'clinician']);

$pageTitle = 'Medical Record Details';
$currentPage = 'medical-records';
$recordId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$recordId || $recordId < 1) {
    http_response_code(400);
    exit('Invalid medical record ID.');
}

$sql = 'SELECT
            mr.record_id,
            mr.patient_id,
            mr.clinician_id,
            mr.appointment_id,
            mr.visit_date,
            mr.chief_complaint,
            mr.clinical_notes,
            mr.treatment_notes,
            mr.created_at,
            mr.updated_at,
            p.patient_code,
            p.first_name,
            p.last_name,
            u.full_name AS clinician_name
        FROM medical_records mr
        INNER JOIN patients p ON p.patient_id = mr.patient_id
        INNER JOIN users u ON u.user_id = mr.clinician_id
        WHERE mr.record_id = ?';
$params = [$recordId];

if (($_SESSION['role'] ?? '') === 'clinician') {
    $sql .= ' AND mr.clinician_id = ?';
    $params[] = current_user_id();
}

$sql .= ' LIMIT 1';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$record = $statement->fetch();

if (!$record) {
    http_response_code(404);
    exit('Medical record not found.');
}

log_activity($pdo, 'medical_record.viewed', 'medical_record', $recordId);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="/caretrack/medical-records/index.php" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to Medical Records
    </a>
    <h1 class="h3 mt-3 mb-1">Medical Record Details</h1>
    <p class="text-secondary mb-0">Record #<?= (int) $record['record_id'] ?></p>
</div>

<section class="dashboard-section p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h5 mb-1">
                <?= e($record['first_name'] . ' ' . $record['last_name']) ?>
            </h2>
            <div class="text-secondary small">
                Patient Code: <?= e($record['patient_code']) ?>
            </div>
        </div>

        <a class="btn btn-outline-secondary btn-sm"
           href="/caretrack/patients/view.php?id=<?= (int) $record['patient_id'] ?>">
            View Patient
        </a>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="text-secondary small">Visit Date</div>
            <div class="fw-medium"><?= e($record['visit_date']) ?></div>
        </div>

        <div class="col-md-6">
            <div class="text-secondary small">Clinician</div>
            <div><?= e($record['clinician_name']) ?></div>
        </div>

        <div class="col-md-6">
            <div class="text-secondary small">Related Appointment</div>
            <div>
                <?php if ($record['appointment_id'] !== null): ?>
                    <a href="/caretrack/appointments/view.php?id=<?= (int) $record['appointment_id'] ?>">
                        Appointment #<?= (int) $record['appointment_id'] ?>
                    </a>
                <?php else: ?>
                    Not linked to an appointment
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12">
            <hr>
            <h3 class="h6">Chief Complaint</h3>
            <div><?= nl2br(e($record['chief_complaint'])) ?></div>
        </div>

        <div class="col-12">
            <h3 class="h6">Clinical Notes</h3>
            <div>
                <?php if ($record['clinical_notes'] !== null && $record['clinical_notes'] !== ''): ?>
                    <?= nl2br(e($record['clinical_notes'])) ?>
                <?php else: ?>
                    <span class="text-secondary">No clinical notes recorded.</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-12">
            <h3 class="h6">Treatment Notes</h3>
            <div>
                <?php if ($record['treatment_notes'] !== null && $record['treatment_notes'] !== ''): ?>
                    <?= nl2br(e($record['treatment_notes'])) ?>
                <?php else: ?>
                    <span class="text-secondary">No treatment notes recorded.</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-6">
            <div class="text-secondary small">Created</div>
            <div><?= e($record['created_at']) ?></div>
        </div>

        <div class="col-md-6">
            <div class="text-secondary small">Last Updated</div>
            <div><?= e($record['updated_at']) ?></div>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0">
        Medical records are read-only in this version. Any correction workflow should
        preserve the original entry and document who made the amendment.
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
