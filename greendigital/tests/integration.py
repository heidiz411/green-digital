"""HTTP integration tests. Run only against an isolated disposable test database.
Usage: python3 tests/integration.py http://127.0.0.1:8098 --disposable
Uses demo seed accounts created by setup.php. Creates test records and accounts.
"""
import sys, json, urllib.request, urllib.parse, urllib.error, http.cookiejar, datetime, uuid, csv, io, base64
assert '--disposable' in sys.argv, 'ต้องระบุ --disposable และใช้ฐานข้อมูลทดสอบเท่านั้น'
BASE=sys.argv[1].rstrip('/')+'/'
checks=[]
def check(condition,label):
    assert condition,label
    checks.append(label)
def eq(actual,expected,label):check(actual==expected,f'{label}: {actual!r} != {expected!r}')
class Client:
    def __init__(self):
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf=self.get('session')['csrf']
    def request(self,action,data=None,query=None,expect=200,token=True,file=None,raw=False):
        url=BASE+'?'+urllib.parse.urlencode({'r':'/api/'+action,**(query or {})})
        headers={}
        if data is not None:
            data=dict(data)
            if token:data['_csrf']=self.csrf
            if file:
                boundary='----GreenDigitalTestBoundary'
                content=b''
                for k,v in data.items(): content+=f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
                name,payload,mime=file
                content+=f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+payload+f'\r\n--{boundary}--\r\n'.encode()
                headers['Content-Type']='multipart/form-data; boundary='+boundary
            else:content=urllib.parse.urlencode(data).encode()
        else:content=None
        try:r=self.opener.open(urllib.request.Request(url,data=content,headers=headers));status=r.status;body=r.read()
        except urllib.error.HTTPError as e:status=e.code;body=e.read()
        eq(status,expect,action+' HTTP')
        if raw:return body
        try:return json.loads(body)
        except Exception:raise AssertionError(body[:1000])
    def get(self,a,**q):return self.request(a,query=q)
    def post(self,a,d,**kw):return self.request(a,data=d,**kw)
    def login(self,email,password='Green1234!'):return self.post('login',{'email':email,'password':password})
    def rows(self,k,**q):return self.get('list',kind=k,**q)['data']
    def save(self,k,**d):return self.post('save',{'kind':k,**d})['id']

stamp=uuid.uuid4().hex[:8]
a=Client();s=Client();m=Client();b=Client();anonymous=Client()
anonymous.request('list',query={'kind':'records'},expect=401)
a.post('login',{'email':'admin@example.test','password':'Green1234!'},token=False,expect=419)
a.login('admin@example.test');s.login('staff@example.test');m.login('student@example.test')
check(a.get('session')['user']['role']=='admin','admin login')
check('password' not in a.get('session')['user'],'password not exposed')
new=b.post('register',{'email':f'{stamp}@example.test','full_name':'ทดสอบสมาชิก','password':'Password123!','password_confirm':'Password123!'})
code=new['recovery_code'];check(len(code)==24,'recovery generated')
b.post('register',{'email':f'{stamp}@example.test','full_name':'ซ้ำ','password':'Password123!','password_confirm':'Password123!'},expect=409)
b.login(f'{stamp}@example.test','Password123!')
uid=b.get('session')['user']['id']
b.post('profile',{'full_name':'แก้โปรไฟล์','phone':'0123456789','address':'วิทยาลัย'})
eq(b.get('session')['user']['phone'],'0123456789','profile persisted')
b.request('list',query={'kind':'users'},expect=403);s.request('list',query={'kind':'users'},expect=403)
b.post('save',{'kind':'types','name':'unauthorized','price':1},expect=403)
b.post('review',{'id':1,'status':'approved'},expect=403)
a.post('save',{'kind':'types','name':'','price':2},expect=422)
a.post('save',{'kind':'types','name':'invalid','price':-1},expect=422)
tid=a.save('types',name='ชนิด '+stamp,price='12.50')
oid=s.save('organizations',name='องค์กร '+stamp)
eq(a.get('detail',kind='types',id=tid)['name'],'ชนิด '+stamp,'read detail')
a.save('types',id=tid,name='ชนิดแก้ไข '+stamp,price='10')
eq(float(a.get('detail',kind='types',id=tid)['price']),10,'update type')
today=datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=7))).strftime('%Y-%m-%d')
record={'org_id':oid,'type_id':tid,'weight':'2.5','record_date':today,'note':'=SUM(1,1)'}
rid=b.save('records',**record,user_id=1,status='approved')
r=b.get('detail',kind='records',id=rid)
eq(r['user_id'],uid,'cannot forge owner');eq(r['status'],'pending','cannot self approve')
m.request('detail',query={'kind':'records','id':rid},expect=403)
m.post('save',{'kind':'records','id':rid,**record},expect=403)
m.post('delete',{'kind':'records','id':rid},expect=403)
check(all(x['id']!=rid for x in m.rows('records')),'list scope enforced')
b.post('save',{'kind':'records',**record,'weight':0},expect=422)
b.post('save',{'kind':'records',**record,'record_date':'2026-02-31'},expect=422)
b.post('save',{'kind':'records',**record,'org_id':99999},expect=422)
a.post('delete',{'kind':'types','id':tid},expect=409)
eq(b.get('dashboard')['kg'],0,'pending excluded')
s.post('review',{'id':rid,'status':'approved','review_note':'ชั่งจริง'})
eq(float(b.get('dashboard')['kg']),2.5,'approved kg');eq(float(b.get('dashboard')['value']),25,'value snapshot')
a.save('types',id=tid,name='ชนิดแก้ไข '+stamp,price='99')
eq(float(b.get('dashboard')['value']),25,'later price does not alter history')
b.post('save',{'kind':'records','id':rid,**record},expect=422);b.post('delete',{'kind':'records','id':rid},expect=422)
b.post('goal',{'month':today[:7],'target':5});eq(float(b.get('dashboard')['target']),5,'goal saved');eq(b.get('dashboard')['points'],25,'points')
b.post('goal',{'month':today[:7],'target':10});eq(float(b.get('dashboard')['target']),10,'goal updated')
filtered=a.get('dashboard',org_id=oid,type_id=tid,**{'from':today,'to':today,'q':'SUM'})
eq(float(filtered['kg']),2.5,'filter total')
a.request('dashboard',query={'from':'2026-02-31'},expect=422)
a.request('dashboard',query={'from':'2026-11-05','to':'2026-11-01'},expect=422)
raw=a.request('export',query={'kind':'records','org_id':oid,'status':'approved'},raw=True).decode('utf-8-sig')
export=list(csv.DictReader(io.StringIO(raw)));eq(len(export),1,'export same filtered set');check(export[0]['note'].startswith("'="),'CSV formula escaped')
# Upload genuine PNG, preserve without new upload, prevent cross-user image access.
s.post('review',{'id':rid,'status':'pending','review_note':'แก้ภาพ'})
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP1sAAAAASUVORK5CYII=')
b.post('save',{'kind':'records','id':rid,**record},file=('test.png',png,'image/png'))
image=b.get('detail',kind='records',id=rid)['image'];check(bool(image),'image stored')
eq(b.request('image',query={'kind':'records','id':rid},raw=True),png,'image served')
m.request('image',query={'kind':'records','id':rid},expect=403)
b.save('records',id=rid,**record);eq(b.get('detail',kind='records',id=rid)['image'],image,'image preserved')
b.post('save',{'kind':'records',**record},file=('evil.php',b'<?php echo 1;', 'image/png'),expect=422)
b.post('save',{'kind':'records',**record},file=('big.png',png+b'0'*(3*1024*1024),'image/png'),expect=422)
# Content visibility and scheduling includes detail and image access.
base={'body':'เนื้อหา <script>alert(1)</script>','active':1}
published=a.save('contents',title='เปิด '+stamp,**base)
future=a.save('contents',title='อนาคต '+stamp,publish_at='2099-01-01T00:00',**base)
expired=a.save('contents',title='หมดอายุ '+stamp,expire_at='2000-01-01T00:00',**base)
hidden=a.save('contents',title='ซ่อน '+stamp,body='ซ่อน',active=0)
ids=[x['id'] for x in b.rows('contents')];check(published in ids and future not in ids and expired not in ids and hidden not in ids,'content schedule')
b.request('detail',query={'kind':'contents','id':future},expect=404)
a.post('save',{'kind':'contents','title':'bad','publish_at':'2026-01-02T00:00','expire_at':'2026-01-01T00:00',**base},expect=422)
a.save('contents',id=published,title='แก้หัวข้อ',**base);eq(a.get('detail',kind='contents',id=published)['title'],'แก้หัวข้อ','content update')
rep=b.save('reports',message='แจ้งทดสอบ '+stamp)
check(int(a.get('dashboard')['notifications'])>=1,'admin notification')
m.request('detail',query={'kind':'reports','id':rep},expect=404)
s.post('reply',{'id':rep,'reply':'แก้ไขแล้ว'});eq(b.get('detail',kind='reports',id=rep)['reply'],'แก้ไขแล้ว','report reply')
# Suspend invalidates existing session immediately; restore does not revive old session.
a.save('users',id=uid,full_name='แก้โปรไฟล์',email=f'{stamp}@example.test',role='member',active=0)
b.request('list',query={'kind':'records'},expect=401)
b.post('login',{'email':f'{stamp}@example.test','password':'Password123!'},expect=401)
a.save('users',id=uid,full_name='แก้โปรไฟล์',email=f'{stamp}@example.test',role='member',active=1)
b.login(f'{stamp}@example.test','Password123!')
a.post('save',{'kind':'users','id':1,'full_name':'ผู้ดูแล','email':'admin@example.test','role':'member','active':0},expect=422)
# Password change and recovery require proof, invalidate old sessions, and are one-use.
b.post('password',{'old_password':'wrong','new_password':'NewPassword123!'},expect=422)
other=Client();other.login(f'{stamp}@example.test','Password123!')
b.post('password',{'old_password':'Password123!','new_password':'NewPassword123!'})
other.request('list',query={'kind':'records'},expect=401)
reset=Client();reset.post('reset',{'new_password':'ResetPassword123!','user_id':uid},expect=403)
reset.post('recover',{'email':f'{stamp}@example.test','recovery_code':'wrong'},expect=401)
reset.post('recover',{'email':f'{stamp}@example.test','recovery_code':code})
res=reset.post('reset',{'new_password':'ResetPassword123!'})
check(res['recovery_code']!=code,'rotated recovery')
reset.post('reset',{'new_password':'AgainPassword123!'},expect=403)
reset.post('recover',{'email':f'{stamp}@example.test','recovery_code':code},expect=401)
b.request('list',query={'kind':'records'},expect=401)
reset.login(f'{stamp}@example.test','ResetPassword123!')
reset.post('recovery',{'old_password':'ResetPassword123!'})
# Delete record then related dictionaries, including FK protection and cleanup.
reset.post('delete',{'kind':'records','id':rid});reset.request('detail',query={'kind':'records','id':rid},expect=404)
a.post('delete',{'kind':'types','id':tid});s.post('delete',{'kind':'organizations','id':oid})
for ident in [published,future,expired,hidden]:a.post('delete',{'kind':'contents','id':ident})
reset.post('logout',{});reset.request('list',query={'kind':'records'},expect=401)
# GET cannot mutate.
anonymous.request('delete',query={'kind':'types','id':1},expect=405)
# Bound failed-login attempts and numeric precision are server enforced.
rate=Client()
for _ in range(8):rate.post('login',{'email':f'absent-{stamp}@example.test','password':'wrong'},expect=401)
rate.post('login',{'email':f'absent-{stamp}@example.test','password':'wrong'},expect=429)
a.post('save',{'kind':'types','name':'precision','price':'0.001'},expect=422)
a.post('save',{'kind':'records',**record,'weight':'0.0001'},expect=422)
print(json.dumps({'passed':len(checks),'result':'PASS','base':BASE},ensure_ascii=False))
