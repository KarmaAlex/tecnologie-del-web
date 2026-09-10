<?php
/**
 * include/labels.php
 *
 * Centralizza la mappatura nome-tecnico -> etichetta leggibile per i
 * nomi che provengono direttamente dal database (services.service_name,
 * groups.group_name) e che altrimenti finirebbero mostrati all'utente
 * così come sono in tabella (es. "admin_crud_departments",
 * "System_Admin") invece che in una forma presentabile.
 *
 * Se un nome non è in mappa (es. un nuovo service appena registrato in
 * schema.sql prima di essere aggiunto qui), si ricade su una
 * derivazione automatica leggibile: underscore -> spazio, capitalizzato
 * — mai il nome tecnico grezzo.
 */

declare(strict_types=1);

/**
 * services.service_name -> etichetta in italiano mostrata nei menu.
 */
function serviceLabel(string $serviceName): string
{
    static $labels = [
        'patient_view_specialties'     => 'Specializzazioni cliniche',
        'patient_book_appointment'     => 'Prenota una visita',
        'patient_view_prescriptions'   => 'Le mie prescrizioni',
        'patient_update_insurance'     => 'Aggiorna assicurazione',
        'patient_view_profile'         => 'Il mio profilo',

        'doctor_view_appointments'     => 'I miei appuntamenti',
        'doctor_view_patient_history'  => 'Storico paziente',
        'doctor_log_medical_record'    => 'Nuovo referto clinico',
        'doctor_issue_prescription'    => 'Nuova prescrizione',
        'doctor_update_shift'          => 'I miei turni',
        'doctor_manage_slots'          => 'Gestione slot',

        'admin_manage_users'           => 'Gestione utenti',
        'admin_assign_groups'          => 'Assegnazione gruppi',
        'admin_crud_departments'       => 'Gestione dipartimenti',
        'admin_manage_specializations' => 'Gestione specializzazioni',
        'admin_manage_schedules'       => 'Gestione turni',
        'admin_audit_access'           => 'Registro accessi',
    ];

    return $labels[$serviceName] ?? humanizeTechnicalName($serviceName);
}

/**
 * groups.group_name -> etichetta in italiano mostrata nell'header e
 * nella dashboard.
 */
function groupLabel(string $groupName): string
{
    static $labels = [
        'Patient_Tier'  => 'Paziente',
        'Medical_Staff' => 'Personale medico',
        'System_Admin'  => 'Amministratore',
    ];

    return $labels[$groupName] ?? humanizeTechnicalName($groupName);
}

/**
 * Fallback per nomi non ancora mappati: 'admin_new_thing' o
 * 'New_Group_Name' -> 'Admin new thing' / 'New group name'.
 * Non è pensato per essere l'etichetta finale di produzione, solo per
 * evitare di mostrare snake_case/underscore grezzo se qualcuno
 * dimentica di aggiungere una voce alle mappe sopra.
 */
function humanizeTechnicalName(string $technicalName): string
{
    $spaced = str_replace('_', ' ', $technicalName);
    return ucfirst(strtolower($spaced));
}
