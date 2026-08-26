-- ═══════════════════════════════════════════════════════════════════
--  Migration — รันครั้งเดียวกับฐานข้อมูลจริง เพื่อแก้บั๊ก + รองรับระบบแอดมิน
--  วิธีรัน:  mysql -u USER -p DBNAME < database_migration.sql
--  ⚠️ ถ้าคอลัมน์/ตารางไหนมีอยู่แล้ว MySQL จะฟ้อง error — ข้ามบรรทัดนั้นได้เลย
-- ═══════════════════════════════════════════════════════════════════

-- 1) เพิ่ม slot_id ใน booking_items  ← จำเป็น! แก้บั๊ก "จองซ้ำรอบเดิม"
--    (api/get_slots.php ใช้คอลัมน์นี้ในการเช็ครอบที่ถูกจองแล้ว)
ALTER TABLE booking_items ADD COLUMN slot_id INT NOT NULL DEFAULT 0;

-- 2) เพิ่มสถานะการจองในตาราง bookings (ถ้ายังไม่มีคอลัมน์นี้)
ALTER TABLE bookings ADD COLUMN status ENUM('pending','approved','cancelled') NOT NULL DEFAULT 'pending';

-- 3) ตารางแอดมิน (สำหรับ admin_login.php / admin.php)
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL
);
