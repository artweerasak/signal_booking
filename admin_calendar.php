<?php
require 'admin_auth.php';
require 'config/database.php';
$slotConfig = require 'config/slots.php';
date_default_timezone_set('Asia/Bangkok');

$court = intval($_GET['court'] ?? 1);
if (!isset($slotConfig['courts'][$court])) $court = array_key_first($slotConfig['courts']);

// วันเริ่มสัปดาห์ (จันทร์)
$week = $_GET['week'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
    $week = date('Y-m-d', strtotime('monday this week'));
}
$weekStart = strtotime($week);
$weekEnd   = strtotime('+6 day', $weekStart);
$days = [];
for ($i = 0; $i < 7; $i++) $days[] = date('Y-m-d', strtotime("+$i day", $weekStart));

$dowTh = [1=>'จ.',2=>'อ.',3=>'พ.',4=>'พฤ.',5=>'ศ.',6=>'ส.',7=>'อา.'];

// ดึงการจองของสนามนี้ในสัปดาห์
$stmt = $conn->prepare("
    SELECT bi.slot_id, bi.booking_date, b.id AS bid, b.fullname, b.status, b.booking_type, b.admin_note
    FROM booking_items bi JOIN bookings b ON bi.booking_id = b.id
    WHERE bi.court_id = ? AND bi.booking_date BETWEEN ? AND ?
      AND b.status IN ('pending','approved')
");
$stmt->execute([$court, $days[0], $days[6]]);
$map = []; // map[date][slot_id] = row
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $map[$r['booking_date']][(int)$r['slot_id']] = $r;
}

$today = date('Y-m-d');
$nowT  = date('H:i');
$prevWeek = date('Y-m-d', strtotime('-7 day', $weekStart));
$nextWeek = date('Y-m-d', strtotime('+7 day', $weekStart));

// นับสรุปว่างในสัปดาห์
$totalCells = count($days) * count($slotConfig['slots']);
$booked = 0; foreach ($map as $d=>$slots) $booked += count($slots);
$free = $totalCells - $booked;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปฏิทินสนาม</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0;font-family:'Prompt',sans-serif;}
        body{background:#F1F5F9;color:#1E293B;padding:16px;}
        .wrap{max-width:1100px;margin:0 auto;}
        .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;}
        .top h1{font-size:1.25rem;}
        .top a{color:#15803D;text-decoration:none;font-weight:600;font-size:0.9rem;}
        .bar{background:#FFF;border-radius:12px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:space-between;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .courts a,.nav a{text-decoration:none;padding:7px 14px;border-radius:8px;font-weight:600;font-size:0.88rem;border:1px solid #E2E8F0;color:#475569;background:#F8FAFC;}
        .courts a.active{background:#15803D;color:#FFF;border-color:#15803D;}
        .nav a{background:#FFF;} .nav .wk{font-weight:600;padding:0 8px;font-size:0.9rem;}
        .legend{display:flex;gap:14px;flex-wrap:wrap;font-size:0.8rem;color:#475569;margin-bottom:12px;align-items:center;}
        .legend .dot{display:inline-block;width:12px;height:12px;border-radius:3px;margin-right:5px;vertical-align:middle;}
        .scroll{overflow-x:auto;background:#FFF;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        table{border-collapse:collapse;width:100%;min-width:720px;}
        th,td{border:1px solid #EDF2F7;text-align:center;font-size:0.78rem;padding:0;}
        thead th{background:#F8FAFC;padding:8px 4px;font-weight:600;position:sticky;top:0;}
        thead th.today{background:#DCFCE7;color:#15803D;}
        td.time{background:#F8FAFC;font-weight:600;color:#334155;white-space:nowrap;padding:4px 8px;width:92px;}
        td.cell{height:40px;position:relative;}
        .c-free{background:#F0FDF4;color:#16A34A;} .c-free:hover{background:#DCFCE7;}
        .c-pending{background:#FEF3C7;color:#B45309;cursor:pointer;}
        .c-approved{background:#FEE2E2;color:#DC2626;cursor:pointer;}
        .c-internal{background:#E0E7FF;color:#4338CA;cursor:pointer;}
        .c-past{background:#F1F5F9;color:#CBD5E1;}
        td.cell a{display:block;width:100%;height:100%;line-height:40px;text-decoration:none;color:inherit;font-weight:600;}
        td.cell small{font-size:0.68rem;font-weight:600;}
        .summary{font-size:0.85rem;color:#64748B;margin:12px 2px;}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1>🗓️ ปฏิทินสนาม</h1>
        <a href="admin.php">← กลับแดชบอร์ด</a>
    </div>

    <div class="bar">
        <div class="courts">
            <?php foreach ($slotConfig['courts'] as $cid=>$cname): ?>
                <a href="?court=<?= $cid ?>&week=<?= $week ?>" class="<?= $court===$cid?'active':'' ?>"><?= htmlspecialchars($cname) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="nav">
            <a href="?court=<?= $court ?>&week=<?= $prevWeek ?>">← สัปดาห์ก่อน</a>
            <a href="?court=<?= $court ?>&week=<?= date('Y-m-d', strtotime('monday this week')) ?>">วันนี้</a>
            <span class="wk"><?= date('j M', $weekStart) ?> – <?= date('j M Y', $weekEnd) ?></span>
            <a href="?court=<?= $court ?>&week=<?= $nextWeek ?>">สัปดาห์ถัดไป →</a>
        </div>
    </div>

    <div class="legend">
        <span><span class="dot" style="background:#F0FDF4;border:1px solid #BBF7D0;"></span>ว่าง</span>
        <span><span class="dot" style="background:#FEF3C7;"></span>รอตรวจสอบ</span>
        <span><span class="dot" style="background:#FEE2E2;"></span>จองแล้ว(อนุมัติ)</span>
        <span><span class="dot" style="background:#E0E7FF;"></span>ล็อกภายใน</span>
        <span><span class="dot" style="background:#F1F5F9;"></span>เลยเวลา</span>
        <span style="margin-left:auto;color:#94A3B8;">คลิกช่องที่จองไว้เพื่อดู/แก้ไข</span>
    </div>

    <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>เวลา</th>
                    <?php foreach ($days as $d): $isToday = ($d===$today); ?>
                        <th class="<?= $isToday?'today':'' ?>"><?= $dowTh[(int)date('N', strtotime($d))] ?><br><?= date('j/n', strtotime($d)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slotConfig['slots'] as $s): ?>
                    <tr>
                        <td class="time"><?= $s['start'] ?>-<?= $s['end'] ?></td>
                        <?php foreach ($days as $d):
                            $row = $map[$d][$s['id']] ?? null;
                            $isPast = ($d < $today) || ($d === $today && $nowT >= $s['start']);
                            if ($row) {
                                $type = $row['booking_type'] === 'internal' ? 'internal' : $row['status']; // internal | pending | approved
                                $cls = 'c-' . $type;
                                $name = $row['booking_type']==='internal'
                                    ? ('🔒 ' . $row['fullname'])
                                    : $row['fullname'];
                                $tip = trim($name . ($row['admin_note'] ? ' — '.$row['admin_note'] : ''));
                        ?>
                            <td class="cell <?= $cls ?>" title="<?= htmlspecialchars($tip) ?>">
                                <a href="admin_edit_booking.php?id=<?= (int)$row['bid'] ?>"><small><?= htmlspecialchars(mb_strimwidth($name, 0, 12, '…')) ?></small></a>
                            </td>
                        <?php } elseif ($isPast) { ?>
                            <td class="cell c-past">·</td>
                        <?php } else { ?>
                            <td class="cell c-free" title="ว่าง">ว่าง</td>
                        <?php } ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="summary">
        สัปดาห์นี้ (<?= htmlspecialchars($slotConfig['courts'][$court]) ?>): ว่าง <b style="color:#16A34A;"><?= $free ?></b> รอบ · ไม่ว่าง <b style="color:#DC2626;"><?= $booked ?></b> รอบ จากทั้งหมด <?= $totalCells ?> รอบ
    </div>
</div>
</body>
</html>
