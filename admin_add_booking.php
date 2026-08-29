<?php
require 'admin_auth.php';
require 'config/database.php';
require 'config/pricing.php';
$slotConfig = require 'config/slots.php';

$msg = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { http_response_code(403); die('CSRF'); }

    $court_id = intval($_POST['court_id'] ?? 0);
    $date     = $_POST['date'] ?? '';
    $slotIds  = array_map('intval', $_POST['slots'] ?? []);
    $label    = trim($_POST['label'] ?? '');
    $note     = trim($_POST['note'] ?? '');
    $collect  = ($_POST['collect'] ?? 'no') === 'yes' ? 'yes' : 'no';   // เก็บเงิน / ไม่เก็บเงิน
    $phone    = preg_replace('/\D/', '', $_POST['phone'] ?? '');          // เบอร์ (เฉพาะกรณีเก็บเงิน)
    $amountIn = trim($_POST['amount'] ?? '');                             // จำนวนเงินที่กรอกเอง (เว้นว่าง = คิดอัตโนมัติ)

    $slotMap = [];
    foreach ($slotConfig['slots'] as $s) $slotMap[$s['id']] = $s;

    if (!isset($slotConfig['courts'][$court_id]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || empty($slotIds) || $label === '') {
        $msg = 'กรุณากรอกให้ครบ: สนาม, วันที่, เลือกอย่างน้อย 1 รอบ, และชื่อ';
    } elseif ($collect === 'yes' && $amountIn !== '' && (!is_numeric($amountIn) || (float)$amountIn < 0)) {
        $msg = 'จำนวนเงินไม่ถูกต้อง (กรอกเป็นตัวเลข หรือเว้นว่างเพื่อคิดราคาปกติ)';
    } else {
        try {
            $conn->beginTransaction();

            if ($collect === 'yes') {
                // ── เก็บเงิน (walk-in) → นับเป็นรายได้ (booking_type=public) ──
                $bookType = 'public'; $userType = 'Walk-in'; $lockBatch = null;
                $ph = (strlen($phone) >= 9) ? $phone : '-';
            } else {
                // ── ไม่เก็บเงิน (ล็อกสวัสดิการ/ผบ.) → ราคา 0 ไม่นับรายได้ ──
                $bookType = 'internal'; $userType = 'Internal'; $ph = '-';
                $lockBatch = 'LB' . date('ymdHis') . strtoupper(substr(uniqid(), -4));
            }

            $conn->prepare("INSERT INTO bookings (booking_no, fullname, phone, user_type, total_price, slip_image, status, booking_type, admin_note, lock_batch, created_at)
                            VALUES (?, ?, ?, ?, 0, '', 'approved', ?, ?, ?, NOW())")
                 ->execute([
                     ($collect==='yes' ? 'WI' : 'LOCK') . date('ymd') . strtoupper(substr(uniqid(), -6)),
                     $label, $ph, $userType, $bookType, $note, $lockBatch,
                 ]);
            $bid = $conn->lastInsertId();

            // คิดราคาต่อรอบ (เฉพาะกรณีเก็บเงิน) จากตัวคิดราคากลาง — ใช้ override รายวันถ้ามี
            $base = get_base_prices($conn);
            $override = get_price_override($conn, $date);

            $check = $conn->prepare("SELECT COUNT(*) FROM booking_items bi JOIN bookings b ON bi.booking_id=b.id
                                     WHERE bi.court_id=? AND bi.booking_date=? AND bi.slot_id=? AND b.status IN ('pending','approved') AND b.id<>?");
            $ins = $conn->prepare("INSERT INTO booking_items (booking_id, court_id, slot_id, booking_date, time_slot, price) VALUES (?, ?, ?, ?, ?, ?)");

            $added = 0; $itemPrices = [];
            foreach ($slotIds as $sid) {
                if (!isset($slotMap[$sid])) continue;
                $check->execute([$court_id, $date, $sid, $bid]);
                if ($check->fetchColumn() > 0) continue; // ข้ามรอบที่ถูกจองแล้ว
                $timeText = $slotMap[$sid]['start'] . ' - ' . $slotMap[$sid]['end'];
                $price = ($collect === 'yes') ? slot_price($base, $override, $slotMap[$sid]['type']) : 0;
                $ins->execute([$bid, $court_id, $sid, $date, $timeText, $price]);
                $itemPrices[] = ['item_slot' => $sid, 'price' => $price];
                $added++;
            }

            if ($added === 0) {
                $conn->rollBack();
                $msg = 'รอบที่เลือกถูกจองไปแล้วทั้งหมด';
            } else {
                // ยอดรวม: ถ้าเก็บเงินและแอดมินกรอกจำนวนเอง → ใช้ยอดนั้น (กระจายลงรายการให้ผลรวมตรงกับรายงาน)
                if ($collect === 'yes') {
                    if ($amountIn !== '') {
                        $manual = (int) round((float)$amountIn);
                        $n = count($itemPrices);
                        $each = intdiv($manual, $n); $remainder = $manual - $each * $n;
                        $updItem = $conn->prepare("UPDATE booking_items SET price=? WHERE booking_id=? AND slot_id=?");
                        foreach ($itemPrices as $i => $ip) {
                            $p = $each + ($i === 0 ? $remainder : 0); // เศษไปรวมรายการแรก
                            $updItem->execute([$p, $bid, $ip['item_slot']]);
                        }
                        $total = $manual;
                    } else {
                        $total = array_sum(array_column($itemPrices, 'price'));
                    }
                } else {
                    $total = 0;
                }
                $conn->prepare("UPDATE bookings SET total_price=? WHERE id=?")->execute([$total, $bid]);

                $conn->commit();
                $ok = true;
                $msg = ($collect === 'yes')
                    ? "บันทึกการจอง (เก็บเงิน) สำเร็จ {$added} รอบ · ยอดรวม " . number_format($total) . " บาท"
                    : "ล็อกสำเร็จ {$added} รอบ (ไม่เก็บเงิน)";
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
    input[type=text],input[type=date],input[type=number],select{width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.95rem;}
    .slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-top:6px;}
    .slots label{display:flex;align-items:center;gap:6px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:8px;margin:0;font-weight:500;font-size:0.85rem;cursor:pointer;}
    button{margin-top:20px;width:100%;padding:12px;background:#15803D;color:#FFF;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:1rem;}
    .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.9rem;}
    .msg.ok{background:#DCFCE7;color:#15803D;} .msg.err{background:#FEE2E2;color:#DC2626;}
    /* ตัวเลือก เก็บเงิน / ไม่เก็บเงิน */
    .modes{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:6px;}
    .modes label{display:block;margin:0;border:2px solid #E2E8F0;border-radius:10px;padding:12px;cursor:pointer;text-align:center;background:#F8FAFC;transition:all .15s;font-weight:600;}
    .modes label .sub{display:block;font-weight:400;font-size:0.78rem;color:#64748B;margin-top:3px;}
    .modes input{display:none;}
    .modes input:checked + span{color:#15803D;}
    .modes label.sel{border-color:#15803D;background:#F0FDF4;}
    #payFields{display:none;}
</style>    <link rel="stylesheet" href="assets/admin-responsive.css">
</head>
<body>
<div class="wrap">
    <div class="top"><h1 style="font-size:1.3rem;">➕ เพิ่ม/ล็อกการจอง</h1><a href="admin.php">← กลับแดชบอร์ด</a></div>
    <div class="card">
        <p class="hint">ใช้จองแทนหน้าเคาน์เตอร์ (walk-in) หรือล็อกรอบให้กำลังพล/ผบ. — จะขึ้นเป็น "ไม่ว่าง" ต่อบุคคลภายนอกทันที โดยไม่เปิดเผยชื่อ</p>
        <?php if ($msg): ?><div class="msg <?= $ok?'ok':'err' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">

            <label>ประเภท</label>
            <div class="modes">
                <label id="m_no" class="sel">
                    <input type="radio" name="collect" value="no" checked onchange="onMode()">
                    <span>🔒 ไม่เก็บเงิน</span>
                    <span class="sub">ล็อกให้ฟรี / สวัสดิการหน่วย</span>
                </label>
                <label id="m_yes">
                    <input type="radio" name="collect" value="yes" onchange="onMode()">
                    <span>💰 เก็บเงิน</span>
                    <span class="sub">walk-in · นับเป็นรายได้</span>
                </label>
            </div>

            <label>สนาม</label>
            <select name="court_id" required>
                <?php foreach ($slotConfig['courts'] as $cid=>$cname): ?>
                    <option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option>
                <?php endforeach; ?>
            </select>
            <label>วันที่</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
            <label>เลือกรอบเวลา (เลือกได้หลายรอบ)</label>
            <div class="slots">
                <?php foreach ($slotConfig['slots'] as $s): ?>
                    <label><input type="checkbox" name="slots[]" value="<?= $s['id'] ?>"> <?= $s['start'] ?>-<?= $s['end'] ?></label>
                <?php endforeach; ?>
            </div>

            <label id="lbl_name">ชื่อ/ผู้ใช้ (แอดมินเห็นเท่านั้น)</label>
            <input type="text" name="label" placeholder="เช่น ผบ.กรม / ล็อกกำลังพล" required>

            <div id="payFields">
                <label>เบอร์โทรผู้จอง (ไม่บังคับ)</label>
                <input type="text" name="phone" placeholder="เช่น 0812345678">
                <label>จำนวนเงินที่เก็บ (บาท)</label>
                <input type="number" name="amount" min="0" step="1" placeholder="เว้นว่าง = คิดราคาปกติอัตโนมัติ">
                <p class="hint" style="margin:6px 0 0;">*เว้นว่างไว้ ระบบจะคิดตามราคามาตรฐานของแต่ละรอบให้เอง (รวมราคาพิเศษรายวันถ้ามี)</p>
            </div>

            <label>หมายเหตุ (ไม่บังคับ)</label>
            <input type="text" name="note" placeholder="เช่น ซ้อมประจำสัปดาห์ / รับเงินสดแล้ว">
            <button type="submit">บันทึก</button>
        </form>
    </div>
</div>
<script>
    function onMode() {
        var yes = document.querySelector('input[name=collect]:checked').value === 'yes';
        document.getElementById('payFields').style.display = yes ? 'block' : 'none';
        document.getElementById('m_yes').classList.toggle('sel', yes);
        document.getElementById('m_no').classList.toggle('sel', !yes);
        document.getElementById('lbl_name').textContent = yes ? 'ชื่อผู้จอง (แอดมินเห็นเท่านั้น)' : 'ชื่อ/ผู้ใช้ (แอดมินเห็นเท่านั้น)';
    }
    onMode();
</script>
</body>
</html>
