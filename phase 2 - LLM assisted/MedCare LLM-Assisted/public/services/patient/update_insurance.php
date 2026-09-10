<?php
/**
 * public/services/patient/update_insurance.php
 *
 * Service registrato come 'patient_update_insurance'.
 * GET  -> mostra il form precompilato con i dati assicurativi attuali.
 * POST -> aggiorna SOLO insurance_provider/insurance_number in
 *         patients. Non tocca dati anagrafici (nome, data di nascita,
 *         indirizzo) — quelli restano fuori scope di questo service,
 *         come da AGENTS.md ("Update primary insurance tracking
 *         profile details").
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../include/auth.php';
require_once __DIR__ . '/../../../include/template2.inc.php';
require_once __DIR__ . '/../../../include/layout.php';

requireService('patient_update_insurance');

$pdo = getPDO();
$userId = $_SESSION['user']['user_id'];

$feedback = null;
$feedbackType = 'info';

$patientStmt = $pdo->prepare('SELECT patient_id FROM patients WHERE user_id = :user_id LIMIT 1');
$patientStmt->execute([':user_id' => $userId]);
$patientId = $patientStmt->fetchColumn();

if ($patientId === false) {
    $feedback = 'Il tuo profilo paziente non è ancora stato completato. Contatta l\'amministrazione.';
    $feedbackType = 'error';
}

// -----------------------------------------------------------------
// POST: aggiornamento dati assicurativi
// -----------------------------------------------------------------
if ($patientId !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $provider = trim((string)($_POST['insurance_provider'] ?? ''));
    $number = trim((string)($_POST['insurance_number'] ?? ''));

    if (mb_strlen($provider) > 120) {
        $feedback = 'Il nome della compagnia assicurativa è troppo lungo (massimo 120 caratteri).';
        $feedbackType = 'error';
    } elseif (mb_strlen($number) > 60) {
        $feedback = 'Il numero di polizza è troppo lungo (massimo 60 caratteri).';
        $feedbackType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare(
                'UPDATE patients SET insurance_provider = :provider, insurance_number = :number
                 WHERE patient_id = :patient_id'
            );
            $stmt->execute([
                ':provider'   => $provider !== '' ? $provider : null,
                ':number'     => $number !== '' ? $number : null,
                ':patient_id' => (int)$patientId,
            ]);

            $feedback = 'Dati assicurativi aggiornati con successo.';
            $feedbackType = 'success';
        } catch (Throwable $e) {
            error_log('[update_insurance] ' . $e->getMessage());
            $feedback = 'Si è verificato un errore durante il salvataggio. Riprova.';
            $feedbackType = 'error';
        }
    }
}

// -----------------------------------------------------------------
// GET: valori correnti per precompilare il form
// -----------------------------------------------------------------
$currentProvider = '';
$currentNumber = '';

if ($patientId !== false) {
    $stmt = $pdo->prepare('SELECT insurance_provider, insurance_number FROM patients WHERE patient_id = :patient_id LIMIT 1');
    $stmt->execute([':patient_id' => (int)$patientId]);
    $current = $stmt->fetch();
    $currentProvider = $current['insurance_provider'] ?? '';
    $currentNumber = $current['insurance_number'] ?? '';
}

$feedbackHtml = $feedback
    ? '<p class="feedback feedback-' . htmlspecialchars($feedbackType, ENT_QUOTES) . '">' . htmlspecialchars($feedback, ENT_QUOTES) . '</p>'
    : '';

$tpl = new Template(dirname(__DIR__, 3) . '/skins/frontend/patient/update_insurance');
$tpl->setContent('feedback_html', $feedbackHtml);
$tpl->setContent('insurance_provider_value', htmlspecialchars($currentProvider, ENT_QUOTES));
$tpl->setContent('insurance_number_value', htmlspecialchars($currentNumber, ENT_QUOTES));
$tpl->setContent('csrf_field', csrfField());

renderHeader('Aggiorna assicurazione');
echo $tpl->get();
renderFooter();
