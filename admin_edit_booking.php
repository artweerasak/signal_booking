<?php
require 'admin_auth.php';
require 'config/database.php';
$slotConfig = require 'config/slots.php';

$id = intval($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) { header('Location: admin.php'); exit; }

$msg = ''; $ok = false;

// ── รับคำสั่ง POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { http_response_code(403); die('CSRF'); }
    $act = $_POST['act'] ?? '';

    if ($act === 'update') {
        $status   = in_array($_POST['status'] ?? '', ['pending','approved','cancelled'], true) ? $_POST['status'] : 'pending';
        $fullname = trim($_POST['fullname'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $note     = trim($_POST['note'] ?? '');
        try {
            $conn->prepare("UPDATE bookings SET status=?, fullname=?, phone=?, admin_note=? WHERE id=?")
                 ->execute([$status, $fullname, $phone, $note, $id]);
            $ok = true; $msg = 'บันทึกข้อมูลแล้ว';
        } catch (PDOException $e) { error_log($e->getMessage()); $msg = 'บันทึกไม่สำเร็จ'; }
    }
    elseif ($act === 'reschedule') {
        $new_date  = $_POST['new_date'] ?? '';
        $new_court = intval($_POST['new_court'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date) || !isset($slotConfig['courts'][$new_court])) {
            $msg = 'กรุณาเลือกวันที่และสนามใหม่ให้ถูกต้อง';
        } else {
            try {
                $conn->beginTransaction();
                // รอบทั้งหมดของการจองนี้
                $items = $conn->prepare("SELECT * FROM booking_items WHERE booking_id=?");
                $items->execute([$id]);
                $rows = $items->fetchAll(PDO::FETCH_ASSOC);

                // เช็คว่าวันใหม่/สนามใหม่ ทุกรอบยังว่าง (ไม่ชนของคนอื่น)
                $check = $conn->prepare("SELECT COUNT(*) FROM booking_items bi JOIN bookings b ON bi.booking_id=b.id
                                         WHERE bi.court_id=? AND bi.booking_date=? AND bi.slot_id=? AND b.status IN ('pending','approved') AND b.id<>?");
                $conflict = false;
                foreach ($rows as $r) {
                    $check->execute([$new_court, $new_date, $r['slot_id'], $id]);
                    if ($check->fetchColumn() > 0) { $conflict = true; break; }
                }
                if ($conflict) {
                    $conn->rollBack();
                    $msg = 'วัน/สนามใหม่มีรอบที่ถูกจองชนอยู่ กรุณาเลือกวันอื่น';
                } else {
                    $conn->prepare("UPDATE booking_items SET booking_date=?, court_id=? WHERE booking_id=?")
                         ->execute([$new_date, $new_court, $id]);
                    $conn->commit();
                    $ok = true; $msg = "เลื่อนการจองไปวันที่ {$new_date} (สนาม {$new_court}) เรียบร้อย";
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                error_log('reschedule failed: ' . $e->getMessage());
                $msg = 'เลื่อนวันไม่สำเร็จ';
            }
        }
    }
}

// ── โหลดข้อมูลปัจจุบัน ──
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id=?");
$stmt->execute([$id]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$b) { header('Location: admin.php'); exit; }

$itemStmt = $conn->prepare("SELECT * FROM booking_items WHERE booking_id=?");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>แก้ไขการจอง #<?= $id ?></title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Prompt',sans-serif;}
    body{background:#F1F5F9;color:#1E293B;padding:20px;}
    .wrap{max-width:600px;margin:0 auto;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .top a{color:#15803D;text-decoration:none;font-weight:600;font-size:0.9rem;}
    .card{background:#FFF;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:16px;}
    .card h2{font-size:1.05rem;margin-bottom:12px;}
    label{display:block;font-weight:600;font-size:0.88rem;margin:12px 0 6px;}
    input,select{width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.95rem;}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    button{margin-top:16px;width:100%;padding:11px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:0.95rem;color:#FFF;}
    .btn-save{background:#15803D;} .btn-move{background:#4338CA;}
    .items{background:#F8FAFC;border-radius:8px;padding:12px;font-size:0.88rem;color:#475569;}
    .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.9rem;}
    .msg.ok{background:#DCFCE7;color:#15803D;} .msg.err{background:#FEE2E2;color:#DC2626;}
</style></head>
<body>
<div class="wrap">
    <div class="top"><h1 style="font-size:1.25rem;">✎ แก้ไขการจอง #<?= $id ?></h1><a href="admin.php">← กลับแดชบอร์ด</a></div>
    <?php if ($msg): ?><div class="msg <?= $ok?'ok':'err' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card">
        <h2>รอบที่จองปัจจุบัน</h2>
        <div class="items">
            <?php foreach ($items as $it): ?>
                • สนาม <?= htmlspecialchars($it['court_id']) ?> | <?= htmlspecialchars($it['booking_date']) ?> | <?= htmlspecialchars($it['time_slot']) ?> น.<br>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2>ข้อมูล & สถานะ</h2>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="update">
            <div class="grid2">
                <div><label>ชื่อผู้จอง</label><input type="text" name="fullname" value="<?= htmlspecialchars($b['fullname']) ?>"></div>
                <div><label>เบอร์โทร</label><input type="text" name="phone" value="<?= htmlspecialchars($b['phone']) ?>"></div>
            </div>
            <label>สถานะ</label>
            <select name="status">
                <?php foreach (['pending'=>'รอตรวจสอบ','approved'=>'อนุมัติแล้ว','cancelled'=>'ยกเลิกแล้ว'] as $v=>$t): ?>
                    <option value="<?= $v ?>" <?= $b['status']===$v?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <label>หมายเหตุแอดมิน (ส่วนตัว)</label>
            <input type="text" name="note" value="<?= htmlspecialchars($b['admin_note'] ?? '') ?>" placeholder="เช่น เลื่อนเพราะฝนตก">
            <button class="btn-save" type="submit">💾 บันทึก</button>
        </form>
    </div>

    <div class="card">
        <h2>เลื่อนวัน/ย้ายสนาม (กรณีฝนตก — ไม่คืนเงิน หาวันใหม่ให้)</h2>
        <form method="POST" onsubmit="return confirm('ย้ายทุกรอบของการจองนี้ไปวัน/สนามใหม่?');">
            <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="reschedule">
            <div class="grid2">
                <div><label>วันใหม่</label><input type="date" name="new_date" value="<?= date('Y-m-d') ?>" required></div>
                <div><label>สนามใหม่</label>
                    <select name="new_court">
                        <?php foreach ($slotConfig['courts'] as $cid=>$cname): ?><option value="<?= $cid ?>"><?= htmlspecialchars($cname) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button class="btn-move" type="submit">📅 เลื่อนไปวันใหม่ (คงรอบเวลาเดิม)</button>
        </form>
    </div>
</div>
</body>
</html>
