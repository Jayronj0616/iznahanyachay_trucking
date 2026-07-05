<?php
// Change this if the project ever moves to a vhost root (e.g. '' for trucking_system.test)
define('BASE_PATH', '/trucking_system');

define('DB_HOST', 'localhost');
define('DB_NAME', 'iznahanyachay_trucking');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}
