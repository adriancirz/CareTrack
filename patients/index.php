<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();

$pageTitle = 'Patients';
$currentPage = 'patients';
$rawSearch = $_GET['search'] ?? '';
$search = is_string($rawSearch) ? trim($rawSearch) : '';
$rawPage = $_GET['page'] ?? '1';
$page = is_scalar($rawPage) ? max(1, (int) $rawPage) : 1;
$perPage = 10;

$where = 'deleted_at IS NULL';
$params = [];

if ($search !== '') {
    $where .= ' AND (
        patient_code LIKE ?
        OR first_name LIKE ?
        OR last_name LIKE ?
        OR contact_number LIKE ?
    )';

    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
}

$countStatement = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE {$where}");
$countStatement->execute($params);
$totalPatients = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalPatients / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$statement = $pdo->prepare(
    "SELECT patient_id, patient_code, first_name, last_name,
            birth_date, sex, contact_number
     FROM patients
     WHERE {$where}
     ORDER BY created_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$statement->execute($params);
$patients = $statement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <p class="page-eyebrow">Care team workspace</p>
        <h1 class="h3 mb-1">Patients</h1>
        <p class="text-secondary mb-0">
            Manage patient information and records.
        </p>
    </div>

    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'receptionist'], true)): ?>
        <a href="/caretrack/patients/create.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>Add Patient
        </a>
    <?php endif; ?>
</div>

<section class="dashboard-section p-3 p-md-4">
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-8 col-lg-6">
            <label class="visually-hidden" for="patient-search">Search patients</label>
            <input
                type="search"
                id="patient-search"
                name="search"
                class="form-control"
                placeholder="Search name, patient code, or contact number"
                value="<?= e($search) ?>"
            >
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Search</button>
            <a href="/caretrack/patients/index.php"
               class="btn btn-outline-secondary">Clear</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th scope="col">Patient Code</th>
                    <th scope="col">Patient Name</th>
                    <th scope="col">Birth Date</th>
                    <th scope="col">Sex</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($patients === []): ?>
                <tr>
                    <td colspan="6" class="text-center py-4 text-secondary">
                        No patient records found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($patients as $patient): ?>
                    <tr>
                        <td><?= e($patient['patient_code']) ?></td>
                        <td><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></td>
                        <td>
                            <?= e($patient['birth_date'] ?? '—') ?>
                        </td>
                        <td><?= e(ucfirst($patient['sex'])) ?></td>
                        <td><?= e($patient['contact_number'] ?: '—') ?></td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="/caretrack/patients/view.php?id=<?= (int) $patient['patient_id'] ?>">
                                    View
                                </a>

                                <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'receptionist'], true)): ?>
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="/caretrack/patients/edit.php?id=<?= (int) $patient['patient_id'] ?>">
                                        Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <small class="text-secondary">
            <?= number_format($totalPatients) ?> patient(s) found
        </small>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Patient pages">
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                            <a class="page-link"
                               href="?search=<?= urlencode($search) ?>&amp;page=<?= $pageNumber ?>"
                               <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>>
                                <?= $pageNumber ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
