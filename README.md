# 勤怠管理アプリ — README

## 📘 環境構築

### 1. Docker を起動する（初回）
```bash
docker-compose up -d
```

### 2. プロジェクト直下で以下を実行（Makefile がある場合）
```bash
make init
```

※`make init` は composer install / npm install / .env 作成 / key:generate / migrate など  
必要な初期セットアップをまとめて行うための便利コマンドです。

---

## ✉️ メール認証について

本プロジェクトでは「MailHog」を利用してメール送信を確認します。  

👉 **http://localhost:8025**

.env の設定：
```
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="勤怠管理アプリ"
```

---

## 🔐 ログインについて

| 種類 | URL |
|------|-----|
| ログイン（一般ユーザー） | http://localhost/login |
| ログイン（管理者） | http://localhost/admin/login |
| メール認証 | http://localhost/email/verify |

---

## 🗄 テーブル仕様（5テーブル）

---

### 1. users テーブル
| カラム名 | 型 | PK | unique | not null | FK | 備考 |
|---|---|---|---|---|---|---|
| id | bigint | ○ |  | ○ |  |  |
| name | varchar(100) |  |  | ○ |  |  |
| email | varchar(255) |  | ○ | ○ |  |  |
| email_verified_at | timestamp |  |  |  |  | nullable |
| password | varchar(255) |  |  | ○ |  |  |
| role | enum('user','admin') |  |  | ○ |  | default 'user' |
| remember_token | varchar(100) | | | | | nullable |
| created_at | timestamp | | | ○ | | |
| updated_at | timestamp | | | ○ | | |

---

### 2. attendance_days テーブル
| カラム名 | 型 | PK | unique | not null | FK | 備考 |
|---|---|---|---|---|---|---|
| id | bigint | ○ | | ○ | | |
| user_id | bigint | | | ○ | users(id) | |
| work_date | date | | | ○ | | ユーザー×日で一意 |
| clock_in_at | datetime | | | | | nullable |
| clock_out_at | datetime | | | | | nullable |
| status | enum('before','working','break','after','off') | | | ○ | | default 'before' |
| total_work_minutes | int unsigned | | | | | nullable |
| total_break_minutes | int unsigned | | | | | nullable |
| note | varchar(255) | | | | | nullable |
| created_at | timestamp | | | ○ | | |
| updated_at | timestamp | | | ○ | | |

**複合ユニーク:** (user_id, work_date)  
**インデックス:** work_date  

---

### 3. break_periods テーブル
| カラム名 | 型 | PK | unique | not null | FK | 備考 |
|---|---|---|---|---|---|---|
| id | bigint | ○ | | ○ | | |
| attendance_day_id | bigint | | | ○ | attendance_days(id) | |
| started_at | datetime | | | ○ | | |
| ended_at | datetime | | | | | nullable |
| created_at | timestamp | | | ○ | | |
| updated_at | timestamp | | | ○ | | |

**インデックス:** (attendance_day_id, started_at)

---

### 4. correction_requests テーブル
| カラム名 | 型 | PK | unique | not null | FK | 備考 |
|---|---|---|---|---|---|---|
| id | bigint | ○ | | ○ | | |
| attendance_day_id | bigint | | | ○ | attendance_days(id) | |
| requested_by | bigint | | | ○ | users(id) | |
| reason | text | | | | | nullable |
| proposed_clock_in_at | datetime | | | | | nullable |
| proposed_clock_out_at | datetime | | | | | nullable |
| proposed_note | varchar(255) | | | | | nullable |
| status | enum('pending','approved','rejected') | | | ○ | | default 'pending' |
| before_payload | json | | | | | nullable |
| after_payload | json | | | | | nullable |
| payload | json | | | | | nullable |
| created_at | timestamp | | | ○ | | |
| updated_at | timestamp | | | ○ | | |

**インデックス:**  
- (attendance_day_id, status)  
- (requested_by, status)  

---

### 5. correction_logs テーブル
| カラム名 | 型 | PK | unique | not null | FK | 備考 |
|---|---|---|---|---|---|---|
| id | bigint | ○ | | ○ | | |
| correction_request_id | bigint | | | ○ | correction_requests(id) | |
| admin_id | bigint | | | ○ | users(id) | |
| action | enum('approved','rejected') | | | ○ | | index |
| comment | text | | | | | nullable |
| created_at | timestamp | | | ○ | | |
| updated_at | timestamp | | | ○ | | |

---

## 🧩 ER 図

（ER 図画像を `/docs/ER.png` などに置いてここに表示）

---

## 👤 テストアカウント

### 一般ユーザー
```
email: user1@example.com
password: password
```

### 一般ユーザー2
```
email: user2@example.com
password: password
```

### 一般ユーザー3
```
email: user3@example.com
password: password
```

### 管理者
```
email: admin@example.com
password: password
```

---

## 🧪 PHPUnit テスト実行方法

### テスト用 DB 作成
```bash
docker-compose exec mysql bash
mysql -u root -p
create database test_database;
exit
```

### マイグレーション & テスト
```bash
docker-compose exec php bash
php artisan migrate:fresh --env=testing
php artisan db:seed --env=testing
./vendor/bin/phpunit
```

---

##　開発環境

・トップ画面 : http://localhost/login
・ユーザー登録画面 : http://localhost/register
・管理者ログイン画面：http://localhost/admin/login
・phpMyAdmin : http://localhost:8080/

##　使用技術

・PHP : 8.1.33
・Laravel : 8.83.29
・MySQL : 8.0.26
・nginx : 1.21.1

##　ER図
![ER 図](docs/er.svg)