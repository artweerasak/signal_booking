-- ═══════════════════════════════════════════════════════════════════
--  Migration — รันกับฐานข้อมูลจริง (infinityfree = MariaDB)
--  ใส่ IF NOT EXISTS ไว้แล้ว → รันซ้ำได้ ไม่ error แม้บางอย่างมีอยู่แล้ว
--  วิธีรัน: phpMyAdmin → เลือก DB → แท็บ SQL → วางทั้งหมด → Go
-- ═══════════════════════════════════════════════════════════════════

-- 1) เพิ่ม slot_id ใน booking_items  ← จำเป็น! แก้บั๊ก "จองซ้ำรอบเดิม"
ALTER TABLE booking_items ADD COLUMN IF NOT EXISTS slot_id INT NOT NULL DEFAULT 0;

-- 2) สถานะการจอง
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','cancelled') NOT NULL DEFAULT 'pending';

-- 3) ประเภทการจอง: public = บุคคลภายนอกจองเอง, internal = แอดมินล็อกให้กำลังพล/ผบ.
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_type ENUM('public','internal') NOT NULL DEFAULT 'public';

-- 4) โน้ตของแอดมิน (เห็นเฉพาะแอดมิน ไม่โชว์ให้บุคคลภายนอก)
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS admin_note VARCHAR(255) NULL;

-- 5) ตารางแอดมิน
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL
);

-- 6) ตารางตั้งค่าระบบ (โหมดฝนตก/ประกาศ)
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
);
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
    ('rain_mode', '0'),
    ('rain_announcement', 'สนามปิดชั่วคราวเนื่องจากฝนตก');

-- 7) ดัชนีช่วยค้นรอบว่างเร็วขึ้น
ALTER TABLE booking_items ADD INDEX IF NOT EXISTS idx_slot_lookup (court_id, booking_date, slot_id);

-- 8) ตารางจำกัดอัตราการยิง (กันบอท spam)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(30) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_rl (action, ip, created_at)
);

-- 9) ราคาค่าเช่าสนาม
--    ราคาพื้นฐาน (กลางวัน/กลางคืน) เก็บใน system_settings
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
    ('base_price_day', '120'),
    ('base_price_night', '150');

--    ตารางปรับราคาเฉพาะวัน — ถ้าวันไหนมีในตารางนี้จะใช้ราคานี้ ถ้าไม่มีใช้ราคาพื้นฐาน
CREATE TABLE IF NOT EXISTS price_overrides (
    override_date DATE PRIMARY KEY,
    price_day INT NOT NULL,
    price_night INT NOT NULL,
    note VARCHAR(255) NULL
);
