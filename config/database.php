<?php
$host     = 'sql112.infinityfree.com';
$dbname   = 'if0_42600216_signal_booking';
$username = 'if0_42600216';
$password = 'v8QHQ18HlT';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}
?>