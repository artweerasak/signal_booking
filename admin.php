<?php
require 'admin_auth.php';
require 'config/database.php';

// ── สลับโหมดฝนตก (ปิดสนามทั้งเว็บ) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'rain') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { http_response_code(403); die('CSRF'); }
    $val = ($_POST['rain'] ?? '0') === '1' ? '1' : '0';
    $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('rain_mode', ?)
                    ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$val, $val]);
    header('Location: admin.php'); exit;
}

// ── สถิติสรุป ──
$stats = [
    'total'     => (int) $conn->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'pending'   => (int) $conn->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
    'approved'  => (int) $conn->query("SELECT COUNT(*) FROM bookings WHERE status='approved'")->fetchColumn(),
    'cancelled' => (int) $conn->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled'")->fetchColumn(),
];
// รายได้ = การจองสาธารณะที่อนุมัติแล้ว (การจองภายใน/ล็อกกำลังพล ไม่นับเป็นรายได้)
$stats['revenue_all']   = (float) $conn->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status='approved' AND booking_type='public'")->fetchColumn();
$stmtRev = $conn->prepare("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status='approved' AND booking_type='public' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$stmtRev->execute();
$stats['revenue_month'] = (float) $stmtRev->fetchColumn();

$rain = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='rain_mode'")->fetchColumn();
$rainOn = ($rain === '1');

// ── กรองรายการ ──
$filter = $_GET['status'] ?? 'all';
if (!in_array($filter, ['all','pending','approved','cancelled'], true)) $filter = 'all';
if ($filter === 'all') {
    $bookings = $conn->query("SELECT * FROM bookings ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $s = $conn->prepare("SELECT * FROM bookings WHERE status=? ORDER BY id DESC"); $s->execute([$filter]);
    $bookings = $s->fetchAll(PDO::FETCH_ASSOC);
}
$itemStmt = $conn->prepare("SELECT * FROM booking_items WHERE booking_id = ?");

function badge_class($s){ return ['pending'=>'b-pending','approved'=>'b-approved','cancelled'=>'b-cancelled'][$s] ?? ''; }
function badge_text($s){ return ['pending'=>'รอตรวจสอบ','approved'=>'อนุมัติแล้ว','cancelled'=>'ยกเลิกแล้ว'][$s] ?? htmlspecialchars($s); }
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดแอดมิน</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0;font-family:'Prompt',sans-serif;}
        body{background:#F1F5F9;color:#1E293B;padding:20px;}
        .wrap{max-width:1100px;margin:0 auto;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
        .header h1{font-size:1.3rem;color:#0F172A;}
        .nav a{margin-left:12px;text-decoration:none;font-size:0.9rem;font-weight:600;color:#15803D;}
        .nav a.logout{color:#DC2626;}
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:18px;}
        .stat{background:#FFF;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .stat .n{font-size:1.6rem;font-weight:700;color:#0F172A;}
        .stat .l{font-size:0.8rem;color:#64748B;}
        .stat.rev .n{color:#15803D;}
        .rainbar{background:<?= $rainOn ? '#FEE2E2' : '#FFF' ?>;border-radius:12px;padding:12px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .rainbar b{color:<?= $rainOn ? '#DC2626' : '#15803D' ?>;}
        .rainbar button{border:none;padding:7px 16px;border-radius:8px;font-weight:600;cursor:pointer;color:#FFF;background:<?= $rainOn ? '#15803D' : '#DC2626' ?>;}
        .filters{margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;}
        .filters a{padding:6px 14px;border-radius:20px;text-decoration:none;font-size:0.85rem;background:#FFF;color:#475569;border:1px solid #E2E8F0;}
        .filters a.active{background:#15803D;color:#FFF;border-color:#15803D;}
        .card{background:#FFF;border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .row{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;}
        .info b{color:#0F172A;} .info span{color:#64748B;font-size:0.9rem;}
        .badge{padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;white-space:nowrap;}
        .b-pending{background:#FEF3C7;color:#D97706;} .b-approved{background:#DCFCE7;color:#15803D;} .b-cancelled{background:#FEE2E2;color:#DC2626;}
        .type-internal{background:#E0E7FF;color:#4338CA;padding:2px 8px;border-radius:6px;font-size:0.72rem;font-weight:600;}
        .note{background:#FEF9C3;color:#854D0E;padding:2px 8px;border-radius:6px;font-size:0.78rem;margin-left:6px;}
        .items{margin-top:10px;font-size:0.85rem;color:#475569;background:#F8FAFC;padding:10px;border-radius:8px;}
        .actions{margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
        .actions button,.actions a{border:none;padding:7px 14px;border-radius:8px;font-weight:600;cursor:pointer;font-size:0.83rem;text-decoration:none;}
        .btn-approve{background:#15803D;color:#FFF;} .btn-cancel{background:#FEE2E2;color:#DC2626;} .btn-edit{background:#E2E8F0;color:#334155;}
        .slip-link{color:#2563EB;text-decoration:none;font-size:0.85rem;}
        .empty{text-align:center;color:#94A3B8;padding:40px;}
        form.inline{display:inline;}
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>🎾 แดชบอร์ดจัดการการจอง</h1>
        <div class="nav">
            <a href="admin_calendar.php">🗓️ ปฏิทินสนาม</a>
            <a href="admin_pricing.php">💰 ปรับราคา</a>
            <a href="admin_add_booking.php">➕ เพิ่ม/ล็อกการจอง</a>
            <a href="admin_bulk_lock.php">🔒 ล็อกประจำ</a>
            <a href="admin_logout.php" class="logout">ออกจากระบบ ↪</a>
        </div>
    </div>

    <div class="stats">
        <div class="stat rev"><div class="n"><?= number_format($stats['revenue_month'],0) ?>฿</div><div class="l">รายได้เดือนนี้</div></div>
        <div class="stat rev"><div class="n"><?= number_format($stats['revenue_all'],0) ?>฿</div><div class="l">รายได้รวม (อนุมัติแล้ว)</div></div>
        <div class="stat"><div class="n"><?= $stats['pending'] ?></div><div class="l">รอตรวจสอบ</div></div>
        <div class="stat"><div class="n"><?= $stats['approved'] ?></div><div class="l">อนุมัติแล้ว</div></div>
        <div class="stat"><div class="n"><?= $stats['total'] ?></div><div class="l">การจองทั้งหมด</div></div>
    </div>

    <div class="rainbar">
        <div>โหมดฝนตก (ปิดรับจองทั้งเว็บ): <b><?= $rainOn ? 'เปิดอยู่ 🌧️' : 'ปิดอยู่ ☀️' ?></b></div>
        <form method="POST" class="inline">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="do" value="rain">
            <input type="hidden" name="rain" value="<?= $rainOn ? '0' : '1' ?>">
            <button type="submit"><?= $rainOn ? 'ปิดโหมดฝน (เปิดสนาม)' : 'เปิดโหมดฝน (ปิดสนาม)' ?></button>
        </form>
    </div>

    <div class="filters">
        <?php foreach (['all'=>'ทั้งหมด','pending'=>'รอตรวจสอบ','approved'=>'อนุมัติแล้ว','cancelled'=>'ยกเลิก'] as $k=>$label): ?>
            <a href="?status=<?= $k ?>" class="<?= $filter===$k?'active':'' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="card"><div class="empty">ไม่มีรายการจอง</div></div>
    <?php else: foreach ($bookings as $b): ?>
        <div class="card">
            <div class="row">
                <div class="info">
                    <b><?= htmlspecialchars($b['fullname']) ?></b>
                    <?php if (($b['booking_type'] ?? 'public') === 'internal'): ?><span class="type-internal">ภายใน/ล็อก</span><?php endif; ?>
                    <?php if (!empty($b['admin_note'])): ?><span class="note">📝 <?= htmlspecialchars($b['admin_note']) ?></span><?php endif; ?>
                    <br><span>📞 <?= htmlspecialchars($b['phone']) ?> · <?= htmlspecialchars($b['user_type']) ?></span>
                    <br><span>💰 <?= number_format($b['total_price'],2) ?> บาท · 🕒 <?= htmlspecialchars($b['created_at']) ?></span>
                </div>
                <span class="badge <?= badge_class($b['status']) ?>"><?= badge_text($b['status']) ?></span>
            </div>
            <div class="items">
                <?php $itemStmt->execute([$b['id']]);
                    foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $it) {
                        echo "• สนาม ".htmlspecialchars($it['court_id'])." | ".htmlspecialchars($it['booking_date'])." | ".htmlspecialchars($it['time_slot'])." น.<br>";
                    } ?>
                <?php if (!empty($b['slip_image'])): ?>
                    <a class="slip-link" href="uploads/slips/<?= htmlspecialchars($b['slip_image']) ?>" target="_blank">📎 ดูสลิปโอนเงิน ↗</a>
                <?php endif; ?>
            </div>
            <div class="actions">
                <?php if ($b['status'] === 'pending'): ?>
                    <form class="inline" method="POST" action="admin_action.php" onsubmit="return confirm('อนุมัติการจองนี้?');">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="action" value="approve">
                        <button class="btn-approve" type="submit">✓ อนุมัติ</button>
                    </form>
                    <form class="inline" method="POST" action="admin_action.php" onsubmit="return confirm('ยกเลิกการจองนี้?');">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="action" value="cancel">
                        <button class="btn-cancel" type="submit">✕ ยกเลิก</button>
                    </form>
                <?php endif; ?>
                <a class="btn-edit" href="admin_edit_booking.php?id=<?= (int)$b['id'] ?>">✎ แก้ไข/เลื่อนวัน</a>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
</body>
</html>
