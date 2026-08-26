<?php
// ─────────────────────────────────────────────────────────────
//  ฟังก์ชันกันบอท / จำกัดอัตราการยิง (ใช้ร่วมกันหลายหน้า)
//  ทำงานด้วย MySQL ล้วน — เหมาะกับ infinityfree (ไม่ต้องพึ่งบริการนอก)
// ─────────────────────────────────────────────────────────────

// IP ของผู้ใช้
function client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// สร้าง token เวลา (ฝังในฟอร์ม) — เซ็นด้วย APP_SECRET กันปลอม
function form_time_token() {
    $ts = time();
    return $ts . ':' . hash_hmac('sha256', (string)$ts, APP_SECRET);
}

// ตรวจ token เวลา: ต้องเซ็นถูก + ส่งช้ากว่า $minSeconds (บอทมักส่งทันที)
// และไม่เก่ากว่า $maxSeconds (ฟอร์มค้างนานเกินไป)
function check_form_time($token, $minSeconds = 3, $maxSeconds = 3600) {
    $parts = explode(':', (string)$token, 2);
    if (count($parts) !== 2) return false;
    [$ts, $sig] = $parts;
    if (!hash_equals(hash_hmac('sha256', $ts, APP_SECRET), $sig)) return false; // token ปลอม
    $elapsed = time() - (int)$ts;
    return $elapsed >= $minSeconds && $elapsed <= $maxSeconds;
}

// honeypot: ช่องซ่อนที่คนมองไม่เห็น ถ้าถูกกรอก = บอท
function is_honeypot_filled($field = 'website') {
    return isset($_POST[$field]) && $_POST[$field] !== '';
}

// rate limit: จำกัด $maxHits ครั้ง ต่อ IP ต่อช่วง $windowSeconds วินาที
// คืน true = ยังทำได้ (และบันทึกครั้งนี้), false = เกินโควตา
function rate_limit_ok(PDO $pdo, $action, $maxHits, $windowSeconds) {
    $ip = client_ip();
    try {
        // ล้าง record เก่าเป็นครั้งคราว (ไม่มี cron บน infinityfree)
        if (random_int(1, 20) === 1) {
            $pdo->prepare("DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL ? SECOND)")
                ->execute([$windowSeconds]);
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM rate_limits WHERE action = ? AND ip = ? AND created_at > (NOW() - INTERVAL ? SECOND)"
        );
        $stmt->execute([$action, $ip, $windowSeconds]);
        if ((int)$stmt->fetchColumn() >= $maxHits) {
            return false;
        }
        $pdo->prepare("INSERT INTO rate_limits (action, ip, created_at) VALUES (?, ?, NOW())")
            ->execute([$action, $ip]);
        return true;
    } catch (PDOException $e) {
        error_log('rate_limit error: ' . $e->getMessage());
        return true; // ถ้า DB มีปัญหา ไม่ล็อกผู้ใช้ทั่วไป (fail-open)
    }
}
