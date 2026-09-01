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

-- Additional departments
INSERT INTO `departments` (`id`,`name`,`description`) VALUES
(3,'Dermatologia','Reparto di dermatologia e cosmetica medica'),
(4,'Pediatria','Reparto di pediatria generale e specialistica'),
(5,'Ortopedia','Reparto di ortopedia e traumatologia');

-- Additional specializations
INSERT INTO `specializations` (`id`,`name`,`description`) VALUES
(3,'Dermatologo','Specialista in dermatologia'),
(4,'Pediatra','Specialista in pediatria'),
(5,'Ortopedico','Specialista in ortopedia');

-- Additional users: 2 doctors + 4 patients
INSERT INTO `users` (`id`,`username`,`password_hash`,`email`,`full_name`,`phone_number`,`role`,`active`) VALUES
(6,'dr_romanelli','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','alessandro.romanelli@example.it','Alessandro Romanelli','+393401112233','doctor',1),
(7,'dr_ferretti','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','elena.ferretti@example.it','Elena Ferretti','+393401122334','doctor',1),
(8,'giulia.mancini','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','giulia.mancini@example.it','Giulia Mancini','+393456789012','patient',1),
(9,'luca.belli','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','luca.belli@example.it','Luca Belli','+393457890123','patient',1),
(10,'sofia.pasquali','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','sofia.pasquali@example.it','Sofia Pasquali','+393458901234','patient',1),
(11,'antonio.rizzi','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','antonio.rizzi@example.it','Antonio Rizzi','+393459012345','patient',1);

-- Map additional users to groups
INSERT INTO `users_has_groups` (`user_id`,`group_id`) VALUES
(6,2),
(7,2),
(8,3),
(9,3),
(10,3),
(11,3);

-- Additional doctor profiles
INSERT INTO `doctor_profiles` (`id`,`user_id`,`department_id`,`specialization_id`,`license_number`,`bio`) VALUES
(3,6,3,3,'REG-DER-2022-04','Dott. Alessandro Romanelli, dermatologo con esperienza in patologie cutanee e cosmetologia medica.'),
(4,7,5,5,'REG-ORT-2021-11','Dott.ssa Elena Ferretti, ortopedica specializzata in riabilitazione e patologie articolari.');

-- Additional patient profiles
INSERT INTO `patient_profiles` (`id`,`user_id`,`dob`,`fiscal_code`) VALUES
(3,8,'1990-02-20','MNCGLI90B20A001F'),
(4,9,'1988-07-16','BLLLCA88L16C123Q'),
(5,10,'2001-05-28','PSQSFA01E28G702X'),
(6,11,'1976-12-09','RZZNTN76T09F205Z');

-- Additional schedules
INSERT INTO `schedules` (`id`,`doctor_id`,`start_at`,`end_at`,`location`) VALUES
(4,1,'2026-09-08 09:00:00','2026-09-08 12:00:00','Ambulatorio D'),
(5,2,'2026-09-12 15:00:00','2026-09-12 17:30:00','Ambulatorio E'),
(6,3,'2026-09-09 11:00:00','2026-09-09 14:00:00','Studio Dermatologico'),
(7,4,'2026-09-10 08:00:00','2026-09-10 10:30:00','Ambulatorio Ortopedia');

-- Additional appointments
INSERT INTO `appointments` (`id`,`patient_id`,`doctor_id`,`schedule_id`,`appointment_at`,`status`,`reason`) VALUES
(3,3,1,4,'2026-09-08 09:30:00','confirmed','Follow-up controllo pressione'),
(4,4,2,5,'2026-09-12 15:30:00','booked','Valutazione sintomi neurologici'),
(5,5,3,6,'2026-09-09 11:30:00','confirmed','Controllo cute e irritazioni'),
(6,6,4,7,'2026-09-10 08:45:00','booked','Dolore al ginocchio e mobilità');

-- Additional medical logs
INSERT INTO `medical_logs` (`id`,`doctor_id`,`patient_id`,`appointment_id`,`note`,`attachments`) VALUES
(3,1,3,3,'Follow-up cardiologico: pressione stabile, confermata terapia di mantenimento e controllo dopo 30 giorni.',''),
(4,2,4,4,'Esame neurologico senza alterazioni rilevanti. Richiesto controllo dopo due settimane.',''),
(5,3,5,5,'Lesione cutanea lieve con irritazione persistente; prescritta crema idratante e monitoraggio.',''),
(6,4,6,6,'Valutazione articolare: lieve infiammazione. Consigliato riposo, fisioterapia e controllo dopo 10 giorni.','');

-- Additional prescriptions
INSERT INTO `prescriptions` (`id`,`patient_id`,`doctor_id`,`issued_at`,`notes`) VALUES
(2,3,1,'2026-09-08 09:40:00','Terapia di mantenimento per 30 giorni'),
(3,4,2,'2026-09-12 15:45:00','Controllo neurologico e integrazione vitamina B12'),
(4,5,3,'2026-09-09 11:45:00','Crema topica e idratazione cutanea'),
(5,6,4,'2026-09-10 09:00:00','Gestione dolore articolare e riabilitazione');

INSERT INTO `prescription_items` (`id`,`prescription_id`,`medication`,`dosage`,`instructions`,`quantity`) VALUES
(2,2,'Amlodipina 5mg','5mg','Assumere una compressa al giorno dopo pranzo','30'),
(3,3,'Magnesio 400mg','400mg','Una compressa al giorno dopo cena','30'),
(4,4,'Idrocolloid Piastra','1 piastra','Applicare sulla zona irritata per 24 ore','10'),
(5,5,'Ibuprofene 200mg','200mg','Assumere una compressa ogni 12 ore se necessario','20');

COMMIT;
