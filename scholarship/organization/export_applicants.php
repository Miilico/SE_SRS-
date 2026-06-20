<?php
session_start();
require_once "db.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/scholarship_access.php";

// 1. 權限與參數檢查
organization_require_scholarship_manager();

$scholarship_id = isset($_GET['scholarship_id']) ? $_GET['scholarship_id'] : null;

if (!$scholarship_id) {
    die("缺少獎助學金編號");
}

// 2. 驗證該獎助學金是否屬於該單位，並取得名稱作為檔名
$scholarship = organization_fetch_managed_scholarship($pdo, $scholarship_id);

if (!$scholarship) {
    die("找不到該獎助學金或無權限匯出");
}

$scholarship_name = $scholarship['NAME'];
$filename = "申請名單_" . $scholarship_name . "_" . date('Ymd_His') . ".csv";

// 3. 設定 HTTP Headers，告訴瀏覽器這是一個 CSV 檔案下載
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 開啟輸出串流
$output = fopen('php://output', 'w');

// 寫入 UTF-8 BOM，讓 Excel 能正確顯示中文
fwrite($output, "\xEF\xBB\xBF");

// 4. 準備 CSV 標題列
$headers = ['申請編號', '學號', '姓名', '申請時間', '審查狀態'];

// 撈取該獎學金的自訂表單欄位名稱，加入到標題列後面
$sql_fields = "SELECT id, field_label, field_type FROM scholarship_fields WHERE scholarship_id = ? ORDER BY id";
$stmt_fields = $pdo->prepare($sql_fields);
$stmt_fields->execute([$scholarship_id]);
$custom_fields = $stmt_fields->fetchAll(PDO::FETCH_ASSOC);

foreach ($custom_fields as $field) {
    $headers[] = $field['field_label'];
}

// 寫入標題列
fputcsv($output, $headers);

// 5. 撈取申請學生基本資料
$sql_app = "SELECT a.APNO AS app_id, a.STID AS student_id, a.RESULT, u.NAME AS student_name, a.APDATE AS create_time 
            FROM application a 
            JOIN users u ON a.STID = u.ID 
            WHERE a.SCID = ? 
            ORDER BY a.APDATE DESC";
$stmt_app = $pdo->prepare($sql_app);
$stmt_app->execute([$scholarship_id]);
$applicants = $stmt_app->fetchAll(PDO::FETCH_ASSOC);

// 6. 逐筆寫入學生資料與自訂欄位回覆
foreach ($applicants as $app) {
    $row = [
        $app['app_id'],
        $app['student_id'],
        $app['student_name'],
        $app['create_time'],
        $app['RESULT']
    ];

    $sql_ans = "SELECT field_id, answer_value 
                FROM application_custom_answers 
                WHERE application_id = ?";
    $stmt_ans = $pdo->prepare($sql_ans);
    $stmt_ans->execute([$app['app_id']]);
    
    $answers = [];
    while ($ans = $stmt_ans->fetch(PDO::FETCH_ASSOC)) {
        $answers[$ans['field_id']] = $ans['answer_value'];
    }

    // 依照標題列的自訂欄位順序填入資料
    foreach ($custom_fields as $field) {
        $field_id = $field['id'];
        if (isset($answers[$field_id]) && $answers[$field_id] !== '') {
            if ($field['field_type'] === 'file') {
                $row[] = '已上傳附件 (請至系統檢視)';
            } else {
                $row[] = $answers[$field_id];
            }
        } else {
            $row[] = ''; // 沒填寫則留白
        }
    }
    // 寫入此學生的資料列
    fputcsv($output, $row);
}

// 關閉串流並結束程式
fclose($output);
exit;
