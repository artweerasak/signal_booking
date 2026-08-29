<?php
require 'admin_auth.php';
require 'config/database.php';

$UPLOAD_DIR = 'uploads/promos/';
$msg = ''; $ok = false;

// อัปโหลดรูป — ตรวจ MIME จริง + จำกัดขนาด (คืน [path|null, error])
function handle_promo_upload($field, $dir) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null]; // ไม่ได้อัปโหลด (ไม่ใช่ error)
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return [null, 'อัปโหลดรูปไม่สำเร็จ'];
    }
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $tmp = $_FILES[$field]['tmp_name'];
    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) return [null, 'รูปใหญ่เกิน 5MB'];
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) return [null, 'ชนิดไฟล์ไม่ถูกต้อง (รับเฉพาะ JPG/PNG/WEBP)'];
    $name = 'promo_' . time() . '_' . rand(1000,9999) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $dir . $name)) return [null, 'บันทึกรูปไม่สำเร็จ'];
    return [$dir . $name, null];
}

function delete_if_local($image, $dir) {
    if ($image && strpos($image, $dir) === 0 && is_file($image)) @unlink($image);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { http_response_code(403); die('CSRF'); }
    $do = $_POST['do'] ?? '';

    if ($do === 'add') {
        $heading = trim($_POST['heading'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $order   = intval($_POST['sort_order'] ?? 0);
        [$path, $err] = handle_promo_upload('image', $UPLOAD_DIR);
        if ($heading === '') { $msg = 'กรุณากรอกหัวข้อ'; }
        elseif ($err) { $msg = $err; }
        elseif (!$path) { $msg = 'กรุณาเลือกรูปโปรโมท'; }
        else {
            try {
                $conn->prepare("INSERT INTO promos (image, heading, caption, sort_order, is_active, created_at) VALUES (?,?,?,?,1,NOW())")
                     ->execute([$path, $heading, $caption, $order]);
                $ok = true; $msg = 'เพิ่มสไลด์โปรโมทแล้ว';
            } catch (PDOException $e) { error_log('promo add: '.$e->getMessage()); $msg = 'บันทึกไม่สำเร็จ'; }
        }
    }
    elseif ($do === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $heading = trim($_POST['heading'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $order   = intval($_POST['sort_order'] ?? 0);
        $active  = isset($_POST['is_active']) ? 1 : 0;
        if ($id > 0 && $heading !== '') {
            try {
                [$path, $err] = handle_promo_upload('image', $UPLOAD_DIR);
                if ($err) { $msg = $err; }
                else {
                    if ($path) {
                        // แทนที่รูปเดิม → ลบไฟล์เก่าถ้าเป็นไฟล์ในเครื่อง
                        $old = $conn->prepare("SELECT image FROM promos WHERE id=?"); $old->execute([$id]);
                        delete_if_local($old->fetchColumn(), $UPLOAD_DIR);
                        $conn->prepare("UPDATE promos SET image=?, heading=?, caption=?, sort_order=?, is_active=? WHERE id=?")
                             ->execute([$path, $heading, $caption, $order, $active, $id]);
                    } else {
                        $conn->prepare("UPDATE promos SET heading=?, caption=?, sort_order=?, is_active=? WHERE id=?")
                             ->execute([$heading, $caption, $order, $active, $id]);
                    }
                    $ok = true; $msg = 'บันทึกการแก้ไขแล้ว';
                }
            } catch (PDOException $e) { error_log('promo update: '.$e->getMessage()); $msg = 'บันทึกไม่สำเร็จ'; }
        } else { $msg = 'ข้อมูลไม่ครบ (ต้องมีหัวข้อ)'; }
    }
    elseif ($do === 'del') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $s = $conn->prepare("SELECT image FROM promos WHERE id=?"); $s->execute([$id]);
                delete_if_local($s->fetchColumn(), $UPLOAD_DIR);
                $conn->prepare("DELETE FROM promos WHERE id=?")->execute([$id]);
                $ok = true; $msg = 'ลบสไลด์แล้ว';
            } catch (PDOException $e) { error_log('promo del: '.$e->getMessage()); $msg = 'ลบไม่สำเร็จ'; }
        }
    }
}

$tableMissing = false;
try {
    $promos = $conn->query("SELECT * FROM promos ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('promos load failed: ' . $e->getMessage());
    $promos = [];
    $tableMissing = true;
}
$csrf = $_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รูปโปรโมท/แคปชั่น</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0;font-family:'Prompt',sans-serif;}
    body{background:#F1F5F9;color:#1E293B;padding:20px;}
    .wrap{max-width:760px;margin:0 auto;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .top a{color:#15803D;text-decoration:none;font-weight:600;font-size:0.9rem;}
    .card{background:#FFF;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:16px;}
    p.hint{color:#64748B;font-size:0.85rem;margin-bottom:14px;line-height:1.6;}
    label{display:block;font-weight:600;font-size:0.88rem;margin:12px 0 6px;}
    input[type=text],input[type=number],input[type=file],textarea{width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:8px;font-size:0.95rem;}
    textarea{resize:vertical;min-height:64px;}
    .grid2{display:grid;grid-template-columns:1fr 140px;gap:12px;align-items:end;}
    button{margin-top:14px;padding:11px 16px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:0.92rem;color:#FFF;background:#15803D;}
    .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.9rem;}
    .msg.ok{background:#DCFCE7;color:#15803D;} .msg.err{background:#FEE2E2;color:#DC2626;}
    .promo{display:flex;gap:14px;border:1px solid #EDF2F7;border-radius:10px;padding:12px;margin-bottom:12px;align-items:flex-start;}
    .thumb{width:120px;height:80px;flex:0 0 120px;border-radius:8px;background:#F1F5F9 center/cover no-repeat;border:1px solid #E2E8F0;}
    .pbody{flex:1;min-width:0;}
    .row2{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
    .chk{display:flex;align-items:center;gap:6px;font-size:0.85rem;font-weight:600;margin-top:10px;}
    .chk input{width:auto;}
    .del{background:#FEE2E2;color:#DC2626;} .save{background:#15803D;}
    .badge-off{background:#FEE2E2;color:#DC2626;padding:2px 8px;border-radius:6px;font-size:0.72rem;font-weight:600;}
    .badge-on{background:#DCFCE7;color:#15803D;padding:2px 8px;border-radius:6px;font-size:0.72rem;font-weight:600;}
    small.mut{color:#94A3B8;font-size:0.78rem;}
</style>    <link rel="stylesheet" href="assets/admin-responsive.css">
</head>
<body>
<div class="wrap">
    <div class="top"><h1 style="font-size:1.3rem;">🖼️ รูปโปรโมท / แคปชั่น</h1><a href="admin.php">← กลับแดชบอร์ด</a></div>

    <?php if ($msg): ?><div class="msg <?= $ok?'ok':'err' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php if ($tableMissing): ?>
        <div class="msg err" style="line-height:1.7;">
            ❌ <b>ยังใช้งานหน้านี้ไม่ได้</b> — ยังไม่ได้รัน <code>database_migration.sql</code> (ไม่พบตาราง <code>promos</code>)<br>
            วิธีแก้: phpMyAdmin → เลือกฐานข้อมูล → แท็บ SQL → วาง <code>database_migration.sql</code> ทั้งหมด → Go แล้วรีเฟรช
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="font-size:1.05rem;margin-bottom:6px;">➕ เพิ่มสไลด์ใหม่</h2>
        <p class="hint">รูปแนวนอนจะสวยที่สุด (เช่น 1600×600) · รับไฟล์ JPG/PNG/WEBP ไม่เกิน 5MB · เลขลำดับน้อย = แสดงก่อน</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="do" value="add">
            <label>รูปโปรโมท</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
            <label>หัวข้อ (ตัวใหญ่)</label>
            <input type="text" name="heading" maxlength="255" placeholder="เช่น โปรโมชั่นเดือนนี้" required>
            <label>แคปชั่น (บรรทัดอธิบาย)</label>
            <textarea name="caption" maxlength="500" placeholder="รายละเอียดสั้น ๆ ใต้หัวข้อ"></textarea>
            <div class="grid2">
                <div></div>
                <div><label>ลำดับ</label><input type="number" name="sort_order" value="<?= count($promos)+1 ?>"></div>
            </div>
            <button type="submit">เพิ่มสไลด์</button>
        </form>
    </div>

    <div class="card">
        <h2 style="font-size:1.05rem;margin-bottom:10px;">สไลด์ทั้งหมด (<?= count($promos) ?>)</h2>
        <?php if (empty($promos)): ?>
            <p class="hint">ยังไม่มีสไลด์ — เพิ่มด้านบน (ถ้าไม่มีเลย หน้าแรกจะโชว์สไลด์ต้อนรับเริ่มต้น 1 อัน)</p>
        <?php else: foreach ($promos as $p): ?>
            <form class="promo" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="do" value="update">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <div class="thumb" style="background-image:url('<?= htmlspecialchars($p['image'], ENT_QUOTES) ?>');"></div>
                <div class="pbody">
                    <div class="row2">
                        <span class="<?= $p['is_active']?'badge-on':'badge-off' ?>"><?= $p['is_active']?'แสดงอยู่':'ซ่อนอยู่' ?></span>
                        <small class="mut">#<?= (int)$p['id'] ?></small>
                    </div>
                    <label>หัวข้อ</label>
                    <input type="text" name="heading" maxlength="255" value="<?= htmlspecialchars($p['heading']) ?>" required>
                    <label>แคปชั่น</label>
                    <textarea name="caption" maxlength="500"><?= htmlspecialchars($p['caption'] ?? '') ?></textarea>
                    <div class="grid2">
                        <div><label>เปลี่ยนรูป (ไม่บังคับ)</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
                        <div><label>ลำดับ</label><input type="number" name="sort_order" value="<?= (int)$p['sort_order'] ?>"></div>
                    </div>
                    <label class="chk"><input type="checkbox" name="is_active" <?= $p['is_active']?'checked':'' ?>> แสดงสไลด์นี้บนหน้าแรก</label>
                    <div class="row2" style="margin-top:6px;">
                        <button class="save" type="submit">💾 บันทึก</button>
                        <button class="del" type="submit" form="del_<?= (int)$p['id'] ?>" onclick="return confirm('ลบสไลด์นี้?');">🗑️ ลบ</button>
                    </div>
                </div>
            </form>
            <form id="del_<?= (int)$p['id'] ?>" method="POST" style="display:none;">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="do" value="del">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            </form>
        <?php endforeach; endif; ?>
    </div>
</div>
</body>
</html>
