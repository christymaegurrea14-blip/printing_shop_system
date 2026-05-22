<?php
// ============================================================
// includes/config/database.php
// PDO Database Connection — Printing Shop Management System
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'printing_shop_system');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO instance.
 * Usage: $pdo = getDB();
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log the error instead of showing it
            die('<div style="font-family:sans-serif;padding:2rem;color:#c0392b;">
                <h2>Database Connection Failed</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Please check your database settings in <code>includes/config/database.php</code></p>
            </div>');
        }
    }

    return $pdo;
}
