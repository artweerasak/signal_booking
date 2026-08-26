<?php
// ไม่แสดง error ให้ผู้ใช้เห็น (กันข้อมูลรั่ว) — เก็บลง log แทน
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

// กล่องข้อความ error แบบสวยงาม (ใช้ซ้ำได้)
function err_box($msg) {
    return "<div style='padding:20px; font-family:sans-serif;'>
            <h3 style='color:#DC2626;'>" . htmlspecialchars($msg) . "</h3>
            <a href='javascript:history.back()' style='color:#15803D; font-weight:bold;'>❮ ย้อนกลับไปแก้ไข</a>
         </div>";
}

// ดึงไฟล์เชื่อมต่อฐานข้อมูล
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} else {
    die("ไม่พบไฟล์ config/database.php กรุณาตรวจสอบตำแหน่งไฟล์");
}
require_once 'config/security.php';
require_once 'config/pricing.php';
$slotConfig = require 'config/slots.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ═══ กันบอท ═══
// 1) honeypot ถูกกรอก = บอท → ตัดจบเงียบๆ (ไม่บอกสาเหตุ)
if (is_honeypot_filled('website')) {
    error_log('bot blocked (honeypot) ip=' . client_ip());
    die(err_box("ไม่สามารถดำเนินการได้"));
}
// 2) ส่งฟอร์มเร็วผิดปกติ/หมดอายุ/token ปลอม = บอท
if (!check_form_time($_POST['form_ts'] ?? '', 3, 3600)) {
    error_log('bot blocked (timing) ip=' . client_ip());
    die(err_box("กรุณาลองใหม่อีกครั้ง (โหลดฟอร์มใหม่แล้วกรอกช้าลงเล็กน้อย)"));
}
// 3) จำกัดอัตรา: จองได้ไม่เกิน 5 ครั้งต่อ 10 นาที ต่อ IP
if (!rate_limit_ok($conn, 'booking', 5, 600)) {
    error_log('rate limited (booking) ip=' . client_ip());
    die(err_box("คุณทำรายการบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่"));
}

// รับค่าจาก Form
$user_type = $_POST['user_type'] ?? 'General';
$fullname = trim($_POST['fullname'] ?? '');
$phone = preg_replace('/\D/', '', $_POST['phone'] ?? ''); // เก็บเฉพาะตัวเลข
$total_price = floatval($_POST['total_price'] ?? 0);
$booking_items_raw = $_POST['booking_items'] ?? '[]';
$booking_items = json_decode($booking_items_raw, true);

if ($fullname === '' || strlen($phone) < 9 || strlen($phone) > 10 || empty($booking_items) || !is_array($booking_items)) {
    die(err_box("ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้อง (กรอกชื่อ และเบอร์โทร 9-10 หลัก)"));
}

// อัปโหลดสลิป — ตรวจชนิดไฟล์จริง (MIME) + จำกัดขนาด ไม่เชื่อนามสกุลจากผู้ใช้
$slip_filename = "";
if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/slips/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $tmp  = $_FILES['payment_slip']['tmp_name'];
    $size = $_FILES['payment_slip']['size'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if ($size > 5 * 1024 * 1024) {
        die(err_box("ไฟล์สลิปใหญ่เกิน 5MB กรุณาย่อขนาดก่อน"));
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        die(err_box("ชนิดไฟล์สลิปไม่ถูกต้อง (รับเฉพาะรูป JPG/PNG/WEBP)"));
    }

    $slip_filename = 'slip_' . time() . '_' . rand(1000, 9999) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $upload_dir . $slip_filename)) {
        error_log('slip upload failed for ' . $phone);
        $slip_filename = ""; // อัปโหลดไม่สำเร็จ — บันทึกการจองต่อได้แต่ไม่มีสลิป
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

    // ── คิดราคาฝั่งเซิร์ฟเวอร์ (ไม่เชื่อราคาที่ส่งมาจากผู้ใช้ + ใช้ราคาตามวัน) ──
    $slotMap = [];
    foreach ($slotConfig['slots'] as $s) $slotMap[$s['id']] = $s;
    $base = get_base_prices($active_pdo);
    $overrideCache = [];

    $prepared = [];
    $total = 0;
    foreach ($booking_items as $item) {
        $court_id = intval($item['courtId'] ?? 1);
        $slot_id  = intval($item['slotId'] ?? 0);
        $date     = $item['date'] ?? date('Y-m-d');
        if (!isset($slotMap[$slot_id]) || !isset($slotConfig['courts'][$court_id]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception("ข้อมูลรอบเวลาไม่ถูกต้อง");
        }
        $slot = $slotMap[$slot_id];
        if (!array_key_exists($date, $overrideCache)) $overrideCache[$date] = get_price_override($active_pdo, $date);
        $price = slot_price($base, $overrideCache[$date], $slot['type']);   // ราคาจากเซิร์ฟเวอร์
        $time  = $slot['start'] . ' - ' . $slot['end'];                     // เวลาจากเซิร์ฟเวอร์
        $prepared[] = ['court' => $court_id, 'slot' => $slot_id, 'date' => $date, 'time' => $time, 'price' => $price];
        $total += $price;
    }

    // เริ่มต้น Transaction
    $active_pdo->beginTransaction();

    // 1. บันทึก bookings (total_price คิดจากเซิร์ฟเวอร์ ไม่เชื่อค่าจากผู้ใช้)
    $stmt = $active_pdo->prepare("INSERT INTO bookings (booking_no, fullname, phone, user_type, total_price, slip_image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$phone, $fullname, $phone, $user_type, $total, $slip_filename]);
    $booking_id = $active_pdo->lastInsertId();

    // 2. เช็ครอบว่าง (กันจองซ้ำ) แล้วบันทึก booking_items
    $check = $active_pdo->prepare("
        SELECT COUNT(*) FROM booking_items bi
        JOIN bookings b ON bi.booking_id = b.id
        WHERE bi.court_id = ? AND bi.booking_date = ? AND bi.slot_id = ?
          AND b.status IN ('pending','approved') AND b.id <> ?
    ");
    $stmt_item = $active_pdo->prepare("INSERT INTO booking_items (booking_id, court_id, slot_id, booking_date, time_slot, price) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($prepared as $p) {
        $check->execute([$p['court'], $p['date'], $p['slot'], $booking_id]);
        if ($check->fetchColumn() > 0) {
            throw new Exception("รอบเวลา {$p['time']} (สนาม {$p['court']}, วันที่ {$p['date']}) ถูกจองไปแล้ว กรุณาเลือกรอบอื่น");
        }
        $stmt_item->execute([$booking_id, $p['court'], $p['slot'], $p['date'], $p['time'], $p['price']]);
    }

    // ยืนยัน Transaction
    $active_pdo->commit();

} catch (Exception $e) {
    if (isset($active_pdo) && $active_pdo instanceof PDO && $active_pdo->inTransaction()) {
        $active_pdo->rollBack();
    }
    error_log('booking failed: ' . $e->getMessage());
    // PDOException = ปัญหาระบบ ไม่โชว์รายละเอียด (กันข้อมูลรั่ว)
    // Exception ที่เราตั้งเอง (เช่น "รอบถูกจองแล้ว") = โชว์ให้ผู้ใช้เห็นได้
    $msg = ($e instanceof PDOException)
        ? "เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง"
        : $e->getMessage();
    die(err_box($msg));
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