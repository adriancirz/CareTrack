CREATE DATABASE IF NOT EXISTS caretrack_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE caretrack_db;

-- 1. User accounts and staff roles
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'receptionist', 'clinician')
        NOT NULL DEFAULT 'receptionist',
    status ENUM('active', 'inactive')
        NOT NULL DEFAULT 'active',
    profile_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Patient information
CREATE TABLE patients (
    patient_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_code VARCHAR(30) NOT NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    birth_date DATE DEFAULT NULL,
    sex ENUM('male', 'female', 'other', 'undisclosed')
        NOT NULL DEFAULT 'undisclosed',
    contact_number VARCHAR(30) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    INDEX idx_patients_name (last_name, first_name),
    INDEX idx_patients_deleted (deleted_at),

    CONSTRAINT fk_patients_creator
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. Appointment scheduling
CREATE TABLE appointments (
    appointment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    clinician_id INT UNSIGNED DEFAULT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM(
        'pending',
        'confirmed',
        'completed',
        'cancelled',
        'missed'
    ) NOT NULL DEFAULT 'pending',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_appointments_schedule
        (appointment_date, appointment_time),
    INDEX idx_appointments_patient (patient_id),
    INDEX idx_appointments_clinician (clinician_id),

    CONSTRAINT fk_appointments_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_appointments_clinician
        FOREIGN KEY (clinician_id) REFERENCES users(user_id)
        ON DELETE SET NULL,
    CONSTRAINT fk_appointments_creator
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Clinical consultation records
CREATE TABLE medical_records (
    record_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    clinician_id INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED DEFAULT NULL,
    visit_date DATE NOT NULL,
    chief_complaint TEXT NOT NULL,
    clinical_notes TEXT DEFAULT NULL,
    treatment_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_records_patient_date (patient_id, visit_date),

    CONSTRAINT fk_records_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_records_clinician
        FOREIGN KEY (clinician_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_records_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(appointment_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Basic activity auditing
CREATE TABLE activity_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_logs_user_date (user_id, created_at),

    CONSTRAINT fk_logs_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. Durable PHP sessions for serverless deployments
CREATE TABLE app_sessions (
    session_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    INDEX idx_sessions_activity (last_activity)
) ENGINE=InnoDB;
