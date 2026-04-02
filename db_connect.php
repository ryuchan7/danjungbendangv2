<?php
try {
    $host = '127.0.0.1';
    $dbname = 'homestay_booking';
    $username = 'root'; // Adjust if you have a different MySQL user
    $password = ''; // Adjust if you have a password
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>