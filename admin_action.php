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
$batch  = trim($_POST['batch'] ?? '');
$map    = ['approve' => 'approved', 'cancel' => 'cancelled'];

try {
    if ($action === 'cancel_batch' && $batch !== '') {
        // ยกเลิกการล็อกทั้งชุด (ทุกวันที่ถูกล็อกพร้อมกันในคราวเดียว)
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE lock_batch = ?");
        $stmt->execute([$batch]);
    } elseif ($id > 0 && isset($map[$action])) {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$map[$action], $id]);
    }
} catch (PDOException $e) {
    error_log('admin_action failed: ' . $e->getMessage());
}

header('Location: admin.php');
exit;
