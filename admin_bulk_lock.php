<?php
require 'admin_auth.php';
require 'config/database.php';
$slotConfig = require 'config/slots.php';

$msg = ''; $ok = false;
$dows = [1=>'จันทร์',2=>'อังคาร',3=>'พุธ',4=>'พฤหัส',5=>'ศุกร์',6=>'เสาร์',7=>'อาทิตย์'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { http_response_code(403); die('CSRF'); }

    $court_id = intval($_POST['court_id'] ?? 0);
    $from     = $_POST['date_from'] ?? '';
    $to       = $_POST['date_to'] ?? '';
    $days     = array_map('intval', $_POST['dows'] ?? []);
    $slotIds  = array_map('intval', $_POST['slots'] ?? []);
    $label    = trim($_POST['label'] ?? '');
    $note     = trim($_POST['note'] ?? '');

    $slotMap = [];
    foreach ($slotConfig['slots'] as $s) $slotMap[$s['id']] = $s;

    $valid = isset($slotConfig['courts'][$court_id])
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)
        && $from <= $to && !empty($days) && !empty($slotIds) && $label !== '';

    if (!$valid) {
        $msg = 'กรุณากรอกให้ครบ: สนาม, ช่วงวันที่, วันในสัปดาห์, รอบเวลา, และชื่อการล็อก';
    } elseif ((strtotime($to) - strtotime($from)) / 86400 > 92) {
        $msg = 'ช่วงวันที่ยาวเกินไป (จำกัดไม่เกิน ~3 เดือนต่อครั้ง)';
    } else {
        try {
            $conn->beginTransaction();
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM booking_items bi JOIN bookings b ON bi.booking_id=b.id
                                         WHERE bi.court_id=? AND bi.booking_date=? AND bi.slot_id=? AND b.status IN ('pending','approved')");
            $bookStmt = $conn->prepare("INSERT INTO bookings (booking_no, fullname, phone, user_type, total_price, slip_image, status, booking_type, admin_note, created_at)
                                        VALUES (?, ?, '-', 'Internal', 0, '', 'approved', 'internal', ?, NOW())");
            $itemStmt = $conn->prepare("INSERT INTO booking_items (booking_id, court_id, slot_id, booking_date, time_slot, price) VALUES (?, ?, ?, ?, ?, 0)");

            $totalSlots = 0; $daysCount = 0;
            $cur = strtotime($from); $end = strtotime($to);
            while ($cur <= $end) {
                $d = date('Y-m-d', $cur);
                $dow = (int) date('N', $cur);
                $cur = strtotime('+1 day', $cur);
                if (!in_array($dow, $days, true)) continue;

                // สร้าง booking ต่อวัน (เผื่ออยากยกเลิกเฉพาะวัน)
                $bookStmt->execute(['LOCK-'.date('Ymd',strtotime($d)).'-'.rand(100,999), $label, $note]);
                $bid = $conn->lastInsertId();
                $addedToday = 0;
                foreach ($slotIds as $sid) {
                    if (!isset($slotMap[$sid])) continue;
                    $checkStmt->execute([$court_id, $d, $sid]);
                    if ($checkStmt->fetchColumn() > 0) continue;
                    $timeText = $slotMap[$sid]['start'].' - '.$slotMap[$sid]['end'];
                    $itemStmt->execute([$bid, $court_id, $sid, $d, $timeText]);
                    $addedToday++; $totalSlots++;
                }
                if ($addedToday > 0) $daysCount++;
                else { $conn->prepare("DELETE FROM bookings WHERE id=?")->execute([$bid]); } // วันนั้นเต็ม ลบ booking เปล่า
            }
            $conn->commit();
            $ok = $totalSlots > 0;
            $msg = $ok ? "ล็อกสำเร็จ {$totalSlots} รอบ ใน {$daysCount} วัน" : 'รอบที่เลือกถูกจองไปแล้วทั้งหมด';
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('bulk_lock failed: ' . $e->getMessage());
            $msg = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        }
    }
}
$csrf = $_SESSION['csrf'];
$firstOfMonth = date('Y-m-01');
$lastOfMonth  = date('Y-m-t');
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ล็อกประจำ</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Prompt',sans-serif;}
    body{background:#F1F5F9;color:#1E293B;padding:20px;}
    .wrap{max-width:680px;margin:0 auto;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .top a{color:#15803D;text-decoration:none;font-weight:600;font-size:0.9rem;}
    .card{background:#FFF;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
    p.hint{color:#64748B;font-size:0.85rem;margin-bottom:18px;}
    label.f{display:block;font-weight:600;font-size:0.9rem;margin:14px 0 6px;}
    input[type=text],input[type=date],select{width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.95rem;}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
    .chips label{display:flex;align-items:center;gap:6px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:8px 10px;font-weight:500;font-size:0.85rem;cursor:pointer;}
    .slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(115px,1fr));gap:8px;margin-top:6px;}
    .slots label{display:flex;align-items:center;gap:6px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:8px;font-weight:500;font-size:0.83rem;cursor:pointer;}
    button{margin-top:20px;width:100%;padding:12px;background:#4338CA;color:#FFF;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem;}
    .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.9rem;}
    .msg.ok{background:#DCFCE7;color:#15803D;} .msg.err{background:#FEE2E2;color:#DC2626;}
</style></head>
<body>
<div class="wrap">
    <div class="top"><h1 style="font-size:1.3rem;">🔒 ล็อกประจำ (ทำซ้ำหลายวัน)</h1><a href="admin.php">← กลับแดชบอร์ด</a></div>
    <div class="card">
        <p class="hint">ใช้ล็อกรอบแบบประจำ เช่น "จันทร์-ศุกร์ 15:00-18:00 ทั้งเดือน สำหรับกำลังพล" — ระบบจะสร้างการจองภายในให้ทุกวันที่ตรงเงื่อนไข (ข้ามรอบที่มีคนจองแล้ว) ทำเดือนต่อเดือนได้</p>
        <?php if ($msg): ?><div class="msg <?= $ok?'ok':'err' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <label class="f">สนาม</label>
            <select name="court_id" required>
                <?php foreach ($slotConfig['courts'] as $cid=>$cname): ?><option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option><?php endforeach; ?>
            </select>
            <div class="grid2">
                <div><label class="f">ตั้งแต่วันที่</label><input type="date" name="date_from" value="<?= $firstOfMonth ?>" required></div>
                <div><label class="f">ถึงวันที่</label><input type="date" name="date_to" value="<?= $lastOfMonth ?>" required></div>
            </div>
            <label class="f">วันในสัปดาห์</label>
            <div class="chips">
                <?php foreach ($dows as $n=>$name): ?>
                    <label><input type="checkbox" name="dows[]" value="<?= $n ?>" <?= $n<=5?'checked':'' ?>> <?= $name ?></label>
                <?php endforeach; ?>
            </div>
            <label class="f">รอบเวลาที่จะล็อก</label>
            <div class="slots">
                <?php foreach ($slotConfig['slots'] as $s): ?>
                    <label><input type="checkbox" name="slots[]" value="<?= $s['id'] ?>"> <?= $s['start'] ?>-<?= $s['end'] ?></label>
                <?php endforeach; ?>
            </div>
            <label class="f">ชื่อการล็อก (แอดมินเห็นเท่านั้น)</label>
            <input type="text" name="label" placeholder="เช่น สงวนสิทธิ์กำลังพล" required>
            <label class="f">หมายเหตุ (ไม่บังคับ)</label>
            <input type="text" name="note" placeholder="เช่น นโยบายเดือน ส.ค.">
            <button type="submit">สร้างการล็อกประจำ</button>
        </form>
    </div>
</div>
</body>
</html>
