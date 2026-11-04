<?php
require dirname(__DIR__) . "/app/core/bootstrap.php";
try {
    $route = $_GET["r"] ?? "/";
    if (str_starts_with($route, "/api/")) {
        require dirname(__DIR__) . "/app/controllers/ApiController.php";
        new ApiController()->dispatch(substr($route, 5));
        exit();
    }
    $user = currentUser();
    require dirname(__DIR__) . "/app/views/layout.php";
} catch (PDOException $e) {
    error_log((string) $e);
    if (
        str_starts_with($_GET["r"] ?? "", "/api/") &&
        in_array((string) $e->getCode(), ["23000", "23505"], true)
    ) {
        fail("ข้อมูลซ้ำ หรือมีรายการอื่นอ้างอิงอยู่ กรุณาแก้ข้อมูลก่อน", 409);
    }
    http_response_code(500);
    if (str_starts_with($_GET["r"] ?? "", "/api/")) {
        fail("เชื่อมต่อหรือบันทึกฐานข้อมูลไม่สำเร็จ", 500);
    }
    echo "<h1>กรุณาติดตั้งฐานข้อมูลตาม README.md</h1>";
} catch (Throwable $e) {
    error_log((string) $e);
    if (str_starts_with($_GET["r"] ?? "", "/api/")) {
        fail("ทำรายการไม่สำเร็จ ตรวจสอบข้อมูลซ้ำหรือข้อมูลที่ถูกอ้างอิง", 500);
    }
    http_response_code(500);
    echo "<h1>ยังเชื่อมต่อฐานข้อมูลไม่ได้</h1><p>กรุณาติดตั้งตาม README.md และตรวจ app/core/Config.php</p>";
}
