<?php

declare(strict_types=1);

/**
 * Check whether a clinician has an active appointment at the requested slot.
 */
function appointment_slot_available(
    PDO $pdo,
    string $date,
    string $time,
    int $clinicianId,
    ?int $excludeAppointmentId = null
): bool {
    $sql = "SELECT COUNT(*)
            FROM appointments
            WHERE clinician_id = ?
              AND appointment_date = ?
              AND appointment_time = ?
              AND status IN ('pending', 'confirmed')";

    $params = [$clinicianId, $date, $time];

    if ($excludeAppointmentId !== null) {
        $sql .= ' AND appointment_id <> ?';
        $params[] = $excludeAppointmentId;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() === 0;
}

function valid_appointment_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date
        && ($errors === false
            || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

function valid_appointment_time(string $time): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!H:i', $time);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsed !== false
        && $parsed->format('H:i') === $time
        && ($errors === false
            || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

/**
 * Lock an active clinician row to serialize appointment-slot checks.
 */
function lock_active_clinician(PDO $pdo, int $clinicianId): bool
{
    $statement = $pdo->prepare(
        "SELECT user_id FROM users
         WHERE user_id = ? AND role = 'clinician' AND status = 'active'
         FOR UPDATE"
    );
    $statement->execute([$clinicianId]);

    return $statement->fetchColumn() !== false;
}

function active_patient_exists(PDO $pdo, int $patientId): bool
{
    $statement = $pdo->prepare(
        'SELECT patient_id FROM patients
         WHERE patient_id = ? AND deleted_at IS NULL'
    );
    $statement->execute([$patientId]);

    return $statement->fetchColumn() !== false;
}

/**
 * @return list<array{user_id: int, full_name: string}>
 */
function active_clinicians(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT user_id, full_name FROM users
         WHERE role = 'clinician' AND status = 'active'
         ORDER BY full_name"
    );

    return $statement->fetchAll();
}
