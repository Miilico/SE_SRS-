# 高大獎助學金系統

更新日期：2026-06-22

本專案是一個以 PHP + MySQL/MariaDB 實作的獎助學金申請與審核系統。系統採傳統多頁式 PHP 架構，頁面、表單處理、資料庫查詢與權限檢查主要分散在各角色目錄中。

## 專案狀態

目前版本已涵蓋學生申請、導師推薦信、獎助單位審核、管理員帳號與公告管理、支援工單、檔案權限下載、忘記密碼、SMTP 通知、自訂申請表、補件、Email queue 與部分登入安全設定。

角色分工：

- 學生：瀏覽獎助學金、送出申請、填寫自訂欄位、上傳附件、查看申請與推薦信狀態、補件。
- 導師：查看被邀請推薦的學生資料、暫存/提交/駁回推薦信。
- 獎助單位：新增與管理獎助學金、設定自訂申請欄位、查看/匯出申請者、單筆或批次審核、發送廣播、發布公告。
- 管理員：審核獎助單位帳號、管理帳號、管理公告、查看申請資料。

## 技術環境

- 後端：PHP 8.x
- 資料庫：MySQL / MariaDB
- 資料庫存取：PDO
- 前端：HTML、CSS、Bootstrap CDN、少量 JavaScript
- 郵件：PHPMailer，檔案放在 `scholarship/student/`
- Session：PHP 原生 `$_SESSION`
- 建議本機環境：XAMPP，專案放在 `htdocs` 下並以 `/scholarship/` 路徑測試

## 目錄結構

```text
.
├── README.md
├── UPDATE_NOTES1.md
├── UPDATE_NOTES2.md
├── scholarship_2026-06-18_17-47-09_mysql_data_Idoge.sql
├── index.php
├── announcement_detail.php
└── scholarship/
    ├── admin/                     管理員頁面
    ├── organization/              獎助單位頁面
    ├── professor/                 導師頁面
    ├── student/                   學生頁面與 PHPMailer
    ├── database/migrations/       資料庫增量 SQL
    ├── assets/js/qrcode.min.js    TOTP QR code
    ├── user_file/                 上傳檔案目錄
    ├── config.simple.php          設定檔範例
    ├── auth.php                   登入與角色權限
    ├── file_helpers.php           檔案上傳與刪除輔助
    ├── file_view.php              權限控管下載入口
    ├── custom_form_helpers.php    自訂申請表輔助
    ├── mail_helpers.php           SMTP 寄信輔助
    ├── login_helpers.php          登入驗證與 TOTP 輔助
    ├── recommendation_helpers.php 推薦信狀態與自動駁回
    ├── process_email_queue.php    Email queue CLI 處理器
    └── cron_auto_reject_recommendations.php
```

## 快速部署

1. 將專案放到 Web root，建議路徑為：

```text
http://127.0.0.1/scholarship/
```

2. 複製設定檔：

```powershell
Copy-Item scholarship\config.simple.php scholarship\config.php
```

3. 修改 `scholarship/config.php` 的資料庫連線、SMTP 與站台 URL。

必要設定：

```php
$pdo = new PDO(
  "mysql:host=localhost;dbname=scholarship;charset=utf8mb4",
  "root",
  "",
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]
);
```

選用 SMTP 設定：

```php
define("SCHOLARSHIP_SMTP_HOST", "smtp.gmail.com");
define("SCHOLARSHIP_SMTP_PORT", 587);
define("SCHOLARSHIP_SMTP_USERNAME", "your_email@gmail.com");
define("SCHOLARSHIP_SMTP_PASSWORD", "your_app_password");
define("SCHOLARSHIP_SMTP_FROM_EMAIL", "your_email@gmail.com");
define("SCHOLARSHIP_SMTP_FROM_NAME", "高大獎助學金系統");
define("SCHOLARSHIP_SMTP_SECURE", "tls");
define("SCHOLARSHIP_BASE_URL", "http://127.0.0.1/scholarship");
define("SCHOLARSHIP_PRIVATE_DATA_DIR", "");
define("TAR_AUTO_REJECT_DAYS", 14);
```

4. 建立資料庫並匯入基礎備份：

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS scholarship CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p scholarship < scholarship_2026-06-18_17-47-09_mysql_data_Idoge.sql
```

5. 依序執行 migration：

```text
scholarship/database/migrations/20260619_custom_application_forms.sql
scholarship/database/migrations/20260619_unique_student_scholarship.sql
scholarship/database/migrations/20260620_custom_field_notes.sql
scholarship/database/migrations/20260620_supplement_note.sql
scholarship/database/migrations/20260621_email_queue_retry.sql
```

6. 確認 `scholarship/user_file/` 可由 PHP 寫入。正式部署時建議阻擋瀏覽器直接存取此目錄，檔案下載統一走 `/scholarship/file_view.php?id=...`。

## 入口與角色導向

`scholarship/index.php` 會依登入角色導向：

```text
role=1 -> student/student-dashboard.php
role=2 -> professor/tea_dashboard.php
role=3 -> admin/admin_dashboard.php
role=4 -> organization/org-dashboard.php
```

角色代碼：

```text
1 = 學生
2 = 導師
3 = 管理員
4 = 獎助單位
```

共用權限函式在 `scholarship/auth.php`：

- `require_login()`：未登入導回登入頁。
- `require_role($role)`：限制指定角色，可傳單一角色或角色陣列。

## 主要功能

### 註冊、登入與帳號

- 學生、導師與獎助單位可自註冊。
- 學生與導師註冊後預設可登入。
- 獎助單位註冊後為 `pending`，需管理員核准。
- 登入使用 `password_verify()` 驗證密碼。
- 忘記密碼流程已完成，透過 Email 寄送重設連結。
- 使用者可在安全設定頁修改密碼。
- 程式支援 Email 登入驗證碼與 TOTP 驗證器 App，但需資料庫先有對應欄位才會啟用。

Email/TOTP 登入驗證所需欄位：

```sql
ALTER TABLE users
  ADD COLUMN EMAIL_LOGIN_VERIFY_ENABLED TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN EMAIL_LOGIN_CODE VARCHAR(10) NULL,
  ADD COLUMN EMAIL_LOGIN_CODE_EXPIRES_AT DATETIME NULL,
  ADD COLUMN TOTP_LOGIN_VERIFY_ENABLED TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN TOTP_SECRET VARCHAR(64) NULL;
```

### 學生端

主要檔案：

- `scholarship/student/student-dashboard.php`
- `scholarship/student/browse_scholarships.php`
- `scholarship/student/apply.php`
- `scholarship/student/apply_submit.php`
- `scholarship/student/my_applications.php`
- `scholarship/student/application_detail.php`
- `scholarship/student/edit_application.php`
- `scholarship/student/upload_supplement.php`

功能：

- 瀏覽已開放且未截止的獎助學金。
- 申請獎助學金並上傳自傳、附件與自訂欄位檔案。
- 每位學生同一獎助學金限制申請一次。
- 非最終狀態的申請可編輯。
- 查看申請狀態、推薦信狀態與補件原因。
- 當申請狀態為 `需補件` 時，可上傳補件，成功後狀態改為 `已補件`。

目前允許的申請狀態：

```text
審查中
需補件
已補件
通過
不通過
已獲獎
未獲獎
```

最終狀態：

```text
通過
不通過
已獲獎
未獲獎
```

### 導師與推薦信

主要檔案：

- `scholarship/professor/tea_dashboard.php`
- `scholarship/professor/student_view.php`
- `scholarship/professor/recommendation.php`
- `scholarship/professor/submit_recommendation.php`
- `scholarship/professor/reject_recommendation.php`
- `scholarship/recommendation_helpers.php`
- `scholarship/cron_auto_reject_recommendations.php`

功能：

- 導師可查看自己被邀請推薦的申請。
- 推薦信支援 `pending`、`draft`、`submitted`、`rejected` 狀態。
- 可暫存草稿，正式提交後不可再次編輯。
- 可手動駁回並記錄原因。
- 預設 14 天未提交會自動駁回，可用 `TAR_AUTO_REJECT_DAYS` 調整。
- 推薦信提交、手動駁回、自動駁回會嘗試寄 Email 通知學生。

每日排程可執行：

```powershell
php scholarship\cron_auto_reject_recommendations.php
```

### 獎助單位端

主要檔案：

- `scholarship/organization/add_scholarship.php`
- `scholarship/organization/insert_scholarship.php`
- `scholarship/organization/edit_scholarship.php`
- `scholarship/organization/my_scholarships.php`
- `scholarship/organization/view_applicants.php`
- `scholarship/organization/review_application.php`
- `scholarship/organization/batch_update_status.php`
- `scholarship/organization/export_applicants.php`
- `scholarship/organization/broadcast_scholarship.php`
- `scholarship/organization/send_broadcast.php`

功能：

- 新增、編輯、刪除獎助學金。
- 可手動開啟或關閉獎助學金，對應 `scholarship.is_active`。
- 可為獎助學金設定自訂申請欄位，支援文字、數字、多行文字、檔案。
- 可查看申請者基本資料、申請內容、附件、自訂欄位與推薦信內容。
- 可單筆或批次更新申請狀態。
- 狀態更新為 `需補件` 時需填補件原因，學生端會顯示。
- 可匯出申請者資料。
- 可依全部學生或指定系所寄送獎助學金廣播。

### 管理員端

主要檔案：

- `scholarship/admin/admin_dashboard.php`
- `scholarship/admin/admin_users_pending.php`
- `scholarship/admin/account_management.php`
- `scholarship/admin/account_form.php`
- `scholarship/admin/account_process.php`
- `scholarship/admin/post_management.php`
- `scholarship/admin/post_process.php`
- `scholarship/admin/app_management.php`

功能：

- 審核獎助單位註冊帳號。
- 新增、修改、刪除非管理員帳號。
- 管理公告。
- 查看申請案件。
- 發布通過名單公告時可將申請標記為已公告。

### 公告、工單與檔案

- 公告資料表為 `announcement`。
- 管理員與獎助單位皆有公告管理頁面。
- 支援工單 `tickets` 與 `ticket_messages`。
- 上傳檔案統一記錄在 `application_files`。
- `file_view.php` 會依登入者角色與檔案關聯檢查權限後才輸出檔案。
- 上傳檔實體位於 `scholarship/user_file/`。

檔案分類：

```text
1 = 公告附件
2 = 申請、自訂欄位、補件相關附件
3 = 工單附件
```

## Email 與排程

即時寄信使用 `scholarship_send_mail()`。SMTP 未設定時會略過寄信並寫入 error log。

Email queue 結構已由 migration 建立，CLI 處理器為：

```powershell
php scholarship\process_email_queue.php
```

處理規則：

- 每次最多取 50 筆。
- `pending` 會被處理。
- `failed` 且嘗試次數小於 3、已到 `available_at` 時會重試。
- 失敗後 5 分鐘再重試。

注意：目前部分頁面仍直接寄信，部分舊程式碼保留 queue 寫入版本但被註解。若正式部署大量廣播，建議統一改成寫入 `email_queue`，再由排程背景寄送。

## 資料庫現況

基礎備份檔：

```text
scholarship_2026-06-18_17-47-09_mysql_data_Idoge.sql
```

基礎表：

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

執行最新 migration 後新增或調整：

- `scholarship.is_active`
- `application.GRADE` 改為 `DECIMAL(5,2) NULL`
- `application.SUPPLEMENT_NOTE`
- `application` 唯一索引 `uq_application_student_scholarship (STID, SCID)`
- `scholarship_fields`
- `application_custom_answers`
- `email_queue`

推薦信表目前支援：

```text
content
draft_content
teacher_id
teacher_name
teacher_email
teacher_unit
teacher_title
rec_rel
created_at
expires_at
submitted_at
status
rejected_reason
rejected_source
rejected_at
application_id
token
student_name
```

## 重要設定與安全注意

不得提交到 GitHub：

- `scholarship/config.php`
- `scholarship/dev_db_tool.php`
- SMTP 密碼
- Gmail App Password
- 真實資料庫帳號密碼
- 正式環境上傳檔

`.gitignore` 目前已排除：

```text
scholarship/config.php
scholarship/dev_db_tool.php
.idea/
php-server-*.log
.vscode/
```

正式部署時建議在 Web server 阻擋直接存取 `user_file`：

```nginx
location = /scholarship/user_file {
    return 404;
}

location ^~ /scholarship/user_file/ {
    access_log off;
    log_not_found off;
    return 404;
}
```

## 已知限制與接手注意

- 多處 URL 仍寫死 `/scholarship/...`，若部署路徑不同需同步調整。
- `SCHOLARSHIP_BASE_URL` 應設定為正式網址，避免 Email 連結錯誤。
- Email/TOTP 登入驗證已有程式支援，但 repository 尚未提供正式 migration。
- `users.NAME` 仍是 UNIQUE，真實使用時可能不符合多人同名情境。
- `students.SID` 是 `char(8)`，若未來學號長度超過 8 位需調整。
- `application.RESULT` 仍是短字串欄位，狀態值增加後建議改為 `varchar` 或 enum。
- 部分舊程式碼仍保留註解或早期路徑，例如 `/scholarship/uploads/...` 測試資料。
- 大量寄信建議統一使用 `email_queue`，避免頁面等待 SMTP。
- 正式環境需設定 HTTPS、SMTP、排程工具、檔案權限與備份策略。

## 建議驗收流程

1. 使用學生帳號註冊並登入。
2. 瀏覽開放中的獎助學金。
3. 送出申請，填寫自訂欄位並上傳附件。
4. 使用導師帳號查看推薦信請求。
5. 暫存推薦信草稿，重新整理確認草稿存在。
6. 正式提交推薦信，確認不可再次編輯。
7. 使用學生帳號確認推薦信狀態。
8. 使用獎助單位帳號查看申請者。
9. 將申請改為 `需補件` 並填寫原因。
10. 使用學生帳號上傳補件，確認狀態變為 `已補件`。
11. 使用獎助單位批次更新狀態並測試 Email 通知。
12. 使用管理員審核獎助單位帳號、管理公告與查看申請。
13. 執行自動駁回推薦信排程。
14. 執行 Email queue 處理器。

