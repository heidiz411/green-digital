-- ข้อมูลสมมติสำหรับฝึกซ้อม นำเข้าครั้งเดียวหลัง greendigital.sql
USE greendigital;
INSERT INTO users(email,password,full_name,role,recovery_hash,created_at) VALUES ('admin@example.test','$2y$12$Um7fbPVZ.Ts1TZnvYZVS3.85JmlGOHc7O3LRTIS3GOFGOyGjYDcyq','ผู้ดูแลฝึกซ้อม','admin','$2y$12$j0MDxC01lhp5O8GtBPuIpuuDxXFMHtjCddNitjZiam3i9K0MY6nQy',CURRENT_TIMESTAMP);
INSERT INTO users(email,password,full_name,role,recovery_hash,created_at) VALUES ('staff@example.test','$2y$12$Um7fbPVZ.Ts1TZnvYZVS3.85JmlGOHc7O3LRTIS3GOFGOyGjYDcyq','เจ้าหน้าที่ตรวจรับ','staff','$2y$12$j0MDxC01lhp5O8GtBPuIpuuDxXFMHtjCddNitjZiam3i9K0MY6nQy',CURRENT_TIMESTAMP);
INSERT INTO users(email,password,full_name,role,recovery_hash,created_at) VALUES ('student@example.test','$2y$12$Um7fbPVZ.Ts1TZnvYZVS3.85JmlGOHc7O3LRTIS3GOFGOyGjYDcyq','นักเรียนฝึกซ้อม','member','$2y$12$j0MDxC01lhp5O8GtBPuIpuuDxXFMHtjCddNitjZiam3i9K0MY6nQy',CURRENT_TIMESTAMP);
INSERT INTO contents(title,body,active,created_at) VALUES ('แยกขยะก่อนส่งรีไซเคิล','ล้างขวดให้สะอาด แยกฝา และทำให้แห้งก่อนนำมาชั่งน้ำหนัก บันทึกข้อมูลตามจริงและรอเจ้าหน้าที่ตรวจรับ',1,CURRENT_TIMESTAMP);
INSERT INTO records(user_id,org_id,type_id,weight,price,record_date,note,status,reviewer_id,created_at) VALUES (3,1,1,12.5,8,DATE_SUB(CURRENT_DATE,INTERVAL 0 DAY),'ข้อมูลสมมติสำหรับฝึกซ้อม','approved',2,CURRENT_TIMESTAMP);
INSERT INTO records(user_id,org_id,type_id,weight,price,record_date,note,status,reviewer_id,created_at) VALUES (3,1,2,20,4,DATE_SUB(CURRENT_DATE,INTERVAL 12 DAY),'ข้อมูลสมมติสำหรับฝึกซ้อม','approved',2,CURRENT_TIMESTAMP);
INSERT INTO records(user_id,org_id,type_id,weight,price,record_date,note,status,reviewer_id,created_at) VALUES (3,2,3,3.2,30,DATE_SUB(CURRENT_DATE,INTERVAL 35 DAY),'ข้อมูลสมมติสำหรับฝึกซ้อม','approved',2,CURRENT_TIMESTAMP);
INSERT INTO records(user_id,org_id,type_id,weight,price,record_date,note,status,reviewer_id,created_at) VALUES (3,2,1,8,8,DATE_SUB(CURRENT_DATE,INTERVAL 50 DAY),'ข้อมูลสมมติสำหรับฝึกซ้อม','approved',2,CURRENT_TIMESTAMP);
INSERT INTO records(user_id,org_id,type_id,weight,price,record_date,note,status,reviewer_id,created_at) VALUES (3,1,2,5,4,DATE_SUB(CURRENT_DATE,INTERVAL 0 DAY),'ข้อมูลสมมติสำหรับฝึกซ้อม','pending',NULL,CURRENT_TIMESTAMP);
INSERT INTO goals(user_id,month,target) VALUES (3,DATE_FORMAT(CURRENT_DATE,'%Y-%m'),50);
