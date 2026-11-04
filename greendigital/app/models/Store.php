<?php
class Store
{
    static ?PDO $db = null;
    static function db(): PDO
    {
        if (self::$db) {
            return self::$db;
        }
        $c = require __DIR__ . "/../core/Config.php";
        $dsn =
            $c["driver"] === "sqlite"
                ? "sqlite:" . $c["sqlite"]
                : "mysql:host=" .
                    $c["host"] .
                    ";port=" .
                    $c["port"] .
                    ";dbname=" .
                    $c["database"] .
                    ";charset=utf8mb4";
        self::$db = new PDO($dsn, $c["user"], $c["password"], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if ($c["driver"] === "sqlite") {
            self::$db->exec("PRAGMA foreign_keys=ON");
        }
        return self::$db;
    }
    static function run(string $sql, array $args = []): PDOStatement
    {
        $s = self::db()->prepare($sql);
        $s->execute($args);
        return $s;
    }
    static function all(string $sql, array $args = []): array
    {
        return self::run($sql, $args)->fetchAll();
    }
    static function one(string $sql, array $args = []): ?array
    {
        return self::run($sql, $args)->fetch() ?: null;
    }
    // ตารางและคอลัมน์มาจากโค้ดที่กำหนดไว้เท่านั้น ไม่รับชื่อจากผู้ใช้
    static function save(string $table, array $data, int $id = 0): int
    {
        if ($id) {
            $set = implode(
                ",",
                array_map(fn($k) => $k . "=?", array_keys($data)),
            );
            self::run("UPDATE $table SET $set WHERE id=?", [
                ...array_values($data),
                $id,
            ]);
            return $id;
        }
        $keys = implode(",", array_keys($data));
        $marks = implode(",", array_fill(0, count($data), "?"));
        self::run(
            "INSERT INTO $table ($keys) VALUES ($marks)",
            array_values($data),
        );
        return (int) self::db()->lastInsertId();
    }
    static function records(array $u, array $f): array
    {
        $sql =
            "SELECT r.*,u.full_name,o.name AS organization,t.name AS type_name FROM records r JOIN users u ON u.id=r.user_id JOIN organizations o ON o.id=r.org_id JOIN types t ON t.id=r.type_id WHERE 1=1";
        $args = [];
        if (!staff($u)) {
            $sql .= " AND r.user_id=?";
            $args[] = $u["id"];
        }
        foreach (["org_id", "type_id", "status"] as $k) {
            if (($f[$k] ?? "") !== "") {
                $sql .= " AND r.$k=?";
                $args[] = $f[$k];
            }
        }
        foreach (["from" => ">=", "to" => "<="] as $k => $op) {
            if (!empty($f[$k])) {
                $date = DateTime::createFromFormat("!Y-m-d", $f[$k]);
                if (!$date || $date->format("Y-m-d") !== $f[$k]) {
                    fail("วันที่ไม่ถูกต้อง");
                }
                $sql .= " AND r.record_date $op ?";
                $args[] = $f[$k];
            }
        }
        if (!empty($f["from"]) && !empty($f["to"]) && $f["from"] > $f["to"]) {
            fail("วันเริ่มต้องไม่เกินวันสิ้นสุด");
        }
        if (!empty($f["q"])) {
            $sql .=
                " AND (r.note LIKE ? OR t.name LIKE ? OR u.full_name LIKE ?)";
            $q = "%" . $f["q"] . "%";
            array_push($args, $q, $q, $q);
        }
        return self::all($sql . " ORDER BY r.id DESC", $args);
    }
}
