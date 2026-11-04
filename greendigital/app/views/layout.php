<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?= esc(
    token(),
) ?>"><title>Green Digital • ธนาคารขยะ</title><link rel="stylesheet" href="assets/vendor/bootstrap.min.css"><link rel="stylesheet" href="assets/vendor/datatables.min.css"><style>
:root{--green:#176b46}body{background:#f5f7f4;color:#203b2d;font-family:Tahoma,sans-serif}.navbar{background:#133e2b}.brand{font-weight:800;letter-spacing:.05em}.hero{background:#e0ecdc;border-radius:24px;padding:32px;margin:24px 0}.card{border:1px solid #dce5da;border-radius:18px;box-shadow:0 4px 20px #183c2210}.btn-primary,.btn-success{background:#176b46;border-color:#176b46}.form-label{margin-top:12px}.metric{font-size:2rem;font-weight:700}.muted{color:#667a6c}.table{color:#203b2d}main{min-height:80vh}textarea{min-height:100px}.nav-link{color:#e3efdf}.nav-link:hover{color:white}.section-title{font-size:1.4rem;font-weight:700}.content-body{white-space:pre-wrap}.chart-wrap{height:260px}.form-control:focus{border-color:#176b46;box-shadow:0 0 0 .2rem #176b4620}.table-responsive{padding:8px}code{word-break:break-all}#notice{white-space:pre-wrap}.auth{max-width:520px;margin:32px auto}.btn{margin:2px}footer{padding:24px;color:#6c7c70}
@media(max-width:575px){.hero{padding:22px}.hero h1{font-size:1.7rem}.card.p-4{padding:18px!important}.metric{overflow-wrap:anywhere}}
@media print{nav,footer,button,#filters,.modal{display:none!important}.card{box-shadow:none;border:0}body{background:white}.hero{margin:0;padding:12px}}
</style></head><body>
<nav class="navbar navbar-expand-lg navbar-dark"><div class="container"><a class="navbar-brand brand" href="?">♻ GREEN DIGITAL</a><button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="เปิดเมนู" aria-controls="nav" aria-expanded="false"><span class="navbar-toggler-icon"></span></button><div class="collapse navbar-collapse" id="nav"><div class="navbar-nav me-auto">
<?php if (
    $user
): ?><a class="nav-link" href="?page=dashboard">ภาพรวม</a><a class="nav-link" href="?page=records">บันทึกขยะ</a><a class="nav-link" href="?page=contents">ความรู้รักษ์โลก</a><a class="nav-link" href="?page=reports">แจ้งปัญหา</a><?php
if (
    staff($user)
): ?><a class="nav-link" href="?page=types">ประเภทขยะ</a><a class="nav-link" href="?page=organizations">องค์กร</a><?php endif;
if (
    admin($user)
): ?><a class="nav-link" href="?page=users">สมาชิก</a><?php endif;
?></div><a class="nav-link" href="?page=profile"><?= esc(
    $user["full_name"],
) ?></a><button class="btn btn-outline-light" id="logout">ออกจากระบบ</button><?php else: ?></div><a class="nav-link" href="?page=login">เข้าสู่ระบบ</a><?php endif; ?></div></div></nav>
<main class="container"><div class="hero"><div class="small text-uppercase mb-2">ลดขยะ • เพิ่มคุณค่า • ร่วมดูแลโลก</div><h1 class="fw-bold">ธนาคารขยะ Green Digital</h1><p class="mb-0">บันทึกการส่งรีไซเคิล ติดตามผลที่ตรวจรับแล้ว และสร้างเป้าหมายของคุณ</p></div><div id="notice" class="alert d-none" role="status" tabindex="-1"></div><div id="app"></div></main>
<div class="modal" id="editor" tabindex="-1" aria-labelledby="editorTitle"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="editorTitle"></h2><button class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div><form id="editForm"><div class="modal-body"><div id="modalNotice" class="alert alert-danger d-none" role="alert"></div><div id="fields"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button><button class="btn btn-success" type="submit">บันทึก</button></div></form></div></div></div>
<footer class="container">Green Digital · ชุดฝึก PHP MVC + jQuery CRUD · หน่วยน้ำหนักมาตรฐาน: กิโลกรัม</footer>
<script src="assets/vendor/jquery.min.js"></script><script src="assets/vendor/bootstrap.bundle.min.js"></script><script src="assets/vendor/datatables.min.js"></script><script src="assets/vendor/chart.umd.js"></script><script src="assets/js/app.js"></script></body></html>
