<?php
// XAMPP: ใช้ mysql ตามค่าเริ่มต้น / SQLite ใช้สำหรับทดลองและทดสอบเท่านั้น
return [
    "driver" => getenv("GD_DRIVER") ?: "mysql",
    "host" => getenv("GD_HOST") ?: "127.0.0.1",
    "port" => getenv("GD_PORT") ?: "3306",
    "database" => getenv("GD_DATABASE") ?: "greendigital",
    "user" => getenv("GD_USER") ?: "root",
    "password" => getenv("GD_PASSWORD") ?: "",
    "sqlite" =>
        getenv("GD_SQLITE") ?: dirname(__DIR__, 2) . "/storage/demo.sqlite",
];
