<?php
// API: คืนสถานะรอบเวลาของสนาม/วันที่ ที่ระบุ (ว่าง/ไม่ว่าง/หมดเวลา)
// ⚠️ ไม่คืน "ชื่อผู้จอง" ให้บุคคลภายนอก — บอกแค่ว่าจองได้หรือไม่ (รักษาความเป็นส่วนตัว)
header('Content-Type: application/json');
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/pricing.php';
$slotConfig = require __DIR__ . '/../config/slots.php';

$date     = $_GET['date'] ?? date('Y-m-d');
$court_id = intval($_GET['court_id'] ?? 1);

// ตรวจรูปแบบวันที่กันค่าแปลกปลอม
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$currentDate = date('Y-m-d');
$currentTime = date('H:i');

$baseSlots = $slotConfig['slots'];

try {
    // ดึงรอบที่ถูกจองแล้ว (ทั้ง public และ internal ที่ยัง active) ของสนาม+วันนี้
    $stmt = $conn->prepare("
        SELECT bi.slot_id
        FROM booking_items bi
        JOIN bookings b ON bi.booking_id = b.id
        WHERE bi.booking_date = ? AND bi.court_id = ? AND b.status IN ('pending','approved')
    ");
    $stmt->execute([$date, $court_id]);
    $bookedSlotIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    // ราคาของวันนี้ (ปรับเฉพาะวันถ้ามี ไม่งั้นใช้ราคาพื้นฐาน)
    $base = get_base_prices($conn);
    $override = get_price_override($conn, $date);

    $out = [];
    foreach ($baseSlots as $slot) {
        $slot['price'] = slot_price($base, $override, $slot['type']); // ราคาตามวัน
        $isBooked = in_array((int)$slot['id'], $bookedSlotIds, true);

        // รอบที่เวลาเริ่มผ่านไปแล้ว (เฉพาะวันนี้) หรือเลือกวันย้อนหลัง = ปิด
        $isPast = ($date < $currentDate)
            || ($date === $currentDate && $currentTime >= $slot['start']);

        if ($isBooked)      { $status = 'booked'; }
        elseif ($isPast)    { $status = 'past'; }
        else                { $status = 'available'; }

        $slot['disabled'] = $isBooked || $isPast;
        $slot['status']   = $status; // available | booked | past  (ไม่บอกว่าใครจอง)
        $out[] = $slot;
    }

    echo json_encode(['success' => true, 'slots' => $out]);
} catch (PDOException $e) {
    error_log('get_slots failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'ไม่สามารถโหลดสถานะรอบเวลาได้']);
}
