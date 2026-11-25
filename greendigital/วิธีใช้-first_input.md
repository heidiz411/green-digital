# ใช้ first_input.php แทน seed.php

1. เลิกใช้และลบ seed.php จากชุดที่ติดตั้งไว้ ไม่ต้อง Import demo.sql
2. นักเรียนกรอกผู้ใช้ในตาราง users ผ่าน phpMyAdmin เอง โดยให้มี id 1, 2, 3
3. วาง first_input.php ในโฟลเดอร์ public ของระบบ แล้วเปิด http://localhost/greendigital/public/first_input.php จากเครื่องติดตั้ง
4. เข้าสู่ระบบด้วยอีเมลที่นักเรียนกรอก และรหัสผ่าน Green1234! ทั้งสามบัญชี
5. ลบ first_input.php หลังใช้ เพราะเปิดซ้ำจะตั้งรหัสผ่านทั้งสาม ID กลับเป็น Green1234! อีกครั้ง

ไฟล์นี้ใช้กับฐานข้อมูล greendigital ตาราง users ของระบบฉบับพัฒนาแล้ว โดย UPDATE เฉพาะ password ไม่สร้างบัญชีและไม่เปลี่ยนข้อมูลช่องอื่น หากชื่อฐานข้อมูล/ผู้ใช้/รหัสฐานข้อมูลต่างจาก XAMPP ค่าเริ่มต้น ให้แก้บรรทัด new PDO

ตอนกรอกใน phpMyAdmin ช่อง password ใส่ค่าว่างไว้ชั่วคราว ฟิลด์อื่นที่จำเป็นต้องครบ: email, full_name, role (admin/staff/member), recovery_hash, created_at ให้ active=1 และ session_version=1 ตามค่าเริ่มต้น หากยังไม่ฝึกกู้บัญชี ช่อง recovery_hash ใส่ค่าว่างชั่วคราว แล้วสร้างรหัสกู้คืนภายหลังจากหน้าโปรไฟล์

## ส่วนหลักที่ต้องจำ

```php
$password = password_hash('Green1234!', PASSWORD_DEFAULT);
$sql = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
foreach ([1, 2, 3] as $id) {
    $sql->execute([$password, $id]);
}
```

ทั้งสาม ID ใช้รหัสผ่านเดียวกัน นักเรียนไม่ต้องจำ hash ยาว และยังใช้ password_verify() เข้าสู่ระบบได้ตามเดิม
