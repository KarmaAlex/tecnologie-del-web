USE `medcare_portal`;

-- Groups
INSERT INTO `groups` (`id`,`name`,`description`) VALUES
(1,'System_Admin','Administrative staff with full access'),
(2,'Medical_Staff','Doctors and clinical personnel'),
(3,'Patient_Tier','Registered patients');

-- Services (match public/services paths)
INSERT INTO `services` (`id`,`path`,`name`,`description`) VALUES
(1,'public/services/admin/crud_departments.php','CRUD Departments','Manage departments and metadata'),
(2,'public/services/admin/manage_schedules.php','Manage Schedules','Create and update doctor schedules'),
(3,'public/services/doctor/update_medical_log.php','Update Medical Log','Doctors add or edit medical notes'),
(4,'public/services/doctor/view_patient_history.php','View Patient History','Access patient historical records'),
(5,'public/services/patient/book_appointment.php','Book Appointment','Patients book appointments'),
(6,'public/services/patient/view_prescriptions.php','View Prescriptions','Patients view their prescriptions');

-- Users (ids set explicitly to simplify relations)
INSERT INTO `users` (`id`,`username`,`password_hash`,`email`,`full_name`,`phone_number`,`role`,`active`) VALUES
(1,'admin','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','admin@example.it','Giulia Rossi','+390612345678','admin',1),
(2,'dr_moretti','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','marco.moretti@example.it','Marco Moretti','+393331234567','doctor',1),
(3,'dr_bianchi','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','laura.bianchi@example.it','Laura Bianchi','+393498765432','doctor',1),
(4,'paolo.verdi','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','paolo.verdi@example.it','Paolo Verdi','+393472345678','patient',1),
(5,'maria.neri','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','maria.neri@example.it','Maria Neri','+393479876543','patient',1);

-- Map users to groups
INSERT INTO `users_has_groups` (`user_id`,`group_id`) VALUES
(1,1), -- admin -> System_Admin
(2,2), -- dr_moretti -> Medical_Staff
(3,2), -- dr_bianchi -> Medical_Staff
(4,3), -- paolo.verdi -> Patient_Tier
(5,3); -- maria.neri -> Patient_Tier

-- Map services to groups
INSERT INTO `services_has_groups` (`service_id`,`group_id`) VALUES
(1,1), -- admin CRUD -> System_Admin
(2,1), -- manage schedules -> System_Admin
(3,2), -- update medical log -> Medical_Staff
(4,2), -- view patient history -> Medical_Staff
(5,3), -- book appointment -> Patient_Tier
(6,3); -- view prescriptions -> Patient_Tier

-- Departments
INSERT INTO `departments` (`id`,`name`,`description`) VALUES
(1,'Cardiologia','Reparto di cardiologia'),
(2,'Neurologia','Reparto di neurologia');

-- Specializations
INSERT INTO `specializations` (`id`,`name`,`description`) VALUES
(1,'Cardiologo','Specialista in cardiologia'),
(2,'Neurologo','Specialista in neurologia');

-- Doctor profiles (ids explicit)
INSERT INTO `doctor_profiles` (`id`,`user_id`,`department_id`,`specialization_id`,`license_number`,`bio`) VALUES
(1,2,1,1,'REG-CRD-2020-01','Dr. Marco Moretti, specialista in cardiologia con 12 anni di esperienza.'),
(2,3,2,2,'REG-NEU-2018-09','Dr.ssa Laura Bianchi, esperta in neurologia pediatrica.');

-- Patient profiles
INSERT INTO `patient_profiles` (`id`,`user_id`,`dob`,`fiscal_code`) VALUES
(1,4,'1985-04-12','VRDPLL85D12H501A'),
(2,5,'1992-11-03','NRIMRA92S43F205B');

-- Schedules for doctors
INSERT INTO `schedules` (`id`,`doctor_id`,`start_at`,`end_at`,`location`) VALUES
(1,1,'2026-06-02 09:00:00','2026-06-02 12:00:00','Ambulatorio A'),
(2,1,'2026-06-03 14:00:00','2026-06-03 17:00:00','Ambulatorio B'),
(3,2,'2026-06-02 10:00:00','2026-06-02 13:00:00','Ambulatorio C');

-- Appointments
INSERT INTO `appointments` (`id`,`patient_id`,`doctor_id`,`schedule_id`,`appointment_at`,`status`,`reason`) VALUES
(1,1,1,1,'2026-06-02 09:30:00','booked','Controllo pressione'),
(2,2,2,3,'2026-06-02 10:45:00','booked','Mal di testa persistente');

-- Medical logs
INSERT INTO `medical_logs` (`id`,`doctor_id`,`patient_id`,`appointment_id`,`note`,`attachments`) VALUES
(1,1,1,1,'Visita di controllo. Pressione arteriosa nella norma, prescritti esami del sangue.',''),
(2,2,2,2,'Anamnesi e osservazioni iniziali. Richiesto EEG.','');

-- Prescriptions and items
INSERT INTO `prescriptions` (`id`,`patient_id`,`doctor_id`,`issued_at`,`notes`) VALUES
(1,1,1,'2026-06-02 09:40:00','Terapia sintomatica per 7 giorni');

INSERT INTO `prescription_items` (`id`,`prescription_id`,`medication`,`dosage`,`instructions`,`quantity`) VALUES
(1,1,'Paracetamolo 500mg','500mg','Assumere una compressa ogni 8 ore se necessario','14');

COMMIT;
