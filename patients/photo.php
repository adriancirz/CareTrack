<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

require_login();

$patientId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$patientId || $patientId < 1) {
    http_response_code(404);
    exit;
}

$statement = $pdo->prepare(
    'SELECT profile_image
     FROM patients
     WHERE patient_id = ? AND deleted_at IS NULL
     LIMIT 1'
);
$statement->execute([$patientId]);
$storedValue = $statement->fetchColumn();

if (!is_string($storedValue)) {
    http_response_code(404);
    exit;
}

try {
    $photo = read_patient_photo($storedValue);
} catch (RuntimeException $exception) {
    error_log('CareTrack patient photo retrieval failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The patient photo is temporarily unavailable.');
}

if ($photo === null) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $photo['content_type']);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($photo['body']));
echo $photo['body'];
