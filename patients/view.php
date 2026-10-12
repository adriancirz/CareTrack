<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();

$pageTitle = 'Patient Details';
$currentPage = 'patients';
$patientId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$patientId || $patientId < 1) {
    http_response_code(400);
    exit('Invalid patient ID.');
}

$statement = $pdo->prepare(
    'SELECT p.*, u.full_name AS creator_name
     FROM patients p
     LEFT JOIN users u ON u.user_id = p.created_by
     WHERE p.patient_id = ? AND p.deleted_at IS NULL
     LIMIT 1'
);
$statement->execute([$patientId]);
$patient = $statement->fetch();

if (!$patient) {
    http_response_code(404);
    exit('Patient not found.');
}

log_activity($pdo, 'patient.viewed', 'patient', $patientId);

$photoFilename = $patient['profile_image'];
$hasPhoto = is_string($photoFilename)
    && preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $photoFilename) === 1;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <a href="/caretrack/patients/index.php" class="text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to Patients
        </a>
        <h1 class="h3 mt-3 mb-1">Patient Details</h1>
        <p class="text-secondary mb-0">
            Patient Code: <?= e($patient['patient_code']) ?>
        </p>
    </div>

    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'receptionist'], true)): ?>
        <a href="/caretrack/patients/edit.php?id=<?= (int) $patient['patient_id'] ?>"
           class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i>Edit Patient
        </a>
    <?php endif; ?>
</div>

<section class="dashboard-section p-3 p-md-4">
    <div class="row g-4">
        <div class="col-md-3 text-center">
            <?php if ($hasPhoto): ?>
                <img
                    src="/caretrack/uploads/patient-photos/<?= e(rawurlencode($photoFilename)) ?>"
                    alt="Patient photo"
                    class="img-fluid rounded-circle border"
                    style="width: 160px; height: 160px; object-fit: cover;"
                >
            <?php else: ?>
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center"
                     style="width: 160px; height: 160px;">
                    <i class="bi bi-person text-secondary" style="font-size: 4rem;"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-9">
            <h2 class="h4 mb-4">
                <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?>
            </h2>

            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="text-secondary small">Birth Date</div>
                    <div><?= e($patient['birth_date'] ?? 'Not provided') ?></div>
                </div>

                <div class="col-sm-6">
                    <div class="text-secondary small">Sex</div>
                    <div><?= e(ucfirst($patient['sex'])) ?></div>
                </div>

                <div class="col-sm-6">
                    <div class="text-secondary small">Contact Number</div>
                    <div><?= e($patient['contact_number'] ?: 'Not provided') ?></div>
                </div>

                <div class="col-sm-6">
                    <div class="text-secondary small">Created By</div>
                    <div><?= e($patient['creator_name'] ?: 'Unavailable') ?></div>
                </div>

                <div class="col-12">
                    <div class="text-secondary small">Address</div>
                    <div><?= nl2br(e($patient['address'] ?: 'Not provided')) ?></div>
                </div>

                <div class="col-sm-6">
                    <div class="text-secondary small">Date Created</div>
                    <div><?= e($patient['created_at']) ?></div>
                </div>

                <div class="col-sm-6">
                    <div class="text-secondary small">Last Updated</div>
                    <div><?= e($patient['updated_at']) ?></div>
                </div>
            </div>

            <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'receptionist'], true)): ?>
                <hr class="my-4">
                <form method="post" action="/caretrack/patients/archive.php"
                      onsubmit="return confirm('Archive this patient record?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="patient_id"
                           value="<?= (int) $patient['patient_id'] ?>">
                    <button type="submit" class="btn btn-outline-danger">
                        Archive Patient
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
