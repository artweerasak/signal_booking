<?php
require_once 'config/database.php';
require_once 'config/security.php';

// รับค่า POST จากหน้า index.php
$booking_items_json = $_POST['booking_items'] ?? '[]';
$user_type = $_POST['user_type'] ?? 'General';
$fullname = $_POST['fullname'] ?? '';
$phone = $_POST['phone'] ?? '';

$booking_items = json_decode($booking_items_json, true);

// หากไม่มีรายการจอง ให้กลับหน้าหลัก
if (empty($booking_items)) {
    header('Location: index.php');
    exit;
}

// คำนวณราคารวมทั้งหมด
$total_price = 0;
foreach ($booking_items as $item) {
    $total_price += $item['price'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ชำระเงินและยืนยันการจอง - สนามเทนนิส กรมการทหารสื่อสาร</title>
    
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --theme-primary: #15803D;
            --theme-dark: #166534;
            --theme-soft: #F0FDF4;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Prompt', sans-serif; }
        body { background-color: #FAFAFC; color: #334155; padding-bottom: 50px; }

        .top-bar {
            background: #FFFFFF;
            padding: 14px 8%;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 20px rgba(21, 128, 61, 0.06);
            border-bottom: 1px solid #F1F5F9;
        }
        .brand-logo-img { width: 45px; height: 45px; object-fit: contain; }

        .container { max-width: 900px; margin: 30px auto; padding: 0 15px; }

        /* กล่องนับถอยหลัง */
        .timer-box {
            background: #FEF2F2;
            border: 1px solid #FCA5A5;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 20px;
            text-align: center;
            color: #991B1B;
        }
        .timer-count {
            font-size: 1.3rem;
            font-weight: 700;
            color: #DC2626;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .card {
            background: #FFF;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid #F1F5F9;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--theme-primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--theme-soft);
        }

        /* Order Summary Styles */
        .info-group { margin-bottom: 12px; font-size: 0.9rem; }
        .info-group label { color: #64748B; font-size: 0.8rem; display: block; }
        .info-group strong { color: #1E293B; font-weight: 600; }

        .item-list { border: 1px solid #F1F5F9; border-radius: 10px; margin: 15px 0; overflow: hidden; }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid #F8FAFC;
            font-size: 0.85rem;
        }
        .item-row:last-child { border-bottom: none; }

        .total-box {
            background: var(--theme-soft);
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            border: 1px dashed rgba(21, 128, 61, 0.25);
            margin-top: 15px;
        }
        .total-box .val { font-size: 2rem; font-weight: 700; color: var(--theme-primary); }

        /* Payment QR Styles */
        .qr-container { text-align: center; }
        .qr-img { width: 100%; max-width: 280px; border-radius: 12px; border: 1px solid #E2E8F0; margin: 10px 0; }
        
        .upload-zone {
            margin-top: 15px;
            border: 2px dashed #CBD5E1;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            background: #F8FAFC;
            cursor: pointer;
            transition: all 0.2s;
        }
        .upload-zone:hover { border-color: var(--theme-primary); background: var(--theme-soft); }

        .btn-submit {
            width: 100%;
            background: var(--theme-primary);
            color: #FFF;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 15px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover { background: var(--theme-dark); }
        .btn-back {
            display: inline-block;
            margin-bottom: 15px;
            color: #64748B;
            text-decoration: none;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .checkout-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <img src="/assets/images/logo.png" alt="Logo" class="brand-logo-img">
        <div>
            <strong style="font-size:1.1rem; color: var(--theme-primary);">สนามเทนนิส กรมการทหารสื่อสาร</strong>
            <div style="font-size:0.75rem; color:#64748B;">ขั้นตอนการชำระเงินและยืนยันการจอง</div>
        </div>
    </div>

    <div class="container">
        <a href="javascript:history.back()" class="btn-back">❮ ย้อนกลับไปแก้ไขรายการ</a>

        <!-- แถบแจ้งเตือนนับถอยหลัง 30 วินาที -->
        <div class="timer-box">
            ⏰ กรุณาทำรายการและแนบสลิปชำระเงินภายใน <span id="countdown" class="timer-count">00:30</span> นาที
            <div style="font-size: 0.8rem; color: #7F1D1D; margin-top: 2px;">หากเกินเวลา ระบบจะทำการยกเลิกรายการจองและปล่อยคิวสนามอัตโนมัติ</div>
        </div>

        <form action="process_booking.php" method="POST" enctype="multipart/form-data" id="bookingForm">
            <input type="hidden" name="booking_items" value='<?= htmlspecialchars($booking_items_json) ?>'>
            <input type="hidden" name="user_type" value="<?= htmlspecialchars($user_type) ?>">
            <input type="hidden" name="fullname" value="<?= htmlspecialchars($fullname) ?>">
            <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
            <input type="hidden" name="total_price" value="<?= $total_price ?>">

            <!-- กันบอท: token เวลา (เซ็นด้วย secret) -->
            <input type="hidden" name="form_ts" value="<?= htmlspecialchars(form_time_token()) ?>">
            <!-- กันบอท: honeypot (คนมองไม่เห็น บอทมักกรอก) -->
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                   aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; opacity:0; height:0; width:0;">

            <div class="checkout-grid">
                
                <!-- ฝั่งซ้าย: สรุปข้อมูลผู้จองและรายการ -->
                <div class="card">
                    <div class="card-title">📋 สรุปรายการจองสนาม</div>
                    
                    <div class="info-group">
                        <label>ชื่อ-นามสกุล ผู้จอง</label>
                        <strong><?= htmlspecialchars($fullname) ?></strong>
                    </div>

                    <div class="info-group">
                        <label>เบอร์โทรศัพท์</label>
                        <strong><?= htmlspecialchars($phone) ?></strong>
                    </div>

                    <div class="info-group">
                        <label>สถานะผู้ใช้บริการ</label>
                        <strong><?= $user_type === 'Military' ? 'ข้าราชการกำลังพล / บุคคลภายใน' : 'ประชาชนทั่วไป / บุคคลภายนอก' ?></strong>
                    </div>

                    <div class="item-list">
                        <?php foreach ($booking_items as $item): ?>
                        <div class="item-row">
                            <div>
                                <strong style="color:#1E293B;"><?= htmlspecialchars($item['courtName']) ?> • <?= htmlspecialchars($item['timeText']) ?> น.</strong>
                                <div style="font-size:0.75rem; color:#64748B;">📅 <?= htmlspecialchars($item['date']) ?></div>
                            </div>
                            <div style="font-weight:600; color:var(--theme-primary);"><?= number_format($item['price']) ?> ฿</div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="total-box">
                        <div style="font-size:0.85rem; color:var(--theme-primary);">ยอดชำระรวมทั้งสิ้น</div>
                        <div class="val"><?= number_format($total_price) ?> <small style="font-size:1.2rem;">บาท</small></div>
                    </div>
                </div>

                <!-- ฝั่งขวา: ช่องทางชำระเงิน & แนบสลิป -->
                <div class="card">
                    <div class="card-title">📱 สแกน QR Code ชำระเงิน</div>
                    
                    <div class="qr-container">
                        <img src="assets/images/qr.jpg" alt="Thai QR Payment" class="qr-img" id="qrImg">
                        
                        <div style="font-size:0.85rem; color:#475569; margin-top:5px;">
                            <strong>บัญชีสวัสดิการ สนามกีฬา</strong><br>
                            ชื่อบัญชี: นาย ณัฐวัฒน์ จงสุทธิพัฒน์<br>
                            <span style="font-size:0.75rem; color:#64748B;">รหัสร้านค้า: DI110200M30693943UP</span>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <label style="font-size:0.85rem; font-weight:600; color:#475569; display:block; margin-bottom:6px;">
                            📤 อัปโหลดหลักฐานการโอนเงิน (สลิป) *
                        </label>
                        <input type="file" name="payment_slip" id="slipInput" accept="image/*" required style="display:none;" onchange="updateFileName(this)">
                        
                        <div class="upload-zone" onclick="document.getElementById('slipInput').click();">
                            <span id="uploadText" style="font-size:0.85rem; color:#64748B;">
                                📷 คลิกเพื่อแนบรูปสลิปการโอนเงิน
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">ส่งหลักฐานและยืนยันการจอง</button>
                </div>

            </div>
        </form>
    </div>

    <script>
        // ฟังก์ชันแสดงชื่อไฟล์สลิปเมื่อมีการเลือกรูปภาพ
        function updateFileName(input) {
            const uploadText = document.getElementById('uploadText');
            if (input.files && input.files[0]) {
                uploadText.innerHTML = `✅ เลือกไฟล์เรียบร้อย: <br><strong style="color:var(--theme-primary);">${input.files[0].name}</strong>`;
            }
        }

        // ระบบนับถอยหลัง 30 วินาที
        let timeInSeconds = 30; 
        const countdownElement = document.getElementById('countdown');

        const timerInterval = setInterval(() => {
            let minutes = Math.floor(timeInSeconds / 60);
            let seconds = timeInSeconds % 60;

            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            countdownElement.textContent = `${minutes}:${seconds}`;

            // เมื่อเวลาหมด (นับถอยหลังถึง 0)
            if (timeInSeconds <= 0) {
                clearInterval(timerInterval);
                alert('หมดเวลาทำรายการจอง กรุณาทำรายการใหม่');
                window.location.href = 'index.php?status=timeout';
            }

            timeInSeconds--;
        }, 1000);
    </script>
</body>
</html>