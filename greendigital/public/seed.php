<?php
// สูตรจำ: รับค่า → ตรวจ → hash → บันทึก (เปิดครั้งแรกที่เครื่องนี้เท่านั้น)
require dirname(__DIR__) . '/app/core/bootstrap.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('กรุณาเปิดหน้าตั้งต้นจาก localhost บนเครื่องที่ติดตั้งระบบ');
}
$error = ''; $done = false; $ready = false;
try {
    $ready = !Store::one('SELECT id FROM users LIMIT 1');
    $orgs = Store::all('SELECT id,name FROM organizations ORDER BY id');
    $types = Store::all('SELECT id,name,price FROM types ORDER BY id');
} catch (Throwable $e) {
    $error = 'กรุณา Import sql/greendigital.sql และตรวจ Config.php ก่อนเปิดหน้านี้';
}
$roles = ['admin'=>'ผู้ดูแล', 'staff'=>'เจ้าหน้าที่', 'member'=>'สมาชิก'];
$users = $_POST['users'] ?? [
    ['full_name'=>'ผู้ดูแลฝึกซ้อม','email'=>'admin@example.test'],
    ['full_name'=>'เจ้าหน้าที่ตรวจรับ','email'=>'staff@example.test'],
    ['full_name'=>'นักเรียนฝึกซ้อม','email'=>'student@example.test']
];
$title = $_POST['title'] ?? 'แยกขยะก่อนส่งรีไซเคิล';
$body = $_POST['body'] ?? 'ล้างขวดให้สะอาด แยกฝา และทำให้แห้งก่อนนำมาชั่งน้ำหนัก บันทึกข้อมูลตามจริงและรอเจ้าหน้าที่ตรวจรับ';
$target = $_POST['target'] ?? 50;
$rows = $_POST['rows'] ?? [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    foreach ([[0,0,12.5,0,'approved'],[0,1,20,12,'approved'],[1,2,3.2,35,'approved'],[1,0,8,50,'approved'],[0,1,5,0,'pending']] as [$o,$t,$w,$days,$status]) {
        $rows[] = ['org_id'=>$orgs[$o]['id']??'', 'type_id'=>$types[$t]['id']??'',
            'weight'=>$w, 'record_date'=>date('Y-m-d', strtotime("-$days days")),
            'status'=>$status, 'note'=>'ข้อมูลสมมติสำหรับฝึกซ้อม'];
    }
}
function seedCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    try {
        seedCheck(is_string($_POST['_csrf']??null) && hash_equals(token(), $_POST['_csrf']), 'กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
        $password = (string)($_POST['password']??'');
        $recovery = trim((string)($_POST['recovery']??''));
        seedCheck(strlen($password)>=8 && strlen($password)<=72, 'รหัสผ่านต้องยาว 8–72 ไบต์');
        seedCheck(strlen($recovery)>=8 && strlen($recovery)<=72, 'รหัสกู้คืนต้องยาว 8–72 ไบต์');
        seedCheck(is_array($users) && count($users)===3 && is_array($rows) && count($rows)<=5, 'รูปแบบข้อมูลไม่ถูกต้อง');
        seedCheck(is_numeric($target) && $target>0 && $target<=1000000 && abs($target-round($target,3))<0.00000001, 'เป้าหมายต้องมากกว่าศูนย์และมีทศนิยมไม่เกิน 3 ตำแหน่ง');
        seedCheck(is_string($title) && trim($title)!=='' && mb_strlen($title)<=200 && is_string($body) && trim($body)!=='' && mb_strlen($body)<=10000, 'ตรวจชื่อบทความและรายละเอียด');
        $db = Store::db(); $db->beginTransaction();
        seedCheck(!Store::one('SELECT id FROM users LIMIT 1'), 'มีข้อมูลเริ่มต้นแล้ว');
        $ids = []; $now = date('Y-m-d H:i:s');
        foreach (array_keys($roles) as $i=>$role) {
            $name = trim((string)($users[$i]['full_name']??''));
            $email = strtolower(trim((string)($users[$i]['email']??'')));
            seedCheck($name!=='' && mb_strlen($name)<=120 && strlen($email)<=255 && filter_var($email,FILTER_VALIDATE_EMAIL)!==false, 'ตรวจชื่อและอีเมลบัญชีที่ '.($i+1));
            $ids[$role] = Store::save('users', [
                'full_name'=>$name, 'email'=>$email, 'role'=>$role,
                'password'=>password_hash($password, PASSWORD_DEFAULT),
                'recovery_hash'=>password_hash($recovery, PASSWORD_DEFAULT), 'created_at'=>$now
            ]);
        }
        Store::save('contents', ['title'=>trim($title),'body'=>trim($body),'active'=>1,'created_at'=>$now]);
        foreach ($rows as $r) {
            if (($r['weight']??'')==='') continue; // เว้นน้ำหนักว่างเพื่อข้ามแถว
            $weight = filter_var($r['weight'], FILTER_VALIDATE_FLOAT);
            $org = (int)($r['org_id']??0); $type = (int)($r['type_id']??0);
            $date = (string)($r['record_date']??''); $d = DateTime::createFromFormat('!Y-m-d',$date);
            $status = $r['status']??''; $note = trim((string)($r['note']??''));
            $typeRow = Store::one('SELECT price FROM types WHERE id=?',[$type]);
            seedCheck($weight!==false && is_finite($weight) && $weight>=0.001 && $weight<=1000000 && abs($weight-round($weight,3))<0.00000001, 'น้ำหนักต้องมากกว่าศูนย์และมีทศนิยมไม่เกิน 3 ตำแหน่ง');
            seedCheck($typeRow!==null && Store::one('SELECT id FROM organizations WHERE id=?',[$org])!==null, 'เลือกองค์กรและประเภทให้ถูกต้อง');
            seedCheck($d!==false && $d->format('Y-m-d')===$date && $date<=date('Y-m-d'), 'วันที่ไม่ถูกต้องหรือเป็นอนาคต');
            seedCheck(in_array($status,['pending','approved'],true) && mb_strlen($note)<=2000, 'สถานะหรือหมายเหตุไม่ถูกต้อง');
            Store::save('records', [
                'user_id'=>$ids['member'],'org_id'=>$org,'type_id'=>$type,'weight'=>$weight,
                'price'=>$typeRow['price'],'record_date'=>$date,'note'=>$note,'status'=>$status,
                'reviewer_id'=>$status==='approved'?$ids['staff']:null,'created_at'=>$now
            ]);
        }
        Store::save('goals',['user_id'=>$ids['member'],'month'=>date('Y-m'),'target'=>$target]);
        $db->commit(); $done = true; $ready = false;
    } catch (Throwable $e) {
        if (Store::$db && Store::$db->inTransaction()) Store::$db->rollBack();
        $error = $e instanceof PDOException ? 'อีเมลซ้ำ หรือบันทึกฐานข้อมูลไม่สำเร็จ ยังไม่เพิ่มข้อมูล กรุณาตรวจอีกครั้ง' : $e->getMessage();
    }
}
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>กรอกข้อมูลเริ่มต้น Green Digital</title><link rel="stylesheet" href="assets/vendor/bootstrap.min.css"><style>body{background:#f4f7f1;font-family:Tahoma,sans-serif}main{max-width:1100px;margin:32px auto;padding:24px;background:white;border-radius:18px}label{margin-top:12px}h2{font-size:1.25rem;margin-top:28px}td{min-width:135px}td input,td select{min-width:125px}</style></head><body><main>
<h1 class="h3">กรอกข้อมูลเริ่มต้น Green Digital</h1>
<p>ใช้หลัง Import <code>greendigital.sql</code> แทนการ Import <code>demo.sql</code> กรอกรหัสผ่านสั้นที่จำได้ แล้ว PHP จะสร้าง hash ให้เอง</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?=esc($error)?></div><?php endif; ?>
<?php if ($done): ?><div class="alert alert-success" role="status">บันทึกข้อมูลเริ่มต้นครบแล้ว ใช้อีเมลและรหัสผ่านที่กรอกเพื่อเข้าสู่ระบบ รหัสกู้คืนใช้ค่าที่กรอกในฟอร์มนี้</div><?php endif; ?>
<?php if (!$ready && !$error && !$done): ?><div class="alert alert-info">ระบบมีบัญชีแล้ว จึงปิดการกรอกข้อมูลเริ่มต้นเพื่อป้องกันการเพิ่มซ้ำ หากต้องการแก้ข้อมูลให้เข้าสู่ระบบแล้วใช้เมนูจัดการตามปกติ</div><?php endif; ?>
<?php if ($ready): ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?=esc(token())?>">
<h2>1. บัญชีเริ่มต้น 3 บทบาท</h2>
<div class="table-responsive"><table class="table"><thead><tr><th>บทบาท</th><th>ชื่อ</th><th>อีเมล</th></tr></thead><tbody>
<?php foreach (array_values($roles) as $i=>$label): ?><tr><td><?=esc($label)?></td><td><input aria-label="ชื่อ<?=$i+1?>" class="form-control" name="users[<?=$i?>][full_name]" value="<?=esc($users[$i]['full_name']??'')?>" required maxlength="120"></td><td><input aria-label="อีเมล<?=$i+1?>" class="form-control" type="email" name="users[<?=$i?>][email]" value="<?=esc($users[$i]['email']??'')?>" required maxlength="255"></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="row"><div class="col-md-6"><label for="password">รหัสผ่านเริ่มต้นร่วมกัน (อย่างน้อย 8 ตัว)</label><input id="password" class="form-control" type="text" name="password" value="Green1234!" required minlength="8" maxlength="72" autocomplete="off"></div><div class="col-md-6"><label for="recovery">รหัสกู้คืนเริ่มต้นร่วมกัน</label><input id="recovery" class="form-control" name="recovery" value="demo-recovery-2026" required minlength="8" maxlength="72" autocomplete="off"></div></div>
<p class="small text-secondary mt-2">รหัสร่วมใช้สำหรับข้อมูลสมมติในห้องฝึก หลังติดตั้งให้แต่ละบัญชีเปลี่ยนรหัสผ่านและรหัสกู้คืนของตนเอง</p>
<h2>2. บทความเริ่มต้น</h2><label for="title">หัวข้อ</label><input id="title" class="form-control" name="title" value="<?=esc($title)?>" required maxlength="200"><label for="body">รายละเอียด</label><textarea id="body" class="form-control" name="body" rows="3" required maxlength="10000"><?=esc($body)?></textarea>
<h2>3. บันทึกขยะตัวอย่างของบัญชีสมาชิก</h2><p>เพิ่มได้ 5 รายการ เว้นน้ำหนักว่างเพื่อข้ามแถว ราคาจะอ่านจากประเภทขยะ องค์กรและประเภทมาจากฐานข้อมูลที่ Import ไว้</p>
<div class="table-responsive"><table class="table"><thead><tr><th>องค์กร</th><th>ประเภท</th><th>น้ำหนัก กก.</th><th>วันที่</th><th>สถานะ</th><th>หมายเหตุ</th></tr></thead><tbody>
<?php foreach ($rows as $i=>$r): ?><tr>
<?php foreach (['org_id'=>$orgs??[], 'type_id'=>$types??[]] as $key=>$items): ?><td><select class="form-select" aria-label="<?=$key?> แถว <?=$i+1?>" name="rows[<?=$i?>][<?=$key?>]"><?php foreach ($items as $item): ?><option value="<?=(int)$item['id']?>" <?=((string)$item['id']===(string)($r[$key]??''))?'selected':''?>><?=esc($item['name'])?></option><?php endforeach; ?></select></td><?php endforeach; ?>
<td><input class="form-control" aria-label="น้ำหนักแถว <?=$i+1?>" type="number" min="0.001" max="1000000" step="0.001" name="rows[<?=$i?>][weight]" value="<?=esc($r['weight']??'')?>"></td>
<td><input class="form-control" aria-label="วันที่แถว <?=$i+1?>" type="date" name="rows[<?=$i?>][record_date]" value="<?=esc($r['record_date']??'')?>" max="<?=date('Y-m-d')?>"></td>
<td><select class="form-select" aria-label="สถานะแถว <?=$i+1?>" name="rows[<?=$i?>][status]"><?php foreach (['approved'=>'ตรวจรับแล้ว','pending'=>'รอตรวจ'] as $k=>$v): ?><option value="<?=$k?>" <?=($r['status']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></td>
<td><input class="form-control" aria-label="หมายเหตุแถว <?=$i+1?>" name="rows[<?=$i?>][note]" value="<?=esc($r['note']??'')?>" maxlength="2000"></td></tr><?php endforeach; ?>
</tbody></table></div>
<h2>4. เป้าหมายเดือนปัจจุบันของสมาชิก</h2><label for="target">น้ำหนักเป้าหมาย (กก.)</label><input id="target" class="form-control" type="number" name="target" min="0.001" max="1000000" step="0.001" value="<?=esc($target)?>" required>
<button class="btn btn-success mt-4" type="submit">บันทึกข้อมูลเริ่มต้นทั้งหมด</button>
</form><?php endif; ?>
<a class="btn btn-outline-secondary mt-3" href="./">ไปหน้าเข้าสู่ระบบ</a>
</main></body></html>
