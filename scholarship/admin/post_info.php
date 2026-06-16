<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/header.php";
unset($adminHeaderBootstrapOnly);

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
$pageTitle = ($mode == 'edit') ? "修改公告" : "新增公告";
$activeNav = "post_management.php";
?>
<?php require __DIR__ . "/header.php"; ?>

    <div class="form-box">
        <div class="admin-actions">
            <a href="post_management.php">返回公告管理清單</a>
        </div>
        <h1 class="admin-page-title"><?php echo ($mode == 'edit') ? "修改公告內容 (#$id)" : "發佈新消息"; ?></h1>
        <div class="admin-page-subtitle admin-form-lead">填寫公告類別、標題與內容後送出。</div>
        
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
</main>
</body>
</html>
