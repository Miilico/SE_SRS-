<?php
session_start();
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(3);

// 權限檢查：根據您的設定 ROLE 3 為管理員
/*if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) {
    die("權限不足，僅限系統管理員操作。");
}*/

$id = isset($_GET['id']) ? $_GET['id'] : '';
$post = null;
$mode = 'add';

// 新增功能：接收來自 URL 參數的預設標題與內容 (用於自動生成名單)
$default_title = isset($_GET['title']) ? $_GET['title'] : '';
$default_content = isset($_GET['content']) ? $_GET['content'] : '';
$default_cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

// 如果有 ID，進入修改模式並抓取舊資料
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM announcement WHERE ID = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if ($post) {
        $mode = 'edit';
        $default_title = $post['title'];
        $default_content = $post['CONTENT'];
        // 假設資料庫已新增 CATEGORY 欄位，若無則預設為 0
        $default_cat = isset($post['CATEGORY']) ? $post['CATEGORY'] : 0;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo ($mode == 'edit') ? "修改公告" : "新增公告"; ?></title>
    <style>
        body { font-family: "Microsoft JhengHei", sans-serif; background-color: #f4f7f6; color: #333; }
        .nav-bar { text-align: right; width: 650px; margin: 20px auto; font-size: 14px; }
        .nav-bar a { color: #666; text-decoration: none; }
        .form-box { width: 650px; margin: 20px auto; padding: 30px; border: 1px solid #ddd; border-radius: 12px; background: white; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .tt { font-size: 24px; font-weight: bold; text-align: center; color: #2c3e50; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 8px; color: #555; }
        
        input[type="text"], textarea, select { 
            width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; 
            box-sizing: border-box; font-size: 16px; background-color: #fafafa;
        }
        input[type="text"]:focus, textarea:focus, select:focus { border-color: #007bff; outline: none; background-color: #fff; }
        
        .btn-group { text-align: center; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .save-btn { background: #28a745; color: white; padding: 12px 40px; border: none; cursor: pointer; border-radius: 6px; font-size: 18px; font-weight: bold; transition: 0.3s; }
        .save-btn:hover { background: #218838; transform: translateY(-2px); }
        .cancel-link { color: #888; text-decoration: none; margin-left: 20px; font-size: 16px; }
        
        /* 獎學金類別的特殊提醒樣式 */
        .hint { font-size: 13px; color: #dc3545; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="nav-bar">
        <a href="admin_dashboard.php">回儀表板首頁</a> | 
        <a href="post_management.php">回公告管理清單</a>
    </div>

    <div class="form-box">
        <div class="tt"><?php echo ($mode == 'edit') ? "📝 修改公告內容 (#$id)" : "📢 發佈新消息"; ?></div>
        
        <form method="post" action="post_process.php">
            <input type="hidden" name="mode" value="<?php echo $mode; ?>">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : ''; ?>
            <input type="hidden" name="aid" value="<?php echo $admin_id; ?>">

            <div class="form-group">
                <label>公告類別：</label>
                <select name="category">
                    <option value="0" <?php echo $default_cat == 0 ? 'selected' : ''; ?>>一般系統公告</option>
                    <option value="1" <?php echo $default_cat == 1 ? 'selected' : ''; ?>>獎學金審查結果</option>
                </select>
                <?php if($default_cat == 1): ?>
                    <div class="hint">* 已自動帶入獲獎學生名單，發佈前請確認格式。</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>公告標題：</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($default_title); ?>" placeholder="例如：2024年第一季獎學金錄取名單" required>
            </div>

            <div class="form-group">
                <label>公告內容：</label>
                <textarea name="content" rows="12" placeholder="請輸入詳細公告內容..." required><?php echo htmlspecialchars($default_content); ?></textarea>
            </div>

            <div class="btn-group">
                <button type="submit" class="save-btn">確認發佈</button>
                <a href="post_management.php" class="cancel-link">取消並返回</a>
            </div>
        </form>
    </div>
</body>
</html>