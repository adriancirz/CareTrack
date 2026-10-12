<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'receptionist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$submittedToken = $_POST['csrf_token'] ?? null;

if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
    http_response_code(403);
    exit('Invalid security token. Go back and try again.');
}

$rawPatientId = $_POST['patient_id'] ?? null;
$patientId = filter_var($rawPatientId, FILTER_VALIDATE_INT);

if ($patientId === false || $patientId === null || $patientId < 1) {
    http_response_code(400);
    exit('Invalid patient ID.');
}

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'UPDATE patients
         SET deleted_at = CURRENT_TIMESTAMP
         WHERE patient_id = ? AND deleted_at IS NULL'
    );
    $statement->execute([$patientId]);

    if ($statement->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Patient not found or already archived.');
    }

    log_activity($pdo, 'patient.archived', 'patient', $patientId);
    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('CareTrack patient archive failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The patient could not be archived. Please try again.');
}

redirect('/caretrack/patients/index.php');
