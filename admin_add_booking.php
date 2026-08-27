<?php
require 'admin_auth.php';
require 'config/database.php';
$slotConfig = require 'config/slots.php';

$msg = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { http_response_code(403); die('CSRF'); }

    $court_id = intval($_POST['court_id'] ?? 0);
    $date     = $_POST['date'] ?? '';
    $slotIds  = array_map('intval', $_POST['slots'] ?? []);
    $label    = trim($_POST['label'] ?? '');
    $note     = trim($_POST['note'] ?? '');

    $slotMap = [];
    foreach ($slotConfig['slots'] as $s) $slotMap[$s['id']] = $s;

    if (!isset($slotConfig['courts'][$court_id]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || empty($slotIds) || $label === '') {
        $msg = 'กรุณากรอกให้ครบ: สนาม, วันที่, เลือกอย่างน้อย 1 รอบ, และชื่อ/หมายเหตุการล็อก';
    } else {
        try {
            $conn->beginTransaction();
            // การจองภายใน = อนุมัติเลย, ราคา 0, ไม่นับรายได้
            $conn->prepare("INSERT INTO bookings (booking_no, fullname, phone, user_type, total_price, slip_image, status, booking_type, admin_note, created_at)
                            VALUES (?, ?, '-', 'Internal', 0, '', 'approved', 'internal', ?, NOW())")
                 ->execute(['LOCK' . date('ymd') . strtoupper(substr(uniqid(), -6)), $label, $note]);
            $bid = $conn->lastInsertId();

            $check = $conn->prepare("SELECT COUNT(*) FROM booking_items bi JOIN bookings b ON bi.booking_id=b.id
                                     WHERE bi.court_id=? AND bi.booking_date=? AND bi.slot_id=? AND b.status IN ('pending','approved')");
            $ins = $conn->prepare("INSERT INTO booking_items (booking_id, court_id, slot_id, booking_date, time_slot, price) VALUES (?, ?, ?, ?, ?, 0)");
            $added = 0;
            foreach ($slotIds as $sid) {
                if (!isset($slotMap[$sid])) continue;
                $check->execute([$court_id, $date, $sid]);
                if ($check->fetchColumn() > 0) continue; // ข้ามรอบที่ถูกจองแล้ว
                $timeText = $slotMap[$sid]['start'] . ' - ' . $slotMap[$sid]['end'];
                $ins->execute([$bid, $court_id, $sid, $date, $timeText]);
                $added++;
            }
            if ($added === 0) {
                $conn->rollBack();
                $msg = 'รอบที่เลือกถูกจองไปแล้วทั้งหมด';
            } else {
                $conn->commit();
                $ok = true; $msg = "ล็อกสำเร็จ {$added} รอบ (สนาม {$court_id}, {$date})";
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('add_booking failed: ' . $e->getMessage());
            $msg = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        }
    }
}
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เพิ่ม/ล็อกการจอง</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Prompt',sans-serif;}
    body{background:#F1F5F9;color:#1E293B;padding:20px;}
    .wrap{max-width:640px;margin:0 auto;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .top a{color:#15803D;text-decoration:none;font-weight:600;font-size:0.9rem;}
    .card{background:#FFF;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
    h1{font-size:1.2rem;margin-bottom:6px;}
    p.hint{color:#64748B;font-size:0.85rem;margin-bottom:18px;}
    label{display:block;font-weight:600;font-size:0.9rem;margin:14px 0 6px;}
    input[type=text],input[type=date],select{width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.95rem;}
    .slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-top:6px;}
    .slots label{display:flex;align-items:center;gap:6px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:8px;margin:0;font-weight:500;font-size:0.85rem;cursor:pointer;}
    button{margin-top:20px;width:100%;padding:12px;background:#15803D;color:#FFF;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem;}
    .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.9rem;}
    .msg.ok{background:#DCFCE7;color:#15803D;} .msg.err{background:#FEE2E2;color:#DC2626;}
</style></head>
<body>
<div class="wrap">
    <div class="top"><h1 style="font-size:1.3rem;">🔒 เพิ่ม/ล็อกการจอง (ภายใน)</h1><a href="admin.php">← กลับแดชบอร์ด</a></div>
    <div class="card">
        <p class="hint">ใช้ล็อกรอบให้กำลังพล/ผู้บังคับบัญชา — จะขึ้นเป็น "ไม่ว่าง" ต่อบุคคลภายนอกทันที โดยไม่เปิดเผยชื่อ (แอดมินเห็นชื่อ/โน้ตเท่านั้น)</p>
        <?php if ($msg): ?><div class="msg <?= $ok?'ok':'err' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <label>สนาม</label>
            <select name="court_id" required>
                <?php foreach ($slotConfig['courts'] as $cid=>$cname): ?>
                    <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
                <?php endforeach; ?>
            </select>
            <label>วันที่</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
            <label>เลือกรอบเวลา (ล็อกได้หลายรอบ)</label>
            <div class="slots">
                <?php foreach ($slotConfig['slots'] as $s): ?>
                    <label><input type="checkbox" name="slots[]" value="<?= $s['id'] ?>"> <?= $s['start'] ?>-<?= $s['end'] ?></label>
                <?php endforeach; ?>
            </div>
            <label>ชื่อ/ผู้ใช้ (แอดมินเห็นเท่านั้น)</label>
            <input type="text" name="label" placeholder="เช่น ผบ.กรม / ล็อกกำลังพล" required>
            <label>หมายเหตุ (ไม่บังคับ)</label>
            <input type="text" name="note" placeholder="เช่น ซ้อมประจำสัปดาห์">
            <button type="submit">ล็อกรอบเวลานี้</button>
        </form>
    </div>
</div>
</body>
</html>
