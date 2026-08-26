<?php
require 'admin_auth.php';
require 'config/database.php';
require 'config/pricing.php';

$msg = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { http_response_code(403); die('CSRF'); }
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'base') {
            $day   = max(0, intval($_POST['price_day'] ?? 0));
            $night = max(0, intval($_POST['price_night'] ?? 0));
            $up = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $up->execute(['base_price_day', $day]);
            $up->execute(['base_price_night', $night]);
            $ok = true; $msg = "บันทึกราคาพื้นฐานแล้ว (กลางวัน {$day}฿ / กลางคืน {$night}฿)";
        }
        elseif ($act === 'add_override') {
            $date  = $_POST['date'] ?? '';
            $day   = max(0, intval($_POST['price_day'] ?? 0));
            $night = max(0, intval($_POST['price_night'] ?? 0));
            $note  = trim($_POST['note'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $msg = 'วันที่ไม่ถูกต้อง';
            } else {
                $conn->prepare("INSERT INTO price_overrides (override_date, price_day, price_night, note) VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE price_day=VALUES(price_day), price_night=VALUES(price_night), note=VALUES(note)")
                     ->execute([$date, $day, $night, $note]);
                $ok = true; $msg = "ตั้งราคาพิเศษวันที่ {$date} แล้ว";
            }
        }
        elseif ($act === 'del_override') {
            $date = $_POST['date'] ?? '';
            $conn->prepare("DELETE FROM price_overrides WHERE override_date = ?")->execute([$date]);
            $ok = true; $msg = "ลบราคาพิเศษวันที่ {$date} แล้ว (กลับไปใช้ราคาพื้นฐาน)";
        }
    } catch (PDOException $e) {
        error_log('pricing failed: ' . $e->getMessage());
        $msg = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
    }
}

$base = get_base_prices($conn);
$overrides = $conn->query("SELECT * FROM price_overrides WHERE override_date >= CURDATE() ORDER BY override_date ASC")->fetchAll(PDO::FETCH_ASSOC);
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ปรับราคาค่าเช่าสนาม</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Prompt',sans-serif;}
    body{background:#F1F5F9;color:#1E293B;padding:20px;}
    .wrap{max-width:720px;margin:0 auto;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .top a{color:#15803D;text-decoration:none;font-weight:600;font-size:0.9rem;}
    .card{background:#FFF;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:16px;}
    .card h2{font-size:1.05rem;margin-bottom:6px;}
    .card p.h{color:#64748B;font-size:0.83rem;margin-bottom:14px;}
    label{display:block;font-weight:600;font-size:0.88rem;margin:10px 0 6px;}
    input[type=number],input[type=date],input[type=text]{width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.95rem;}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .grid4{display:grid;grid-template-columns:1.2fr 1fr 1fr 1.4fr;gap:10px;align-items:end;}
    button{margin-top:14px;padding:11px 18px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:0.95rem;color:#FFF;background:#15803D;}
    button.add{background:#4338CA;} button.del{background:#FEE2E2;color:#DC2626;margin:0;padding:6px 12px;font-size:0.8rem;}
    table{width:100%;border-collapse:collapse;margin-top:12px;font-size:0.88rem;}
    th,td{border:1px solid #EDF2F7;padding:8px;text-align:center;}
    th{background:#F8FAFC;font-weight:600;}
    .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.9rem;}
    .msg.ok{background:#DCFCE7;color:#15803D;} .msg.err{background:#FEE2E2;color:#DC2626;}
    .empty{color:#94A3B8;padding:16px;text-align:center;}
</style></head>
<body>
<div class="wrap">
    <div class="top"><h1 style="font-size:1.3rem;">💰 ปรับราคาค่าเช่าสนาม</h1><a href="admin.php">← กลับแดชบอร์ด</a></div>
    <?php if ($msg): ?><div class="msg <?= $ok?'ok':'err' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card">
        <h2>ราคาพื้นฐาน (ใช้กับทุกวันที่ไม่ได้ตั้งราคาพิเศษ)</h2>
        <p class="h">กลางวัน = รอบ 06:00-18:00 · กลางคืน = รอบ 18:00-21:00</p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="base">
            <div class="grid2">
                <div><label>ราคากลางวัน (บาท/ชม.)</label><input type="number" name="price_day" min="0" value="<?= $base['day'] ?>" required></div>
                <div><label>ราคากลางคืน (บาท/ชม.)</label><input type="number" name="price_night" min="0" value="<?= $base['night'] ?>" required></div>
            </div>
            <button type="submit">💾 บันทึกราคาพื้นฐาน</button>
        </form>
    </div>

    <div class="card">
        <h2>ปรับราคาเฉพาะวัน</h2>
        <p class="h">ตั้งราคาพิเศษสำหรับบางวัน (เช่น วันหยุด/อีเวนต์) — วันไหนไม่ได้ตั้ง จะใช้ราคาพื้นฐานด้านบนอัตโนมัติ</p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="add_override">
            <div class="grid4">
                <div><label>วันที่</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
                <div><label>กลางวัน</label><input type="number" name="price_day" min="0" value="<?= $base['day'] ?>" required></div>
                <div><label>กลางคืน</label><input type="number" name="price_night" min="0" value="<?= $base['night'] ?>" required></div>
                <div><label>หมายเหตุ</label><input type="text" name="note" placeholder="เช่น วันหยุดนักขัตฤกษ์"></div>
            </div>
            <button class="add" type="submit">➕ เพิ่ม/แก้ราคาวันนี้</button>
        </form>

        <table>
            <tr><th>วันที่</th><th>กลางวัน</th><th>กลางคืน</th><th>หมายเหตุ</th><th></th></tr>
            <?php if (empty($overrides)): ?>
                <tr><td colspan="5" class="empty">ยังไม่มีการตั้งราคาพิเศษ (ใช้ราคาพื้นฐานทุกวัน)</td></tr>
            <?php else: foreach ($overrides as $o): ?>
                <tr>
                    <td><?= htmlspecialchars($o['override_date']) ?></td>
                    <td><?= (int)$o['price_day'] ?>฿</td>
                    <td><?= (int)$o['price_night'] ?>฿</td>
                    <td><?= htmlspecialchars($o['note'] ?? '') ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('ลบราคาพิเศษวันนี้?');" style="margin:0;">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="act" value="del_override"><input type="hidden" name="date" value="<?= htmlspecialchars($o['override_date']) ?>">
                            <button class="del" type="submit">ลบ</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>
</body>
</html>
