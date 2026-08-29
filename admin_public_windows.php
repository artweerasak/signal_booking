<?php
require 'admin_auth.php';
require 'config/database.php';
date_default_timezone_set('Asia/Bangkok');

$msg = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { http_response_code(403); die('CSRF'); }
    $do = $_POST['do'] ?? '';

    if ($do === 'add') {
        $from = $_POST['date_from'] ?? '';
        $to   = $_POST['date_to'] ?? '';
        $note = trim($_POST['note'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $msg = 'กรุณาเลือกวันเริ่มและวันสิ้นสุดให้ครบ';
        } elseif ($from > $to) {
            $msg = 'วันเริ่มต้องไม่อยู่หลังวันสิ้นสุด';
        } else {
            try {
                $conn->prepare("INSERT INTO public_open_windows (date_from, date_to, note, created_at) VALUES (?, ?, ?, NOW())")
                     ->execute([$from, $to, $note]);
                $ok = true; $msg = "เปิดรับจองสาธารณะช่วง {$from} ถึง {$to} เรียบร้อย";
            } catch (PDOException $e) { error_log('open_window add failed: '.$e->getMessage()); $msg = 'บันทึกไม่สำเร็จ'; }
        }
    } elseif ($do === 'del') {
        $wid = intval($_POST['id'] ?? 0);
        if ($wid > 0) {
            try {
                $conn->prepare("DELETE FROM public_open_windows WHERE id=?")->execute([$wid]);
                $ok = true; $msg = 'ปิด (ลบ) ช่วงที่เปิดไว้เรียบร้อย';
            } catch (PDOException $e) { error_log('open_window del failed: '.$e->getMessage()); $msg = 'ลบไม่สำเร็จ'; }
        }
    }
}

$windows = $conn->query("SELECT * FROM public_open_windows ORDER BY date_from")->fetchAll(PDO::FETCH_ASSOC);
$today = date('Y-m-d');
$firstOfMonth = date('Y-m-01');
$lastOfMonth  = date('Y-m-t');
$csrf = $_SESSION['csrf'];

function win_state($w, $today) {
    if ($today > $w['date_to'])   return ['ผ่านไปแล้ว', '#94A3B8', '#F1F5F9'];
    if ($today < $w['date_from'])  return ['กำลังจะเปิด', '#B45309', '#FEF3C7'];
    return ['เปิดอยู่ตอนนี้', '#15803D', '#DCFCE7'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เปิดรับจองสาธารณะ</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Prompt',sans-serif;}
    body{background:#F1F5F9;color:#1E293B;padding:20px;}
    .wrap{max-width:720px;margin:0 auto;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .top a{color:#15803D;text-decoration:none;font-weight:600;font-size:0.9rem;}
    .card{background:#FFF;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:16px;}
    p.hint{color:#64748B;font-size:0.85rem;margin-bottom:16px;line-height:1.6;}
    label.f{display:block;font-weight:600;font-size:0.9rem;margin:12px 0 6px;}
    input[type=text],input[type=date]{width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.95rem;}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    button{margin-top:16px;padding:11px 18px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:0.95rem;color:#FFF;background:#15803D;}
    .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.9rem;}
    .msg.ok{background:#DCFCE7;color:#15803D;} .msg.err{background:#FEE2E2;color:#DC2626;}
    table{width:100%;border-collapse:collapse;margin-top:6px;}
    th,td{text-align:left;padding:10px;border-bottom:1px solid #EDF2F7;font-size:0.9rem;}
    th{color:#64748B;font-weight:600;font-size:0.82rem;}
    .pill{padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;white-space:nowrap;}
    .del{background:#FEE2E2;color:#DC2626;border:none;padding:6px 12px;border-radius:8px;font-weight:600;cursor:pointer;font-size:0.82rem;margin:0;}
    .empty{color:#94A3B8;text-align:center;padding:26px;}
    .warn{background:#FEF3C7;color:#854D0E;border-radius:10px;padding:12px 14px;font-size:0.86rem;margin-bottom:16px;}
</style>    <link rel="stylesheet" href="assets/admin-responsive.css">
</head>
<body>
<div class="wrap">
    <div class="top"><h1 style="font-size:1.3rem;">📆 เปิดรับจองสาธารณะ</h1><a href="admin.php">← กลับแดชบอร์ด</a></div>

    <?php if ($msg): ?><div class="msg <?= $ok?'ok':'err' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php if (empty($windows)): ?>
        <div class="warn">⚠️ ตอนนี้ <b>ยังไม่มีช่วงที่เปิด</b> — บุคคลทั่วไปจะจองไม่ได้เลย จนกว่าจะเพิ่มช่วงด้านล่าง
            (แนะนำ: ล็อกสวัสดิการให้กำลังพลก่อนที่เมนู "ล็อกประจำ" แล้วค่อยมาเปิดช่วงให้ทั่วไป)</div>
    <?php endif; ?>

    <div class="card">
        <h2 style="font-size:1.05rem;margin-bottom:8px;">➕ เพิ่มช่วงที่เปิดให้จอง</h2>
        <p class="hint">เลือกช่วงวันที่ที่จะให้ "บุคคลทั่วไป" เข้ามาจองได้ เพิ่มได้หลายช่วง (เช่น เปิดทั้งเดือน หรือเว้นบางช่วงที่กันไว้ให้หน่วย) — วันที่อยู่นอกช่วงเหล่านี้ หน้าจองจะขึ้นว่า "ยังไม่เปิดรับจอง"</p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="do" value="add">
            <div class="grid2">
                <div><label class="f">เปิดตั้งแต่วันที่</label><input type="date" name="date_from" value="<?= $firstOfMonth ?>" required></div>
                <div><label class="f">ถึงวันที่</label><input type="date" name="date_to" value="<?= $lastOfMonth ?>" required></div>
            </div>
            <label class="f">หมายเหตุ (ไม่บังคับ — แอดมินเห็นเท่านั้น)</label>
            <input type="text" name="note" placeholder="เช่น เปิดจองทั่วไป เดือน ก.ย.">
            <button type="submit">เปิดช่วงนี้ให้จอง</button>
        </form>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem;margin-bottom:8px;">ช่วงที่เปิดอยู่ทั้งหมด</h2>
        <?php if (empty($windows)): ?>
            <div class="empty">ยังไม่มีช่วงที่เปิด</div>
        <?php else: ?>
            <div class="tbl-wrap">
            <table>
                <thead><tr><th>ช่วงวันที่</th><th>สถานะ</th><th>หมายเหตุ</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($windows as $w): [$stTxt,$stFg,$stBg] = win_state($w, $today); ?>
                    <tr>
                        <td><b><?= htmlspecialchars($w['date_from']) ?></b> → <b><?= htmlspecialchars($w['date_to']) ?></b></td>
                        <td><span class="pill" style="color:<?= $stFg ?>;background:<?= $stBg ?>;"><?= $stTxt ?></span></td>
                        <td style="color:#64748B;"><?= htmlspecialchars($w['note'] ?: '—') ?></td>
                        <td style="text-align:right;">
                            <form method="POST" onsubmit="return confirm('ปิด (ลบ) ช่วงนี้? บุคคลทั่วไปจะจองวันในช่วงนี้ไม่ได้อีก\n(การจองที่ทำไปแล้วยังอยู่เหมือนเดิม)');">
                                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="do" value="del">
                                <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                                <button class="del" type="submit">ปิด/ลบ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
