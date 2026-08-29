<?php
require 'admin_auth.php';
require 'config/database.php';

// รายได้คิดจาก "การจองสาธารณะที่อนุมัติแล้ว" ตามวันที่ใช้สนาม (booking_date)
// การจองภายใน (ล็อกกำลังพล/ผบ.) ราคา 0 ไม่นับเป็นรายได้

$view  = in_array($_GET['view'] ?? '', ['daily','monthly'], true) ? $_GET['view'] : 'monthly';
$year  = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));
if ($month < 1 || $month > 12) $month = date('n');

$thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

// ปีที่มีข้อมูล (ไว้ทำ dropdown)
$years = $conn->query("SELECT DISTINCT YEAR(bi.booking_date) y FROM booking_items bi
                       JOIN bookings b ON bi.booking_id=b.id
                       WHERE b.status='approved' AND b.booking_type='public' ORDER BY y DESC")
              ->fetchAll(PDO::FETCH_COLUMN);
if (!in_array((int)date('Y'), array_map('intval',$years), true)) array_unshift($years, (int)date('Y'));
if (empty($years)) $years = [(int)date('Y')];

// ── ดึงข้อมูลตามมุมมอง ──
$rows = []; $total_rev = 0; $total_cnt = 0;
if ($view === 'daily') {
    $st = $conn->prepare("SELECT bi.booking_date d, COUNT(DISTINCT b.id) cnt, COALESCE(SUM(bi.price),0) rev
        FROM booking_items bi JOIN bookings b ON bi.booking_id=b.id
        WHERE b.status='approved' AND b.booking_type='public'
          AND YEAR(bi.booking_date)=? AND MONTH(bi.booking_date)=?
        GROUP BY bi.booking_date ORDER BY bi.booking_date ASC");
    $st->execute([$year, $month]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = ['label'=>date('j', strtotime($r['d'])).' '.$thMonths[$month], 'raw'=>$r['d'], 'cnt'=>(int)$r['cnt'], 'rev'=>(float)$r['rev']];
        $total_rev += $r['rev']; $total_cnt += $r['cnt'];
    }
} else {
    $st = $conn->prepare("SELECT MONTH(bi.booking_date) m, COUNT(DISTINCT b.id) cnt, COALESCE(SUM(bi.price),0) rev
        FROM booking_items bi JOIN bookings b ON bi.booking_id=b.id
        WHERE b.status='approved' AND b.booking_type='public' AND YEAR(bi.booking_date)=?
        GROUP BY MONTH(bi.booking_date) ORDER BY m ASC");
    $st->execute([$year]);
    $byMonth = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $byMonth[(int)$r['m']] = $r;
    $prev = null;
    for ($m = 1; $m <= 12; $m++) {
        $rev = isset($byMonth[$m]) ? (float)$byMonth[$m]['rev'] : 0;
        $cnt = isset($byMonth[$m]) ? (int)$byMonth[$m]['cnt'] : 0;
        $change = ($prev !== null && $prev > 0) ? (($rev - $prev) / $prev * 100) : null;
        $rows[] = ['label'=>$thMonths[$m], 'raw'=>sprintf('%04d-%02d', $year, $m), 'cnt'=>$cnt, 'rev'=>$rev, 'change'=>$change];
        $total_rev += $rev; $total_cnt += $cnt;
        $prev = $rev;
    }
}

// ── Export CSV (สำหรับตรวจสอบ) ──
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revenue_'.$view.'_'.$year.($view==='daily'?('-'.$month):'').'.csv"');
    echo "\xEF\xBB\xBF"; // BOM ให้ Excel อ่านไทยได้
    $out = fopen('php://output', 'w');
    fputcsv($out, [$view==='daily' ? 'วันที่' : 'เดือน', 'จำนวนการจอง', 'รายได้ (บาท)']);
    foreach ($rows as $r) fputcsv($out, [$r['raw'], $r['cnt'], $r['rev']]);
    fputcsv($out, ['รวม', $total_cnt, $total_rev]);
    fclose($out);
    exit;
}

$maxRev = 0; foreach ($rows as $r) $maxRev = max($maxRev, $r['rev']);
$csvLink = '?'.http_build_query(['view'=>$view,'year'=>$year,'month'=>$month,'export'=>'csv']);
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รายงานรายได้</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Prompt',sans-serif;}
    body{background:#F1F5F9;color:#1E293B;padding:20px;}
    .wrap{max-width:900px;margin:0 auto;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;}
    .top a{color:#15803D;text-decoration:none;font-weight:600;font-size:0.9rem;}
    .tabs{display:flex;gap:8px;margin-bottom:14px;}
    .tabs a{padding:8px 18px;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.9rem;background:#FFF;color:#475569;border:1px solid #E2E8F0;}
    .tabs a.active{background:#15803D;color:#FFF;border-color:#15803D;}
    .bar{background:#FFF;border-radius:12px;padding:14px 16px;margin-bottom:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;box-shadow:0 1px 3px rgba(0,0,0,.06);}
    .bar select{padding:8px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.9rem;}
    .bar .csv{margin-left:auto;background:#166534;color:#FFF;text-decoration:none;padding:8px 14px;border-radius:8px;font-weight:600;font-size:0.85rem;}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;}
    .stat{background:#FFF;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
    .stat .n{font-size:1.6rem;font-weight:700;color:#15803D;} .stat .l{font-size:0.8rem;color:#64748B;}
    .stat.b .n{color:#0F172A;}
    .card{background:#FFF;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
    table{width:100%;border-collapse:collapse;font-size:0.9rem;}
    th,td{border-bottom:1px solid #EDF2F7;padding:9px 8px;text-align:right;}
    th{color:#64748B;font-weight:600;text-align:right;font-size:0.82rem;}
    th:first-child,td:first-child{text-align:left;}
    td.rev{font-weight:600;color:#15803D;}
    .barcell{background:#F1F5F9;border-radius:4px;height:8px;overflow:hidden;margin-top:4px;}
    .barcell > div{height:100%;background:#22C55E;}
    .up{color:#16A34A;font-size:0.82rem;} .down{color:#DC2626;font-size:0.82rem;} .flat{color:#94A3B8;font-size:0.82rem;}
    tfoot td{font-weight:700;border-top:2px solid #E2E8F0;color:#0F172A;}
    .empty{text-align:center;color:#94A3B8;padding:30px;}
</style>    <link rel="stylesheet" href="assets/admin-responsive.css">
</head>
<body>
<div class="wrap">
    <div class="top"><h1 style="font-size:1.3rem;">📊 รายงานรายได้</h1><a href="admin.php">← กลับแดชบอร์ด</a></div>

    <div class="tabs">
        <a href="?view=monthly&year=<?= $year ?>" class="<?= $view==='monthly'?'active':'' ?>">รายเดือน (ทั้งปี)</a>
        <a href="?view=daily&year=<?= $year ?>&month=<?= $month ?>" class="<?= $view==='daily'?'active':'' ?>">รายวัน (ในเดือน)</a>
    </div>

    <form method="GET" class="bar">
        <input type="hidden" name="view" value="<?= $view ?>">
        <label>ปี:</label>
        <select name="year" onchange="this.form.submit()">
            <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= (int)$y===$year?'selected':'' ?>><?= $y ?></option><?php endforeach; ?>
        </select>
        <?php if ($view === 'daily'): ?>
            <label>เดือน:</label>
            <select name="month" onchange="this.form.submit()">
                <?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= $thMonths[$m] ?></option><?php endfor; ?>
            </select>
        <?php endif; ?>
        <a class="csv" href="<?= htmlspecialchars($csvLink) ?>">⬇ ดาวน์โหลด CSV</a>
    </form>

    <div class="cards">
        <div class="stat"><div class="n"><?= number_format($total_rev,0) ?>฿</div><div class="l">รายได้รวม <?= $view==='daily' ? ($thMonths[$month].' '.$year) : ('ปี '.$year) ?></div></div>
        <div class="stat b"><div class="n"><?= number_format($total_cnt) ?></div><div class="l">จำนวนการจอง</div></div>
        <div class="stat b"><div class="n"><?= $total_cnt>0 ? number_format($total_rev/$total_cnt,0) : 0 ?>฿</div><div class="l">เฉลี่ยต่อการจอง</div></div>
    </div>

    <div class="card">
        <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= $view==='daily' ? 'วันที่' : 'เดือน' ?></th>
                    <th>จำนวนจอง</th>
                    <th>รายได้</th>
                    <?php if ($view==='monthly'): ?><th>เทียบเดือนก่อน</th><?php endif; ?>
                    <th style="width:28%;">สัดส่วน</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hasData = false;
                foreach ($rows as $r):
                    if ($view==='daily' && $r['rev']==0 && $r['cnt']==0) continue; // รายวัน: ข้ามวันที่ไม่มีรายได้
                    $hasData = true;
                    $w = $maxRev>0 ? round($r['rev']/$maxRev*100) : 0;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($r['label']) ?></td>
                        <td><?= $r['cnt'] ?></td>
                        <td class="rev"><?= number_format($r['rev'],0) ?>฿</td>
                        <?php if ($view==='monthly'): ?>
                            <td>
                                <?php if ($r['change']===null): ?><span class="flat">—</span>
                                <?php elseif ($r['change']>0): ?><span class="up">▲ <?= number_format($r['change'],1) ?>%</span>
                                <?php elseif ($r['change']<0): ?><span class="down">▼ <?= number_format(abs($r['change']),1) ?>%</span>
                                <?php else: ?><span class="flat">0%</span><?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td><div class="barcell"><div style="width:<?= $w ?>%;"></div></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$hasData): ?>
                    <tr><td colspan="<?= $view==='monthly'?5:4 ?>" class="empty">ยังไม่มีข้อมูลรายได้ในช่วงนี้</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($hasData): ?>
            <tfoot>
                <tr>
                    <td>รวม</td>
                    <td><?= number_format($total_cnt) ?></td>
                    <td class="rev"><?= number_format($total_rev,0) ?>฿</td>
                    <?php if ($view==='monthly'): ?><td></td><?php endif; ?>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
        </div>
    </div>
    <p style="font-size:0.8rem;color:#94A3B8;margin-top:10px;">* รายได้ = การจองสาธารณะที่ "อนุมัติแล้ว" นับตามวันที่ใช้สนาม · การล็อกภายในไม่นับเป็นรายได้</p>
</div>
</body>
</html>
