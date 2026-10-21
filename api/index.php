<?php

declare(strict_types=1);

$requestedPath = $_GET['path'] ?? 'index.php';
$pathSegments = is_string($requestedPath)
    ? explode('/', $requestedPath)
    : [];
$publicRootFiles = [
    'index.php',
    'login.php',
    'logout.php',
    'register.php',
    'setup-admin.php',
];
$publicDirectories = [
    'activity-logs',
    'appointments',
    'dashboard',
    'medical-records',
    'patients',
    'users',
];

if (
    !is_string($requestedPath)
    || preg_match('/\A[a-zA-Z0-9_./-]+\.php\z/', $requestedPath) !== 1
    || preg_match('~(?:\A|/)\.\.(?:/|\z)~', $requestedPath) === 1
    || (
        count($pathSegments) === 1
        && !in_array($requestedPath, $publicRootFiles, true)
    )
    || (
        count($pathSegments) > 1
        && !in_array($pathSegments[0], $publicDirectories, true)
    )
) {
    http_response_code(404);
    exit('Not found.');
}

$projectRoot = realpath(dirname(__DIR__));
$scriptPath = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . $requestedPath);

if (
    $projectRoot === false
    || $scriptPath === false
    || !str_starts_with($scriptPath, $projectRoot . DIRECTORY_SEPARATOR)
    || !is_file($scriptPath)
) {
    http_response_code(404);
    exit('Not found.');
}

require $scriptPath;
