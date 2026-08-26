<?php
// เชื่อมต่อฐานข้อมูล — โหลดค่าลับจาก config/db_config.php (อยู่ใน .gitignore)
$cfgFile = __DIR__ . '/db_config.php';
if (!file_exists($cfgFile)) {
    error_log('Missing config/db_config.php');
    http_response_code(500);
    die('ระบบยังไม่ได้ตั้งค่าฐานข้อมูล (ไม่พบ config/db_config.php) — ดูตัวอย่างที่ db_config.example.php');
}
$cfg = require $cfgFile;

try {
    $conn = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4",
        $cfg['username'],
        $cfg['password'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // ไม่โชว์รายละเอียด error ให้ผู้ใช้ (กันข้อมูลรั่ว) — เก็บลง log แทน
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง');
}
