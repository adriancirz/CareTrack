<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role(['admin', 'receptionist', 'clinician']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$submittedToken = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token(is_string($submittedToken) ? $submittedToken : null)) {
    http_response_code(403);
    exit('Invalid request token. Refresh the page and try again.');
}

$rawAppointmentId = $_POST['appointment_id'] ?? null;
$appointmentId = filter_var($rawAppointmentId, FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? null;
$allowedStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'missed'];

if ($appointmentId === false || $appointmentId === null || $appointmentId < 1) {
    http_response_code(400);
    exit('Invalid appointment ID.');
}
if (!is_string($status) || !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    exit('Invalid appointment status.');
}

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'SELECT clinician_id, status
         FROM appointments
         WHERE appointment_id = ?
         FOR UPDATE'
    );
    $statement->execute([$appointmentId]);
    $appointment = $statement->fetch();

    if (!$appointment) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Appointment not found.');
    }

    if (!in_array($appointment['status'], ['pending', 'confirmed'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        exit('This appointment is closed and its status cannot be changed.');
    }

    $role = $_SESSION['role'] ?? '';
    if (
        $role === 'clinician'
        && (int) $appointment['clinician_id'] !== current_user_id()
    ) {
        $pdo->rollBack();
        http_response_code(403);
        exit('You can only update the status of your assigned appointments.');
    }

    $update = $pdo->prepare(
        "UPDATE appointments
         SET status = ?
         WHERE appointment_id = ?
           AND status IN ('pending', 'confirmed')"
    );
    $update->execute([$status, $appointmentId]);
    if ($appointment['status'] !== $status) {
        log_activity(
            $pdo,
            'appointment.status_changed_to_' . $status,
            'appointment',
            $appointmentId
        );
    }
    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('CareTrack appointment status update failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Appointment status could not be updated. Please try again.');
}

redirect('/caretrack/appointments/view.php?id=' . $appointmentId);
