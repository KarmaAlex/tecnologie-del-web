-- Application DB user for MedCare Portal.
-- Update the password before using in production environments.
CREATE USER IF NOT EXISTS 'medcare_user'@'localhost' IDENTIFIED BY 'ChangeMeStrong123!';
CREATE USER IF NOT EXISTS 'medcare_user'@'127.0.0.1' IDENTIFIED BY 'ChangeMeStrong123!';

CREATE DATABASE IF NOT EXISTS `medcare_portal`;
GRANT ALL PRIVILEGES ON `medcare_portal`.* TO 'medcare_user'@'localhost';
GRANT ALL PRIVILEGES ON `medcare_portal`.* TO 'medcare_user'@'127.0.0.1';
FLUSH PRIVILEGES;

USE `medcare_portal`;

CREATE TABLE `users` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`username` VARCHAR(100) NOT NULL UNIQUE,
	`password_hash` CHAR(60) NOT NULL,
	`email` VARCHAR(255) NOT NULL UNIQUE,
	`full_name` VARCHAR(255) DEFAULT NULL,
    `phone_number` VARCHAR(13) NOT NULL,
	`role` ENUM('patient','doctor','admin','staff') NOT NULL DEFAULT 'patient',
	`active` TINYINT(1) NOT NULL DEFAULT 1,
	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
);

CREATE TABLE `groups` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(100) NOT NULL UNIQUE,
	`description` TEXT DEFAULT NULL,
	PRIMARY KEY (`id`)
);

CREATE TABLE `services` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`path` VARCHAR(255) NOT NULL UNIQUE,
	`name` VARCHAR(150) NOT NULL,
	`description` TEXT DEFAULT NULL,
	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
);

CREATE TABLE `users_has_groups` (
	`user_id` INT UNSIGNED NOT NULL,
	`group_id` INT UNSIGNED NOT NULL,
	`assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`user_id`,`group_id`),
	CONSTRAINT `uhg_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `uhg_group_fk` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `services_has_groups` (
	`service_id` INT UNSIGNED NOT NULL,
	`group_id` INT UNSIGNED NOT NULL,
	PRIMARY KEY (`service_id`,`group_id`),
	CONSTRAINT `shg_service_fk` FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `shg_group_fk` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `departments` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(150) NOT NULL UNIQUE,
	`description` TEXT DEFAULT NULL,
	PRIMARY KEY (`id`)
);

CREATE TABLE `specializations` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(150) NOT NULL UNIQUE,
	`description` TEXT DEFAULT NULL,
	PRIMARY KEY (`id`)
);

CREATE TABLE `doctor_profiles` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` INT UNSIGNED NOT NULL UNIQUE,
	`department_id` INT UNSIGNED DEFAULT NULL,
	`specialization_id` INT UNSIGNED DEFAULT NULL,
	`license_number` VARCHAR(100) DEFAULT NULL,
	`bio` TEXT DEFAULT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT `doc_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `doc_dept_fk` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT `doc_spec_fk` FOREIGN KEY (`specialization_id`) REFERENCES `specializations`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE `patient_profiles` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id` INT UNSIGNED NOT NULL UNIQUE,
	`dob` DATE DEFAULT NULL,
	`fiscal_code` VARCHAR(16) NOT NULL UNIQUE,
	PRIMARY KEY (`id`),
	CONSTRAINT `pat_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE `schedules` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`doctor_id` INT UNSIGNED DEFAULT NULL,
	`start_at` DATETIME NOT NULL,
	`end_at` DATETIME NOT NULL,
	`location` VARCHAR(255) DEFAULT NULL,
	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	CONSTRAINT `sched_doc_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
	INDEX (`doctor_id`,`start_at`)
);

CREATE TABLE `appointments` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`patient_id` INT UNSIGNED DEFAULT NULL,
	`doctor_id` INT UNSIGNED DEFAULT NULL,
	`schedule_id` INT UNSIGNED DEFAULT NULL,
	`appointment_at` DATETIME NOT NULL,
	`status` ENUM('booked','confirmed','cancelled','completed') NOT NULL DEFAULT 'booked',
	`reason` TEXT DEFAULT NULL,
	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	CONSTRAINT `appt_patient_fk` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT `appt_doctor_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT `appt_sched_fk` FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
	INDEX (`patient_id`,`doctor_id`,`appointment_at`)
);

CREATE TABLE `medical_logs` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`doctor_id` INT UNSIGNED DEFAULT NULL,
	`patient_id` INT UNSIGNED NOT NULL,
	`appointment_id` INT UNSIGNED DEFAULT NULL,
	`note` TEXT NOT NULL,
	`attachments` TEXT DEFAULT NULL,
	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	CONSTRAINT `ml_doc_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
	CONSTRAINT `ml_pat_fk` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `ml_appt_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
	INDEX (`patient_id`,`doctor_id`)
);

CREATE TABLE `prescriptions` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`patient_id` INT UNSIGNED NOT NULL,
	`doctor_id` INT UNSIGNED DEFAULT NULL,
	`issued_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`notes` TEXT DEFAULT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT `pres_pat_fk` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `pres_doc_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE `prescription_items` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`prescription_id` INT UNSIGNED NOT NULL,
	`medication` VARCHAR(255) NOT NULL,
	`dosage` VARCHAR(255) DEFAULT NULL,
	`instructions` TEXT DEFAULT NULL,
	`quantity` INT DEFAULT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT `pi_pres_fk` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Optional, maybe add later

--CREATE TABLE `audit_logs` (
--	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
--	`user_id` INT UNSIGNED DEFAULT NULL,
--	`action` VARCHAR(150) NOT NULL,
--	`target` VARCHAR(255) DEFAULT NULL,
--	`ip_address` VARCHAR(45) DEFAULT NULL,
--	`metadata` JSON DEFAULT NULL,
--	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
--	PRIMARY KEY (`id`),
--	CONSTRAINT `audit_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
--);

-- Optional session store for extended session management
--CREATE TABLE `user_sessions` (
--	`id` CHAR(128) NOT NULL,
--	`user_id` INT UNSIGNED NOT NULL,
--	`data` TEXT DEFAULT NULL,
--	`last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--	PRIMARY KEY (`id`),
--	CONSTRAINT `us_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
--);
