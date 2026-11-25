import sys,urllib.request,urllib.parse,http.cookiejar,re,json
assert '--disposable' in sys.argv, 'ใช้กับฐานข้อมูลใหม่สำหรับทดสอบเท่านั้น และระบุ --disposable'
base=sys.argv[1].rstrip('/')+'/'
c=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
page=c.open(base+'seed.php').read().decode()
token=re.search(r'name="_csrf" value="([^"]+)"',page)[1]
data={'_csrf':token,'password':'Simple123','recovery':'Recover123','title':'บทความจากฟอร์ม','body':'รายละเอียดทดสอบ','target':60}
for i in range(3):
 data[f'users[{i}][full_name]']=f'บัญชี {i}'
 data[f'users[{i}][email]']=f'seed{i}@example.test'
for i,(o,t,w,days,status) in enumerate([(1,1,12.5,0,'approved'),(1,2,20,12,'approved'),(2,3,3.2,35,'approved'),(2,1,8,50,'approved'),(1,2,5,0,'pending')]):
 import datetime
 date=(datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=7)))-datetime.timedelta(days=days)).strftime('%Y-%m-%d')
 for k,v in dict(org_id=o,type_id=t,weight=w,record_date=date,status=status,note='ตัวอย่าง').items():data[f'rows[{i}][{k}]']=v
checks=0
def post(d):return c.open(base+'seed.php',urllib.parse.urlencode(d).encode()).read().decode()
# CSRF and validation must not create a partial admin.
assert 'โหลดหน้าใหม่' in post({**data,'_csrf':'wrong'});checks+=1
assert 'รหัสผ่านต้อง' in post({**data,'password':'123'});checks+=1
bad={**data,'users[1][email]':'seed0@example.test'}
assert 'อีเมลซ้ำ' in post(bad);checks+=1
assert 'บันทึกข้อมูลเริ่มต้นทั้งหมด' in c.open(base+'seed.php').read().decode();checks+=1
assert 'บันทึกข้อมูลเริ่มต้นครบแล้ว' in post(data);checks+=1
assert 'ระบบมีบัญชีแล้ว' in post(data);checks+=1
session=json.load(c.open(base+'?r=/api/session'))
login=json.load(c.open(base+'?r=/api/login',urllib.parse.urlencode({'_csrf':session['csrf'],'email':'seed0@example.test','password':'Simple123'}).encode()))
assert login['success'];checks+=1
stats=json.load(c.open(base+'?r=/api/dashboard'))
assert stats['kg']==43.7 and stats['value']==340 and stats['pending']==1;checks+=1
users=json.load(c.open(base+'?r=/api/list&kind=users'))['data']
assert len(users)==3 and {u['role'] for u in users}=={'admin','staff','member'};checks+=1
contents=json.load(c.open(base+'?r=/api/list&kind=contents'))['data']
assert len(contents)==1 and contents[0]['title']=='บทความจากฟอร์ม';checks+=1
print('PASS',checks,'seed form checks (CSRF, validation, rollback, duplicate prevention, login, roles, totals, content)')
