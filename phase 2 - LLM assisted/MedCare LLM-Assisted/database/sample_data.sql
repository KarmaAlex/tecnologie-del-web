-- =====================================================================
-- MedCare Portal — Sample Data (Fase 5)
-- Da eseguire DOPO database/schema.sql (che crea già groups, services e
-- services_has_groups). Questo file aggiunge SOLO utenti applicativi e
-- dati di dominio, più le righe in users_has_groups — che sono la parte
-- che il gatekeeper (include/auth.php::requireService) legge per ogni
-- richiesta: senza queste, ogni login riesce ma OGNI service risponde
-- 403, anche con dati di dominio perfetti altrove.
--
-- Password di TUTTI gli utenti di test: Password123!
-- (hash bcrypt pre-calcolato, compatibile con password_verify() di PHP;
-- password_hash() PHP userebbe prefisso $2y$, qui $2b$: password_verify()
-- accetta entrambi, sono lo stesso algoritmo bcrypt).
-- =====================================================================

USE medcare_portal_llm;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- users — un utente per ruolo, più profili specifici sotto
-- ---------------------------------------------------------------------
INSERT INTO users (user_id, username, email, password_hash, first_name, last_name, is_active) VALUES
    (1, 'admin.rossi',    'admin.rossi@medcare.test',    '$2b$10$Awf4siaZ8lVivtKaJ9ddlOgSbONHO14FAVJcGmr0P8LKoLclqedbO', 'Giulia',    'Rossi',    1),
    (2, 'dr.bianchi',     'dr.bianchi@medcare.test',     '$2b$10$Awf4siaZ8lVivtKaJ9ddlOgSbONHO14FAVJcGmr0P8LKoLclqedbO', 'Marco',     'Bianchi',  1),
    (3, 'dr.verdi',       'dr.verdi@medcare.test',       '$2b$10$Awf4siaZ8lVivtKaJ9ddlOgSbONHO14FAVJcGmr0P8LKoLclqedbO', 'Elena',     'Verdi',    1),
    (4, 'paz.colombo',    'paz.colombo@medcare.test',    '$2b$10$Awf4siaZ8lVivtKaJ9ddlOgSbONHO14FAVJcGmr0P8LKoLclqedbO', 'Luca',      'Colombo',  1),
    (5, 'paz.ferrari',    'paz.ferrari@medcare.test',    '$2b$10$Awf4siaZ8lVivtKaJ9ddlOgSbONHO14FAVJcGmr0P8LKoLclqedbO', 'Sara',      'Ferrari',  1),
    (6, 'paz.romano',     'paz.romano@medcare.test',     '$2b$10$Awf4siaZ8lVivtKaJ9ddlOgSbONHO14FAVJcGmr0P8LKoLclqedbO', 'Andrea',    'Romano',   1),
    (7, 'paz.inattivo',   'paz.inattivo@medcare.test',   '$2b$10$Awf4siaZ8lVivtKaJ9ddlOgSbONHO14FAVJcGmr0P8LKoLclqedbO', 'Paolo',     'Esposito', 0);
    -- user 7: account disattivato (is_active=0), per testare che
    -- login.php rifiuti correttamente anche con password corretta.

-- ---------------------------------------------------------------------
-- users_has_groups — SENZA queste righe il gatekeeper blocca tutto
-- ---------------------------------------------------------------------
INSERT INTO users_has_groups (user_id, group_id)
SELECT 1, group_id FROM groups WHERE group_name = 'System_Admin';

INSERT INTO users_has_groups (user_id, group_id)
SELECT u.user_id, g.group_id
FROM (SELECT 2 AS user_id UNION SELECT 3) u
JOIN groups g ON g.group_name = 'Medical_Staff';

INSERT INTO users_has_groups (user_id, group_id)
SELECT u.user_id, g.group_id
FROM (SELECT 4 AS user_id UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) u
JOIN groups g ON g.group_name = 'Patient_Tier';

-- ---------------------------------------------------------------------
-- departments
-- ---------------------------------------------------------------------
INSERT INTO departments (department_id, department_name, description) VALUES
    (1, 'Cardiologia', 'Diagnosi e cura delle patologie cardiovascolari'),
    (2, 'Dermatologia', 'Diagnosi e cura delle patologie della pelle'),
    (3, 'Pediatria', 'Assistenza sanitaria per pazienti in età pediatrica');

-- ---------------------------------------------------------------------
-- specializations
-- ---------------------------------------------------------------------
INSERT INTO specializations (specialization_id, specialization_name, department_id) VALUES
    (1, 'Cardiologia generale', 1),
    (2, 'Aritmologia', 1),
    (3, 'Dermatologia clinica', 2);

-- ---------------------------------------------------------------------
-- patients — profilo esteso per gli user_id 4, 5, 6, 7
-- ---------------------------------------------------------------------
INSERT INTO patients (patient_id, user_id, date_of_birth, phone, address, insurance_provider, insurance_number) VALUES
    (1, 4, '1985-03-14', '+39 333 1112222', 'Via Roma 12, Milano',    'ASL Milano',  'AS-MI-00184'),
    (2, 5, '1992-07-22', '+39 333 3334444', 'Via Torino 5, Torino',   'ASL Torino',  'AS-TO-00299'),
    (3, 6, '1978-11-02', '+39 333 5556666', 'Via Napoli 8, Napoli',   'ASL Napoli',  'AS-NA-00457'),
    (4, 7, '1990-01-01', '+39 333 7778888', 'Via Bari 3, Bari',       'ASL Bari',    'AS-BA-00512');
    -- patient_id 4 appartiene all'utente disattivato (user 7): serve per
    -- verificare che nessun dato clinico "trapeli" quando l'account non
    -- può nemmeno autenticarsi.

-- ---------------------------------------------------------------------
-- doctors — profilo esteso per gli user_id 2, 3
-- ---------------------------------------------------------------------
INSERT INTO doctors (doctor_id, user_id, specialization_id, department_id, license_number) VALUES
    (1, 2, 1, 1, 'MED-LIC-10021'),  -- Dr. Bianchi, Cardiologia generale
    (2, 3, 3, 2, 'MED-LIC-10047');  -- Dr.ssa Verdi, Dermatologia clinica

-- ---------------------------------------------------------------------
-- doctor_shifts — turni passati (storico) e futuri (per manage_schedules)
-- ---------------------------------------------------------------------
INSERT INTO doctor_shifts (doctor_id, shift_date, start_time, end_time) VALUES
    (1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:00:00', '13:00:00'),
    (1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '13:00:00'),
    (1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', '18:00:00'),
    (2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:00:00', '14:00:00'),
    (2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '10:00:00', '14:00:00');

-- ---------------------------------------------------------------------
-- appointment_slots — mix di stati per esercitare book_appointment.php
-- e manage_schedules.php: alcuni 'open' (futuri, prenotabili), alcuni
-- 'booked' (già associati a un paziente, per popolare la relazione
-- clinica medico<->paziente usata da view_patient_history.php e
-- update_medical_log.php), uno 'cancelled'.
-- ---------------------------------------------------------------------
INSERT INTO appointment_slots (slot_id, doctor_id, slot_date, slot_time, status, patient_id) VALUES
    -- Slot passati, già prenotati: stabiliscono la relazione clinica
    (1, 1, DATE_SUB(CURDATE(), INTERVAL 30 DAY), '09:00:00', 'booked', 1),  -- Bianchi <-> Colombo
    (2, 1, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '09:30:00', 'booked', 2),  -- Bianchi <-> Ferrari
    (3, 2, DATE_SUB(CURDATE(), INTERVAL 20 DAY), '10:00:00', 'booked', 3),  -- Verdi <-> Romano

    -- Slot futuri, ancora liberi: visibili e prenotabili in book_appointment.php
    (4, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', 'open', NULL),
    (5, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:30:00', 'open', NULL),
    (6, 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', 'open', NULL),
    (7, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:00:00', 'open', NULL),
    (8, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:30:00', 'open', NULL),
    (9, 2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '10:00:00', 'open', NULL),

    -- Slot futuro annullato: verifica che non compaia mai come prenotabile
    (10, 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:30:00', 'cancelled', NULL);

-- ---------------------------------------------------------------------
-- medical_logs — referti collegati agli slot passati sopra
-- ---------------------------------------------------------------------
INSERT INTO medical_logs (log_id, patient_id, doctor_id, slot_id, log_date, notes) VALUES
    (1, 1, 1, 1, DATE_SUB(NOW(), INTERVAL 30 DAY),
        'Visita di controllo cardiologico. Pressione nella norma (120/80). ECG regolare. Consigliato controllo tra 6 mesi.'),
    (2, 2, 1, 2, DATE_SUB(NOW(), INTERVAL 14 DAY),
        'Prima visita cardiologica. Lieve tachicardia riferita dalla paziente, da monitorare. Prescritto Holter 24h.'),
    (3, 3, 2, 3, DATE_SUB(NOW(), INTERVAL 20 DAY),
        'Visita dermatologica per nevo sospetto su avambraccio destro. Dermatoscopia eseguita: nessun segno di atipia. Controllo annuale consigliato.');

-- ---------------------------------------------------------------------
-- prescriptions — collegate ai referti sopra, per view_prescriptions.php
-- ---------------------------------------------------------------------
INSERT INTO prescriptions (prescription_id, patient_id, doctor_id, log_id, issued_at, medication, dosage, instructions) VALUES
    (1, 1, 1, 1, DATE_SUB(NOW(), INTERVAL 30 DAY),
        'Ramipril', '5 mg', 'Una compressa al mattino, a stomaco pieno.'),
    (2, 2, 1, 2, DATE_SUB(NOW(), INTERVAL 14 DAY),
        'Bisoprololo', '2.5 mg', 'Una compressa al giorno, stessa ora, non sospendere bruscamente.'),
    (3, 3, 2, 3, DATE_SUB(NOW(), INTERVAL 20 DAY),
        'Crema idratante dermatologica', 'Applicazione topica', 'Due volte al giorno sulla zona interessata.');

-- ---------------------------------------------------------------------
-- access_audit_log — qualche riga storica di esempio (non obbligatoria
-- per il funzionamento: logAudit() la popola comunque da sola a runtime,
-- ma utile per vedere subito qualcosa in un futuro admin_audit_access.php)
-- ---------------------------------------------------------------------
INSERT INTO access_audit_log (user_id, service_id, action, ip_address, occurred_at)
SELECT 1, NULL, 'LOGIN_OK', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 1 DAY);

INSERT INTO access_audit_log (user_id, service_id, action, ip_address, occurred_at)
SELECT 2, s.service_id, 'ACCESS_GRANTED', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 20 DAY)
FROM services s WHERE s.service_name = 'doctor_log_medical_record';

SET FOREIGN_KEY_CHECKS = 1;
