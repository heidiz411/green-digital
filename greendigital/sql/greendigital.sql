-- ฐานข้อมูลใหม่เท่านั้น ไม่ใช้ร่วมกับ patch รุ่นเดิม
CREATE DATABASE IF NOT EXISTS greendigital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE greendigital;
CREATE TABLE users (id INTEGER PRIMARY KEY AUTO_INCREMENT, email VARCHAR(255) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, full_name VARCHAR(120) NOT NULL, phone VARCHAR(30) NOT NULL DEFAULT '', address TEXT, role VARCHAR(20) NOT NULL DEFAULT 'member' CHECK(role IN ('member','staff','admin')), active INTEGER NOT NULL DEFAULT 1, recovery_hash VARCHAR(255) NOT NULL, session_version INTEGER NOT NULL DEFAULT 1, created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE organizations (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(120) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE types (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(120) NOT NULL UNIQUE, price DECIMAL(10,2) NOT NULL DEFAULT 0, CHECK(price>=0)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE records (id INTEGER PRIMARY KEY AUTO_INCREMENT,user_id INTEGER NOT NULL,org_id INTEGER NOT NULL,type_id INTEGER NOT NULL,weight DECIMAL(12,3) NOT NULL,price DECIMAL(10,2) NOT NULL,record_date DATE NOT NULL,note TEXT,image VARCHAR(100),status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),review_note VARCHAR(255) NOT NULL DEFAULT '',reviewer_id INTEGER,created_at DATETIME NOT NULL,FOREIGN KEY(user_id) REFERENCES users(id),FOREIGN KEY(org_id) REFERENCES organizations(id),FOREIGN KEY(type_id) REFERENCES types(id),CHECK(weight>0),CHECK(price>=0),FOREIGN KEY(reviewer_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE contents (id INTEGER PRIMARY KEY AUTO_INCREMENT,title VARCHAR(200) NOT NULL,body TEXT NOT NULL,image VARCHAR(100),publish_at DATETIME,expire_at DATETIME,active INTEGER NOT NULL DEFAULT 1,created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE reports (id INTEGER PRIMARY KEY AUTO_INCREMENT,user_id INTEGER NOT NULL,message TEXT NOT NULL,is_read INTEGER NOT NULL DEFAULT 0,reply TEXT,created_at DATETIME NOT NULL,FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE goals (id INTEGER PRIMARY KEY AUTO_INCREMENT,user_id INTEGER NOT NULL,month VARCHAR(7) NOT NULL,target DECIMAL(12,3) NOT NULL,UNIQUE(user_id,month),FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE attempts (id INTEGER PRIMARY KEY AUTO_INCREMENT,bucket VARCHAR(64) NOT NULL,attempted_at INTEGER NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_attempt ON attempts(bucket,attempted_at);
CREATE INDEX idx_record_user ON records(user_id,record_date);
CREATE INDEX idx_record_status ON records(status,record_date);
INSERT INTO organizations(name) VALUES ('วิทยาลัย'),('ชุมชน');
INSERT INTO types(name,price) VALUES ('ขวดพลาสติก PET',8),('กระดาษ',4),('กระป๋องอะลูมิเนียม',30);
