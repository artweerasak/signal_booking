-- ═══════════════════════════════════════════════════════════════════
--  Signal Booking — สคีมาฉบับเต็ม (สร้างทุกตารางตั้งแต่ศูนย์)
--  ใช้ตอนลง hosting จริงที่ฐานข้อมูลยังว่างเปล่า (แก้ error 1146)
--  ทุกอย่างเป็น IF NOT EXISTS → รันซ้ำได้ ไม่พังแม้บางตารางมีอยู่แล้ว
--
--  วิธีรัน: phpMyAdmin → เลือก DB → แท็บ SQL → วางทั้งไฟล์นี้ → Go
--  (รันไฟล์นี้ไฟล์เดียวพอ ไม่ต้องรัน database_migration.sql อีก)
-- ═══════════════════════════════════════════════════════════════════

-- ── ตารางฐาน 1: การจอง ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bookings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    booking_no   VARCHAR(40)  NOT NULL,                    -- รหัส BK/WI/LOCK ที่ลูกค้าใช้เช็ก
    fullname     VARCHAR(255) NOT NULL,
    phone        VARCHAR(30)  NOT NULL DEFAULT '-',
    user_type    VARCHAR(30)  NOT NULL DEFAULT 'Public',   -- Public / Internal / ฯลฯ
    total_price  INT          NOT NULL DEFAULT 0,
    slip_image   VARCHAR(255) NOT NULL DEFAULT '',          -- ไฟล์สลิปโอนเงิน (ถ้ามี)
    status       ENUM('pending','approved','cancelled') NOT NULL DEFAULT 'pending',
    booking_type ENUM('public','internal')              NOT NULL DEFAULT 'public',
    admin_note   VARCHAR(255) NULL,                         -- โน้ตแอดมิน (ไม่โชว์บุคคลภายนอก)
    lock_batch   VARCHAR(40)  NULL,                         -- รหัสชุดล็อก (ยกเลิกทั้งชุดได้)
    created_at   DATETIME     NOT NULL,
    INDEX idx_booking_no (booking_no),
    INDEX idx_phone (phone),
    INDEX idx_status (status),
    INDEX idx_lock_batch (lock_batch)
);

-- ── ตารางฐาน 2: รายการรอบที่จอง (1 booking มีได้หลายรอบ) ─────────
CREATE TABLE IF NOT EXISTS booking_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    booking_id   INT         NOT NULL,
    court_id     INT         NOT NULL,
    slot_id      INT         NOT NULL DEFAULT 0,            -- แก้บั๊ก "จองซ้ำรอบเดิม"
    booking_date DATE        NOT NULL,
    time_slot    VARCHAR(50) NOT NULL,
    price        INT         NOT NULL DEFAULT 0,
    INDEX idx_slot_lookup (court_id, booking_date, slot_id),
    INDEX idx_booking_id (booking_id)
);

-- ── แอดมิน ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL
);

-- ── ตั้งค่าระบบ (โหมดฝนตก/ประกาศ/ราคาพื้นฐาน) ────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
);
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
    ('rain_mode', '0'),
    ('rain_announcement', 'สนามปิดชั่วคราวเนื่องจากฝนตก'),
    ('base_price_day', '120'),
    ('base_price_night', '150');

-- ── จำกัดอัตราการยิง (กันบอท spam) ───────────────────────────────
CREATE TABLE IF NOT EXISTS rate_limits (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    action     VARCHAR(30) NOT NULL,
    ip         VARCHAR(45) NOT NULL,
    created_at DATETIME    NOT NULL,
    INDEX idx_rl (action, ip, created_at)
);

-- ── ราคาปรับเฉพาะวัน ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS price_overrides (
    override_date DATE PRIMARY KEY,
    price_day     INT NOT NULL,
    price_night   INT NOT NULL,
    note          VARCHAR(255) NULL
);

-- ── ช่วงวันที่เปิดรับจองสาธารณะ (ไม่มีแถว = ปิดทั้งหมด) ──────────
CREATE TABLE IF NOT EXISTS public_open_windows (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    date_from  DATE NOT NULL,
    date_to    DATE NOT NULL,
    note       VARCHAR(255) NULL,
    created_at DATETIME NOT NULL
);

-- ── สไลด์โปรโมทหน้าแรก ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS promos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    image      VARCHAR(255) NOT NULL,
    heading    VARCHAR(255) NOT NULL,
    caption    VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active  TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

-- Seed สไลด์เริ่มต้น 4 อัน (เฉพาะตอนตารางยังว่าง → รันซ้ำไม่ซ้ำข้อมูล)
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
