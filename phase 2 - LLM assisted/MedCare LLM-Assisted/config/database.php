<?php
/**
 * config/database.php
 *
 * Parser .env minimale (nessuna dipendenza esterna, coerente con il vincolo
 * "framework prohibition" dell'AGENTS.md) + factory PDO singleton.
 *
 * Il file .env NON va mai committato: qui viene solo letto.
 * Atteso in project-root/.env con le chiavi:
 *   DB_HOST=localhost
 *   DB_PORT=3306
 *   DB_NAME=medcare_portal
 *   DB_USER=root
 *   DB_PASS=
 *   DB_CHARSET=utf8mb4
 */

declare(strict_types=1);

/**
 * Legge un file .env e lo carica in una mappa associativa.
 * Supporta commenti (#), righe vuote, valori tra virgolette, niente export.
 */
function loadEnvFile(string $path): array
{
    static $cache = [];

    if (isset($cache[$path])) {
        return $cache[$path];
    }

    if (!is_readable($path)) {
        throw new RuntimeException("Impossibile leggere il file di configurazione: {$path}");
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Rimuove eventuali virgolette avvolgenti (singole o doppie)
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $values[$key] = $value;
    }

    $cache[$path] = $values;
    return $values;
}

/**
 * Restituisce una connessione PDO condivisa (singleton per request).
 * Lancia PDOException se la connessione fallisce: i chiamanti nei controller
 * NON devono mai stampare il messaggio grezzo all'utente finale.
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $envPath = dirname(__DIR__) . '/.env';
    $env = loadEnvFile($envPath);

    $host    = $env['DB_HOST']    ?? '127.0.0.1';
    $port    = $env['DB_PORT']    ?? '3306';
    $dbname  = $env['DB_NAME']    ?? 'medcare_portal';
    $user    = $env['DB_USER']    ?? 'root';
    $pass    = $env['DB_PASS']    ?? '';
    $charset = $env['DB_CHARSET'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}
