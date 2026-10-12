<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = $currentPage ?? 'dashboard';

$navigation = [
    'dashboard' => [
        'label' => 'Dashboard',
        'url' => '/caretrack/dashboard/index.php',
        'icon' => 'bi-speedometer2',
    ],
    'patients' => [
        'label' => 'Patients',
        'url' => '/caretrack/patients/index.php',
        'icon' => 'bi-people',
    ],
    'appointments' => [
        'label' => 'Appointments',
        'url' => '/caretrack/appointments/index.php',
        'icon' => 'bi-calendar-check',
    ],
];

if (in_array($_SESSION['role'] ?? '', ['admin', 'clinician'], true)) {
    $navigation['medical-records'] = [
        'label' => 'Medical Records',
        'url' => '/caretrack/medical-records/index.php',
        'icon' => 'bi-clipboard2-pulse',
    ];
}

if (($_SESSION['role'] ?? '') === 'admin') {
    $navigation['users'] = [
        'label' => 'User Management',
        'url' => '/caretrack/users/index.php',
        'icon' => 'bi-person-gear',
    ];
    $navigation['activity-logs'] = [
        'label' => 'Activity Logs',
        'url' => '/caretrack/activity-logs/index.php',
        'icon' => 'bi-journal-text',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | CareTrack</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >
    <link
        href="/caretrack/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>"
        rel="stylesheet"
    >
</head>
<body>
<nav class="navbar app-navbar sticky-top">
    <div class="container-fluid px-3 px-lg-4 gap-2">
        <button
            class="btn btn-icon d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileSidebar"
            aria-controls="mobileSidebar"
            aria-label="Open navigation"
        >
            <i class="bi bi-list fs-5"></i>
        </button>

        <a class="navbar-brand app-brand" href="/caretrack/dashboard/index.php">
            <span class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
            <span>CareTrack <small>CLINIC MANAGEMENT</small></span>
        </a>

        <div class="ms-auto d-flex align-items-center gap-2 gap-sm-3">
            <div class="user-chip d-none d-sm-flex align-items-center gap-2">
                <span class="user-avatar"><i class="bi bi-person-fill"></i></span>
                <span class="user-details">
                    <span class="user-name"><?= e($_SESSION['full_name'] ?? 'Staff') ?></span>
                    <span class="user-role"><?= e(ucfirst((string) ($_SESSION['role'] ?? 'staff'))) ?></span>
                </span>
            </div>

            <form method="post" action="/caretrack/logout.php" class="m-0">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >
                <button class="btn btn-logout btn-sm" type="submit">
                    <i class="bi bi-box-arrow-right me-1"></i><span class="d-none d-sm-inline">Sign out</span><span class="d-sm-none">Exit</span>
                </button>
            </form>
        </div>
    </div>
</nav>

<div class="container-fluid app-layout">
    <div class="row">
        <aside class="col-lg-2 d-none d-lg-block sidebar py-4">
            <p class="sidebar-label px-3">
                Workspace
            </p>

            <nav class="nav flex-column gap-1">
                <?php foreach ($navigation as $key => $item): ?>
                    <a
                        class="nav-link sidebar-link <?= $currentPage === $key ? 'active' : '' ?>"
                        href="<?= e($item['url']) ?>"
                        <?= $currentPage === $key ? 'aria-current="page"' : '' ?>
                    >
                        <i class="bi <?= e($item['icon']) ?> me-2"></i>
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <div
            class="offcanvas offcanvas-start d-lg-none"
            tabindex="-1"
            id="mobileSidebar"
            aria-labelledby="mobileSidebarLabel"
        >
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h5" id="mobileSidebarLabel">
                    CareTrack Menu
                </h2>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="Close"
                ></button>
            </div>

            <div class="offcanvas-body">
                <nav class="nav flex-column gap-1">
                    <?php foreach ($navigation as $key => $item): ?>
                        <a
                            class="nav-link sidebar-link <?= $currentPage === $key ? 'active' : '' ?>"
                            href="<?= e($item['url']) ?>"
                            <?= $currentPage === $key ? 'aria-current="page"' : '' ?>
                        >
                            <i class="bi <?= e($item['icon']) ?> me-2"></i>
                            <?= e($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <main class="col-lg-10 app-main px-3 px-lg-4 py-4 py-xl-5">
