<?php
// ─────────────────────────────────────────────────────────────
//  สร้างบัญชีแอดมินคนแรก — ใช้ครั้งเดียว
//  ⚠️ หลังสร้างแอดมินเสร็จ ให้ "ลบไฟล์นี้ทิ้ง" เพื่อความปลอดภัย
//  (สคริปต์นี้จะไม่ทำงานถ้ามีแอดมินอยู่แล้ว)
// ─────────────────────────────────────────────────────────────
require 'config/database.php';

$msg = '';

// มีแอดมินอยู่แล้วหรือยัง
$exists = (int) $conn->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if ($exists > 0) {
    die('มีบัญชีแอดมินอยู่แล้ว — กรุณาลบไฟล์ create_admin.php นี้ทิ้งเพื่อความปลอดภัย');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || strlen($password) < 6) {
        $msg = 'กรุณากรอกชื่อผู้ใช้ และรหัสผ่านอย่างน้อย 6 ตัวอักษร';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        die('สร้างแอดมินเรียบร้อย! เข้าสู่ระบบที่ <a href="admin_login.php">admin_login.php</a><br><b style="color:#DC2626;">⚠️ อย่าลืมลบไฟล์ create_admin.php ทิ้ง</b>');
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><title>สร้างแอดมินคนแรก</title>
<style>
    body { font-family: sans-serif; display:flex; justify-content:center; align-items:center; height:100vh; background:#f4f4f9; }
    .box { background:#fff; padding:30px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,.1); width:320px; }
    input { width:100%; padding:10px; margin:8px 0; border:1px solid #ccc; border-radius:4px; }
    button { width:100%; padding:10px; background:#15803D; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:600; }
    .err { color:#DC2626; font-size:0.9rem; margin-bottom:8px; }
</style></head>
<body>
    <div class="box">
        <h2 style="margin-bottom:15px;">สร้างแอดมินคนแรก</h2>
        <?php if ($msg): ?><div class="err"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="ชื่อผู้ใช้" required>
            <input type="password" name="password" placeholder="รหัสผ่าน (อย่างน้อย 6 ตัว)" required>
            <button type="submit">สร้างบัญชี</button>
        </form>
    </div>
</body>
</html>
