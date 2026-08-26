-- ═══════════════════════════════════════════════════════════════════
--  Migration — รันครั้งเดียวกับฐานข้อมูลจริง เพื่อแก้บั๊ก + รองรับระบบเต็ม
--  วิธีรัน:  mysql -u USER -p DBNAME < database_migration.sql
--  ⚠️ ถ้าคอลัมน์/ตารางไหนมีอยู่แล้ว MySQL จะฟ้อง error — ข้ามบรรทัดนั้นได้เลย
-- ═══════════════════════════════════════════════════════════════════

-- 1) เพิ่ม slot_id ใน booking_items  ← จำเป็น! แก้บั๊ก "จองซ้ำรอบเดิม"
ALTER TABLE booking_items ADD COLUMN slot_id INT NOT NULL DEFAULT 0;

-- 2) สถานะการจอง
ALTER TABLE bookings ADD COLUMN status ENUM('pending','approved','cancelled') NOT NULL DEFAULT 'pending';

-- 3) ประเภทการจอง: public = บุคคลภายนอกจองเอง, internal = แอดมินล็อกให้กำลังพล/ผบ.
ALTER TABLE bookings ADD COLUMN booking_type ENUM('public','internal') NOT NULL DEFAULT 'public';

-- 4) โน้ตของแอดมิน (เช่น "ผบ.กรม", "ล็อกกำลังพล") — เห็นเฉพาะแอดมิน ไม่โชว์ให้บุคคลภายนอก
ALTER TABLE bookings ADD COLUMN admin_note VARCHAR(255) NULL;

-- 5) ตารางแอดมิน
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL
);

-- 6) ตารางตั้งค่าระบบ (ถ้ายังไม่มี) — สำหรับโหมดฝนตก/ประกาศ
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
);
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
    ('rain_mode', '0'),
    ('rain_announcement', 'สนามปิดชั่วคราวเนื่องจากฝนตก');

-- 7) ดัชนีช่วยให้ค้นรอบว่างเร็วขึ้น
CREATE INDEX idx_slot_lookup ON booking_items (court_id, booking_date, slot_id);

-- 8) ตารางจำกัดอัตราการยิง (กันบอท spam ฟอร์มจอง/ล็อกอิน)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(30) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_rl (action, ip, created_at)
);
