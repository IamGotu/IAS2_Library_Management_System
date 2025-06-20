<?php
$host = 'localhost';
$dbname = 'library_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // For better error handling
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}
?>