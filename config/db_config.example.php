<?php
// ตัวอย่างไฟล์ตั้งค่าฐานข้อมูล — คัดลอกเป็น db_config.php แล้วใส่ค่าจริง
// (db_config.php อยู่ใน .gitignore จะไม่ถูก commit)
return [
    'host'     => 'localhost',
    'dbname'   => 'your_db_name',
    'username' => 'your_db_user',
    'password' => 'your_db_password',
    // กุญแจลับกันบอท — สุ่มด้วย: php -r "echo bin2hex(random_bytes(24));"
    'app_secret' => 'random-long-secret-here',
];
