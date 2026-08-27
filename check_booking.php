<?php
// ไม่แสดง error ให้ผู้ใช้ (กันข้อมูลรั่ว) — เก็บลง log แทน
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} else {
    die("ไม่พบไฟล์ config/database.php");
}

$active_pdo = null;
if (isset($pdo) && $pdo instanceof PDO) {
    $active_pdo = $pdo;
} elseif (isset($conn) && $conn instanceof PDO) {
    $active_pdo = $conn;
}

// ค้นได้ทั้งเบอร์โทร และรหัสจอง (BK...) — ใส่อะไรมาก็หาให้
$phone_search = trim($_GET['phone'] ?? '');
$bookings = [];
$searched = false;

if (!empty($phone_search)) {
    $searched = true;
    $stmt = $active_pdo->prepare("SELECT * FROM bookings WHERE phone = ? OR booking_no = ? ORDER BY id DESC");
    $stmt->execute([$phone_search, $phone_search]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบสถานะการจอง - สนามเทนนิส กรมการทหารสื่อสาร</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Prompt', sans-serif; }
        body { background: #FAFAFC; color: #1E293B; padding: 20px; display: flex; justify-content: center; min-height: 100vh; }
        .container { max-width: 600px; width: 100%; }
        .card { background: #FFFFFF; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #F1F5F9; margin-bottom: 20px; }
        h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 10px; color: #0F172A; text-align: center; }
        p.subtitle { text-align: center; color: #64748B; font-size: 0.9rem; margin-bottom: 25px; }
        .form-group { display: flex; gap: 10px; margin-bottom: 20px; }
        input[type="text"] { flex: 1; padding: 12px 16px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 1rem; outline: none; transition: border-color 0.2s; }
        input[type="text"]:focus { border-color: #15803D; }
        button { background: #15803D; color: #FFF; border: none; padding: 0 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #166534; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #64748B; text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { color: #15803D; }
        
        .booking-item { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-bottom: 15px; }
        .booking-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #E2E8F0; padding-bottom: 8px; }
        .badge { background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge.success { background: #DCFCE7; color: #15803D; }
        .detail-row { display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 6px; color: #475569; }
        .detail-row span:last-child { font-weight: 600; color: #1E293B; }
        .no-result { text-align: center; color: #64748B; padding: 20px; }
        
        .item-list { margin-top: 10px; background: #FFF; padding: 10px; border-radius: 8px; border: 1px solid #EDF2F7; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h2>🔍 ตรวจสอบสถานะการจองสนาม</h2>
            <p class="subtitle">กรอกเบอร์โทรศัพท์ หรือ รหัสจอง (BK...) เพื่อดูประวัติและสถานะ</p>

            <form method="GET" action="">
                <div class="form-group">
                    <input type="text" name="phone" placeholder="เบอร์โทร หรือ รหัสจอง เช่น BK260827ABC123" value="<?php echo htmlspecialchars($phone_search); ?>" required>
                    <button type="submit">ค้นหา</button>
                </div>
            </form>
            <a href="index.php" class="back-link">❮ กลับสู่หน้าหลักจองสนาม</a>
        </div>

        <?php if ($searched): ?>
            <div class="card">
                <h3 style="font-size: 1.1rem; margin-bottom: 15px; font-weight: 600;">ผลการค้นหาสำหรับ: <?php echo htmlspecialchars($phone_search); ?></h3>
                
                <?php if (count($bookings) > 0): ?>
                    <?php foreach ($bookings as $b): ?>
                        <div class="booking-item">
                            <div class="booking-header">
                                <span style="font-weight: 600; color: #1E293B;">ผู้จอง: <?php echo htmlspecialchars($b['fullname']); ?></span>
                                <span class="badge <?php echo ($b['status'] == 'approved' ? 'success' : ''); ?>">
                                    <?php
                                        if($b['status'] == 'pending') echo 'รอดำเนินการ / รอตรวจสอบ';
                                        elseif($b['status'] == 'approved') echo 'อนุมัติแล้ว';
                                        elseif($b['status'] == 'cancelled') echo 'ยกเลิกแล้ว';
                                        else echo htmlspecialchars($b['status']);
                                    ?>
                                </span>
                            </div>
                            <div class="detail-row">
                               <span>รหัสจอง:</span>
                               <span style="color:#15803D; letter-spacing:0.5px;"><?php echo htmlspecialchars($b['booking_no']); ?></span>
                            </div>
                            <div class="detail-row">
                               <span>ประเภทผู้ใช้งาน:</span>
                               <span><?php echo htmlspecialchars($b['user_type']); ?></span>
                            </div>
                            <div class="detail-row">
                               <span>ราคารวมทั้งสิ้น:</span>
                               <span style="color: #15803D;"><?php echo number_format($b['total_price'], 2); ?> บาท</span>
                            </div>
                            <div class="detail-row">
                               <span>วันที่ทำรายการ:</span>
                               <span><?php echo htmlspecialchars($b['created_at']); ?></span>
                            </div>

                            <!-- ดึงรายการสนามย่อย -->
                            <div class="item-list">
                                <div style="font-weight: 600; margin-bottom: 5px; color: #334155;">สนามและรอบเวลาที่จอง:</div>
                                <?php
                                    $stmt_items = $active_pdo->prepare("SELECT * FROM booking_items WHERE booking_id = ?");
                                    $stmt_items->execute([$b['id']]);
                                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($items as $it) {
                                        echo "<div style='color: #64748B;'>• สนามที่ {$it['court_id']} | วันที่: {$it['booking_date']} | เวลา: {$it['time_slot']}</div>";
                                    }
                                ?>
                            </div>

                            <?php if (!empty($b['slip_image'])): ?>
                                <div style="margin-top: 10px; font-size: 0.85rem;">
                                    <a href="uploads/slips/<?php echo htmlspecialchars($b['slip_image']); ?>" target="_blank" style="color: #2563EB; text-decoration: none;">ดูรูปสลิปที่แนบไว้ ↗</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-result">ไม่พบประวัติการจองด้วยเบอร์โทรศัพท์นี้</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>