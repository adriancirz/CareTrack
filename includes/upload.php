<?php

declare(strict_types=1);

function upload_patient_photo(string $field = 'profile_image'): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The photo could not be uploaded.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $maxSize = 2 * 1024 * 1024;

    if ($file['size'] <= 0 || $file['size'] > $maxSize) {
        throw new RuntimeException('The photo must be smaller than 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Only JPG, PNG, and WebP photos are allowed.');
    }

    $directory = dirname(__DIR__) . '/uploads/patient-photos';

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The photo upload directory could not be created.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $destination = $directory . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The photo could not be saved.');
    }

    return $filename;
}

function delete_patient_photo(?string $filename): void
{
    if (!$filename || basename($filename) !== $filename) {
        return;
    }

    $path = dirname(__DIR__) . '/uploads/patient-photos/' . $filename;

    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('The patient photo could not be deleted.');
    }
}
