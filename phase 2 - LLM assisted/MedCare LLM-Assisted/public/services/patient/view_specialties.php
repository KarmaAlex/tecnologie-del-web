<?php
/**
 * public/services/patient/view_specialties.php
 *
 * Service registrato come 'patient_view_specialties'.
 * Sola lettura: elenca le specializzazioni cliniche disponibili,
 * raggruppate per dipartimento, con il numero di medici che le offrono
 * — utile al paziente per orientarsi prima di prenotare una visita.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('patient_view_specialties');

$pdo = getPDO();

$stmt = $pdo->query(
    "SELECT dep.department_name, sp.specialization_name,
            COUNT(doc.doctor_id) AS doctor_count
     FROM departments dep
     JOIN specializations sp ON sp.department_id = dep.department_id
     LEFT JOIN doctors doc ON doc.specialization_id = sp.specialization_id
     GROUP BY dep.department_id, dep.department_name, sp.specialization_id, sp.specialization_name
     ORDER BY dep.department_name, sp.specialization_name"
);
$rows = $stmt->fetchAll();

$specialtyRows = '';
if (empty($rows)) {
    $specialtyRows = '<tr><td colspan="3"><em>Nessuna specializzazione registrata.</em></td></tr>';
} else {
    foreach ($rows as $row) {
        $doctorCount = (int)$row['doctor_count'];
        $countLabel = $doctorCount === 0
            ? '<span class="muted">Nessun medico disponibile</span>'
            : $doctorCount . ' ' . ($doctorCount === 1 ? 'medico' : 'medici');

        $specialtyRows .= '<tr>'
            . '<td>' . htmlspecialchars($row['department_name'], ENT_QUOTES) . '</td>'
            . '<td>' . htmlspecialchars($row['specialization_name'], ENT_QUOTES) . '</td>'
            . '<td>' . $countLabel . '</td>'
            . '</tr>';
    }
}

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/patient/view_specialties');
$tpl->setContent('specialty_rows', $specialtyRows);

renderHeader('Specializzazioni cliniche');
echo $tpl->get();
renderFooter();
