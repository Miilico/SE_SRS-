# 高大獎助學金系統功能更新說明

本文件整理本次專案開發期間新增、修改與調整的功能，方便放置於 GitHub 作為開發紀錄與驗收說明。

## 一、開發環境與部署調整

- 專案以 PHP + MySQL/MariaDB 為主，使用 XAMPP 作為本機測試環境。
- 將專案掛載到 XAMPP 的 `htdocs` 下，讓系統可透過以下網址測試：

```text
http://127.0.0.1/scholarship/
```

- 新增 `config.simple.php` 作為範例設定檔，正式的 `config.php` 不應提交到 GitHub。
- `.gitignore` 已排除：

```text
scholarship/config.php
scholarship/dev_db_tool.php
.idea/
```

注意：資料庫密碼、SMTP 密碼、Gmail App Password 都屬於敏感資訊，不應放入 GitHub。

## 二、註冊與登入相關調整

### 1. 註冊頁面科系改為下拉選單

將原本需要自行輸入科系的欄位，改為下拉式選單，並放入國立高雄大學現有科系

### 2. 註冊錯誤後保留表單內容

調整註冊流程，當發生錯誤時，例如：

- 密碼至少 6 碼
- 電話格式不正確
- Email 格式不正確

系統不會清空整個註冊表單，而是保留使用者已輸入的資料，並回到錯誤欄位提醒使用者修正。

### 3. Email、密碼與電話格式提示

註冊頁面新增提示：

- Email 必須使用 `@mail.nuk.edu.tw`
- 密碼至少 6 碼
- 電話需符合台灣手機格式

## 三、忘記密碼功能

新增完整忘記密碼流程：

- `forgot_password.php`
- `forgot_password_submit.php`
- `reset_password.php`
- `reset_password_submit.php`
- `password_reset_helpers.php`

功能流程：

1. 使用者輸入 Email。
2. 系統產生重設密碼 token。
3. 系統寄出重設密碼連結。
4. 使用者透過連結設定新密碼。
5. 新密碼會以 `password_hash()` 加密後存入資料庫。

同時新增 `mail_helpers.php` 作為共用寄信工具。

## 四、SMTP 寄信設定

系統新增 SMTP 設定支援，使用 Gmail SMTP 寄送通知信。

需要在 `config.php` 中設定：

```php
define("SCHOLARSHIP_SMTP_HOST", "smtp.gmail.com");
define("SCHOLARSHIP_SMTP_PORT", 587);
define("SCHOLARSHIP_SMTP_USERNAME", "your_email@gmail.com");
define("SCHOLARSHIP_SMTP_PASSWORD", "your_app_password");
define("SCHOLARSHIP_SMTP_FROM_EMAIL", "your_email@gmail.com");
define("SCHOLARSHIP_SMTP_FROM_NAME", "高大獎助學金系統");
define("SCHOLARSHIP_SMTP_SECURE", "tls");
```

注意：`SCHOLARSHIP_SMTP_PASSWORD` 必須使用 Gmail 的「應用程式密碼」，不可直接使用 Gmail 登入密碼。

## 五、導師與推薦信子系統 TAR 功能

本次主要依照需求規格書中的 TAR 子系統進行實作與補強。

### 1. 導師首頁

檔案：

- `scholarship/professor/tea_dashboard.php`

新增與調整：

- 導師可查看被邀請撰寫推薦信的申請。
- 提供狀態篩選：
  - 全部
  - 待填寫
  - 草稿
  - 已提交
  - 已駁回
- 顯示推薦信統計數量。
- 自動執行逾期推薦信檢查。

### 2. 學生資料檢視

檔案：

- `scholarship/professor/student_view.php`

對應需求：

- TAR001：教師可以瀏覽學生基本資料。
- TAR002：教師可以瀏覽學生申請書內容。
- TAR003 / TAR018：教師只能查看自己被邀請推薦的學生資料。
- TAR004 / TAR017：電話與帳號等私人資訊會遮蔽。

調整內容：

- 非管理員導師只能查看與自己推薦信請求相關的學生。
- 學生電話會遮蔽，例如 `091****678`。
- 系統帳號會部分遮蔽。
- 顯示學生申請資料與推薦信狀態。

### 3. 推薦信填寫、暫存與提交

檔案：

- `scholarship/professor/recommendation.php`
- `scholarship/professor/submit_recommendation.php`

對應需求：

- TAR005：教師可填寫、編輯、提交推薦信。
- TAR006：未提交推薦信可保存在系統中。
- TAR007：已提交推薦信不可再次編輯。
- TAR008：每份申請書只可填寫一封推薦信。
- TAR015：推薦信撰寫過程提供暫存功能。

調整內容：

- 新增「暫存草稿」功能。
- 新增 `draft_content` 欄位保存草稿。
- 正式提交後狀態改為 `submitted`。
- 提交後頁面改為唯讀，不可再次編輯。
- 封鎖舊有撤回推薦信功能。
- 資料庫新增唯一索引，限制一份申請只會對應一封推薦信：

```text
uq_recommendations_application_id(application_id)
```

### 4. 推薦信駁回

檔案：

- `scholarship/professor/reject_recommendation.php`
- `scholarship/professor/recommendation.php`

對應需求：

- TAR009：教師可駁回推薦信撰寫請求，需記錄原因。
- TAR020 / TAR028：需記錄駁回來源為系統自動或導師手動。

調整內容：

- 導師可在推薦信頁面輸入駁回原因。
- 駁回後狀態改為 `rejected`。
- 記錄駁回原因 `rejected_reason`。
- 記錄駁回來源 `rejected_source`：
  - `teacher`：導師手動駁回
  - `system`：系統自動駁回
- 記錄駁回時間 `rejected_at`。

### 5. 14 天自動駁回

檔案：

- `scholarship/recommendation_helpers.php`
- `scholarship/cron_auto_reject_recommendations.php`

對應需求：

- TAR010：14 日未撰寫推薦信會被視為駁回。
- TAR014：後台自動監控計時器應在固定時段執行。
- TAR016：自動駁回後需確保資料庫狀態與 Email 發送一致。
- TAR019：14 天期限需設為可調整參數。

調整內容：

- 新增 `TAR_AUTO_REJECT_DAYS` 參數，預設 14 天。
- 新增自動駁回函式 `tar_auto_reject_overdue_recommendations()`。
- 自動駁回時會：
  - 更新推薦信狀態為 `rejected`
  - 寫入駁回原因
  - 記錄來源為 `system`
  - 寄 Email 通知學生
- 若 SMTP 已設定但 Email 發送失敗，會回復資料庫交易，避免資料庫狀態與通知不同步。
- 新增排程入口：

```text
scholarship/cron_auto_reject_recommendations.php
```

此檔可由 Windows 工作排程器或伺服器 cron 每日固定時間呼叫。

### 6. 推薦信 Email 通知

對應需求：

- TAR011：系統通知申請人推薦信撰寫成功或被駁回。
- TAR012：以 Email 通知申請人。
- TAR021：伺服端需正確配置 SMTP。

調整內容：

- 推薦信提交成功後寄信通知學生。
- 導師手動駁回後寄信通知學生。
- 系統自動駁回後寄信通知學生。
- 使用共用寄信工具 `mail_helpers.php`。

### 7. 學生端推薦信狀態查詢

檔案：

- `scholarship/student/student-dashboard.php`

對應需求：

- TAR027：避免 Email 收發問題，學生介面需提供狀態查詢作為備援。

調整內容：

- 學生首頁新增推薦信狀態欄位。
- 可查看：
  - 尚未建立推薦信請求
  - 待填寫
  - 草稿
  - 已提交
  - 已駁回
- 若已駁回，顯示駁回來源與原因。

## 六、資料庫結構調整

在 `recommendations` 資料表新增欄位：

```text
draft_content text NULL
expires_at datetime NULL
submitted_at datetime NULL
status varchar(20) NOT NULL DEFAULT 'pending'
rejected_reason text NULL
rejected_source varchar(20) NULL
rejected_at datetime NULL
```

新增唯一索引：

```text
uq_recommendations_application_id(application_id)
```

用途：

- 支援推薦信草稿。
- 支援推薦信狀態追蹤。
- 支援手動與自動駁回。
- 確保每份申請只會有一封推薦信。

## 七、組織端申請資料頁修正

檔案：

- `scholarship/organization/view_applicants.php`

修正內容：

- 修正推薦信內容為 `NULL` 時，`htmlspecialchars()` 在 PHP 8.1+ 產生 deprecated warning 的問題。
- 若推薦信尚未填寫，畫面顯示「尚未填寫推薦信」。

## 八、檔案上傳與下載權限調整

檔案：

- `scholarship/file_helpers.php`
- `scholarship/file_view.php`

調整內容：

- 補強 `application_files` 欄位相容性。
- 新增檔案分類與關聯欄位，例如：
  - `application_id`
  - `scholarship_id`
  - `recommendation_id`
  - `ticket_id`
  - `file_category`
  - `file_path`
- 下載檔案時依照使用者角色與資料關聯檢查權限。
- 避免使用者直接存取不屬於自己的申請或推薦信附件。

## 九、測試資料與管理員新增

曾建立測試流程資料，包含：

- 管理員帳號：

```text
帳號：manager
密碼：12345678
```

- 學生測試帳號
- 導師測試帳號
- 組織測試帳號
- 測試獎助學金
- 測試申請與推薦信 token

另外新增本機開發用資料庫工具：

- `scholarship/dev_db_tool.php`

功能：

- 一鍵建立或重設 `manager / 12345678`
- 執行簡單 SQL：
  - `SELECT`
  - `SHOW`
  - `DESCRIBE`
  - `EXPLAIN`
  - `INSERT`

安全限制：

- 只允許從 `localhost` 或 `127.0.0.1` 開啟。
- 已加入 `.gitignore`，不建議提交到 GitHub。

## 十、VS Code 資料庫管理

可使用 VS Code 外掛管理資料庫，例如：

- Database Client
- Database Client JDBC
- SQLTools
- SQLTools MySQL/MariaDB Driver

連線設定範例：

```text
Type: MySQL
Host: your-db-host
Port: 3306
Database: scholarship
User: your-db-user
Password: your-db-password
```

JDBC URL 範例：

```text
jdbc:mysql://your-db-host:3306/scholarship?useSSL=false&serverTimezone=Asia/Taipei&characterEncoding=utf8
```

注意：GitHub 文件中不應放真實資料庫 IP、帳號或密碼。

## 十一、目前 TAR 需求完成狀態

| 編號 | 狀態 | 說明 |
| --- | --- | --- |
| TAR001 | 已完成 | 導師可瀏覽學生基本資料 |
| TAR002 | 已完成 | 導師可瀏覽學生申請資料 |
| TAR003 | 已完成 | 導師只能查看自己相關學生 |
| TAR004 | 已完成 | 私人資料遮蔽 |
| TAR005 | 已完成 | 推薦信可填寫、編輯、提交 |
| TAR006 | 已完成 | 推薦信草稿可保存 |
| TAR007 | 已完成 | 已提交不可再次編輯 |
| TAR008 | 已完成 | 每份申請限制一封推薦信 |
| TAR009 | 已完成 | 導師可駁回並填原因 |
| TAR010 | 已完成 | 14 天自動駁回邏輯 |
| TAR011 | 已完成 | 推薦信成功或駁回會通知學生 |
| TAR012 | 已完成 | 使用 Email 通知 |
| TAR013 | 已支援 | 查詢使用索引與限制資料範圍，實際秒數需依部署環境測試 |
| TAR014 | 已支援 | 已提供排程腳本，需由伺服器排程執行 |
| TAR015 | 已完成 | 推薦信草稿暫存 |
| TAR016 | 已完成 | 自動駁回使用交易控制 |
| TAR017 | 已完成 | 電話與帳號遮蔽 |
| TAR018 | 已完成 | 導師資料存取權限限制 |
| TAR019 | 已完成 | 自動駁回天數參數化 |
| TAR020 | 已完成 | 記錄駁回來源 |
| TAR021 | 已支援 | SMTP 設定已支援，需正確填入環境設定 |
| TAR022 | 不適用 | 本專案為 PHP/XAMPP，非 Java/Tomcat 架構 |
| TAR023 | 已支援 | 前端為 HTML5，可於 Chrome/Edge/Firefox 使用 |
| TAR024 | 部署項目 | HTTPS/443 需正式伺服器設定 |
| TAR025 | 已符合限制 | 系統不追蹤 Email 是否開啟 |
| TAR026 | 已列為限制 | 若外部學務 API 離線，無法取得最新資料 |
| TAR027 | 已完成 | 學生端提供推薦信狀態查詢 |
| TAR028 | 已完成 | 區分系統自動與導師手動駁回 |

## 十二、測試建議

建議依照以下流程測試：

1. 使用學生帳號登入並申請獎助學金。
2. 填入推薦老師資料。
3. 使用導師帳號登入。
4. 在導師首頁查看推薦信請求。
5. 進入推薦信頁面，先暫存草稿。
6. 重新整理頁面確認草稿仍存在。
7. 正式提交推薦信。
8. 確認提交後不可再次編輯。
9. 使用學生帳號登入，確認推薦信狀態顯示為已提交。
10. 新增另一筆測試申請，測試導師手動駁回。
11. 確認學生端可看到駁回原因。
12. 執行自動駁回腳本，確認逾期推薦信會被系統駁回。

自動駁回腳本可用 PHP CLI 執行：

```bash
php scholarship/cron_auto_reject_recommendations.php
```

在 XAMPP Windows 環境可使用：

```powershell
C:\xampp\php\php.exe scholarship\cron_auto_reject_recommendations.php
```

## 十三、注意事項

- 不要把 `config.php`、資料庫密碼、SMTP 密碼、Gmail App Password 推到 GitHub。
- 若曾將密碼貼在公開平台，應重新產生資料庫密碼與 Gmail App Password。
- `dev_db_tool.php` 只適合本機開發，不應部署到正式環境。
- 若正式部署，需設定 HTTPS、網域、SMTP、排程工具與伺服器權限。
