<?php
require_once 'config/database.php';

// ดึงการตั้งค่าระบบ
$stmtSettings = $conn->query("SELECT setting_key, setting_value FROM system_settings");
$settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

// กำหนดเบอร์ติดต่อเป็นเบอร์ใหม่โดยตรง
$contact_phone = '087-562-7531';

// ตรวจสอบ Rain Mode
if (($settings['rain_mode'] ?? '0') === '1') {
    die("<div style='text-align:center; padding:80px 20px; font-family:sans-serif;'>
            <h1 style='font-size:2.5rem; color:#DC2626;'>🌧️ แจ้งปิดสนามชั่วคราว</h1>
            <p style='font-size:1.2rem; color:#475569;'>" . htmlspecialchars($settings['rain_announcement'] ?? 'สนามปิดเนื่องจากสภาพอากาศ') . "</p>
            <p style='font-size:1rem; color:#64748B; margin-top:10px;'>สอบถามเพิ่มเติม โทร. " . htmlspecialchars($contact_phone) . "</p>
         </div>");
}

$selected_date = $_GET['booking_date'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจองสนามเทนนิสออนไลน์</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <link rel="apple-touch-icon" href="/assets/images/logo.png">

    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --theme-primary: #15803D;    /* สีเขียวคอร์ดเทนนิสหลัก */
            --theme-dark: #166534;       /* เขียวเข้ม */
            --theme-light: #22C55E;      /* เขียวสว่าง */
            --theme-soft: #F0FDF4;       /* เขียวอ่อนพาสเทล */
            --theme-border: #DCFCE7;     /* เส้นขอบเขียวอ่อน */
        }

        /* SVG Cursor: Tennis Ball Normal (Yellow Neon) */
        body, button, input, select, a {
            cursor: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28"><circle cx="14" cy="14" r="11" fill="%23ccff00" stroke="%23333333" stroke-width="1.2"/><path d="M 5.5,8.5 A 11,11 0 0,1 19.5,22.5" fill="none" stroke="%23ffffff" stroke-width="1.8" stroke-linecap="round"/><path d="M 8.5,22.5 A 11,11 0 0,1 22.5,8.5" fill="none" stroke="%23ffffff" stroke-width="1.8" stroke-linecap="round"/></svg>') 14 14, auto !important;
        }

        /* SVG Cursor: Tennis Ball Hover (Larger Yellow Neon) */
        a:hover, button:hover, select:hover, input:hover, .slot:hover, .cart-del:hover {
            cursor: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><circle cx="16" cy="16" r="13" fill="%23ccff00" stroke="%23222222" stroke-width="1.5"/><path d="M 6,9.5 A 13,13 0 0,1 22.5,26" fill="none" stroke="%23ffffff" stroke-width="2" stroke-linecap="round"/><path d="M 9.5,26 A 13,13 0 0,1 26,9.5" fill="none" stroke="%23ffffff" stroke-width="2" stroke-linecap="round"/></svg>') 16 16, pointer !important;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Prompt', sans-serif; }
        body { background-color: #FAFAFC; color: #334155; }

        /* Top Bar */
        .top-bar { 
            background: #FFFFFF; 
            color: #334155; 
            padding: 14px 8%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: relative; 
            z-index: 20; 
            box-shadow: 0 4px 20px rgba(21, 128, 61, 0.06);
            border-bottom: 1px solid #F1F5F9;
        }
        .brand-logo { display: flex; align-items: center; gap: 14px; }
        .brand-logo-img { 
            width: 50px; 
            height: 50px; 
            object-fit: contain; 
            filter: drop-shadow(0 2px 8px rgba(21, 128, 61, 0.15));
        }
        .top-right-group { display: flex; align-items: center; gap: 12px; }
        .top-info-badges { display: flex; gap: 10px; font-size: 0.85rem; }
        .info-pill { 
            background: var(--theme-soft); 
            color: var(--theme-primary); 
            padding: 6px 16px; 
            border-radius: 20px; 
            display: flex; 
            align-items: center; 
            gap: 6px; 
            font-weight: 500;
            border: 1px solid rgba(21, 128, 61, 0.15);
        }
        .btn-check-status {
            background: #2563EB;
            color: #FFF;
            padding: 7px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
        }
        .btn-check-status:hover {
            background: #1D4ED8;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
        }

        /* Hero Carousel Section */
        .hero-slider { position: relative; overflow: hidden; width: 100%; height: 380px; background: var(--theme-dark); }
        .slide-track { display: flex; width: 400%; height: 100%; transition: transform 0.7s cubic-bezier(0.25, 1, 0.5, 1); }
        .slide { 
            width: 25%; 
            height: 100%; 
            position: relative; 
            background-size: cover; 
            background-position: center; 
            display: flex; 
            align-items: center; 
            padding: 0 8%; 
        }
        .slide::before { 
            content: ''; 
            position: absolute; 
            inset: 0; 
            background: linear-gradient(90deg, rgba(22, 101, 52, 0.92) 0%, rgba(21, 128, 61, 0.7) 50%, rgba(21, 128, 61, 0.2) 100%); 
        }
        
        .slide-content { position: relative; z-index: 10; color: #FFF; max-width: 720px; }
        .slide-content h1 { font-size: 2.2rem; font-weight: 700; margin-bottom: 10px; line-height: 1.25; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .slide-content p { font-size: 0.95rem; color: #F1F5F9; opacity: 0.92; line-height: 1.6; font-weight: 300; }

        /* Slider Controls */
        .slider-btn { 
            position: absolute; 
            top: 50%; 
            transform: translateY(-50%); 
            z-index: 12; 
            background: rgba(255,255,255,0.2); 
            color: #FFF; 
            border: 1px solid rgba(255,255,255,0.3); 
            width: 46px; 
            height: 46px; 
            border-radius: 50%; 
            backdrop-filter: blur(6px); 
            transition: all 0.25s; 
            font-size: 1.2rem; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .slider-btn:hover { background: #FFFFFF; color: var(--theme-primary); }
        .slider-btn.prev { left: 24px; }
        .slider-btn.next { right: 24px; }
        .dots-container { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 12; display: flex; gap: 10px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.35); transition: all 0.3s; }
        .dot.active { background: #FFFFFF; width: 32px; border-radius: 10px; box-shadow: 0 0 8px rgba(255,255,255,0.5); }

        /* Main Grid */
        .wrapper { max-width: 1250px; margin: 30px auto 40px auto; padding: 0 15px; display: grid; grid-template-columns: 1fr 420px; gap: 24px; }

        /* Left Side */
        .card { background: #FFF; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); margin-bottom: 20px; border: 1px solid #F1F5F9; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .field label { font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 6px; display: block; }
        .input-box { width: 100%; padding: 11px 14px; border: 1px solid #E2E8F0; border-radius: 10px; font-size: 0.9rem; background: #FFF; outline: none; transition: border-color 0.2s; }
        .input-box:focus { border-color: var(--theme-light); }
        .input-box.error { border-color: #dc3545; }
        .input-box.success { border-color: #28a745; }

        .error-message { color: #dc3545; font-size: 0.8rem; margin-top: 4px; display: block; }
        .success-message { color: #28a745; font-size: 0.8rem; margin-top: 4px; display: block; }

        /* Legend */
        .legend { display: flex; justify-content: space-around; background: #F8FAFC; padding: 14px; border-radius: 12px; margin: 20px 0 15px 0; font-size: 0.8rem; border: 1px solid #E2E8F0; }
        .legend-item { display: flex; align-items: center; gap: 8px; color: #334155; font-weight: 500; }
        .color-dot { width: 14px; height: 14px; border-radius: 4px; }

        /* Slots Grid */
        .grid-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .grid-title h4 { font-size: 1rem; color: #1E293B; }
        .time-slots { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .slot { border: 1px solid #E2E8F0; background: #FFFFFF; border-radius: 12px; padding: 12px 6px; text-align: center; transition: all 0.2s; position: relative; }
        .slot.night { background: #FEFCE8; border-color: #FEF08A; }
        .slot.selected { background: var(--theme-primary) !important; border-color: var(--theme-primary) !important; color: #FFF !important; box-shadow: 0 4px 12px rgba(21, 128, 61, 0.3); transform: scale(0.98); }
        .slot.disabled { background: #F1F5F9 !important; border-color: #E2E8F0 !important; color: #94A3B8 !important; cursor: not-allowed !important; }
        .slot .t { font-size: 0.85rem; font-weight: 600; display: block; }
        .slot .p { font-size: 0.72rem; opacity: 0.85; margin-top: 2px; display: block; }
        .slot-badge { position: absolute; top: -6px; right: -6px; background: #10B981; color: #FFF; border-radius: 50%; width: 20px; height: 20px; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid #FFF; }

        /* Right Side: Cart Summary */
        .summary-box { background: #FFF; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); position: sticky; top: 20px; border: 1px solid #F1F5F9; }
        .summary-header { font-size: 1.1rem; font-weight: 600; color: var(--theme-primary); margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--theme-soft); padding-bottom: 12px; }
        .cart-badge { background: var(--theme-soft); color: var(--theme-primary); font-size: 0.78rem; padding: 3px 12px; border-radius: 12px; font-weight: 600; }

        /* Selected Cart Table */
        .cart-list { max-height: 220px; overflow-y: auto; margin-bottom: 15px; border: 1px solid #F1F5F9; border-radius: 10px; }
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-bottom: 1px solid #F8FAFC; font-size: 0.82rem; }
        .cart-item:last-child { border-bottom: none; }
        .cart-info strong { display: block; color: #1E293B; font-weight: 600; }
        .cart-info span { color: #64748B; font-size: 0.75rem; }
        .cart-del { color: #EF4444; font-weight: bold; padding: 4px 8px; border-radius: 6px; transition: background 0.2s; cursor: pointer; }
        .cart-del:hover { background: #FEE2E2; }
        .empty-cart { text-align: center; color: #94A3B8; padding: 25px 10px; font-size: 0.85rem; }

        .total-box { background: var(--theme-soft); padding: 16px; border-radius: 12px; text-align: center; margin-bottom: 18px; border: 1px dashed rgba(21, 128, 61, 0.25); }
        .total-box .lbl { font-size: 0.85rem; color: var(--theme-primary); font-weight: 500; }
        .total-box .val { font-size: 2.2rem; font-weight: 700; color: var(--theme-primary); line-height: 1.1; }

        .btn-confirm { width: 100%; background: #CBD5E1; color: #FFF; border: none; padding: 14px; border-radius: 10px; font-weight: 600; font-size: 0.95rem; transition: all 0.2s; }
        .btn-confirm.active { background: var(--theme-primary); box-shadow: 0 4px 16px rgba(21, 128, 61, 0.3); cursor: pointer; }
        .btn-confirm.active:hover { background: var(--theme-dark); }

        @media (max-width: 900px) { .wrapper { grid-template-columns: 1fr; } .time-slots { grid-template-columns: repeat(2, 1fr); } .top-bar { flex-direction: column; gap: 15px; align-items: flex-start; } .top-right-group { width: 100%; justify-content: space-between; } }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <div class="top-bar">
        <div class="brand-logo">
            <img src="/assets/images/logo.png" alt="Signal Tennis Club Logo" class="brand-logo-img">
            <div>
                <strong style="font-size:1.1rem; color: var(--theme-primary);">สนามเทนนิส กรมการทหารสื่อสาร</strong>
                <div style="font-size:0.75rem; color:#64748B; font-weight:400;">149 ถนนพระราม5 แขวงถนนนครไชยศรี เขตดุสิต กรุงเทพมหานคร</div>
            </div>
        </div>
        <div class="top-right-group">
            <a href="check_booking.php" class="btn-check-status">
                🔍 ตรวจสอบสถานะการจอง
            </a>
            <div class="top-info-badges">
                <div class="info-pill">📞 <?= htmlspecialchars($contact_phone) ?></div>
                <div class="info-pill">⏰ 06:00 - 21:00 น.</div>
            </div>
        </div>
    </div>

    <!-- Hero Slider -->
    <div class="hero-slider">
        <div class="slide-track" id="slideTrack">
            <!-- Slide 1 -->
            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h1>ยกระดับเกมเทนนิสของคุณ บนสนามมาตรฐานสากล</h1>
                    <p>ระบบจองสนามออนไลน์ที่สะดวกที่สุด เลือกจองล่วงหน้าได้หลายวันและเลือกรอบเวลาที่ต้องการได้ทันที</p>
                </div>
            </div>
            <!-- Slide 2 -->
            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1530915534664-4ac6423ca938?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h1>สัมผัสประสบการณ์เล่นยามค่ำคืน</h1>
                    <p>ระบบไฟส่องสว่าง LED คุณภาพสูง กระจายแสงสม่ำเสมอ คมชัดสบายตา เหมาะสำหรับการฝึกซ้อมและการแข่งขัน</p>
                </div>
            </div>
            <!-- Slide 3 -->
            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h1>สิ่งอำนวยความสะดวกครบครัน</h1>
                    <p>ลานจอดรถกว้างขวาง ปลอดภัยภายในพื้นที่กรมการทหารสื่อสาร พร้อมห้องน้ำ จุดพักนักกีฬา และการดูแลอย่างเป็นกันเอง</p>
                </div>
            </div>
            <!-- Slide 4 -->
            <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=1600&q=80');">
                <div class="slide-content">
                    <h1>สิทธิพิเศษสำหรับกำลังพลและสมาชิก</h1>
                    <p>รับอัตราค่าบริการพิเศษในการซ้อมประจำ และกิจกรรมแข่งขันกระชับมิตร ติดต่อเจ้าหน้าที่เพื่อสอบถามข้อมูลเพิ่มเติม</p>
                </div>
            </div>
        </div>

        <button class="slider-btn prev" onclick="moveSlide(-1)">❮</button>
        <button class="slider-btn next" onclick="moveSlide(1)">❯</button>

        <div class="dots-container" id="dotsContainer">
            <div class="dot active" onclick="goToSlide(0)"></div>
            <div class="dot" onclick="goToSlide(1)"></div>
            <div class="dot" onclick="goToSlide(2)"></div>
            <div class="dot" onclick="goToSlide(3)"></div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="wrapper">
        
        <!-- Left Section: Controls & Slots -->
        <div>
            <div class="card">
                <div class="form-row">
                    <div class="field">
                        <label>📅 เลือกวันที่ใช้งาน</label>
                        <input type="date" id="bookingDate" class="input-box" value="<?= htmlspecialchars($selected_date) ?>">
                    </div>
                    <div class="field">
                        <label>🎾 เลือกสนาม</label>
                        <select id="courtSelect" class="input-box">
                            <option value="1" data-name="Court 1">Court 1 (Hard Court - สนามกลางแจ้ง)</option>
                            <option value="2" data-name="Court 2">Court 2 (Hard Court - สนามกลางแจ้ง)</option>
                        </select>
                    </div>
                </div>

                <!-- Legend Bar -->
                <div class="legend">
                    <div class="legend-item"><div class="color-dot" style="background:#FFFFFF; border:1px solid #CBD5E1;"></div> ว่าง (กลางวัน 120฿)</div>
                    <div class="legend-item"><div class="color-dot" style="background:#FEFCE8; border:1px solid #FEF08A;"></div> ว่าง (เปิดไฟ 150฿)</div>
                    <div class="legend-item"><div class="color-dot" style="background:var(--theme-primary);"></div> เลือกรอบนี้</div>
                    <div class="legend-item"><div class="color-dot" style="background:#E2E8F0;"></div> จองแล้ว</div>
                </div>

                <!-- Slots Grid -->
                <div class="grid-title">
                    <h4>เลือกรอบเวลาที่ต้องการ (เลือกได้หลายรอบ)</h4>
                    <span style="font-size:0.75rem; color:var(--theme-primary); font-weight:600;">คลิกเพื่อเพิ่ม/ยกเลิกรายการ</span>
                </div>
                
                <div class="time-slots" id="slotsContainer">
                    <!-- Dynamic slots populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Right Section: Cart Summary & Form -->
        <div>
            <form action="checkout.php" method="POST" onsubmit="return validateForm(event)" class="summary-box">
                <div class="summary-header">
                    <span>📋 รายการที่เลือกทั้งหมด</span>
                    <span class="cart-badge" id="cartCount">0 รายการ</span>
                </div>
                
                <div class="cart-list" id="cartContainer">
                    <div class="empty-cart">ยังไม่มีการเลือกรอบเวลา<br><small>กรุณาเลือกรอบเวลาที่ต้องการด้านซ้าย</small></div>
                </div>

                <div class="total-box">
                    <div class="lbl">ราคารวมทั้งสิ้น:</div>
                    <div class="val"><span id="sumPrice">0</span> <small style="font-size:1.2rem;">฿</small></div>
                </div>

                <!-- Hidden Input for Multiple Bookings JSON -->
                <input type="hidden" name="booking_items" id="inputItems" value="">

                <div class="field" style="margin-bottom:12px;">
                    <label>สถานะผู้ใช้บริการ *</label>
                    <select name="user_type" class="input-box" required>
                        <option value="General">ประชาชนทั่วไป / บุคคลภายนอก</option>
                        <option value="Military">ข้าราชการกำลังพล / บุคคลภายใน</option>
                    </select>
                </div>

                <div class="field" style="margin-bottom:12px;">
                    <label>ชื่อ-นามสกุล ผู้จอง *</label>
                    <input type="text" id="fullname" name="fullname" class="input-box" placeholder="สมชาย ใจดี" oninput="validateName()" required>
                    <span id="nameError"></span>
                </div>

                <div class="field" style="margin-bottom:20px;">
                    <label>เบอร์โทรศัพท์ *</label>
                    <input type="tel" id="phone" name="phone" class="input-box" placeholder="08X-XXX-XXXX" oninput="validatePhone()" required>
                    <span id="phoneError"></span>
                </div>

                <button type="submit" id="btnConfirm" class="btn-confirm" disabled>ยืนยันการจองสนาม</button>
            </form>
        </div>

    </div>

    <script>
        // เสียงเอฟเฟกต์ตีเทนนิส
        function playTennisHitSound() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();

                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(220, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(40, ctx.currentTime + 0.08);

                gain.gain.setValueAtTime(0.8, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.08);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.08);
            } catch(e) {
                console.log('Audio error:', e);
            }
        }

        // Hero Slide Logic
        let currentSlide = 0;
        const totalSlides = 4;
        const slideTrack = document.getElementById('slideTrack');
        const dots = document.querySelectorAll('.dot');

        function updateSlider() {
            slideTrack.style.transform = `translateX(-${currentSlide * (100 / totalSlides)}%)`;
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
            });
        }

        function moveSlide(direction) {
            currentSlide = (currentSlide + direction + totalSlides) % totalSlides;
            updateSlider();
        }

        function goToSlide(index) {
            currentSlide = index;
            updateSlider();
        }

        setInterval(() => { moveSlide(1); }, 6000);

        // Rate Rules: 06:00 - 18:00 = 120฿ | 18:00 - 21:00 = 150฿
        const rawSlots = [
            { id: 1, start: '06:00', end: '07:00', price: 120, type: 'day' },
            { id: 2, start: '07:00', end: '08:00', price: 120, type: 'day' },
            { id: 3, start: '08:00', end: '09:00', price: 120, type: 'day', disabled: true },
            { id: 4, start: '09:00', end: '10:00', price: 120, type: 'day' },
            { id: 5, start: '10:00', end: '11:00', price: 120, type: 'day' },
            { id: 6, start: '11:00', end: '12:00', price: 120, type: 'day' },
            { id: 7, start: '12:00', end: '13:00', price: 120, type: 'day' },
            { id: 8, start: '13:00', end: '14:00', price: 120, type: 'day' },
            { id: 9, start: '14:00', end: '15:00', price: 120, type: 'day' },
            { id: 10, start: '15:00', end: '16:00', price: 120, type: 'day' },
            { id: 11, start: '16:00', end: '17:00', price: 120, type: 'day' },
            { id: 12, start: '17:00', end: '18:00', price: 120, type: 'day' },
            { id: 13, start: '18:00', end: '19:00', price: 150, type: 'night' },
            { id: 14, start: '19:00', end: '20:00', price: 150, type: 'night' },
            { id: 15, start: '20:00', end: '21:00', price: 150, type: 'night' },
        ];

        let cart = [];

        function renderSlots() {
            const container = document.getElementById('slotsContainer');
            const selectedDateStr = document.getElementById('bookingDate').value;
            const currentCourt = document.getElementById('courtSelect').value;
            
            container.innerHTML = '';
            
            // ดึงวันที่และเวลาปัจจุบันของเครื่องผู้.ช้
            const now = new Date();
            const todayStr = now.getFullYear()+"-"+
                  			String(now.getMonth()+1).padStart(2,'0')+"-"+
                  			String(now.getDate()).padStart(2,'0');
            const currentHour = now.getHours();
    		const currentMinute = now.getMinutes();

            rawSlots.forEach(slot => {
        		// เช็คว่าถูกจองไปแล้วในฐานข้อมูลหรือไม่
        		const isSelected = cart.some(item => 
            		item.date === selectedDateStr && 
            		item.courtId === currentCourt && 
            		item.slotId === slot.id
        		);

        		// เช็คเงื่อนไข "เลยเวลาปัจจุบัน" (ถ้าเลือกวันเดียวกันกับวันนี้)
        		let isTimePassed = false;
        		if (selectedDateStr < todayStr) {
                    // ถ้าเลือกวันย้อนหลัง ให้ปิดการใช้งานทั้งหมด
                    isTimePassed = true;
                } else if (selectedDateStr === todayStr) {
                    // ถ้าเป็นวันปัจจุบัน ให้เช็คเวลาตามปกติ
                    const [slotHour, slotMinute] = slot.start.split(':').map(Number);
            
                    if (currentHour > slotHour || (currentHour === slotHour && currentMinute >= slotMinute)) {
                        isTimePassed = true;
                    }
                }

       			 // สถานะ Disable (รวมถึงกรณีถูกจองแล้ว หรือเลยเวลาปัจจุบันแล้ว)
        		const isDisabled = slot.disabled || isTimePassed;

        		const btn = document.createElement('div');
        		btn.className = `slot ${slot.type} ${isDisabled ? 'disabled' : ''} ${isSelected ? 'selected' : ''}`;
        
        		let checkBadge = isSelected ? '<div class="slot-badge">✓</div>' : '';
        		let priceText = slot.disabled ? 'จองแล้ว' : (isTimePassed ? 'หมดเวลา' : slot.price + '฿');

        		btn.innerHTML = `
            		${checkBadge}
            		<span class="t">${slot.start} - ${slot.end}</span>
            		<span class="p">${priceText}</span>
        		`;
        
        		// ถ้าไม่ Disable ถึงจะกดเลือกได้
        		if (!isDisabled) {
            		btn.onclick = () => toggleSlot(slot);
        		}
        
        		container.appendChild(btn);
    		});
          
        }

        function toggleSlot(slot) {
            playTennisHitSound();

            const date = document.getElementById('bookingDate').value;
            const courtSelect = document.getElementById('courtSelect');
            const courtId = courtSelect.value;
            const courtName = courtSelect.options[courtSelect.selectedIndex].getAttribute('data-name');

            const index = cart.findIndex(item => 
                item.date === date && 
                item.courtId === courtId && 
                item.slotId === slot.id
            );

            if (index > -1) {
                cart.splice(index, 1);
            } else {
                cart.push({
                    date: date,
                    courtId: courtId,
                    courtName: courtName,
                    slotId: slot.id,
                    timeText: `${slot.start} - ${slot.end}`,
                    price: slot.price
                });
            }

            renderSlots();
            renderCart();
        }

        function removeFromCart(index) {
            playTennisHitSound();
            cart.splice(index, 1);
            renderSlots();
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cartContainer');
            const cartCount = document.getElementById('cartCount');
            const sumPrice = document.getElementById('sumPrice');
            const inputItems = document.getElementById('inputItems');
            const btnConfirm = document.getElementById('btnConfirm');

            cartCount.innerText = `${cart.length} รายการ`;

            if (cart.length === 0) {
                container.innerHTML = `<div class="empty-cart">ยังไม่มีการเลือกรอบเวลา<br><small>กรุณาเลือกรอบเวลาที่ต้องการด้านซ้าย</small></div>`;
                sumPrice.innerText = '0';
                inputItems.value = '';
                btnConfirm.disabled = true;
                btnConfirm.classList.remove('active');
                return;
            }

            let html = '';
            let total = 0;

            cart.forEach((item, idx) => {
                total += item.price;
                html += `
                    <div class="cart-item">
                        <div class="cart-info">
                            <strong>${item.courtName} • ${item.timeText} น.</strong>
                            <span>📅 ${item.date} (${item.price}฿)</span>
                        </div>
                        <div class="cart-del" onclick="removeFromCart(${idx})" title="ลบรายการ">✕</div>
                    </div>
                `;
            });

            container.innerHTML = html;
            sumPrice.innerText = total.toLocaleString();
            inputItems.value = JSON.stringify(cart);
            btnConfirm.disabled = false;
            btnConfirm.classList.add('active');
        }

        document.getElementById('bookingDate').addEventListener('change', renderSlots);
        document.getElementById('courtSelect').addEventListener('change', renderSlots);

        // เรียกใช้งานตอนโหลดหน้าครั้งแรก
        renderSlots();

        // 1. ฟังก์ชันตรวจสอบชื่อ-นามสกุล
        function validateName() {
            const nameInput = document.getElementById('fullname');
            const nameError = document.getElementById('nameError');
            const value = nameInput.value.trim();

            if (value === "") {
                nameInput.className = "input-box error";
                nameError.textContent = "กรุณากรอกชื่อ-นามสกุล";
                nameError.className = "error-message";
                return false;
            } else if (value.length < 2) {
                nameInput.className = "input-box error";
                nameError.textContent = "ชื่อต้องมีอย่างน้อย 2 ตัวอักษรขึ้นไป";
                nameError.className = "error-message";
                return false;
            } else {
                nameInput.className = "input-box success";
                nameError.textContent = "✓ ชื่อถูกต้อง";
                nameError.className = "success-message";
                return true;
            }
        }

        // 2. ฟังก์ชันตรวจสอบเบอร์โทรศัพท์
        function validatePhone() {
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phoneError');
            const value = phoneInput.value.trim();

            const cleaned = value.replace(/[\s-]/g, '');
            const thaiPhoneRegex = /^0[689]\d{8}$/;

            if (value === "") {
                phoneInput.className = "input-box error";
                phoneError.textContent = "กรุณากรอกเบอร์โทรศัพท์";
                phoneError.className = "error-message";
                return false;
            } else if (thaiPhoneRegex.test(cleaned)) {
                phoneInput.className = "input-box success";
                phoneError.textContent = "✓ เบอร์โทรศัพท์ถูกต้อง";
                phoneError.className = "success-message";
                return true;
            } else {
                phoneInput.className = "input-box error";
                phoneError.textContent = "กรุณากรอกเบอร์มือถือ 10 หลักให้ถูกต้อง (เช่น 0812345678)";
                phoneError.className = "error-message";
                return false;
            }
        }

        // 3. ฟังก์ชันตรวจสอบภาพรวมตอนกดปุ่ม Submit
        function validateForm(event) {
            const isNameValid = validateName();
            const isPhoneValid = validatePhone();

            if (!isNameValid || !isPhoneValid) {
                event.preventDefault();
                alert("กรุณาตรวจสอบข้อมูลชื่อและเบอร์โทรศัพท์ให้ถูกต้องครบถ้วนก่อนส่ง");
                return false;
            }

            return true;
        }
    </script>
</body>
</html>