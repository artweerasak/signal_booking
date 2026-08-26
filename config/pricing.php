<?php
// ─────────────────────────────────────────────────────────────
//  ตัวคิดราคาค่าเช่าสนาม (ใช้ร่วมกัน get_slots + process_booking + admin)
//  หลัก: ถ้าวันนั้นมี "ปรับราคาเฉพาะวัน" → ใช้ราคานั้น, ถ้าไม่มี → ใช้ราคาพื้นฐาน
// ─────────────────────────────────────────────────────────────

// ราคาพื้นฐาน (กลางวัน/กลางคืน) จาก system_settings — มี fallback กันพัง
function get_base_prices(PDO $pdo) {
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings
                             WHERE setting_key IN ('base_price_day','base_price_night')")
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) { $rows = []; }
    return [
        'day'   => isset($rows['base_price_day'])   ? (int)$rows['base_price_day']   : 120,
        'night' => isset($rows['base_price_night']) ? (int)$rows['base_price_night'] : 150,
    ];
}

// ราคาที่ปรับเฉพาะวัน (คืน ['day'=>x,'night'=>y] หรือ null ถ้าไม่มี)
function get_price_override(PDO $pdo, $date) {
    try {
        $st = $pdo->prepare("SELECT price_day, price_night FROM price_overrides WHERE override_date = ?");
        $st->execute([$date]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $r = false; }
    return $r ? ['day' => (int)$r['price_day'], 'night' => (int)$r['price_night']] : null;
}

// ราคาสุดท้ายของรอบหนึ่ง ตามชนิด (day/night) — ใช้ override ถ้ามี ไม่งั้นใช้ base
function slot_price(array $base, $override, $slotType) {
    $src = $override ?: $base;
    return $slotType === 'night' ? (int)$src['night'] : (int)$src['day'];
}
