<?php
class ApiController
{
    function dispatch(string $action): void
    {
        $post = $_SERVER["REQUEST_METHOD"] === "POST";
        $reads = [
            "session",
            "list",
            "detail",
            "options",
            "dashboard",
            "export",
            "image",
        ];
        if (!in_array($action, $reads, true) && !$post) {
            fail("Method not allowed", 405);
        }
        if ($post) {
            csrf();
        }
        if ($action === "session") {
            $u = currentUser();
            if ($u) {
                unset($u["password"], $u["recovery_hash"]);
            }
            jsonOut(["user" => $u, "csrf" => token()]);
        }
        if (
            in_array($action, ["login", "register", "recover", "reset"], true)
        ) {
            $this->$action();
            return;
        }
        $u = loginRequired();
        switch ($action) {
            case "logout":
                $_SESSION = [];
                session_regenerate_id(true);
                jsonOut(["success" => true]);
            case "profile":
                $d = [
                    "full_name" => textInput("full_name", 120),
                    "phone" => textInput("phone", 30, false),
                    "address" => textInput("address", 1000, false),
                ];
                Store::save("users", $d, $u["id"]);
                $this->ok();
                break;
            case "password":
                if (
                    !password_verify(
                        $_POST["old_password"] ?? "",
                        $u["password"],
                    )
                ) {
                    fail("รหัสผ่านเดิมไม่ถูกต้อง");
                }
                Store::save(
                    "users",
                    [
                        "password" => password_hash(
                            passwordInput("new_password"),
                            PASSWORD_DEFAULT,
                        ),
                        "session_version" => $u["session_version"] + 1,
                    ],
                    $u["id"],
                );
                $_SESSION["version"] = $u["session_version"] + 1;
                $this->ok();
                break;
            case "recovery":
                if (
                    !password_verify(
                        $_POST["old_password"] ?? "",
                        $u["password"],
                    )
                ) {
                    fail("รหัสผ่านเดิมไม่ถูกต้อง");
                }
                $code = bin2hex(random_bytes(12));
                Store::save(
                    "users",
                    [
                        "recovery_hash" => password_hash(
                            $code,
                            PASSWORD_DEFAULT,
                        ),
                        "session_version" => (int) $u["session_version"] + 1,
                    ],
                    $u["id"],
                );
                $_SESSION["version"] = (int) $u["session_version"] + 1;
                jsonOut([
                    "success" => true,
                    "message" =>
                        "เก็บรหัสกู้คืนใหม่นี้ไว้ รหัสเดิมใช้ไม่ได้แล้ว",
                    "recovery_code" => $code,
                ]);
                break;
            case "options":
                jsonOut([
                    "types" => Store::all("SELECT * FROM types ORDER BY name"),
                    "organizations" => Store::all(
                        "SELECT * FROM organizations ORDER BY name",
                    ),
                ]);
                break;
            case "list":
            case "export":
                $rows = $this->listing($u);
                if ($action === "export") {
                    $this->csv($rows);
                }
                jsonOut(["data" => $rows]);
                break;
            case "detail":
                $row = $this->detail($u);
                jsonOut($row);
                break;
            case "save":
                $this->save($u);
                break;
            case "delete":
                $this->delete($u);
                break;
            case "review":
                requireStaff($u);
                $r = $this->record((int) ($_POST["id"] ?? 0), $u);
                $status = $_POST["status"] ?? "";
                if (
                    !in_array(
                        $status,
                        ["approved", "rejected", "pending"],
                        true,
                    )
                ) {
                    fail("สถานะไม่ถูกต้อง");
                }
                Store::save(
                    "records",
                    [
                        "status" => $status,
                        "review_note" => textInput("review_note", 255, false),
                        "reviewer_id" => $u["id"],
                    ],
                    $r["id"],
                );
                $this->ok();
                break;
            case "reply":
                requireStaff($u);
                $id = (int) ($_POST["id"] ?? 0);
                if (!Store::one("SELECT id FROM reports WHERE id=?", [$id])) {
                    fail("ไม่พบรายงาน", 404);
                }
                Store::save(
                    "reports",
                    ["is_read" => 1, "reply" => textInput("reply", 2000)],
                    $id,
                );
                $this->ok();
                break;
            case "goal":
                $month = $_POST["month"] ?? "";
                if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                    fail("เดือนไม่ถูกต้อง");
                }
                $old = Store::one(
                    "SELECT id FROM goals WHERE user_id=? AND month=?",
                    [$u["id"], $month],
                );
                Store::save(
                    "goals",
                    [
                        "user_id" => $u["id"],
                        "month" => $month,
                        "target" => positive("target"),
                    ],
                    $old["id"] ?? 0,
                );
                $this->ok();
                break;
            case "dashboard":
                $this->dashboard($u);
                break;
            case "image":
                $kind = $_GET["kind"] ?? "";
                if (!in_array($kind, ["records", "contents"], true)) {
                    fail("ไม่พบ", 404);
                }
                $row = $this->detail($u);
                $name = $row["image"] ?? "";
                if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name)) {
                    fail("ไม่พบภาพ", 404);
                }
                $path = dirname(__DIR__, 2) . "/storage/" . $name;
                if (!is_file($path)) {
                    fail("ไม่พบภาพ", 404);
                }
                header(
                    "Content-Type: " .
                        new finfo(FILEINFO_MIME_TYPE)->file($path),
                );
                readfile($path);
                exit();
            default:
                fail("ไม่พบเส้นทาง", 404);
        }
    }
    function ok(): void
    {
        jsonOut(["success" => true, "message" => "บันทึกเรียบร้อย"]);
    }
    function throttle(string $name, string $key): void
    {
        $bucket = hash("sha256", $name . $key);
        $now = time();
        Store::run("DELETE FROM attempts WHERE attempted_at<?", [$now - 900]);
        $n = Store::one("SELECT COUNT(*) n FROM attempts WHERE bucket=?", [
            $bucket,
        ]);
        if ((int) $n["n"] >= 8) {
            fail("ลองหลายครั้งเกินไป กรุณารอ 15 นาที", 429);
        }
        Store::save("attempts", ["bucket" => $bucket, "attempted_at" => $now]);
    }
    function login(): void
    {
        $email = emailInput();
        $this->throttle("login", $email);
        $u = Store::one("SELECT * FROM users WHERE email=?", [$email]);
        if (
            !$u ||
            !$u["active"] ||
            !password_verify($_POST["password"] ?? "", $u["password"])
        ) {
            fail("อีเมลหรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกระงับ", 401);
        }
        Store::run("DELETE FROM attempts WHERE bucket=?", [
            hash("sha256", "login" . $email),
        ]);
        session_regenerate_id(true);
        $_SESSION["uid"] = $u["id"];
        $_SESSION["version"] = $u["session_version"];
        $this->ok();
    }
    function register(): void
    {
        $this->throttle("register", $_SERVER["REMOTE_ADDR"] ?? "local");
        $email = emailInput();
        $pwd = passwordInput();
        if ($pwd !== ($_POST["password_confirm"] ?? "")) {
            fail("ยืนยันรหัสผ่านไม่ตรงกัน");
        }
        if (Store::one("SELECT id FROM users WHERE email=?", [$email])) {
            fail("อีเมลนี้ถูกใช้แล้ว", 409);
        }
        $code = bin2hex(random_bytes(12));
        Store::save("users", [
            "email" => $email,
            "password" => password_hash($pwd, PASSWORD_DEFAULT),
            "full_name" => textInput("full_name", 120),
            "recovery_hash" => password_hash($code, PASSWORD_DEFAULT),
            "created_at" => date("Y-m-d H:i:s"),
        ]);
        jsonOut([
            "success" => true,
            "message" => "สมัครสำเร็จ เก็บรหัสกู้คืนไว้ในที่ปลอดภัย",
            "recovery_code" => $code,
        ]);
    }
    function recover(): void
    {
        unset(
            $_SESSION["reset_uid"],
            $_SESSION["reset_until"],
            $_SESSION["reset_version"],
        );
        $email = emailInput();
        $this->throttle("recover", $email);
        $u = Store::one("SELECT * FROM users WHERE email=?", [$email]);
        if (
            !$u ||
            !$u["active"] ||
            !password_verify($_POST["recovery_code"] ?? "", $u["recovery_hash"])
        ) {
            fail("ข้อมูลกู้คืนไม่ถูกต้อง", 401);
        }
        $_SESSION["reset_version"] = (int) $u["session_version"];
        $_SESSION["reset_uid"] = $u["id"];
        $_SESSION["reset_until"] = time() + 300;
        session_regenerate_id(true);
        $this->ok();
    }
    function reset(): void
    {
        if (
            empty($_SESSION["reset_uid"]) ||
            ($_SESSION["reset_until"] ?? 0) < time()
        ) {
            fail("กรุณายืนยันรหัสกู้คืนอีกครั้ง", 403);
        }
        $pwd = passwordInput("new_password");
        $code = bin2hex(random_bytes(12));
        $u = Store::one("SELECT * FROM users WHERE id=?", [
            $_SESSION["reset_uid"],
        ]);
        if (
            !$u ||
            !$u["active"] ||
            (int) $u["session_version"] !== ($_SESSION["reset_version"] ?? 0)
        ) {
            fail("กรุณายืนยันการกู้คืนอีกครั้ง", 403);
        }
        Store::save(
            "users",
            [
                "password" => password_hash($pwd, PASSWORD_DEFAULT),
                "recovery_hash" => password_hash($code, PASSWORD_DEFAULT),
                "session_version" => $u["session_version"] + 1,
            ],
            $u["id"],
        );
        unset(
            $_SESSION["reset_uid"],
            $_SESSION["reset_until"],
            $_SESSION["uid"],
        );
        jsonOut([
            "success" => true,
            "message" => "เปลี่ยนรหัสผ่านแล้ว เก็บรหัสกู้คืนชุดใหม่",
            "recovery_code" => $code,
        ]);
    }
    function kind(): string
    {
        $k = $_POST["kind"] ?? ($_GET["kind"] ?? "");
        if (
            !in_array(
                $k,
                [
                    "types",
                    "organizations",
                    "users",
                    "contents",
                    "records",
                    "reports",
                ],
                true,
            )
        ) {
            fail("รายการไม่ถูกต้อง");
        }
        return $k;
    }
    function listing(array $u): array
    {
        $k = $this->kind();
        if ($k === "records") {
            return Store::records($u, $_GET);
        }
        if ($k === "users") {
            if (!admin($u)) {
                fail("เฉพาะผู้ดูแล", 403);
            }
            return Store::all(
                "SELECT id,email,full_name,role,active,created_at FROM users ORDER BY id DESC",
            );
        }
        if ($k === "reports") {
            return Store::all(
                "SELECT r.*,u.full_name FROM reports r JOIN users u ON u.id=r.user_id" .
                    (staff($u) ? "" : " WHERE r.user_id=?") .
                    " ORDER BY r.id DESC",
                staff($u) ? [] : [$u["id"]],
            );
        }
        if ($k === "contents" && !staff($u)) {
            return Store::all(
                "SELECT * FROM contents WHERE active=1 AND (publish_at IS NULL OR publish_at<=?) AND (expire_at IS NULL OR expire_at>?) ORDER BY id DESC",
                [date("Y-m-d H:i:s"), date("Y-m-d H:i:s")],
            );
        }
        return Store::all("SELECT * FROM $k ORDER BY id DESC");
    }
    function record(int $id, array $u): array
    {
        $r = Store::one("SELECT * FROM records WHERE id=?", [$id]);
        if (!$r) {
            fail("ไม่พบรายการ", 404);
        }
        if (!staff($u) && (int) $r["user_id"] !== (int) $u["id"]) {
            fail("ไม่มีสิทธิ์", 403);
        }
        return $r;
    }
    function detail(array $u): array
    {
        $id = (int) ($_GET["id"] ?? 0);
        $k = $this->kind();
        if ($k === "records") {
            return $this->record($id, $u);
        }
        foreach ($this->listing($u) as $r) {
            if ((int) $r["id"] === $id) {
                return $r;
            }
        }
        fail("ไม่พบข้อมูล", 404);
    }
    function upload(?string $old): ?string
    {
        if (
            empty($_FILES["image"]) ||
            $_FILES["image"]["error"] === UPLOAD_ERR_NO_FILE
        ) {
            return $old;
        }
        $f = $_FILES["image"];
        if ($f["error"] !== UPLOAD_ERR_OK || $f["size"] > 3 * 1024 * 1024) {
            fail("ภาพต้องไม่เกิน 3 MB");
        }
        $mime = new finfo(FILEINFO_MIME_TYPE)->file($f["tmp_name"]);
        $ext =
            [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp",
            ][$mime] ?? null;
        if (!$ext || !getimagesize($f["tmp_name"])) {
            fail("รองรับภาพ JPG PNG WEBP เท่านั้น");
        }
        $name = bin2hex(random_bytes(16)) . "." . $ext;
        if (
            !move_uploaded_file(
                $f["tmp_name"],
                dirname(__DIR__, 2) . "/storage/" . $name,
            )
        ) {
            fail("บันทึกภาพไม่สำเร็จ", 500);
        }
        return $name;
    }
    function save(array $u): void
    {
        $k = $this->kind();
        $id = (int) ($_POST["id"] ?? 0);
        $old = $id ? Store::one("SELECT * FROM $k WHERE id=?", [$id]) : null;
        if ($id && !$old) {
            fail("ไม่พบข้อมูล", 404);
        }
        if (in_array($k, ["types", "organizations", "contents"], true)) {
            requireStaff($u);
        }
        $d = [];
        switch ($k) {
            case "types":
                $d = [
                    "name" => textInput("name", 120),
                    "price" => positive("price", true),
                ];
                break;
            case "organizations":
                $d = ["name" => textInput("name", 120)];
                break;
            case "users":
                if (!admin($u)) {
                    fail("เฉพาะผู้ดูแล", 403);
                }
                $role = $_POST["role"] ?? "";
                if (!in_array($role, ["admin", "staff", "member"], true)) {
                    fail("บทบาทไม่ถูกต้อง");
                }
                $active = (int) ($_POST["active"] ?? 0) === 1 ? 1 : 0;
                if ($id === (int) $u["id"] && (!$active || $role !== "admin")) {
                    fail("ไม่สามารถระงับหรือลดสิทธิ์ตนเอง");
                }
                $d = [
                    "email" => emailInput(),
                    "full_name" => textInput("full_name", 120),
                    "role" => $role,
                    "active" => $active,
                ];
                if ($old) {
                    $d["session_version"] =
                        $old["session_version"] +
                        ($role !== $old["role"] ||
                        $active !== (int) $old["active"]
                            ? 1
                            : 0);
                } else {
                    $d["password"] = password_hash(
                        passwordInput(),
                        PASSWORD_DEFAULT,
                    );
                    $d["recovery_hash"] = password_hash(
                        bin2hex(random_bytes(24)),
                        PASSWORD_DEFAULT,
                    );
                    $d["created_at"] = date("Y-m-d H:i:s");
                }
                break;
            case "contents":
                $start = $this->datetime("publish_at");
                $end = $this->datetime("expire_at");
                if ($start && $end && $start >= $end) {
                    fail("วันปิดต้องหลังวันเปิด");
                }
                $d = [
                    "title" => textInput("title", 200),
                    "body" => textInput("body", 10000),
                    "publish_at" => $start,
                    "expire_at" => $end,
                    "active" => (int) ($_POST["active"] ?? 0) === 1 ? 1 : 0,
                ];
                if (!$id) {
                    $d["created_at"] = date("Y-m-d H:i:s");
                }
                break;
            case "records":
                if ($id) {
                    $old = $this->record($id, $u);
                    if ($old["status"] === "approved") {
                        fail("ยกเลิกการตรวจรับก่อนแก้ไข");
                    }
                }
                $type = Store::one("SELECT * FROM types WHERE id=?", [
                    (int) ($_POST["type_id"] ?? 0),
                ]);
                $org = (int) ($_POST["org_id"] ?? 0);
                if (
                    !$type ||
                    !Store::one("SELECT id FROM organizations WHERE id=?", [
                        $org,
                    ])
                ) {
                    fail("เลือกประเภทและองค์กร");
                }
                $date = $_POST["record_date"] ?? "";
                $dt = DateTime::createFromFormat("!Y-m-d", $date);
                if (
                    !$dt ||
                    $dt->format("Y-m-d") !== $date ||
                    $date > date("Y-m-d")
                ) {
                    fail("วันที่ไม่ถูกต้องหรือเป็นอนาคต");
                }
                $d = [
                    "user_id" => $old["user_id"] ?? $u["id"],
                    "org_id" => $org,
                    "type_id" => $type["id"],
                    "weight" => positive("weight"),
                    "price" => $type["price"],
                    "record_date" => $date,
                    "note" => textInput("note", 2000, false),
                    "status" => "pending",
                    "review_note" => "",
                    "reviewer_id" => null,
                ];
                if (!$id) {
                    $d["created_at"] = date("Y-m-d H:i:s");
                }
                break;
            case "reports":
                if ($id) {
                    fail("แก้ไขข้อความที่ส่งแล้วไม่ได้");
                }
                $d = [
                    "user_id" => $u["id"],
                    "message" => textInput("message", 2000),
                    "created_at" => date("Y-m-d H:i:s"),
                ];
                break;
        }
        $newImage = null;
        if (in_array($k, ["contents", "records"], true)) {
            $newImage = $this->upload($old["image"] ?? null);
            $d["image"] = $newImage;
        }
        try {
            $savedId = Store::save($k, $d, $id);
        } catch (Throwable $e) {
            if ($newImage && $newImage !== ($old["image"] ?? null)) {
                @unlink(dirname(__DIR__, 2) . "/storage/" . $newImage);
            }
            throw $e;
        }
        if ($newImage && !empty($old["image"]) && $newImage !== $old["image"]) {
            @unlink(dirname(__DIR__, 2) . "/storage/" . $old["image"]);
        }
        jsonOut([
            "success" => true,
            "message" => "บันทึกเรียบร้อย",
            "id" => $savedId,
        ]);
    }
    function datetime(string $key): ?string
    {
        $v = trim($_POST[$key] ?? "");
        if ($v === "") {
            return null;
        }
        $d = DateTime::createFromFormat("!Y-m-d\TH:i", $v);
        if (!$d || $d->format("Y-m-d\TH:i") !== $v) {
            fail("วันเวลาไม่ถูกต้อง");
        }
        return $d->format("Y-m-d H:i:s");
    }
    function delete(array $u): void
    {
        $k = $this->kind();
        $id = (int) ($_POST["id"] ?? 0);
        if ($k === "users" || $k === "reports") {
            fail("ใช้การระงับสมาชิก หรืออ่านและตอบรายงานแทนการลบ");
        }
        if ($k === "records") {
            $r = $this->record($id, $u);
            if ($r["status"] === "approved") {
                fail("ยกเลิกการตรวจรับก่อนลบ");
            }
        } else {
            requireStaff($u);
            $r = Store::one("SELECT * FROM $k WHERE id=?", [$id]);
            if (!$r) {
                fail("ไม่พบ", 404);
            }
        }
        Store::run("DELETE FROM $k WHERE id=?", [$id]);
        if (!empty($r["image"])) {
            @unlink(dirname(__DIR__, 2) . "/storage/" . $r["image"]);
        }
        $this->ok();
    }
    function csv(array $rows): void
    {
        header("Content-Type: text/csv; charset=utf-8");
        header('Content-Disposition: attachment; filename="greendigital.csv"');
        $out = fopen("php://output", "w");
        fwrite($out, "\xEF\xBB\xBF");
        if (!$rows) {
            fputcsv($out, ["ไม่มีข้อมูลตามตัวกรอง"], ",", '"', "");
        }
        if ($rows) {
            fputcsv($out, array_keys($rows[0]), ",", '"', "");
            foreach ($rows as $r) {
                foreach ($r as &$v) {
                    if (is_string($v) && preg_match('/^[=+@\-\t\r]/', $v)) {
                        $v = "'" . $v;
                    }
                }
                unset($v);
                fputcsv($out, $r, ",", '"', "");
            }
        }
        fclose($out);
        exit();
    }
    function dashboard(array $u): void
    {
        $rows = Store::records($u, $_GET);
        $kg = 0;
        $value = 0;
        $byType = [];
        $byMonth = [];
        $pending = 0;
        foreach ($rows as $r) {
            if ($r["status"] === "pending") {
                $pending++;
            }
            if ($r["status"] !== "approved") {
                continue;
            }
            $w = (float) $r["weight"];
            $kg += $w;
            $value += $w * (float) $r["price"];
            $byType[$r["type_name"]] = ($byType[$r["type_name"]] ?? 0) + $w;
            $m = substr($r["record_date"], 0, 7);
            $byMonth[$m] = ($byMonth[$m] ?? 0) + $w;
        }
        ksort($byMonth);
        $month = date("Y-m");
        $own = Store::all(
            "SELECT weight FROM records WHERE user_id=? AND status=? AND record_date>=? AND record_date<=?",
            [$u["id"], "approved", $month . "-01", date("Y-m-t")],
        );
        $ownKg = round(array_sum(array_column($own, "weight")), 3);
        $goal = Store::one(
            "SELECT target FROM goals WHERE user_id=? AND month=?",
            [$u["id"], $month],
        );
        $notifications = staff($u)
            ? Store::one("SELECT COUNT(*) n FROM reports WHERE is_read=0")["n"]
            : 0;
        $newUsers = staff($u)
            ? Store::one("SELECT COUNT(*) n FROM users WHERE created_at>=?", [
                date("Y-m-d") . " 00:00:00",
            ])["n"]
            : 0;
        $members = staff($u)
            ? Store::one("SELECT COUNT(*) n FROM users")["n"]
            : null;
        $organizations = staff($u)
            ? Store::one("SELECT COUNT(*) n FROM organizations")["n"]
            : null;
        jsonOut([
            "members" => $members,
            "organizations" => $organizations,
            "records" => count($rows),
            "kg" => round($kg, 3),
            "value" => round($value, 2),
            "pending" => $pending,
            "byType" => $byType,
            "byMonth" => $byMonth,
            "month" => $month,
            "ownKg" => $ownKg,
            "target" => $goal["target"] ?? 0,
            "points" => (int) floor(round($ownKg * 10, 6)),
            "notifications" => $notifications,
            "newUsers" => $newUsers,
        ]);
    }
}
