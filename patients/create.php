<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

require_role(['admin', 'receptionist']);

$pageTitle = 'Add Patient';
$currentPage = 'patients';

$errors = [];
$firstName = '';
$lastName = '';
$birthDate = '';
$sex = 'undisclosed';
$contactNumber = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
        $errors[] = 'Your session token is invalid. Refresh the page and try again.';
    }

    $firstNameInput = $_POST['first_name'] ?? '';
    $lastNameInput = $_POST['last_name'] ?? '';
    $birthDateInput = $_POST['birth_date'] ?? '';
    $sexInput = $_POST['sex'] ?? 'undisclosed';
    $contactNumberInput = $_POST['contact_number'] ?? '';
    $addressInput = $_POST['address'] ?? '';

    $firstName = is_string($firstNameInput) ? trim($firstNameInput) : '';
    $lastName = is_string($lastNameInput) ? trim($lastNameInput) : '';
    $birthDate = is_string($birthDateInput) ? trim($birthDateInput) : '';
    $sex = is_string($sexInput) ? $sexInput : 'undisclosed';
    $contactNumber = is_string($contactNumberInput) ? trim($contactNumberInput) : '';
    $address = is_string($addressInput) ? trim($addressInput) : '';

    if ($firstName === '' || mb_strlen($firstName) > 80) {
        $errors[] = 'Enter a first name of up to 80 characters.';
    }

    if ($lastName === '' || mb_strlen($lastName) > 80) {
        $errors[] = 'Enter a last name of up to 80 characters.';
    }

    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validDate = $dateObject !== false
        && $dateObject->format('Y-m-d') === $birthDate
        && ($dateErrors === false
            || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0));

    if (!$validDate || $birthDate > date('Y-m-d')) {
        $errors[] = 'Enter a valid birth date that is not in the future.';
    }

    if (!in_array($sex, ['male', 'female', 'other', 'undisclosed'], true)) {
        $errors[] = 'Select a valid sex option.';
    }

    if (mb_strlen($contactNumber) > 30) {
        $errors[] = 'Contact number must be 30 characters or fewer.';
    }

    if (mb_strlen($address) > 255) {
        $errors[] = 'Address must be 255 characters or fewer.';
    }

    $photo = null;

    if ($errors === []) {
        try {
            $photo = upload_patient_photo();
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if ($errors === []) {
        try {
            $patientCode = 'PT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'INSERT INTO patients
                    (patient_code, first_name, last_name, birth_date, sex,
                     contact_number, address, profile_image, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $statement->execute([
                $patientCode,
                $firstName,
                $lastName,
                $birthDate,
                $sex,
                $contactNumber !== '' ? $contactNumber : null,
                $address !== '' ? $address : null,
                $photo,
                current_user_id(),
            ]);

            $patientId = (int) $pdo->lastInsertId();
            log_activity($pdo, 'patient.created', 'patient', $patientId);
            $pdo->commit();
            redirect('/caretrack/patients/view.php?id=' . $patientId);
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($photo !== null) {
                try {
                    delete_patient_photo($photo);
                } catch (RuntimeException $cleanupException) {
                    error_log('CareTrack patient photo cleanup failed: ' . $cleanupException->getMessage());
                }
            }

            error_log('CareTrack patient creation failed: ' . $exception->getMessage());
            $errors[] = 'The patient could not be saved. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="/caretrack/patients/index.php" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to Patients
    </a>
    <h1 class="h3 mt-3 mb-1">Add Patient</h1>
    <p class="text-secondary mb-0">Enter the patient's basic information.</p>
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

<form method="post" enctype="multipart/form-data" class="dashboard-section p-3 p-md-4">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="2097152">

    <div class="row g-3">
        <div class="col-md-6">
            <label for="first_name" class="form-label">First Name *</label>
            <input id="first_name" name="first_name" class="form-control"
                   maxlength="80" required value="<?= e($firstName) ?>">
        </div>

        <div class="col-md-6">
            <label for="last_name" class="form-label">Last Name *</label>
            <input id="last_name" name="last_name" class="form-control"
                   maxlength="80" required value="<?= e($lastName) ?>">
        </div>

        <div class="col-md-6">
            <label for="birth_date" class="form-label">Birth Date *</label>
            <input id="birth_date" name="birth_date" type="date"
                   max="<?= e(date('Y-m-d')) ?>" class="form-control"
                   required value="<?= e($birthDate) ?>">
        </div>

        <div class="col-md-6">
            <label for="sex" class="form-label">Sex *</label>
            <select id="sex" name="sex" class="form-select" required>
                <?php foreach ([
                    'undisclosed' => 'Undisclosed',
                    'male' => 'Male',
                    'female' => 'Female',
                    'other' => 'Other',
                ] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $sex === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label for="contact_number" class="form-label">Contact Number</label>
            <input id="contact_number" name="contact_number"
                   class="form-control" maxlength="30"
                   value="<?= e($contactNumber) ?>">
        </div>

        <div class="col-md-6">
            <label for="profile_image" class="form-label">Patient Photo (optional)</label>
            <input id="profile_image" name="profile_image" type="file"
                   accept=".jpg,.jpeg,.png,.webp" class="form-control">
            <div class="form-text">JPG, PNG, or WebP. Maximum 2 MB.</div>
        </div>

        <div class="col-12">
            <label for="address" class="form-label">Address</label>
            <textarea id="address" name="address" rows="3"
                      maxlength="255" class="form-control"><?= e($address) ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Save Patient</button>
        <a href="/caretrack/patients/index.php"
           class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
