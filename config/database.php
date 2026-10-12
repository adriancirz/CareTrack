<?php

declare(strict_types=1);

$host = getenv('CARETRACK_DB_HOST') ?: '127.0.0.1';
$dbname = getenv('CARETRACK_DB_NAME') ?: 'caretrack_db';
$username = getenv('CARETRACK_DB_USER') ?: 'root';
$password = getenv('CARETRACK_DB_PASSWORD');

if ($password === false) {
    $password = '';
}

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $exception) {
    error_log('CareTrack database connection failed: ' . $exception->getMessage());

    http_response_code(500);
    exit('The application is temporarily unavailable.');
}
