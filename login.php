<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/caretrack/dashboard/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
        $error = 'Invalid request. Refresh the page and try again.';
    } else {
        $identifier = $_POST['identifier'] ?? '';
        $password = $_POST['password'] ?? '';

        $identifier = is_string($identifier) ? trim($identifier) : '';
        $password = is_string($password) ? $password : '';

        if ($identifier === '' || $password === '') {
            $error = 'Enter your username or email and password.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'SELECT user_id, full_name, username, email,
                            password_hash, role, status
                     FROM users
                     WHERE username = ? OR email = ?
                     LIMIT 1'
                );

                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch();

                if (
                    $user
                    && password_verify($password, $user['password_hash'])
                    && $user['status'] === 'active'
                ) {
                    if (password_needs_rehash(
                        $user['password_hash'],
                        PASSWORD_DEFAULT
                    )) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $update = $pdo->prepare(
                            'UPDATE users SET password_hash = ? WHERE user_id = ?'
                        );
                        $update->execute([$newHash, $user['user_id']]);
                    }

                    log_activity(
                        $pdo,
                        'user.logged_in',
                        'user',
                        (int) $user['user_id'],
                        (int) $user['user_id']
                    );
                    establish_user_session($user);
                    redirect('/caretrack/dashboard/index.php');
                }

                $error = 'Invalid login details or account not yet activated.';
            } catch (PDOException $exception) {
                error_log('CareTrack login query failed: ' . $exception->getMessage());
                http_response_code(503);
                $error = 'Sign-in is temporarily unavailable. Please try again.';
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
    <title>Login | CareTrack</title>
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
            <span class="auth-kicker"><i class="bi bi-shield-check me-2"></i>STAFF PORTAL</span>
            <h2>Care that stays connected.</h2>
            <p>Appointments, patient details, and clinical records in one thoughtful workspace.</p>
            <div class="auth-feature"><i class="bi bi-calendar2-check"></i><span>Keep the day running smoothly</span></div>
            <div class="auth-feature"><i class="bi bi-lock"></i><span>Secure access for your care team</span></div>
        </div>
        <div class="auth-aside-foot">A clearer view of better care.</div>
        <span class="auth-orbit auth-orbit-one" aria-hidden="true"></span>
        <span class="auth-orbit auth-orbit-two" aria-hidden="true"></span>
    </aside>

    <section class="auth-content">
        <div class="auth-content-inner">
            <a class="auth-brand auth-brand-mobile" href="/caretrack/index.php">
                <span class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
                <span>CareTrack <small>CLINIC MANAGEMENT</small></span>
            </a>
            <div class="auth-heading">
                <p class="page-eyebrow">Welcome back</p>
                <h1>Sign in to your account</h1>
                <p>Enter your staff credentials to continue to your workspace.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger auth-alert" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i><?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token"
                       value="<?= e(csrf_token()) ?>">

                <div class="mb-4">
                    <label class="form-label" for="identifier">Username or email</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-person" aria-hidden="true"></i>
                        <input class="form-control" id="identifier"
                               name="identifier" autocomplete="username"
                               maxlength="190" placeholder="Enter your username or email" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input class="form-control" type="password"
                               id="password" name="password"
                               autocomplete="current-password" placeholder="Enter your password" required>
                    </div>
                </div>

                <button class="btn btn-primary auth-submit w-100" type="submit">
                    Sign in <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </form>

            <p class="auth-switch">Need a staff account? <a href="register.php">Create an account</a></p>
            <p class="auth-security"><i class="bi bi-shield-lock me-1"></i>Your account is protected by secure sign-in.</p>
        </div>
    </section>
</main>
</body>
</html>
