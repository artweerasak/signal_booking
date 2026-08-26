<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

// ดึงไฟล์เชื่อมต่อฐานข้อมูล
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} else {
    die("ไม่พบไฟล์ config/database.php กรุณาตรวจสอบตำแหน่งไฟล์");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// รับค่าจาก Form
$user_type = $_POST['user_type'] ?? 'General';
$fullname = trim($_POST['fullname'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$total_price = floatval($_POST['total_price'] ?? 0);
$booking_items_raw = $_POST['booking_items'] ?? '[]';
$booking_items = json_decode($booking_items_raw, true);

if (empty($fullname) || empty($phone) || empty($booking_items) || !is_array($booking_items)) {
    die("<div style='padding:20px; font-family:sans-serif;'>
            <h3 style='color:#DC2626;'>ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้อง</h3>
            <a href='javascript:history.back()' style='color:#15803D; font-weight:bold;'>❮ ย้อนกลับไปแก้ไข</a>
         </div>");
}

// อัปโหลดสลิป
$slip_filename = "";
if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/slips/';
    if (!file_exists($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $file_ext = strtolower(pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($file_ext, $allowed_ext)) {
        $slip_filename = 'slip_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        $target_path = $upload_dir . $slip_filename;
        move_uploaded_file($_FILES['payment_slip']['tmp_name'], $target_path);
    }
}

// บันทึกข้อมูลลงฐานข้อมูลโดยใช้ PDO อย่างถูกต้อง
try {
    $active_pdo = null;
    if (isset($pdo) && $pdo instanceof PDO) {
        $active_pdo = $pdo;
    } elseif (isset($conn) && $conn instanceof PDO) {
        $active_pdo = $conn;
    }

    if (!$active_pdo) {
        throw new Exception("ไม่พบตัวแปรเชื่อมต่อฐานข้อมูล PDO ในไฟล์ config/database.php");
    }

    // เริ่มต้น Transaction ของ PDO
    $active_pdo->beginTransaction();

    // 1. บันทึกข้อมูลลงตาราง bookings (บันทึก phone ลงใน booking_no ด้วยเลยเพื่อให้ใช้เบอร์โทรเป็นรหัสอ้างอิง)
    $stmt = $active_pdo->prepare("INSERT INTO bookings (booking_no, fullname, phone, user_type, total_price, slip_image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$phone, $fullname, $phone, $user_type, $total_price, $slip_filename]);
    $booking_id = $active_pdo->lastInsertId();

    // 2. บันทึกรายการสนามลงตาราง booking_items
    $stmt_item = $active_pdo->prepare("INSERT INTO booking_items (booking_id, court_id, booking_date, time_slot, price) VALUES (?, ?, ?, ?, ?)");
    foreach ($booking_items as $item) {
        $court_id = intval($item['courtId'] ?? 1);
        $date = $item['date'] ?? date('Y-m-d');
        $time = $item['timeText'] ?? '';
        $price = floatval($item['price'] ?? 0);
        
        $stmt_item->execute([$booking_id, $court_id, $date, $time, $price]);
    }

    // ยืนยัน Transaction
    $active_pdo->commit();

} catch (Exception $e) {
    if (isset($active_pdo) && $active_pdo instanceof PDO && $active_pdo->inTransaction()) {
        $active_pdo->rollBack();
    }
    die("<div style='padding:20px; font-family:sans-serif;'>
            <h3 style='color:#DC2626;'>เกิดข้อผิดพลาดในการบันทึกข้อมูล:</h3>
            <p style='color:#4B5563;'>" . htmlspecialchars($e->getMessage()) . "</p>
            <br>
            <a href='javascript:history.back()' style='color:#15803D; font-weight:bold; text-decoration:none;'>❮ ย้อนกลับไปแก้ไข</a>
         </div>");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันการทำรายการ - สนามเทนนิส กรมการทหารสื่อสาร</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Prompt', sans-serif; }
        body { background: #FAFAFC; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .card { background: #FFFFFF; padding: 40px 30px; border-radius: 16px; text-align: center; max-width: 450px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #F1F5F9; }
        .icon { font-size: 64px; margin-bottom: 15px; }
        h2 { color: #1E293B; font-size: 1.4rem; margin-bottom: 10px; font-weight: 700; }
        p { color: #64748B; font-size: 0.95rem; margin-bottom: 25px; line-height: 1.6; }
        .btn-home { display: inline-block; background: #15803D; color: #FFFFFF; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; transition: background 0.2s; }
        .btn-home:hover { background: #166534; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🎉</div>
        <h2>ส่งข้อมูลการจองสำเร็จ!</h2>
        <p>ระบบได้รับข้อมูลและหลักฐานการโอนเงินเรียบร้อยแล้ว ใช้เบอร์โทรศัพท์ <b style="color: #15803D; font-size: 1.1rem;"><?php echo htmlspecialchars($phone); ?></b> เป็นรหัสอ้างอิงในการตรวจสอบ เจ้าหน้าที่จะดำเนินการโดยเร็วที่สุด</p>
        <a href="index.php" class="btn-home">กลับสู่หน้าหลัก</a>
    </div>
</body>
</html>