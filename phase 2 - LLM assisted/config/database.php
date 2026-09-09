<?php
/**
 * Database connection factory, configure credentials in .env
 */

if (!function_exists('loadDotEnvFile')) {
    function loadDotEnvFile(string $filePath): void
    {
        if (!is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);

            if ($name === '') {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }
}

loadDotEnvFile(__DIR__ . '/.env');

if (!function_exists('getEnvVar')) {
    function getEnvVar(string $name, $default = null)
    {
        $val = getenv($name);
        if ($val === false) {
            if (array_key_exists($name, $_ENV)) {
                $val = $_ENV[$name];
            } else {
                $val = $default;
            }
        }
        return $val;
    }
}

if (!function_exists('getPDO')) {
    function getPDO(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Missing required PHP extension: pdo_mysql.');
        }

        $host = (string) getEnvVar('DB_HOST', '127.0.0.1');
        $port = (int) getEnvVar('DB_PORT', 3306);
        $db   = (string) getEnvVar('DB_NAME', 'medcare_portal');
        $user = (string) getEnvVar('DB_USER', 'root');
        $pass = (string) getEnvVar('DB_PASS', '');
        $charset = (string) getEnvVar('DB_CHARSET', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $db, $charset);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw $e;
        }
    }
}

if (!function_exists('db_query')) {
    /**
     * execute a prepared statement and return rows or false on failure
     */
    function db_query(string $sql, array $params = [])
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            return $stmt->fetchAll();
        }
        return false;
    }
}