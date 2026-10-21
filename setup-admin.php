<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$error = '';
$success = false;

$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

if ($count > 0) {
    http_response_code(403);
    exit('Setup is disabled because a user account already exists.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
        $error = 'Invalid request token. Refresh the page and try again.';
    } else {
        $name = $_POST['full_name'] ?? '';
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $name = is_string($name) ? trim($name) : '';
        $username = is_string($username) ? trim($username) : '';
        $email = is_string($email) ? trim($email) : '';
        $password = is_string($password) ? $password : '';
        $confirm = is_string($confirm) ? $confirm : '';

        if ($name === '' || mb_strlen($name) > 120) {
            $error = 'Enter a valid full name.';
        } elseif (!preg_match('/\A[a-zA-Z0-9_.-]{3,50}\z/', $username)) {
            $error = 'Username must be 3–50 characters and contain only letters, numbers, dots, underscores, or hyphens.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 12) {
            $error = 'Use a password with at least 12 characters.';
        } elseif ($password !== $confirm) {
            $error = 'The passwords do not match.';
        } else {
            $lockAcquired = false;

            try {
                $lock = $pdo->query(
                    "SELECT GET_LOCK('caretrack_initial_admin_setup', 10)"
                )->fetchColumn();

                if ((int) $lock !== 1) {
                    $error = 'Administrator setup is busy. Please try again.';
                } else {
                    $lockAcquired = true;
                    $pdo->beginTransaction();

                    $check = $pdo->query('SELECT COUNT(*) FROM users');

                    if ((int) $check->fetchColumn() !== 0) {
                        $pdo->rollBack();
                        $error = 'An account already exists. Setup is disabled.';
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO users
                             (full_name, username, email, password_hash, role, status)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );

                        $stmt->execute([
                            $name,
                            $username,
                            $email,
                            password_hash($password, PASSWORD_DEFAULT),
                            'admin',
                            'active',
                        ]);

                        $userId = (int) $pdo->lastInsertId();
                        log_activity($pdo, 'user.initial_admin_created', 'user', $userId);
                        $pdo->commit();
                        $success = true;
                    }
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('CareTrack initial administrator setup failed: ' . $exception->getMessage());

                if ($exception->getCode() === '23000') {
                    $error = 'That username or email is already in use.';
                } else {
                    $error = 'The administrator account could not be created.';
                }
            } finally {
                if ($lockAcquired) {
                    $pdo->query(
                        "SELECT RELEASE_LOCK('caretrack_initial_admin_setup')"
                    );
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CareTrack</title>
    <link rel="icon" type="image/svg+xml" href="/caretrack/assets/caretrack-mark.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 560px;">
        <div class="card-body p-4">
            <h1 class="h3">CareTrack</h1>
            <p class="text-secondary">Initial administrator setup</p>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    Administrator created. Delete or disable setup-admin.php
                    before using the application further.
                </div>
                <a class="btn btn-primary" href="login.php">Go to login</a>
            <?php else: ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token"
                           value="<?= e(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="full_name">Full name</label>
                        <input class="form-control" id="full_name"
                               name="full_name" maxlength="120" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username"
                               name="username" minlength="3" maxlength="50"
                               pattern="[a-zA-Z0-9_.-]+" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email"
                               name="email" maxlength="190" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" type="password"
                               id="password" name="password"
                               minlength="12" autocomplete="new-password"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">
                            Confirm password
                        </label>
                        <input class="form-control" type="password"
                               id="confirm_password" name="confirm_password"
                               minlength="12" autocomplete="new-password"
                               required>
                    </div>

                    <button class="btn btn-primary w-100" type="submit">
                        Create administrator
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
