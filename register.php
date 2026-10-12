<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/caretrack/dashboard/index.php');
}

$error = '';
$success = false;

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
            $error = 'Username must be 3–50 characters and use only letters, numbers, dots, underscores, or hyphens.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 12) {
            $error = 'Password must contain at least 12 characters.';
        } elseif ($password !== $confirm) {
            $error = 'The passwords do not match.';
        } else {
            try {
                $pdo->beginTransaction();
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
                    'receptionist',
                    'inactive',
                ]);

                $userId = (int) $pdo->lastInsertId();
                log_activity($pdo, 'user.registered', 'user', $userId);
                $pdo->commit();
                $success = true;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($exception->getCode() === '23000') {
                    $error = 'That username or email may already be registered.';
                } else {
                    error_log('CareTrack registration failed: ' . $exception->getMessage());
                    $error = 'Registration could not be completed. Please try again.';
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
    <title>Staff Registration | CareTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">
    <link href="/caretrack/assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
<main class="auth-layout">
    <aside class="auth-aside">
        <a class="auth-brand" href="/caretrack/index.php">
            <span class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
            <span>CareTrack <small>CLINIC MANAGEMENT</small></span>
        </a>
        <div class="auth-aside-copy">
            <span class="auth-kicker"><i class="bi bi-people me-2"></i>YOUR CARE TEAM</span>
            <h2>Good care starts with a connected team.</h2>
            <p>Create your staff profile to request access to the CareTrack clinic workspace.</p>
            <div class="auth-feature"><i class="bi bi-person-check"></i><span>One profile for your clinic work</span></div>
            <div class="auth-feature"><i class="bi bi-shield-check"></i><span>Account access reviewed by an administrator</span></div>
        </div>
        <div class="auth-aside-foot">A clearer view of better care.</div>
        <span class="auth-orbit auth-orbit-one" aria-hidden="true"></span>
        <span class="auth-orbit auth-orbit-two" aria-hidden="true"></span>
    </aside>

    <section class="auth-content auth-content-register">
        <div class="auth-content-inner">
            <a class="auth-brand auth-brand-mobile" href="/caretrack/index.php">
                <span class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
                <span>CareTrack <small>CLINIC MANAGEMENT</small></span>
            </a>
            <div class="auth-heading">
                <p class="page-eyebrow">Staff registration</p>
                <h1>Create your account</h1>
                <p>Fill in your details to request a CareTrack staff account.</p>
            </div>

            <?php if ($success): ?>
                <div class="auth-success-panel">
                    <span class="auth-success-icon"><i class="bi bi-check2-circle"></i></span>
                    <h2>Request received</h2>
                    <p>Registration received. An administrator must activate your account before you can log in.</p>
                    <a href="login.php" class="btn btn-primary auth-submit w-100">
                        Return to sign in <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                </div>
            <?php else: ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger auth-alert" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i><?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="auth-form auth-register-form">
                    <input type="hidden" name="csrf_token"
                           value="<?= e(csrf_token()) ?>">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="full_name">Full name</label>
                            <div class="auth-input-wrap">
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <input class="form-control" id="full_name"
                                       name="full_name" maxlength="120"
                                       placeholder="Your full name" autocomplete="name" required>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label" for="username">Username</label>
                            <div class="auth-input-wrap">
                                <i class="bi bi-at" aria-hidden="true"></i>
                                <input class="form-control" id="username"
                                       name="username" minlength="3" maxlength="50"
                                       pattern="[a-zA-Z0-9_.-]+" placeholder="Choose a username"
                                       autocomplete="username" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="email">Work email</label>
                            <div class="auth-input-wrap">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <input class="form-control" type="email" id="email"
                                       name="email" maxlength="190"
                                       placeholder="name@clinic.com" autocomplete="email" required>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label" for="password">Password</label>
                            <div class="auth-input-wrap">
                                <i class="bi bi-lock" aria-hidden="true"></i>
                                <input class="form-control" type="password"
                                       id="password" name="password" minlength="12"
                                       autocomplete="new-password" placeholder="At least 12 characters" required>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="form-label" for="confirm_password">Confirm password</label>
                            <div class="auth-input-wrap">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                <input class="form-control" type="password"
                                       id="confirm_password" name="confirm_password"
                                       minlength="12" autocomplete="new-password"
                                       placeholder="Enter it again" required>
                            </div>
                        </div>
                    </div>

                    <p class="auth-form-note"><i class="bi bi-info-circle me-1"></i>New accounts need administrator approval before sign-in.</p>
                    <button class="btn btn-primary auth-submit w-100" type="submit">
                        Create staff account <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>

                <p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
            <?php endif; ?>
            <p class="auth-security"><i class="bi bi-shield-lock me-1"></i>Your information is used to set up your staff profile.</p>
        </div>
    </section>
</main>
</body>
</html>
