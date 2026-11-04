<?php
// ใช้ผ่าน command line เท่านั้น ไม่เปิดตัวติดตั้งบนเว็บ
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit();
}
require __DIR__ . "/app/core/bootstrap.php";
$c = require __DIR__ . "/app/core/Config.php";
if ($c["driver"] === "sqlite") {
    if (
        Store::db()
            ->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='users'",
            )
            ->fetchColumn()
    ) {
        exit("ฐานข้อมูลมีอยู่แล้ว ไม่เปลี่ยนข้อมูล\n");
    }
    Store::db()->exec(file_get_contents(__DIR__ . "/sql/schema.sqlite.sql"));
}
if (Store::one("SELECT id FROM users LIMIT 1")) {
    exit("มีผู้ใช้แล้ว ไม่สร้างบัญชีซ้ำ\n");
}
foreach (
    [
        ["admin@example.test", "ผู้ดูแลฝึกซ้อม", "admin"],
        ["staff@example.test", "เจ้าหน้าที่ตรวจรับ", "staff"],
        ["student@example.test", "นักเรียนฝึกซ้อม", "member"],
    ]
    as [$email, $name, $role]
) {
    Store::save("users", [
        "email" => $email,
        "full_name" => $name,
        "role" => $role,
        "password" => password_hash("Green1234!", PASSWORD_DEFAULT),
        "recovery_hash" => password_hash(
            "demo-recovery-2026",
            PASSWORD_DEFAULT,
        ),
        "created_at" => date("Y-m-d H:i:s"),
    ]);
}
Store::save("contents", [
    "title" => "แยกขยะก่อนส่งรีไซเคิล",
    "body" =>
        "ล้างขวดให้สะอาด แยกฝา และทำให้แห้งก่อนนำมาชั่งน้ำหนัก บันทึกข้อมูลตามจริงและรอเจ้าหน้าที่ตรวจรับ",
    "active" => 1,
    "created_at" => date("Y-m-d H:i:s"),
]);
echo "สร้างบัญชีฝึกซ้อม 3 บัญชีแล้ว รหัสผ่าน Green1234! / รหัสกู้คืน demo-recovery-2026\n";
