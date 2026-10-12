<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

require_role(['admin', 'receptionist']);

$pageTitle = 'Edit Patient';
$currentPage = 'patients';
$patientId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$patientId || $patientId < 1) {
    http_response_code(400);
    exit('Invalid patient ID.');
}

$statement = $pdo->prepare(
    'SELECT * FROM patients
     WHERE patient_id = ? AND deleted_at IS NULL
     LIMIT 1'
);
$statement->execute([$patientId]);
$patient = $statement->fetch();

if (!$patient) {
    http_response_code(404);
    exit('Patient not found.');
}

$errors = [];
$firstName = $patient['first_name'];
$lastName = $patient['last_name'];
$birthDate = $patient['birth_date'] ?? '';
$sex = $patient['sex'];
$contactNumber = $patient['contact_number'] ?? '';
$address = $patient['address'] ?? '';

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

    $newPhoto = null;

    if ($errors === []) {
        try {
            $newPhoto = upload_patient_photo();
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if ($errors === []) {
        $photoToSave = $newPhoto ?? $patient['profile_image'];

        try {
            $pdo->beginTransaction();
            $update = $pdo->prepare(
                'UPDATE patients
                 SET first_name = ?, last_name = ?, birth_date = ?, sex = ?,
                     contact_number = ?, address = ?, profile_image = ?
                 WHERE patient_id = ? AND deleted_at IS NULL'
            );

            $update->execute([
                $firstName,
                $lastName,
                $birthDate,
                $sex,
                $contactNumber !== '' ? $contactNumber : null,
                $address !== '' ? $address : null,
                $photoToSave,
                $patientId,
            ]);

            if ($update->rowCount() === 0) {
                $exists = $pdo->prepare(
                    'SELECT 1 FROM patients
                     WHERE patient_id = ? AND deleted_at IS NULL'
                );
                $exists->execute([$patientId]);

                if ($exists->fetchColumn() === false) {
                    $pdo->rollBack();

                    if ($newPhoto !== null) {
                        try {
                            delete_patient_photo($newPhoto);
                        } catch (RuntimeException $cleanupException) {
                            error_log('CareTrack replacement photo cleanup failed: ' . $cleanupException->getMessage());
                        }
                    }

                    http_response_code(404);
                    exit('Patient not found.');
                }
            }

            log_activity($pdo, 'patient.updated', 'patient', $patientId);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($newPhoto !== null) {
                try {
                    delete_patient_photo($newPhoto);
                } catch (RuntimeException $cleanupException) {
                    error_log('CareTrack replacement photo cleanup failed: ' . $cleanupException->getMessage());
                }
            }

            error_log('CareTrack patient update failed: ' . $exception->getMessage());
            $errors[] = 'The patient could not be updated. Please try again.';
        }

        if ($errors === []) {
            if ($newPhoto !== null && is_string($patient['profile_image'])) {
                try {
                    delete_patient_photo($patient['profile_image']);
                } catch (RuntimeException $exception) {
                    error_log('CareTrack old patient photo cleanup failed: ' . $exception->getMessage());
                }
            }

            redirect('/caretrack/patients/view.php?id=' . $patientId);
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <a href="/caretrack/patients/view.php?id=<?= (int) $patientId ?>"
       class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to Patient Details
    </a>
    <h1 class="h3 mt-3 mb-1">Edit Patient</h1>
    <p class="text-secondary mb-0">
        Patient Code: <?= e($patient['patient_code']) ?>
    </p>
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
                   maxlength="30" class="form-control"
                   value="<?= e($contactNumber) ?>">
        </div>

        <div class="col-md-6">
            <label for="profile_image" class="form-label">
                Replace Patient Photo (optional)
            </label>
            <input id="profile_image" name="profile_image" type="file"
                   accept=".jpg,.jpeg,.png,.webp" class="form-control">
            <div class="form-text">
                Leave empty to keep the current photo. Maximum 2 MB.
            </div>
        </div>

        <div class="col-12">
            <label for="address" class="form-label">Address</label>
            <textarea id="address" name="address" rows="3"
                      maxlength="255" class="form-control"><?= e($address) ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="/caretrack/patients/view.php?id=<?= (int) $patientId ?>"
           class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
