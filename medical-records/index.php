<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'clinician']);

$pageTitle = 'Medical Records';
$currentPage = 'medical-records';
$rawSearch = $_GET['search'] ?? '';
$search = is_string($rawSearch) ? trim($rawSearch) : '';
$where = [];
$params = [];

if (($_SESSION['role'] ?? '') === 'clinician') {
    $where[] = 'mr.clinician_id = ?';
    $params[] = current_user_id();
}

if ($search !== '') {
    $where[] = '(
        p.patient_code LIKE ?
        OR p.first_name LIKE ?
        OR p.last_name LIKE ?
    )';

    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}

$sql = 'SELECT
            mr.record_id,
            mr.visit_date,
            mr.chief_complaint,
            mr.created_at,
            p.patient_id,
            p.patient_code,
            p.first_name,
            p.last_name,
            u.full_name AS clinician_name
        FROM medical_records mr
        INNER JOIN patients p ON p.patient_id = mr.patient_id
        INNER JOIN users u ON u.user_id = mr.clinician_id';

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY mr.visit_date DESC, mr.record_id DESC LIMIT 200';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$records = $statement->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
        <p class="page-eyebrow">Clinical workspace</p>
        <h1 class="h3 mb-1">Medical Records</h1>
        <p class="text-secondary mb-0">
            View consultation records and clinical notes.
        </p>
    </div>

    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'clinician'], true)): ?>
        <a href="/caretrack/medical-records/create.php" class="btn btn-primary">
            <i class="bi bi-file-earmark-plus me-1"></i>Create Record
        </a>
    <?php endif; ?>
</div>

<section class="dashboard-section p-3 p-md-4">
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-7 col-lg-5">
            <label for="medical-record-search" class="visually-hidden">
                Search patient name or patient code
            </label>
            <input type="search" id="medical-record-search" name="search"
                   class="form-control"
                   placeholder="Search patient name or patient code"
                   value="<?= e($search) ?>">
        </div>

        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Search</button>
            <a href="/caretrack/medical-records/index.php"
               class="btn btn-outline-secondary">Clear</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th scope="col">Visit Date</th>
                    <th scope="col">Patient</th>
                    <th scope="col">Chief Complaint</th>
                    <th scope="col">Clinician</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($records === []): ?>
                <tr>
                    <td colspan="5" class="text-center text-secondary py-4">
                        No medical records found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= e($record['visit_date']) ?></td>
                        <td>
                            <?= e($record['first_name'] . ' ' . $record['last_name']) ?><br>
                            <small class="text-secondary">
                                <?= e($record['patient_code']) ?>
                            </small>
                        </td>
                        <td><?= e($record['chief_complaint']) ?></td>
                        <td><?= e($record['clinician_name']) ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary"
                               href="/caretrack/medical-records/view.php?id=<?= (int) $record['record_id'] ?>">
                                View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="text-secondary small mt-3 mb-0">
        Showing up to 200 records. Clinical notes are available only to authorized users.
    </p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
