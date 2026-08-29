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

-- 10) ช่วงวันที่ "เปิดรับจองสาธารณะ"
--     ค่าเริ่มต้น: ไม่มีแถว = ปิดทั้งหมด (บุคคลทั่วไปจองไม่ได้จนกว่าแอดมินจะเปิดช่วงเอง)
--     แอดมินเพิ่มช่วงที่ admin_public_windows.php หลังล็อกสวัสดิการหน่วยเสร็จ
CREATE TABLE IF NOT EXISTS public_open_windows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_from DATE NOT NULL,
    date_to   DATE NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL
);

-- 11) รหัสชุดล็อก (lock_batch) — ให้ล็อกที่สร้างพร้อมกัน (โดยเฉพาะล็อกประจำหลายวัน)
--     มีรหัสเดียวกัน เพื่อให้แอดมิน "ยกเลิกทั้งชุด" ได้ในคลิกเดียว
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS lock_batch VARCHAR(40) NULL;
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_lock_batch (lock_batch);

-- 12) สไลด์โปรโมทหน้าแรก (รูป + หัวข้อ + แคปชั่น) — แอดมินแก้เองได้ที่ admin_promos.php
--     image = พาธไฟล์ที่อัปโหลด (uploads/promos/xxx.jpg) หรือ URL ก็ได้
CREATE TABLE IF NOT EXISTS promos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image VARCHAR(255) NOT NULL,
    heading VARCHAR(255) NOT NULL,
    caption VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

-- Seed สไลด์เดิม 4 อัน (ใส่ให้เฉพาะตอนตารางยังว่าง → รันซ้ำไม่ซ้ำข้อมูล)
INSERT INTO promos (image, heading, caption, sort_order, is_active, created_at)
SELECT * FROM (
    SELECT 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=1600&q=80' AS image,
           'ยกระดับเกมเทนนิสของคุณ บนสนามมาตรฐานสากล' AS heading,
           'ระบบจองสนามออนไลน์ที่สะดวกที่สุด เลือกจองล่วงหน้าได้หลายวันและเลือกรอบเวลาที่ต้องการได้ทันที' AS caption,
           1 AS sort_order, 1 AS is_active, NOW() AS created_at
    UNION ALL SELECT 'https://images.unsplash.com/photo-1530915534664-4ac6423ca938?auto=format&fit=crop&w=1600&q=80',
           'สัมผัสประสบการณ์เล่นยามค่ำคืน',
           'ระบบไฟส่องสว่าง LED คุณภาพสูง กระจายแสงสม่ำเสมอ คมชัดสบายตา เหมาะสำหรับการฝึกซ้อมและการแข่งขัน', 2, 1, NOW()
    UNION ALL SELECT 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?auto=format&fit=crop&w=1600&q=80',
           'สิ่งอำนวยความสะดวกครบครัน',
           'ลานจอดรถกว้างขวาง ปลอดภัยภายในพื้นที่กรมการทหารสื่อสาร พร้อมห้องน้ำ จุดพักนักกีฬา และการดูแลอย่างเป็นกันเอง', 3, 1, NOW()
    UNION ALL SELECT 'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=1600&q=80',
           'สิทธิพิเศษสำหรับกำลังพลและสมาชิก',
           'รับอัตราค่าบริการพิเศษในการซ้อมประจำ และกิจกรรมแข่งขันกระชับมิตร ติดต่อเจ้าหน้าที่เพื่อสอบถามข้อมูลเพิ่มเติม', 4, 1, NOW()
) seed
WHERE NOT EXISTS (SELECT 1 FROM promos);
