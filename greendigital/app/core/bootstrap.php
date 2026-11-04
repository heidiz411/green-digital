<?php
declare(strict_types=1);
date_default_timezone_set("Asia/Bangkok");
ini_set("session.use_strict_mode", "1");
session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Lax",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
]);
session_start();
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store");
require_once __DIR__ . "/../models/Store.php";
function jsonOut($data, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
function fail(string $message, int $status = 422): void
{
    jsonOut(["success" => false, "message" => $message], $status);
}
function esc($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, "UTF-8");
}
function token(): string
{
    return $_SESSION["csrf"] ??= bin2hex(random_bytes(32));
}
function csrf(): void
{
    if (
        !is_string($_POST["_csrf"] ?? null) ||
        !hash_equals(token(), $_POST["_csrf"])
    ) {
        fail("กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง", 419);
    }
}
function currentUser(): ?array
{
    if (empty($_SESSION["uid"])) {
        return null;
    }
    $u = Store::one("SELECT * FROM users WHERE id=?", [$_SESSION["uid"]]);
    if (
        !$u ||
        !$u["active"] ||
        (int) $u["session_version"] !== (int) ($_SESSION["version"] ?? 0)
    ) {
        unset($_SESSION["uid"]);
        return null;
    }
    return $u;
}
function loginRequired(): array
{
    $u = currentUser();
    if (!$u) {
        fail("กรุณาเข้าสู่ระบบ", 401);
    }
    return $u;
}
function admin(array $u): bool
{
    return $u["role"] === "admin";
}
function staff(array $u): bool
{
    return in_array($u["role"], ["admin", "staff"], true);
}
function requireStaff(array $u): void
{
    if (!staff($u)) {
        fail("ไม่มีสิทธิ์เข้าถึง", 403);
    }
}
function textInput(string $name, int $max = 255, bool $required = true): string
{
    $v = trim((string) ($_POST[$name] ?? ""));
    if (($required && $v === "") || mb_strlen($v) > $max) {
        fail("ตรวจสอบช่อง " . $name);
    }
    return $v;
}
function positive(string $name, bool $zero = false): float
{
    $v = filter_var($_POST[$name] ?? null, FILTER_VALIDATE_FLOAT);
    if (
        $v === false ||
        !is_finite($v) ||
        ($zero ? $v < 0 : $v < 0.001) ||
        $v > 1000000
    ) {
        fail("ค่าตัวเลขไม่ถูกต้อง: " . $name);
    }
    if (abs($v - round($v, $name === "price" ? 2 : 3)) > 0.00000001) {
        fail("ทศนิยมเกินกำหนด: " . $name);
    }
    return $v;
}
function passwordInput(string $name = "password"): string
{
    $v = (string) ($_POST[$name] ?? "");
    if (strlen($v) < 8 || strlen($v) > 72) {
        fail("รหัสผ่านต้องยาว 8–72 ไบต์");
    }
    return $v;
}
function emailInput(): string
{
    $v = strtolower(textInput("email"));
    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
        fail("อีเมลไม่ถูกต้อง");
    }
    return $v;
}
