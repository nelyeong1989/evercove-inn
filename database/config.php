<?php
// Database connection setup
function getConnection(): PDO
{
    static $pdo = null;

    // Reuse connection if already opened
    if ($pdo !== null) {
        return $pdo;
    }

    $host = 'localhost';
    $db   = 'evercove_db';
    $user = 'root';
    $pass = 'njgwapo2006';

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        die("Could not connect to the database. Please try again later.");
    }
}