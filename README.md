# 獎助學金系統開發文檔

## 1. 專案概覽

本專案是一個傳統 PHP 多頁式網站，沒有使用 Laravel、Symfony 等 PHP 框架，也沒有前端建置工具。頁面、表單處理、SQL 查詢、權限檢查大多直接寫在各個 PHP 檔案中。

部署環境推測為 Apache + PHP + MySQL，網址根路徑多處寫死為 `/scholarship/...`。資料庫備份 `scholarship (3).sql` 

主要功能是獎助學金申請與審核，依使用者角色分成：

- 學生：瀏覽獎助學金、送出申請、上傳附件、查看申請狀態。
- 教授：查看學生資料、填寫推薦信。
- 管理員：審核帳號、管理獎助單位、公告管理、查看申請結果。
- 獎助單位：新增獎助學金、查看申請者、審核申請結果。

## 2. 環境

- 後端：PHP 8.3
- 資料庫：MariaDB 10.11
- 資料庫存取：PDO
- 前端：HTML、CSS、Bootstrap CDN
- 郵件：PHPMailer，放在 `student/` 目錄
- Session：PHP 原生 `$_SESSION`

## 3. 目錄結構

```text
/
  config.php
  auth.php
  index.php
  login.php
  login_submit.php
  logout.php
  register.php
  register_submit.php
  profile.php
  profile_edit.php
  announcement_board.php
  announcement_view.php
  assets/css/auth.css

student/
  student-dashboard.php
  browse_scholarships.php
  apply.php
  apply_submit.php
  PHPMailer.php
  SMTP.php
  Exception.php

organization/
  db.php
  org-dashboard.php
  add_scholarship.php
  insert_scholarship.php
  my_scholarships.php
  view_applicants.php
  review_application.php
  delete_scholarship.php

admin/
  admin_dashboard.php
  admin_users_pending.php
  org_management.php
  org_form.php
  org_process.php
  post_management.php
  post_info.php
  post_process.php
  post_view.php
  app_management.php

professor/
  tea_dashboard.php
  student_view.php
  recommendation.php
  submit_recommendation.php
```

## 4. 共用入口與基礎配置

### `config.php`

全站主要資料庫連線設定。

```php
$pdo = new PDO(
  "mysql:host=localhost;dbname=scholarship;charset=utf8mb4",
  "root",
  "a1125518",
  [...]
);
```

同時也會在 session 尚未開始時呼叫 `session_start()`。

### `auth.php`

提供兩個共用權限函式：

- `require_login()`：未登入時導向登入頁。
- `require_role($role)`：檢查目前登入使用者是否符合指定角色。

角色代碼：

```text
1 = 學生
2 = 教授
3 = 管理員
4 = 獎助單位
```

### `index.php`

登入後的角色導向入口：

```text
role=1 -> student/student-dashboard.php
role=2 -> professor/tea_dashboard.php
role=3 -> admin/admin_dashboard.php
role=4 -> organization/org-dashboard.php
```

## 5. 登入與註冊流程

### 登入流程

1. 使用者進入 `login.php`。
2. 表單送到 `login_submit.php`。
3. `login_submit.php` 從 `users` 查詢帳號。
4. 使用 `password_verify()` 驗證密碼。
5. 檢查 `STATUS` 是否為 `active`。
6. 寫入 `$_SESSION["user"]`。
7. 導向 `index.php` 或管理員 dashboard。

Session 格式：

```php
$_SESSION["user"] = [
  "id" => $u["id"],
  "name" => $u["name"],
  "role" => (int)$u["role"],
  "status" => $u["status"]
];
```

如果是學生，會額外查 `students` 表，將學生主鍵寫入：

```php
$_SESSION["user"]["stid"]
```

### 註冊流程

1. 使用者進入 `register.php`。
2. 表單送到 `register_submit.php`。
3. 允許學生、教授與獎助單位自註冊。
4. 學生與教授新帳號寫入 `users`，狀態為 `active`，註冊後可直接登入。
5. 學生額外寫入 `students`。
6. 教授額外寫入 `teachers`。
7. 獎助單位新帳號寫入 `users`，狀態為 `pending`，並額外寫入 `organization` 與 `ophone`。
8. 管理員需到 `admin/admin_users_pending.php` 核准獎助單位帳號，將 `users.status` 改為 `active`。

## 6. 資料庫結構

本節依據資料庫備份 `scholarship (3).sql` 更新。資料庫名稱為 `scholarship`，預設字元集多數為 `utf8`。

### 資料表清單

```text
administrator
announcement
application
application_files
ophone
organization
recommendations
scholarship
students
teachers
users
```

### 欄位摘要

`users`：所有登入帳號主表。

```text
ID char(10) PK
ROLE int
NAME varchar(20) UNIQUE
EMAIL varchar(100)
TEL varchar(10)
PWD varchar(100)
status enum('pending','active','','') default 'pending'
created_at timestamp nullable
```

`administrator`：管理員擴充表。

```text
ID char(10) PK, FK -> users.ID
```

`students`：學生擴充表。

```text
ID char(10) PK, FK -> users.ID
SID char(8) UNIQUE
DNAME varchar(10)
```

`teachers`：教師擴充表。

```text
ID char(10) PK, FK -> users.ID
DNAME varchar(10)
```

`organization`：獎助單位擴充表。

```text
ID char(10) PK, FK -> users.ID
ONAME varchar(20) UNIQUE
CONTACT varchar(10)
```

`ophone`：獎助單位多電話。

```text
ID char(10) PK, FK -> organization.ID
TEL varchar(10) PK
```

`scholarship`：獎助學金主檔。

```text
id int PK AUTO_INCREMENT
NAME varchar(65)
provider_id char(10) FK -> users.ID
DEADLINE date
CONDI varchar(1000)
AMOUNT int
start_date date
```

`application`：學生申請主檔。

```text
APNO int(10) PK AUTO_INCREMENT
AUTOBI varchar(1000)
RANK varchar(11)
APDATE date
GRADE int
AMOUNT int
RESULT char(3) default '審查中'
STID char(10) FK -> students.ID
OID char(10) FK -> organization.ID
SCID int(10) FK -> scholarship.id
SCNAME varchar(100)
IS_POSTED int default 0
```

`application_files`：申請附件。

```text
id int PK AUTO_INCREMENT
apno int FK -> application.APNO
file_type varchar(50)
original_name varchar(255)
path varchar(255)
```

`recommendations`：推薦信。

```text
id int PK AUTO_INCREMENT
content text
teacher_id char(10) nullable, FK -> users.ID
teacher_name varchar(100)
teacher_email varchar(255)
rec_rel varchar(100)
created_at datetime default CURRENT_TIMESTAMP
application_id int FK -> application.APNO
token varchar(100)
student_name varchar(100)
```

`announcement`：公告。

```text
id int PK AUTO_INCREMENT
title varchar(100)
ADATE date
ATIME time
CONTENT varchar(1000)
AID char(10) FK -> administrator.ID
CATEGORY int default 0
```

### 外鍵關係

```text
administrator.ID -> users.ID
students.ID -> users.ID
teachers.ID -> users.ID
organization.ID -> users.ID

ophone.ID -> organization.ID

scholarship.provider_id -> users.ID

application.STID -> students.ID
application.OID -> organization.ID
application.SCID -> scholarship.id

application_files.apno -> application.APNO

recommendations.application_id -> application.APNO
recommendations.teacher_id -> users.ID

announcement.AID -> administrator.ID
```

### 刪除與更新規則

備份中多數角色擴充表與業務表使用 `ON DELETE CASCADE ON UPDATE CASCADE`：

```text
users 刪除 -> administrator/students/teachers/organization 連動刪除
organization 刪除 -> ophone/application 連動刪除
students 刪除 -> application 連動刪除
scholarship 刪除 -> application 連動刪除
application 刪除 -> application_files/recommendations 連動刪除
```

注意：`announcement.AID` 外鍵指向 `administrator.ID`，因此管理員若只存在於 `users` 但沒有對應 `administrator` 記錄，新增公告會失敗。

### 初始資料概況

備份內含測試/初始資料：

- `users`：學生、老師、獎助單位、管理員帳號。
- `administrator`：`Z0000000`。
- `organization`：`S1111111`、`S2222222`、`S3333333`、`S4444444`、`S8888888`、`S9999999`。
- `scholarship`：id 34 到 44 的獎助學金資料。
- `announcement`：id 15、16 的公告資料。

### Schema 與程式碼差異注意

- 程式常用大寫欄位名，例如 `ID`、`ROLE`、`NAME`；MySQL 在部分環境大小寫敏感度會受作業系統與設定影響，建議統一 SQL 欄位大小寫。
- `users.NAME` 在 schema 中是 UNIQUE，代表不能有兩個使用者同名；這可能不符合真實業務需求。
- `students.SID` 是 `char(8)`，但註冊驗證允許 ID 最多 10 位，若未來學號超過 8 位會寫入失敗或被截斷。
- `application.RESULT` 是 `char(3)`，目前狀態值包含 `審查中`、`需補件`、`不通過`、`通過`。在 utf8 下通常可存，但語意上建議改為 enum 或 varchar。
- `recommendations.content` 設為 NOT NULL，但建立推薦信邀請時程式未填 content，這在嚴格 SQL mode 下可能失敗。備份的 MySQL 5.7 設定可能允許隱含預設空字串。
- `scholarship.DEADLINE` 與 `start_date` 應使用 `date`；
- `announcement` schema 欄位是小寫 `id`、`title`，程式有時用大寫 `ID`、`TITLE`。目前在 Windows/MySQL 預設可能可用，但跨環境需確認。

## 7. 學生端流程

主要檔案：

- `student/student-dashboard.php`
- `student/browse_scholarships.php`
- `student/apply.php`
- `student/apply_submit.php`

### Dashboard

`student/student-dashboard.php` 顯示：

- 審核中案件數
- 本年度通過數
- 已發放金額
- 最新通知
- 最新申請列表

主要查詢 `application`，並關聯 `scholarship`。

### 申請獎助學金

`student/apply.php`：

- 查詢目前可申請的 `scholarship`。
- 查詢教授清單。
- 顯示申請表。
- 表單送到 `student/apply_submit.php`。

`student/apply_submit.php`：

1. 檢查學生身份。
2. 查詢所選 `scholarship`。
3. 建立 `application`。
4. 上傳自傳檔案到 `/uploads/`。
5. 寫入 `application_files`。
6. 若有其他附件，也寫入 `application_files`。
7. 若有推薦教授 email，建立 `recommendations` token。
8. 使用 PHPMailer 嘗試寄出推薦信連結。

申請結果狀態目前使用中文字串：

```text
審查中
通過
需補件
不通過
```

## 8. 教授端流程

主要檔案：

- `professor/tea_dashboard.php`
- `professor/student_view.php`
- `professor/recommendation.php`
- `professor/submit_recommendation.php`

### 教授 Dashboard

`professor/tea_dashboard.php`：

- 允許 role=2 教授與 role=3 管理員進入。
- 查詢目前教授曾寫過推薦信的學生。
- 可透過學號查詢學生資料。

### 推薦信流程

1. 學生送出申請時，`student/apply_submit.php` 建立 `recommendations.token`。
2. 推薦連結指向 `professor/recommendation.php?token=...`。
3. 教授查看學生資料、附件、申請獎助學金。
4. 表單送到 `professor/submit_recommendation.php`。
5. 更新 `recommendations.content` 與 `created_at`。

## 9. 獎助單位端流程

主要檔案：

- `organization/org-dashboard.php`
- `organization/add_scholarship.php`
- `organization/insert_scholarship.php`
- `organization/my_scholarships.php`
- `organization/view_applicants.php`
- `organization/review_application.php`
- `organization/delete_scholarship.php`

### 新增獎助學金

`organization/add_scholarship.php` 顯示表單。

`organization/insert_scholarship.php` 寫入：

```text
scholarship.NAME
scholarship.provider_id
scholarship.DEADLINE
scholarship.CONDI
scholarship.AMOUNT
scholarship.start_date
```

### 查看自己提供的獎助學金

`organization/my_scholarships.php` 依 `provider_id` 查詢 `scholarship`。

`provider_id` 來源：

- 優先使用 `$_SESSION["user"]["id"]`
- 如果沒有 session，部分頁面允許從 GET 參數帶入

這是安全風險，應改為只使用 session，並檢查 role=4。

### 查看與審核申請者

`organization/view_applicants.php`：

- 查詢該獎助單位的 scholarship。
- 查詢對應的 `application`。
- 顯示學生資料、自傳檔案、附件、推薦信內容。

`organization/review_application.php`：

- 接收 `application_id`、`scholarship_id`、`provider_id`、`status`。
- 更新 `application.RESULT`。

## 10. 管理員流程

主要檔案：

- `admin/admin_dashboard.php`
- `admin/admin_users_pending.php`
- `admin/org_management.php`
- `admin/org_form.php`
- `admin/org_process.php`
- `admin/post_management.php`
- `admin/post_info.php`
- `admin/post_process.php`
- `admin/app_management.php`

### 帳號審核

`admin/admin_users_pending.php`：

- 查詢 `users.status = pending` 且 `role = 4` 的獎助單位帳號。
- 核准後更新為 `active`。

### 獎助單位管理

`admin/org_management.php`：

- 顯示 role=4 的使用者。
- 關聯 `organization` 與 `ophone`。

`admin/org_process.php`：

- 新增、修改、刪除獎助單位。
- 新增時會寫入 `users`、`organization`、`ophone`。

### 公告管理

`admin/post_management.php`：

- 顯示公告列表。

`admin/post_info.php`：

- 新增或編輯公告表單。

`admin/post_process.php`：

- 新增、更新、刪除 `announcement`。
- 發布通過名單公告時，會將符合條件的 `application.IS_POSTED` 設為 1。

### 申請管理

`admin/app_management.php`：

- 依 `application.RESULT` 查看申請案。
- 可以將尚未公告的通過名單整理成公告內容。

## 11. 公告流程

前台：

- `announcement_board.php`：公告列表。
- `announcement_view.php`：公告內容。

後台：

- `admin/post_management.php`
- `admin/post_info.php`
- `admin/post_process.php`
- `admin/post_view.php`

主要資料表是 `announcement`。

## 12. 個人資料流程

主要檔案：

- `profile.php`
- `profile_edit.php`

`profile.php` 根據目前登入者角色查詢：

- role=1：`users` + `students`
- role=2：`users` + `teachers`
- role=3：`users`
- role=4：`users` + `organization` + `ophone`

`profile_edit.php` 負責更新使用者資料與角色擴充資料。

## 13. 已知問題與接手注意事項

### 權限檢查不一致

部分頁面有使用 `require_role()`，例如：

- `admin/admin_dashboard.php`
- `admin/admin_users_pending.php`
- `student/apply.php`
- `student/apply_submit.php`

但部分頁面只檢查是否登入，或權限檢查被註解，例如：

- `organization/org-dashboard.php`
- `organization/my_scholarships.php`
- `organization/view_applicants.php`
- `organization/review_application.php`
- `admin/app_management.php`

建議所有角色頁都統一引用：

```php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_role(4);
```

或依實際角色改成對應 role。

### DB 連線重複

目前至少有三種 DB 連線方式：

- `config.php`
- `organization/db.php`
- `professor/recommendation.php` 與 `professor/submit_recommendation.php` 內部硬寫 PDO

建議統一使用 `config.php`。

### 密碼與環境設定硬編碼

DB 帳號密碼直接寫在程式中：

```text
root / a1125518
```

建議改成環境變數或獨立的本機設定檔，並避免提交正式密碼。

### URL 寫死

多處寫死：

```text
/scholarship/...
http://127.0.0.1:5050/scholarship/...
```

如果換部署路徑或主機，需逐一修改。

### PHPMailer 尚未完成正式設定

`student/apply_submit.php` 中 Gmail SMTP 帳密與寄件人目前是空字串。正式寄信前必須補上：

- SMTP 帳號
- App Password
- 寄件人信箱
- 正式站台 URL

### `apply_submit.php` 可能影響 redirect

`student/apply_submit.php` 中間有輸出：

```php
echo "新申請編號: " . $apno;
```

後面又使用 `header("Location: ...")`。PHP 一旦已輸出內容，可能導致 header redirect 失敗。建議移除此 debug 輸出。

### 上傳目錄

申請附件會上傳到：

```text
/uploads/
```

程式會嘗試自動建立目錄，但部署時仍需確認：

- PHP 對該目錄有寫入權限
- Web server 可讀取檔案
- 檔案下載路徑正確

### GET 參數可覆蓋 provider_id

部分 organization 頁面允許透過 GET 傳入 `provider_id`。這可能讓使用者查看或操作其他獎助單位資料。建議移除 GET fallback，只使用 session 中的登入者 ID。

### SQL schema 缺失

專案中沒有資料庫建表檔。建議從現有 MySQL 匯出：

```bash
mysqldump -u root -p --no-data scholarship > schema.sql
```

再匯出測試資料或種子資料。

## 14. 建議優先重構順序

1. 匯出並補上 `schema.sql`。
2. 統一 DB 連線，只保留 `config.php`。
3. 所有角色頁補上 `require_role()`。
4. 移除 organization 頁面的 `provider_id` GET fallback。
5. 修正 `student/apply_submit.php` 的 debug 輸出、推薦信 URL 與 PHPMailer 設定。
6. 將 `/scholarship` base path 抽成設定。
7. 將共用 header、nav、樣式抽出，減少重複 HTML。
8. 補最基本的錯誤頁與表單驗證。

## 15. 快速維護索引

```text
登入驗證              login_submit.php
角色導向              index.php
權限函式              auth.php
DB 連線               config.php
學生申請表            student/apply.php
學生申請送出          student/apply_submit.php
獎助單位新增獎學金    organization/insert_scholarship.php
獎助單位審核申請      organization/review_application.php
教授推薦信頁          professor/recommendation.php
教授推薦信送出        professor/submit_recommendation.php
管理員帳號審核        admin/admin_users_pending.php
管理員公告處理        admin/post_process.php
個人資料              profile.php / profile_edit.php
```
