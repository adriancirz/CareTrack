<?php

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $submittedToken): bool
{
    $storedToken = $_SESSION['csrf_token'] ?? null;

    return is_string($storedToken)
        && is_string($submittedToken)
        && hash_equals($storedToken, $submittedToken);
}
