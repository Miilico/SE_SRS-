# 獎助學金系統開發文檔

## 1. 專案概覽

本專案是一個傳統 PHP 多頁式網站。
頁面、表單處理、SQL 查詢、權限檢查大多直接寫在各個 PHP 檔案中。

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


## 4. 共用入口與基礎配置

### `config.php`

全站主要資料庫連線設定。

```php
$pdo = new PDO(
  "mysql:host=localhost;dbname=scholarship;charset=utf8mb4",
  "username",
  "password",
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

本節依據目前專案內的資料庫備份 `scholarship/scholarship_2026-06-16_23-58-29_mysql_data_OrCAk.sql` 與最新 PHP 程式碼更新。資料庫名稱為 `scholarship`，備份由 MariaDB 10.11 匯出，多數資料表使用 `utf8mb3`。

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
ticket_messages
tickets
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
DNAME varchar(50)
```

`teachers`：教師擴充表。

```text
ID char(10) PK, FK -> users.ID
DNAME varchar(50)
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

備份檔目前的基礎欄位：

```text
id int PK AUTO_INCREMENT
apno int FK -> application.APNO
file_type varchar(50)
original_name varchar(255)
path varchar(255)
```

最新的 `file_helpers.php`、`file_view.php`、工單與公告附件功能會額外讀寫下列欄位，部署資料庫時必須補齊，否則上傳會在 `INSERT INTO application_files` 時失敗：

```text
uploader_id char(10)
file_category int
stored_name varchar(255)
mime_type varchar(255)
file_size int
file_path varchar(255)
announcement_id int nullable
application_id int nullable
scholarship_id int nullable
scholarship_provider_id char(10) nullable
ticket_id int nullable
recommendation_id int nullable
created_at datetime/timestamp default current_timestamp
```

其中 `path` 會存 `/scholarship/file_view.php?id=...` 的下載入口，`file_path` 才是 `user_file/` 下的實體相對路徑。

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

`tickets`：使用者支援工單。

```text
TICKET_ID int PK AUTO_INCREMENT
USER_ID char(10) FK -> users.ID
ADMIN_ID char(10) nullable, FK -> users.ID
TITLE varchar(255)
STATUS enum('open','pending','closed') default 'open'
CREATED_AT datetime default current_timestamp
UPDATED_AT datetime default current_timestamp on update current_timestamp
```

`ticket_messages`：工單對話訊息。

```text
MESSAGE_ID int PK AUTO_INCREMENT
TICKET_ID int FK -> tickets.TICKET_ID
SENDER_ID char(10) FK -> users.ID
MESSAGE text
CREATED_AT datetime default current_timestamp
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

tickets.USER_ID -> users.ID
tickets.ADMIN_ID -> users.ID
ticket_messages.TICKET_ID -> tickets.TICKET_ID
ticket_messages.SENDER_ID -> users.ID
```

### 刪除與更新規則

備份中多數角色擴充表與業務表使用 `ON DELETE CASCADE ON UPDATE CASCADE`：

```text
users 刪除 -> administrator/students/teachers/organization 連動刪除
organization 刪除 -> ophone/application 連動刪除
students 刪除 -> application 連動刪除
scholarship 刪除 -> application 連動刪除
application 刪除 -> application_files/recommendations 連動刪除
tickets 刪除 -> ticket_messages 連動刪除
```

注意：`announcement.AID` 外鍵指向 `administrator.ID`，因此管理員若只存在於 `users` 但沒有對應 `administrator` 記錄，新增公告會失敗。

### 初始資料概況

備份內含測試/初始資料：

- `users`：學生、老師、獎助單位、管理員帳號。
- `administrator`：`Z0000000`。
- `organization`：`S1111111`、`S2222222`、`S3333333`、`S4444444`、`S8888888`、`S9999999`。
- `scholarship`：id 34 到 44 的獎助學金資料。
- `announcement`：id 15、16 的公告資料。
- `tickets`、`ticket_messages`：含少量測試工單資料。

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
4. 透過 `store_uploaded_file()` 儲存自傳檔案到 `scholarship/user_file/`。
5. 寫入 `application_files`，並以 `/scholarship/file_view.php?id=...` 作為下載入口。
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
- `admin/account_management.php`
- `admin/account_form.php`
- `admin/account_process.php`
- `admin/post_management.php`
- `admin/post_info.php`
- `admin/post_process.php`
- `admin/app_management.php`

### 帳號審核

`admin/admin_users_pending.php`：

- 查詢 `users.status = pending` 且 `role = 4` 的獎助單位帳號。
- 核准後更新為 `active`。

### 帳號管理

`admin/account_management.php`：

- 顯示 role!=3 的使用者，不開放管理員帳號操作。
- 可依學生、教師、獎助單位篩選。
- 依角色關聯 `students`、`teachers`、`organization` 與 `ophone`。

`admin/account_process.php`：

- 新增、修改、刪除非管理員帳號。
- 新增時會依角色寫入 `users` 與對應擴充表。

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

### `student/apply_submit.php` 可能影響 redirect

`student/apply_submit.php` 中間有輸出：

```php
echo "新申請編號: " . $apno;
```

後面又使用 `header("Location: ...")`。PHP 一旦已輸出內容，可能導致 header redirect 失敗。建議移除此 debug 輸出。

### 上傳目錄

申請、公告、工單與推薦信附件會由 `store_uploaded_file()` 儲存到：

```text
scholarship/user_file/
```

下載不直接暴露實體檔案路徑，統一經過 `/scholarship/file_view.php?id=...` 做權限檢查後輸出。`file_view.php` 會先查 `application_files`，再依 `file_category` 檢查目前使用者是否可下載，最後只允許讀取 `scholarship/user_file/` 內的實體檔案。

程式會嘗試自動建立目錄，但部署時仍需確認：

- PHP 對該目錄有寫入權限
- PHP 可讀取檔案並透過 `file_view.php` 輸出
- `application_files.file_path` 指向 `user_file/` 下的實體檔案

#### nginx 攔截直接請求 `user_file`

正式部署時應在 nginx 層阻擋瀏覽器直接請求 `/scholarship/user_file/...`，只允許透過 `/scholarship/file_view.php?id=...` 下載。以下規則需放在 `server { ... }` 內，且要放在一般 PHP `location ~ \.php$` 規則之前：

```nginx
# 不暴露上傳檔實體路徑；下載一律走 /scholarship/file_view.php?id=...
location = /scholarship/user_file {
    return 404;
}

location ^~ /scholarship/user_file/ {
    access_log off;
    log_not_found off;
    return 404;
}
```

範例站台設定：

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/html;
    index index.php index.html;

    location = /scholarship/user_file {
        return 404;
    }

    location ^~ /scholarship/user_file/ {
        access_log off;
        log_not_found off;
        return 404;
    }

    location /scholarship/ {
        try_files $uri $uri/ /scholarship/index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

如果部署路徑不是 `/scholarship`，需同步調整上述 `location` 路徑與程式中目前寫死的 `/scholarship/file_view.php?id=...`。

### GET 參數可覆蓋 provider_id

部分 organization 頁面允許透過 GET 傳入 `provider_id`。這可能讓使用者查看或操作其他獎助單位資料。建議移除 GET fallback，只使用 session 中的登入者 ID。

### SQL schema 與附件欄位

專案目前已有資料庫備份：

```text
scholarship/scholarship_2026-06-16_23-58-29_mysql_data_OrCAk.sql
```

但此備份中的 `application_files` 仍是舊欄位；最新檔案上傳代碼已依 `uploader_id`、`file_category`、`file_path`、`ticket_id`、`created_at` 等欄位運作。正式部署前需先用實際線上資料庫重新匯出 schema，或補一份 migration 將附件欄位補齊：

```bash
mysqldump -u root -p --no-data scholarship > schema.sql
```

再依需要匯出測試資料或種子資料。

## 14. 建議優先重構順序

1. 補上正式 `schema.sql` 或 migration，特別是 `application_files` 的最新欄位。
2. 統一 DB 連線，只保留 `config.php`。
3. 所有角色頁補上 `require_role()`。
4. 移除 organization 頁面的 `provider_id` GET fallback。
5. 修正 `student/apply_submit.php` 的 debug 輸出、推薦信 URL 與 PHPMailer 設定。
6. 將 `/scholarship` base path 抽成設定。
7. 將共用 header、nav、樣式抽出，減少重複 HTML。
8. 補最基本的錯誤頁與表單驗證。

