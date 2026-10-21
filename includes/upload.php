<?php

declare(strict_types=1);

use Aws\S3\S3Client;

function patient_photo_storage_mode(): string
{
    $mode = getenv('CARETRACK_PHOTO_STORAGE') ?: 'local';

    if (!in_array($mode, ['local', 's3'], true)) {
        throw new RuntimeException(
            'CARETRACK_PHOTO_STORAGE must be "local" or "s3".'
        );
    }

    if (getenv('VERCEL') === '1' && $mode !== 's3') {
        throw new RuntimeException(
            'CARETRACK_PHOTO_STORAGE=s3 is required on Vercel.'
        );
    }

    return $mode;
}

function patient_photo_s3_client(): S3Client
{
    static $client;

    if ($client instanceof S3Client) {
        return $client;
    }

    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

    if (!is_file($autoloadPath)) {
        throw new RuntimeException(
            'S3 photo storage dependencies are missing. Run composer install.'
        );
    }

    require_once $autoloadPath;

    if (!class_exists(S3Client::class)) {
        throw new RuntimeException('The AWS SDK for PHP is unavailable.');
    }

    $bucket = getenv('CARETRACK_S3_BUCKET');
    $accessKey = getenv('CARETRACK_S3_ACCESS_KEY_ID');
    $secretKey = getenv('CARETRACK_S3_SECRET_ACCESS_KEY');

    if (
        $bucket === false || $bucket === ''
        || $accessKey === false || $accessKey === ''
        || $secretKey === false || $secretKey === ''
    ) {
        throw new RuntimeException('S3 photo storage is not fully configured.');
    }

    $endpoint = getenv('CARETRACK_S3_ENDPOINT');
    $configuration = [
        'version' => 'latest',
        'region' => getenv('CARETRACK_S3_REGION') ?: 'us-east-1',
        'credentials' => [
            'key' => $accessKey,
            'secret' => $secretKey,
        ],
    ];

    if ($endpoint !== false && $endpoint !== '') {
        $configuration['endpoint'] = $endpoint;
        $configuration['use_path_style_endpoint'] = true;
    }

    $client = new S3Client($configuration);

    return $client;
}

function patient_photo_filename(?string $storedValue): ?string
{
    if (
        $storedValue === null
        || preg_match(
            '/\A(?:patient-photos\/)?([a-f0-9]{32}\.(?:jpg|png|webp))\z/',
            $storedValue,
            $matches
        ) !== 1
    ) {
        return null;
    }

    return $matches[1];
}

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

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];

    if (patient_photo_storage_mode() === 's3') {
        $body = fopen($file['tmp_name'], 'rb');

        if ($body === false) {
            throw new RuntimeException('The uploaded photo could not be read.');
        }

        try {
            patient_photo_s3_client()->putObject([
                'Bucket' => getenv('CARETRACK_S3_BUCKET'),
                'Key' => 'patient-photos/' . $filename,
                'Body' => $body,
                'ContentType' => $mimeType,
                'CacheControl' => 'private, no-store',
            ]);
        } finally {
            fclose($body);
        }

        return 'patient-photos/' . $filename;
    }

    $directory = dirname(__DIR__) . '/uploads/patient-photos';

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The photo upload directory could not be created.');
    }

    $destination = $directory . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The photo could not be saved.');
    }

    return $filename;
}

/**
 * @return array{body: string, content_type: string}|null
 */
function read_patient_photo(?string $storedValue): ?array
{
    $filename = patient_photo_filename($storedValue);

    if ($filename === null) {
        return null;
    }

    $contentTypes = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    $extension = pathinfo($filename, PATHINFO_EXTENSION);

    if (patient_photo_storage_mode() === 's3') {
        $result = patient_photo_s3_client()->getObject([
            'Bucket' => getenv('CARETRACK_S3_BUCKET'),
            'Key' => 'patient-photos/' . $filename,
        ]);

        return [
            'body' => $result['Body']->getContents(),
            'content_type' => $contentTypes[$extension],
        ];
    }

    $path = dirname(__DIR__) . '/uploads/patient-photos/' . $filename;

    if (!is_file($path)) {
        return null;
    }

    $body = file_get_contents($path);

    if ($body === false) {
        return null;
    }

    return [
        'body' => $body,
        'content_type' => $contentTypes[$extension],
    ];
}

function delete_patient_photo(?string $storedValue): void
{
    $filename = patient_photo_filename($storedValue);

    if ($filename === null) {
        return;
    }

    if (patient_photo_storage_mode() === 's3') {
        patient_photo_s3_client()->deleteObject([
            'Bucket' => getenv('CARETRACK_S3_BUCKET'),
            'Key' => 'patient-photos/' . $filename,
        ]);

        return;
    }

    $path = dirname(__DIR__) . '/uploads/patient-photos/' . $filename;

    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('The patient photo could not be deleted.');
    }
}
