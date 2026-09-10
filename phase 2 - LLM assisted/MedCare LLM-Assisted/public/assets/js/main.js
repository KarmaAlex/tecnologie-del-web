/**
 * public/assets/js/main.js
 *
 * Script vanilla condiviso da TUTTI i verticali (frontend paziente/medico
 * e backend admin). Nessuna libreria esterna (vincolo AGENTS.md:
 * "Strictly no jQuery or modern frontend components/frameworks").
 *
 * Due responsabilità distinte, tenute separate apposta:
 *
 *  1. MedCare.ui   — comportamenti dichiarativi via data-attribute,
 *                    applicati automaticamente al DOMContentLoaded.
 *                    Nessun controller deve scrivere <script> inline
 *                    per queste interazioni: basta il markup giusto.
 *
 *  2. MedCare.api  — helper fetch/FormData, pronto per quando i
 *                    controller inizieranno a esporre endpoint JSON
 *                    (fuori dallo scope delle fasi attuali, che sono
 *                    ancora submit-form classici). Non ancora invocato
 *                    da nessuna pagina: è la base per l'estensione
 *                    futura richiesta in fase di design.
 */

(function (window, document) {
    'use strict';

    var MedCare = window.MedCare || {};

    // ===============================================================
    // MedCare.ui — comportamenti dichiarativi via data-attribute
    // ===============================================================

    MedCare.ui = {

        /**
         * <form data-confirm="Testo del messaggio">...</form>
         * Sostituisce gli onsubmit="return confirm(...)" inline sparsi
         * nei controller (es. eliminazione dipartimento/turno) con un
         * unico punto di gestione.
         */
        initConfirmForms: function () {
            document.querySelectorAll('form[data-confirm]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    var message = form.getAttribute('data-confirm') || 'Confermi l\'operazione?';
                    if (!window.confirm(message)) {
                        event.preventDefault();
                    }
                });
            });
        },

        /**
         * Pattern "modifica riga -> precompila form in cima alla pagina".
         * Markup atteso:
         *   <button class="edit-trigger" data-edit-target="#my-form"
         *           data-edit-values='{"field_id":"nome_campo_form"}'
         *           data-id="3" data-name="Cardiologia">...
         *
         * Generalizza quanto scritto inline in crud_departments.html: un
         * bottone con data-edit-* popola i campi del form indicato,
         * usando gli altri data-* della riga come sorgente valori.
         * Se un controller non ha bisogno di editing inline, semplicemente
         * non emette questi data-attribute: nessun costo, nessun errore.
         */
        initEditTriggers: function () {
            document.querySelectorAll('.edit-trigger[data-edit-target]').forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    var targetForm = document.querySelector(trigger.getAttribute('data-edit-target'));
                    if (!targetForm) {
                        return;
                    }

                    var row = trigger.closest('[data-id]') || trigger;
                    var mapping = {};
                    try {
                        mapping = JSON.parse(trigger.getAttribute('data-edit-values') || '{}');
                    } catch (err) {
                        console.error('MedCare.ui: data-edit-values non è JSON valido', err);
                        return;
                    }

                    Object.keys(mapping).forEach(function (datasetKey) {
                        var fieldName = mapping[datasetKey];
                        var field = targetForm.elements[fieldName];
                        var value = row.dataset[datasetKey];
                        if (field && value !== undefined) {
                            field.value = value;
                        }
                    });

                    var editModeEvent = new CustomEvent('medcare:formEditMode', { detail: { row: row } });
                    targetForm.dispatchEvent(editModeEvent);
                });
            });
        },

        /**
         * Badge con titolo automatico: se un <span class="badge"> non ha
         * già un title esplicito, lo eredita dal proprio testo (utile per
         * troncamenti CSS futuri, nessun impatto se non applicato).
         */
        initBadgeTooltips: function () {
            document.querySelectorAll('.badge:not([title])').forEach(function (badge) {
                badge.setAttribute('title', badge.textContent.trim());
            });
        },

        initAll: function () {
            MedCare.ui.initConfirmForms();
            MedCare.ui.initEditTriggers();
            MedCare.ui.initBadgeTooltips();
        }
    };

    // ===============================================================
    // MedCare.api — helper fetch/FormData per future interazioni AJAX
    // ===============================================================
    //
    // Non ancora usato da nessun controller: i service attuali (Fase 3)
    // sono submit-form classici con redirect PRG. Questo modulo esiste
    // per quando un verticale futuro vorrà, ad esempio, aggiornare una
    // tabella senza reload pagina. Include già il token CSRF (letto dal
    // meta tag iniettato da include/layout.php) perché ogni POST verso
    // un service deve comunque passare da verifyCsrfToken() lato server.

    MedCare.api = {

        /**
         * Legge il token CSRF corrente dal meta tag <meta name="csrf-token">.
         * Se il meta tag non è presente (pagina che non lo espone ancora),
         * restituisce null: il chiamante decide come comportarsi.
         */
        getCsrfToken: function () {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : null;
        },

        /**
         * POST di un FormData verso un service, con CSRF già incluso.
         * Esempio d'uso futuro:
         *
         *   MedCare.api.postForm('/services/patient/book_appointment.php', formData)
         *     .then(function (data) { ... aggiorna il DOM ... })
         *     .catch(function (err) { ... mostra errore ... });
         *
         * Il service target deve rispondere con JSON (Content-Type
         * application/json) perché questo helper fa .json() sulla
         * risposta; un service ancora "solo redirect" non è compatibile
         * finché non viene esteso a restituire JSON su richieste
         * X-Requested-With: fetch.
         */
        postForm: function (url, formData) {
            var token = MedCare.api.getCsrfToken();
            if (token && !formData.has('csrf_token')) {
                formData.append('csrf_token', token);
            }

            return fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'fetch'
                }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Richiesta fallita con stato ' + response.status);
                }
                return response.json();
            });
        },

        /**
         * GET verso un service che risponde JSON (es. un futuro endpoint
         * di ricerca/autocomplete). Stessa convenzione header di postForm.
         */
        getJSON: function (url) {
            return fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'fetch',
                    'Accept': 'application/json'
                }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Richiesta fallita con stato ' + response.status);
                }
                return response.json();
            });
        }
    };

    window.MedCare = MedCare;

    document.addEventListener('DOMContentLoaded', function () {
        MedCare.ui.initAll();
    });

})(window, document);
