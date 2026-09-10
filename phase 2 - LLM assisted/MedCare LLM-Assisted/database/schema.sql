-- =====================================================================
-- MedCare Portal — Database Schema (Phase 1)
-- Engine: MySQL (InnoDB, utf8mb4)
-- Core: users -> users_has_groups -> groups -> services_has_groups -> services
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS medcare_portal_llm
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medcare_portal_llm;

CREATE USER IF NOT EXISTS 'medcare_user'@'localhost' IDENTIFIED BY 'ChangeMeStrong123!';
GRANT ALL PRIVILEGES ON `medcare_portal`.* TO 'medcare_user'@'localhost';
FLUSH PRIVILEGES;

-- ---------------------------------------------------------------------
-- 1. users
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(120) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    first_name      VARCHAR(80)  NOT NULL,
    last_name       VARCHAR(80)  NOT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. groups  (security tiers)
-- ---------------------------------------------------------------------
CREATE TABLE groups (
    group_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_name      VARCHAR(50)  NOT NULL UNIQUE,
    description     VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. services  (registered endpoints / capabilities)
-- ---------------------------------------------------------------------
CREATE TABLE services (
    service_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_name    VARCHAR(100) NOT NULL UNIQUE,
    execution_path  VARCHAR(255) NOT NULL UNIQUE,   -- e.g. services/doctor/update_medical_log.php
    description     VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. users_has_groups  (junction: RBAC core)
-- ---------------------------------------------------------------------
CREATE TABLE users_has_groups (
    user_id         INT UNSIGNED NOT NULL,
    group_id        INT UNSIGNED NOT NULL,
    assigned_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, group_id),
    CONSTRAINT fk_uhg_user  FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
    CONSTRAINT fk_uhg_group FOREIGN KEY (group_id) REFERENCES groups(group_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. services_has_groups  (junction: RBAC core)
-- ---------------------------------------------------------------------
CREATE TABLE services_has_groups (
    service_id      INT UNSIGNED NOT NULL,
    group_id        INT UNSIGNED NOT NULL,
    PRIMARY KEY (service_id, group_id),
    CONSTRAINT fk_shg_service FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE CASCADE,
    CONSTRAINT fk_shg_group   FOREIGN KEY (group_id)   REFERENCES groups(group_id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. patients  (profile extension for Patient_Tier users)
-- ---------------------------------------------------------------------
CREATE TABLE patients (
    patient_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    date_of_birth   DATE NULL,
    phone           VARCHAR(30)  NULL,
    address         VARCHAR(255) NULL,
    insurance_provider VARCHAR(120) NULL,
    insurance_number   VARCHAR(60)  NULL,
    CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. departments  (clinical departments)
-- ---------------------------------------------------------------------
CREATE TABLE departments (
    department_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    description     VARCHAR(255) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. specializations  (medical specialties)
-- ---------------------------------------------------------------------
CREATE TABLE specializations (
    specialization_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    specialization_name VARCHAR(100) NOT NULL UNIQUE,
    department_id        INT UNSIGNED NOT NULL,
    CONSTRAINT fk_spec_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. doctors  (profile extension for Medical_Staff users)
-- ---------------------------------------------------------------------
CREATE TABLE doctors (
    doctor_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL UNIQUE,
    specialization_id   INT UNSIGNED NOT NULL,
    department_id       INT UNSIGNED NOT NULL,
    license_number      VARCHAR(60) NOT NULL UNIQUE,
    CONSTRAINT fk_doctors_user           FOREIGN KEY (user_id)           REFERENCES users(user_id)                     ON DELETE CASCADE,
    CONSTRAINT fk_doctors_specialization FOREIGN KEY (specialization_id) REFERENCES specializations(specialization_id) ON DELETE RESTRICT,
    CONSTRAINT fk_doctors_department     FOREIGN KEY (department_id)     REFERENCES departments(department_id)         ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. doctor_shifts  (work shifts, updatable by doctors)
-- ---------------------------------------------------------------------
CREATE TABLE doctor_shifts (
    shift_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id       INT UNSIGNED NOT NULL,
    shift_date      DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    CONSTRAINT fk_shifts_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. appointment_slots  (openings registered/booked by patients)
-- ---------------------------------------------------------------------
CREATE TABLE appointment_slots (
    slot_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id       INT UNSIGNED NOT NULL,
    slot_date       DATE NOT NULL,
    slot_time       TIME NOT NULL,
    status          ENUM('open','booked','cancelled') NOT NULL DEFAULT 'open',
    patient_id      INT UNSIGNED NULL,
    CONSTRAINT fk_slots_doctor  FOREIGN KEY (doctor_id)  REFERENCES doctors(doctor_id)   ON DELETE CASCADE,
    CONSTRAINT fk_slots_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. medical_logs  (clinical data records logged by doctors)
-- ---------------------------------------------------------------------
CREATE TABLE medical_logs (
    log_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT UNSIGNED NOT NULL,
    doctor_id       INT UNSIGNED NOT NULL,
    slot_id         INT UNSIGNED NULL,
    log_date        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes           TEXT NOT NULL,
    CONSTRAINT fk_logs_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    CONSTRAINT fk_logs_doctor  FOREIGN KEY (doctor_id)  REFERENCES doctors(doctor_id)   ON DELETE CASCADE,
    CONSTRAINT fk_logs_slot    FOREIGN KEY (slot_id)    REFERENCES appointment_slots(slot_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 13. prescriptions  (issued by doctors, viewed by patients)
-- ---------------------------------------------------------------------
CREATE TABLE prescriptions (
    prescription_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT UNSIGNED NOT NULL,
    doctor_id       INT UNSIGNED NOT NULL,
    log_id          INT UNSIGNED NULL,
    issued_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    medication      VARCHAR(150) NOT NULL,
    dosage          VARCHAR(100) NOT NULL,
    instructions    VARCHAR(255) NULL,
    CONSTRAINT fk_presc_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    CONSTRAINT fk_presc_doctor  FOREIGN KEY (doctor_id)  REFERENCES doctors(doctor_id)   ON DELETE CASCADE,
    CONSTRAINT fk_presc_log     FOREIGN KEY (log_id)     REFERENCES medical_logs(log_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 14. access_audit_log  (admin audit trail of access/security events)
-- ---------------------------------------------------------------------
CREATE TABLE access_audit_log (
    audit_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,
    service_id      INT UNSIGNED NULL,
    action          VARCHAR(50) NOT NULL,      -- e.g. 'LOGIN_OK', 'LOGIN_FAIL', 'ACCESS_DENIED'
    ip_address      VARCHAR(45) NULL,
    occurred_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user    FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE SET NULL,
    CONSTRAINT fk_audit_service FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- ---------------------------------------------------------------------
-- Groups (security tiers) — fixed as per project requirements
-- ---------------------------------------------------------------------
INSERT INTO groups (group_name, description) VALUES
    ('Patient_Tier',   'Standard patient accounts: booking, insurance profile, prescriptions view'),
    ('Medical_Staff',  'Doctors: clinical logs, prescriptions, shifts'),
    ('System_Admin',   'Administrators: user/group management, department/specialization management, audit');

-- ---------------------------------------------------------------------
-- Services — every future endpoint must be registered here so the
-- gatekeeper (include/auth.php) has a row to check against.
-- ---------------------------------------------------------------------
INSERT INTO services (service_name, execution_path, description) VALUES
    -- Patient services
    ('patient_view_specialties',       'services/patient/view_specialties.php',       'Browse clinic specialties'),
    ('patient_book_appointment',       'services/patient/book_appointment.php',       'Register an available appointment slot'),
    ('patient_view_prescriptions',     'services/patient/view_prescriptions.php',     'View personal prescription history'),
    ('patient_update_insurance',       'services/patient/update_insurance.php',       'Update insurance profile details'),
    ('patient_view_profile',           'services/patient/view_profile.php',           'View own profile data'),

    -- Doctor services
    ('doctor_view_appointments',       'services/doctor/view_appointments.php',       'Review assigned appointment history'),
    ('doctor_view_patient_history',    'services/doctor/view_patient_history.php',    'Review a specific patient clinical history (logs + prescriptions)'),
    ('doctor_log_medical_record',      'services/doctor/update_medical_log.php',      'Log clinical data for a patient'),
    ('doctor_issue_prescription',      'services/doctor/issue_prescription.php',      'Issue a prescription to a patient'),
    ('doctor_update_shift',            'services/doctor/update_shift.php',            'Update own work shift'),
    ('doctor_manage_slots',            'services/doctor/manage_slots.php',            'Open/cancel appointment slots'),

    -- Admin services
    ('admin_manage_users',             'services/admin/manage_users.php',             'Create/edit/deactivate user accounts'),
    ('admin_assign_groups',            'services/admin/assign_groups.php',            'Assign users to permission groups'),
    ('admin_crud_departments',         'services/admin/crud_departments.php',         'Create/edit/delete clinic departments'),
    ('admin_manage_specializations',   'services/admin/manage_specializations.php',   'Manage doctor specializations'),
    ('admin_manage_schedules',         'services/admin/manage_schedules.php',         'Oversee/adjust doctor shifts across the clinic'),
    ('admin_audit_access',             'services/admin/audit_access.php',             'Review access audit log');

-- ---------------------------------------------------------------------
-- services_has_groups — default authorization mapping
-- ---------------------------------------------------------------------
INSERT INTO services_has_groups (service_id, group_id)
SELECT s.service_id, g.group_id
FROM services s JOIN groups g ON g.group_name = 'Patient_Tier'
WHERE s.service_name IN (
    'patient_view_specialties', 'patient_book_appointment',
    'patient_view_prescriptions', 'patient_update_insurance', 'patient_view_profile'
);

INSERT INTO services_has_groups (service_id, group_id)
SELECT s.service_id, g.group_id
FROM services s JOIN groups g ON g.group_name = 'Medical_Staff'
WHERE s.service_name IN (
    'doctor_view_appointments', 'doctor_view_patient_history', 'doctor_log_medical_record',
    'doctor_issue_prescription', 'doctor_update_shift', 'doctor_manage_slots'
);

INSERT INTO services_has_groups (service_id, group_id)
SELECT s.service_id, g.group_id
FROM services s JOIN groups g ON g.group_name = 'System_Admin'
WHERE s.service_name IN (
    'admin_manage_users', 'admin_assign_groups', 'admin_crud_departments',
    'admin_manage_specializations', 'admin_manage_schedules', 'admin_audit_access'
);

SET FOREIGN_KEY_CHECKS = 1;
