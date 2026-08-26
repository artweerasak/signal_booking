<?php
// เช็คสิทธิ์แอดมิน — include ไฟล์นี้ไว้บนสุดของทุกหน้า admin ที่ต้องล็อกอิน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}
// สร้าง CSRF token ไว้กันการปลอมคำสั่ง (approve/cancel)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
