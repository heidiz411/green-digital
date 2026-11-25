<?php
// เปิดจาก localhost เพื่อตั้งรหัสผ่านบัญชีฝึก แล้วลบไฟล์นี้หลังใช้
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('กรุณาเปิดจาก localhost');
}
$db = new PDO('mysql:host=127.0.0.1;dbname=greendigital;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$password = password_hash('Green1234!', PASSWORD_DEFAULT);
$sql = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
foreach ([1, 2, 3] as $id) {
    $sql->execute([$password, $id]);
}
echo 'ตั้งรหัสผ่าน Green1234! ให้ ID 1, 2, 3 ที่มีอยู่แล้ว กรุณาลบ first_input.php หลังใช้งาน';
