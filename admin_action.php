<?php
// รับคำสั่งอนุมัติ/ยกเลิกการจอง จากหน้า admin.php (POST + CSRF)
require 'admin_auth.php';
require 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

// ตรวจ CSRF token
if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(403);
    die('คำขอไม่ถูกต้อง (CSRF)');
}

$id     = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$map    = ['approve' => 'approved', 'cancel' => 'cancelled'];

if ($id > 0 && isset($map[$action])) {
    try {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$map[$action], $id]);
    } catch (PDOException $e) {
        error_log('admin_action failed: ' . $e->getMessage());
    }
}

header('Location: admin.php');
exit;
