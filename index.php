<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$tables = [
    'users',
    'patients',
    'appointments',
    'medical_records',
    'activity_logs',
];

$databaseReady = true;

try {
    foreach ($tables as $table) {
        $statement = $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        $statement->fetch();
    }
} catch (PDOException $exception) {
    error_log('CareTrack schema check failed: ' . $exception->getMessage());
    $databaseReady = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CareTrack - Setup Check</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="card shadow-sm mx-auto" style="max-width: 650px;">
            <div class="card-body p-4">
                <h1 class="h3 mb-2">CareTrack</h1>
                <p class="text-secondary">
                    Clinic Information and Appointment Management System
                </p>

                <?php if ($databaseReady): ?>
                    <div class="alert alert-success">
                        Database connection and schema check passed.
                    </div>

                    <h2 class="h6">Verified tables</h2>
                    <ul>
                        <?php foreach ($tables as $table): ?>
                            <li><?= e($table) ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="mb-0">
                        <strong>Stage 1:</strong> Setup verification passed.
                    </p>
                <?php else: ?>
                    <div class="alert alert-danger">
                        The database schema could not be verified.
                        Check your database configuration and SQL setup.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
