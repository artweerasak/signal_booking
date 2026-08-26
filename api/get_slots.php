<?php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Bangkok'); // กำหนดโซนเวลาไทย

require_once '../config/database.php';

$date = $_GET['date'] ?? date('Y-m-d');
$court_id = $_GET['court_id'] ?? 1;

$currentDate = date('Y-m-d');
$currentTime = date('H:i');

$baseSlots = [
    ['id' => 1, 'start' => '06:00', 'end' => '07:00', 'price' => 120, 'type' => 'day'],
    ['id' => 2, 'start' => '07:00', 'end' => '08:00', 'price' => 120, 'type' => 'day'],
    ['id' => 3, 'start' => '08:00', 'end' => '09:00', 'price' => 120, 'type' => 'day'],
    ['id' => 4, 'start' => '09:00', 'end' => '10:00', 'price' => 120, 'type' => 'day'],
    ['id' => 5, 'start' => '10:00', 'end' => '11:00', 'price' => 120, 'type' => 'day'],
    ['id' => 6, 'start' => '11:00', 'end' => '12:00', 'price' => 120, 'type' => 'day'],
    ['id' => 7, 'start' => '12:00', 'end' => '13:00', 'price' => 120, 'type' => 'day'],
    ['id' => 8, 'start' => '13:00', 'end' => '14:00', 'price' => 120, 'type' => 'day'],
    ['id' => 9, 'start' => '14:00', 'end' => '15:00', 'price' => 120, 'type' => 'day'],
    ['id' => 10, 'start' => '15:00', 'end' => '16:00', 'price' => 120, 'type' => 'day'],
    ['id' => 11, 'start' => '16:00', 'end' => '17:00', 'price' => 120, 'type' => 'day'],
    ['id' => 12, 'start' => '17:00', 'end' => '18:00', 'price' => 120, 'type' => 'day'],
    ['id' => 13, 'start' => '18:00', 'end' => '19:00', 'price' => 150, 'type' => 'night'],
    ['id' => 14, 'start' => '19:00', 'end' => '20:00', 'price' => 150, 'type' => 'night'],
    ['id' => 15, 'start' => '20:00', 'end' => '21:00', 'price' => 150, 'type' => 'night']
];

try {
    // ดึงสล็อตที่ถูกจองแล้วจาก DB
    $stmt = $conn->prepare("
        SELECT bi.slot_id 
        FROM booking_items bi
        JOIN bookings b ON bi.booking_id = b.id
        WHERE bi.booking_date = ? AND bi.court_id = ? AND b.status IN ('pending', 'approved')
    ");
    $stmt->execute([$date, $court_id]);
    $bookedSlotIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($baseSlots as &$slot) {
        $isBooked = in_array($slot['id'], $bookedSlotIds);
        
        // เช็คว่าถ้าเป็นวันที่ปัจจุบัน และเวลาเริ่มต้นรอบนั้นเหลื่อมหรือผ่านมาแล้ว ให้ปิดการจอง
        $isPast = false;
        if ($date === $currentDate && $currentTime >= $slot['start']) {
            $isPast = true;
        }

        // ปิดสล็อตถ้าจองแล้ว หรือ เป็นรอบที่ผ่านไปแล้ว
        $slot['disabled'] = $isBooked || $isPast;
        $slot['is_past'] = $isPast; // เพิ่มไว้เช็คสถานะ
    }

    echo json_encode(['success' => true, 'slots' => $baseSlots]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}