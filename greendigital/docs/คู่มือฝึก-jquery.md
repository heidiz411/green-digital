# คู่มือฝึก jQuery CRUD — จากหนึ่งตารางสู่ทั้งระบบ

เป้าหมาย: นักเรียนเขียนโครงเดิมจากความเข้าใจ แล้วเปลี่ยนชื่อฟิลด์/กฎธุรกิจตามโจทย์ได้ ไม่จำโค้ดทุกบรรทัดรวดเดียว

## แผนที่การทำงาน

```text
View → jQuery → public/index.php → ApiController → Store → ฐานข้อมูล
     ← JSON / ข้อมูลตาราง ←
```

ชุดนี้ใช้รูปแบบ URL เดียวกันทุกตาราง:

| งาน | Method | URL | ข้อมูลหลัก |
|---|---|---|---|
| อ่านหลายรายการ | GET | ?r=/api/list | kind=types |
| อ่านหนึ่งรายการ | GET | ?r=/api/detail | kind=types, id |
| เพิ่ม/แก้ไข | POST | ?r=/api/save | kind=types, id, name, price, _csrf |
| ลบ | POST | ?r=/api/delete | kind=types, id, _csrf |

`kind` คือชื่องานที่อยู่ในรายการอนุญาตเท่านั้น PHP ตรวจสิทธิ์ซ้ำทุกครั้ง

## บทที่ 1: ประเภทขยะ

จำสามฟิลด์: `id`, `name`, `price` หน่วยน้ำหนักทุกประเภทเป็น kg

1. เปิด `Store::save()` ดู INSERT และ UPDATE ใช้ PDO prepare/execute
2. เปิด `ApiController::save()` ดู `case 'types'` และ `requireStaff()`
3. เปิด `app.js` ดู `listing()` → `openEditor()` → submit ของ `#editForm`
4. ทดลองเพิ่ม แก้ชื่อ แก้ราคา ลบ และเพิ่มชื่อซ้ำเพื่ออ่านข้อความผิดพลาด

## สูตรฝั่งหน้าจอ

**อ่าน → เปิด → เติม → ส่ง → ตรวจ → โหลดใหม่**

โค้ดย่อประกอบการเรียน (ใช้ endpoint ของชุดจริง แต่ต้องสร้าง HTML ให้มี ID ตรงกับตัวอย่าง):

```javascript
// <form id="typeForm"> ต้องมี name: id, name, price
// ตาราง #typeTable ต้องมีหัวตาราง 4 ช่อง
const token = $('meta[name="csrf-token"]').attr('content');
const table = $('#typeTable').DataTable({
  ajax: { url: '?r=/api/list&kind=types', dataSrc: 'data' },
  columns: [
    { data: 'id' },
    { data: 'name', render: $.fn.dataTable.render.text() },
    { data: 'price', render: $.fn.dataTable.render.text() },
    { data: null, defaultContent: '<button type="button" class="edit">แก้ไข</button>' }
  ]
});

$('#addType').on('click', function () {
  $('#typeForm')[0].reset();
  $('#typeForm [name=id]').val(0);
});

$('#typeTable').on('click', '.edit', function () {
  const row = table.row($(this).closest('tr')).data();
  $.getJSON('?r=/api/detail', { kind: 'types', id: row.id })
    .done(function (data) {
      $('#typeForm [name=id]').val(data.id);
      $('#typeForm [name=name]').val(data.name);
      $('#typeForm [name=price]').val(data.price);
    })
    .fail(function (xhr) { alert(xhr.responseJSON?.message || 'โหลดไม่สำเร็จ'); });
});

$('#typeForm').on('submit', function (event) {
  event.preventDefault();
  const data = new FormData(this);
  data.set('kind', 'types');
  data.set('_csrf', token);
  $.ajax({
    url: '?r=/api/save', method: 'POST', data,
    dataType: 'json', processData: false, contentType: false
  })
  .done(function (res) {
    if (!res.success) { alert(res.message); return; }
    table.ajax.reload(null, false);
  })
  .fail(function (xhr) { alert(xhr.responseJSON?.message || 'บันทึกไม่สำเร็จ'); });
});
```

ระบบจริงมี `post()` รวมโค้ดส่ง AJAX และ CSRF ให้ทุกฟอร์มใช้ร่วมกัน ฟังก์ชันนี้คืน Promise จึงใช้ `await` กับ `try/catch` เพื่อเรียงขั้นตอนอ่านง่าย หลักเดียวกับ `.done()` / `.fail()` ในตัวอย่าง ครูเลือกเริ่มจากแบบใดแบบหนึ่งแล้วให้นักเรียนแปลงเทียบกัน

ตัวอย่างลบ:

```javascript
if (confirm('ยืนยันการลบ?')) {
  $.post('?r=/api/delete', {kind:'types', id:id, _csrf:token})
    .done(function(res) { if(res.success) table.ajax.reload(null,false); })
    .fail(function(xhr) { alert(xhr.responseJSON?.message || 'ลบไม่สำเร็จ'); });
}
```

## คำสำคัญที่ต้องอธิบายได้

| คำ | ความหมาย |
|---|---|
| `$(function(){ ... })` | รอ DOM พร้อม |
| `.on('click', '.edit', ...)` | รับคลิกปุ่มที่สร้างภายหลัง |
| `.val()` | อ่าน/เติม input |
| `.text()` / `esc()` | แสดงข้อความผู้ใช้โดยไม่แปลเป็น HTML |
| `preventDefault()` | ยกเลิก submit ปกติ |
| `FormData` | รวมข้อมูลและไฟล์; field ต้องมี name |
| `processData:false` | ไม่ให้ jQuery แปลง FormData |
| `contentType:false` | ให้เบราว์เซอร์สร้าง multipart boundary |
| `dataType:'json'` | คาดว่าคำตอบเป็น JSON |
| `ajax.reload(null,false)` | โหลดข้อมูลใหม่และคงหน้าตาราง |
| `try/catch` | รับผลสำเร็จ/ผิดพลาดเมื่อใช้ await |

## สูตรฝั่ง PHP

**สิทธิ์ → CSRF → ข้อมูล → SQL → JSON**

- ตรวจ session และสถานะสมาชิกในทุก API ที่ต้องล็อกอิน
- ปุ่มซ่อนบนจอไม่ใช่การป้องกันสิทธิ์
- `kind` ตรวจ whitelist ก่อนใช้เป็นชื่อตาราง
- ข้อมูลผู้ใช้ส่งผ่าน `?` placeholders ไม่ต่อเข้า SQL
- `id=0` เพิ่ม, `id>0` แก้ไข; ต้องตรวจว่ารายการมีอยู่จริง
- รายการขยะตรวจเจ้าของ และห้ามแก้หลังตรวจรับ
- ผู้ใช้ส่ง `user_id` หรือ `status=approved` มาเองก็ไม่มีผล เพราะ PHP กำหนดค่า
- ส่ง JSON พร้อม HTTP status: 401 ยังไม่ล็อกอิน, 403 ไม่มีสิทธิ์, 404 ไม่พบ, 409 ข้อมูลซ้ำ/ถูกอ้างอิง, 419 CSRF, 422 ข้อมูลผิด, 429 ลองถี่เกินไป

## แปลงหนึ่งชุดเป็นหลายงาน

| kind | ฟิลด์เพิ่มจาก id | กฎสำคัญ |
|---|---|---|
| types | name, price | เจ้าหน้าที่; ราคาไม่ติดลบ |
| organizations | name | เจ้าหน้าที่; ชื่อไม่ซ้ำ |
| users | email, full_name, role, active, password ตอนเพิ่ม | เฉพาะ admin; ป้องกันระงับตนเอง |
| contents | title, body, image, publish_at, expire_at, active | เวลาเปิดก่อนปิด; สมาชิกเห็นเฉพาะเผยแพร่ |
| records | org_id, type_id, weight, record_date, note, image | เจ้าของ; น้ำหนัก >0; วันที่ไม่เป็นอนาคต |
| reports | message | สมาชิกเพิ่ม; เจ้าหน้าที่ตอบด้วย /api/reply |

ใช้ `FormData` เหมือนกันทุกฟอร์ม จึงเพิ่มภาพได้โดยไม่เปลี่ยนสูตร AJAX

## ตารางฝึก 8 รอบ

| รอบ | งาน | เกณฑ์ผ่าน |
|---|---|---|
| 1 | วาด ERD และสร้างตาราง | PK/FK ถูกต้อง ทดลองข้อมูลผิด |
| 2 | CRUD types | เขียนจากหน้าเปล่าได้ |
| 3 | organizations | เปลี่ยนตารางโดยใช้รูปแบบเดิม |
| 4 | login / session / roles | ลองเรียก URL โดยตรงแล้วถูกปฏิเสธ |
| 5 | users / contents | ระงับบัญชีและตั้งเวลาแสดงได้ |
| 6 | records + ภาพ + review | เจ้าของแก้ได้ก่อนตรวจรับเท่านั้น |
| 7 | filters / chart / CSV / goals | คำนวณยอดด้วยมือเทียบระบบ |
| 8 | จับเวลา 8 ชั่วโมง | ติดตั้งเครื่องอื่นและสาธิตครบ |

แต่ละรอบ: ดูตัวอย่าง → เขียนตาม → ปิดต้นฉบับเขียนใหม่ → เปลี่ยนโจทย์ → อธิบายให้เพื่อนฟัง

## แบบทดสอบที่ครูใช้ถาม

1. ทำไมข้อมูลจาก `.val()` ต้องตรวจใน PHP อีกครั้ง?
2. ทำไมราคาประเมินเก็บใน records ทั้งที่ types มีราคาแล้ว?
3. ถ้าเปลี่ยน id ในคำขอเป็นของเพื่อน ระบบป้องกันตรงไหน?
4. ถ้าลบประเภทที่มีรายการอ้างอิง ควรเกิดอะไร?
5. ทำไมยอดรายงานไม่รวม pending และ rejected?
6. เหตุใดต้อง encode ข้อความทั้งในตารางและใน value ของ input?
7. หากส่งฟอร์มแล้วเครือข่ายขาด จะตรวจได้อย่างไรว่าบันทึกแล้ว?
8. CSV ที่ตรงกับตัวกรองต้องใช้ชุดข้อมูลใด?

## แบ่งทีม 3 คน / คอมพิวเตอร์ 2 เครื่อง

คนที่ 1 ฐานข้อมูลและ PHP, คนที่ 2 หน้าจอและ jQuery, คนที่ 3 ทดสอบข้อมูลและเกณฑ์/เตรียมนำเสนอ แล้วสลับใช้เครื่องเป็นช่วง ทุกคนต้องอธิบายเส้นทางข้อมูลหนึ่งรายการได้ครบ ไม่ให้คนใดรู้เฉพาะไฟล์ของตน
