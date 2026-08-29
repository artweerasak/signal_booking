<?php
// ─────────────────────────────────────────────────────────────
//  ช่วงวันที่ "เปิดรับจองสาธารณะ" (public open windows)
//  ค่าเริ่มต้น: ไม่มีแถวในตาราง = ปิดทั้งหมด (บุคคลทั่วไปจองไม่ได้)
//  แอดมินเพิ่มช่วง from–to เอง (admin_public_windows.php) หลังล็อกสวัสดิการหน่วยเสร็จ
//  ใช้ร่วมกัน: api/get_slots.php, process_booking.php, index.php, admin_public_windows.php
// ─────────────────────────────────────────────────────────────

function get_open_windows(PDO $conn): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $cache = $conn->query(
            "SELECT id, date_from, date_to, note FROM public_open_windows ORDER BY date_from"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('get_open_windows failed: ' . $e->getMessage());
        $cache = [];
    }
    return $cache;
}

// วันที่ (รูปแบบ Y-m-d) นี้ อยู่ในช่วงที่เปิดรับจองสาธารณะไหม
function is_date_open(PDO $conn, string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    foreach (get_open_windows($conn) as $w) {
        if ($date >= $w['date_from'] && $date <= $w['date_to']) return true;
    }
    return false;
}
