<?php
require 'admin_auth.php';
require 'config/database.php';

// กรองตามสถานะ (ถ้าเลือก)
$filter = $_GET['status'] ?? 'all';
$valid  = ['all', 'pending', 'approved', 'cancelled'];
if (!in_array($filter, $valid, true)) {
    $filter = 'all';
}

try {
    if ($filter === 'all') {
        $stmt = $conn->query("SELECT * FROM bookings ORDER BY id DESC");
    } else {
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE status = ? ORDER BY id DESC");
        $stmt->execute([$filter]);
    }
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itemStmt = $conn->prepare("SELECT * FROM booking_items WHERE booking_id = ?");
} catch (PDOException $e) {
    error_log('admin dashboard failed: ' . $e->getMessage());
    die('เกิดข้อผิดพลาดในการโหลดข้อมูล');
}

function badge_class($s) {
    return ['pending' => 'b-pending', 'approved' => 'b-approved', 'cancelled' => 'b-cancelled'][$s] ?? '';
}
function badge_text($s) {
    return ['pending' => 'รอตรวจสอบ', 'approved' => 'อนุมัติแล้ว', 'cancelled' => 'ยกเลิกแล้ว'][$s] ?? htmlspecialchars($s);
}
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดแอดมิน - จองสนามเทนนิส</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Prompt', sans-serif; }
        body { background: #F1F5F9; color: #1E293B; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; max-width: 1100px; margin: 0 auto 20px; }
        .header h1 { font-size: 1.4rem; color: #0F172A; }
        .logout { color: #DC2626; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .container { max-width: 1100px; margin: 0 auto; }
        .filters { margin-bottom: 15px; display: flex; gap: 8px; flex-wrap: wrap; }
        .filters a { padding: 6px 14px; border-radius: 20px; text-decoration: none; font-size: 0.85rem; background: #FFF; color: #475569; border: 1px solid #E2E8F0; }
        .filters a.active { background: #15803D; color: #FFF; border-color: #15803D; }
        .card { background: #FFF; border-radius: 12px; padding: 18px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .row { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; }
        .info b { color: #0F172A; }
        .info span { color: #64748B; font-size: 0.9rem; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; white-space: nowrap; }
        .b-pending { background: #FEF3C7; color: #D97706; }
        .b-approved { background: #DCFCE7; color: #15803D; }
        .b-cancelled { background: #FEE2E2; color: #DC2626; }
        .items { margin-top: 10px; font-size: 0.85rem; color: #475569; background: #F8FAFC; padding: 10px; border-radius: 8px; }
        .actions { margin-top: 12px; display: flex; gap: 8px; align-items: center; }
        .actions button { border: none; padding: 7px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.85rem; }
        .btn-approve { background: #15803D; color: #FFF; }
        .btn-cancel { background: #FEE2E2; color: #DC2626; }
        .slip-link { color: #2563EB; text-decoration: none; font-size: 0.85rem; }
        .empty { text-align: center; color: #94A3B8; padding: 40px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎾 แดชบอร์ดจัดการการจอง</h1>
        <a href="admin_logout.php" class="logout">ออกจากระบบ ↪</a>
    </div>
    <div class="container">
        <div class="filters">
            <?php foreach (['all' => 'ทั้งหมด', 'pending' => 'รอตรวจสอบ', 'approved' => 'อนุมัติแล้ว', 'cancelled' => 'ยกเลิก'] as $k => $label): ?>
                <a href="?status=<?php echo $k; ?>" class="<?php echo $filter === $k ? 'active' : ''; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="card"><div class="empty">ไม่มีรายการจอง</div></div>
        <?php else: foreach ($bookings as $b): ?>
            <div class="card">
                <div class="row">
                    <div class="info">
                        <b><?php echo htmlspecialchars($b['fullname']); ?></b>
                        &nbsp;<span>📞 <?php echo htmlspecialchars($b['phone']); ?> · <?php echo htmlspecialchars($b['user_type']); ?></span><br>
                        <span>💰 <?php echo number_format($b['total_price'], 2); ?> บาท · 🕒 <?php echo htmlspecialchars($b['created_at']); ?></span>
                    </div>
                    <span class="badge <?php echo badge_class($b['status']); ?>"><?php echo badge_text($b['status']); ?></span>
                </div>

                <div class="items">
                    <?php
                        $itemStmt->execute([$b['id']]);
                        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $it) {
                            echo "• สนาม " . htmlspecialchars($it['court_id'])
                               . " | " . htmlspecialchars($it['booking_date'])
                               . " | " . htmlspecialchars($it['time_slot']) . " น.<br>";
                        }
                    ?>
                    <?php if (!empty($b['slip_image'])): ?>
                        <a class="slip-link" href="uploads/slips/<?php echo htmlspecialchars($b['slip_image']); ?>" target="_blank">📎 ดูสลิปโอนเงิน ↗</a>
                    <?php endif; ?>
                </div>

                <?php if ($b['status'] === 'pending'): ?>
                <div class="actions">
                    <form method="POST" action="admin_action.php" onsubmit="return confirm('อนุมัติการจองนี้?');">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="btn-approve" type="submit">✓ อนุมัติ</button>
                    </form>
                    <form method="POST" action="admin_action.php" onsubmit="return confirm('ยกเลิกการจองนี้?');">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button class="btn-cancel" type="submit">✕ ยกเลิก</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
</body>
</html>
