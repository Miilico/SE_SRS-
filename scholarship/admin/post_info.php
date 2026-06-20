<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/../header.php";
unset($adminHeaderBootstrapOnly);
require_once __DIR__ . "/../file_helpers.php";

// 權限檢查：根據您的設定 ROLE 3 為管理員
/*if (!isset($_SESSION['role']) || $_SESSION['role'] != 3) {
    die("權限不足，僅限系統管理員操作。");
}*/

$id = isset($_GET['id']) ? $_GET['id'] : '';
$post = null;
$mode = 'add';
$files = array();

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
        $announcementId = isset($post["id"]) ? (int)$post["id"] : (int)$id;
        $files = fetch_uploaded_files($pdo, 1, "announcement_id", $announcementId);
    }
}
$pageTitle = ($mode == 'edit') ? "修改公告" : "新增公告";
$activeNav = "post_management.php";
?>
<?php require __DIR__ . "/../header.php"; ?>

    <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
        <h1 class="h3 fw-bold mb-1"><?php echo ($mode == 'edit') ? "修改公告內容 (#$id)" : "發佈新消息"; ?></h1>
        <div class="text-secondary mb-4">填寫公告類別、標題與內容後送出。</div>
        
        <form method="post" action="post_process.php" enctype="multipart/form-data" class="vstack gap-3">
            <input type="hidden" name="mode" value="<?php echo $mode; ?>">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : ''; ?>
            <input type="hidden" name="aid" value="<?php echo $admin_id; ?>">

            <div>
                <label class="form-label fw-semibold">公告類別：</label>
                <select class="form-select" name="category">
                    <option value="0" <?php echo $default_cat == 0 ? 'selected' : ''; ?>>一般系統公告</option>
                    <option value="1" <?php echo $default_cat == 1 ? 'selected' : ''; ?>>獎學金審查結果</option>
                    <option value="2" <?php echo $default_cat == 2 ? 'selected' : ''; ?>>獎助單位訊息</option>
                </select>
                <?php if($default_cat == 1): ?>
                    <div class="form-text text-danger">提醒：已自動帶入獲獎學生名單，發佈前請確認格式。</div>
                <?php endif; ?>
            </div>

            <div>
                <label class="form-label fw-semibold">公告標題：<span class="text-danger" aria-label="必填">*</span></label>
                <input class="form-control" type="text" name="title" value="<?php echo htmlspecialchars($default_title); ?>" placeholder="例如：2024年第一季獎學金錄取名單" required>
            </div>

            <div>
                <label class="form-label fw-semibold">公告內容：<span class="text-danger" aria-label="必填">*</span></label>
                <textarea class="form-control" name="content" rows="12" placeholder="請輸入詳細公告內容..." required><?php echo htmlspecialchars($default_content); ?></textarea>
            </div>

            <div>
                <label class="form-label fw-semibold">公告附件：</label>
                <input class="form-control" type="file" id="announcementFiles" name="ANNOUNCEMENT_FILES[]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx,.txt,.zip,.odt,.odc,.webp,.ppt,.pptx,.ods,.gif" multiple>
                <div class="form-text text-danger">可一次選擇多個檔案，也可分次選取後一併上傳；公告附件屬公開文件，所有人可下載。允許格式：PDF、DOC、DOCX、JPG、JPEG、PNG、XLS、XLSX、TXT、ZIP、ODT、ODC、WEBP、PPT、PPTX、ODS、GIF；單檔上限 20MB。</div>
                <div id="selectedAnnouncementFiles" class="mt-3" hidden>
                    <strong>待上傳附件：</strong>
                    <ul id="selectedAnnouncementFileList"></ul>
                </div>
                <?php if ($mode == 'edit'): ?>
                    <div class="mt-3">
                        <strong>目前附件：</strong>
                        <?php if (!empty($files)): ?>
                            <ul class="list-unstyled mt-2">
                                <?php foreach ($files as $file): ?>
                                    <li class="d-flex flex-wrap align-items-center gap-2 mb-2 existing-announcement-file">
                                        <span class="existing-announcement-file-name">
                                            <a class="existing-announcement-file-link" href="/scholarship/file_view.php?id=<?php echo urlencode((string)$file["id"]); ?>">
                                                <?php echo htmlspecialchars($file["original_name"], ENT_QUOTES, "UTF-8"); ?>
                                            </a>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-danger announcement-file-delete-toggle">刪除</button>
                                        <input type="hidden" name="delete_announcement_files[]" value="<?php echo htmlspecialchars((string)$file["id"], ENT_QUOTES, "UTF-8"); ?>" disabled>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-secondary">尚未上傳附件。</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="submit" class="btn btn-primary">確認發佈</button>
                <a href="post_management.php" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
        </div>
    </div>
    </div>
    </div>
    <script>
        (function() {
            var input = document.getElementById("announcementFiles");
            var form = input ? input.form : null;
            var selectedWrap = document.getElementById("selectedAnnouncementFiles");
            var selectedList = document.getElementById("selectedAnnouncementFileList");

            if (form) {
                form.addEventListener("submit", function(event) {
                    if (!form.querySelector("input[name='delete_announcement_files[]']:not(:disabled)")) {
                        return;
                    }

                    if (!window.confirm("確定要刪除勾選的公告附件嗎？")) {
                        event.preventDefault();
                    }
                });
            }

            Array.prototype.forEach.call(document.querySelectorAll(".announcement-file-delete-toggle"), function(button) {
                button.addEventListener("click", function() {
                    var item = button.closest(".existing-announcement-file");
                    var input = item ? item.querySelector("input[name='delete_announcement_files[]']") : null;

                    if (!item || !input) {
                        return;
                    }

                    var marked = item.classList.toggle("marked-delete");
                    input.disabled = !marked;
                    button.textContent = marked ? "恢復" : "刪除";
                    button.classList.toggle("btn-danger", !marked);
                    button.classList.toggle("btn-secondary", marked);
                    item.querySelectorAll(".existing-announcement-file-name, .existing-announcement-file-link").forEach(function(target) {
                        target.classList.toggle("text-secondary", marked);
                        target.classList.toggle("text-decoration-line-through", marked);
                    });
                });
            });

            if (!input || !selectedWrap || !selectedList || typeof DataTransfer === "undefined") {
                return;
            }

            var selectedFiles = new DataTransfer();

            function fileKey(file) {
                return [file.name, file.size, file.lastModified].join(":");
            }

            function renderSelectedFiles() {
                selectedList.innerHTML = "";
                selectedWrap.hidden = selectedFiles.files.length === 0;

                Array.prototype.forEach.call(selectedFiles.files, function(file, index) {
                    var item = document.createElement("li");
                    var name = document.createElement("span");
                    var remove = document.createElement("button");

                    name.textContent = file.name;
                    remove.type = "button";
                    remove.className = "btn btn-sm btn-secondary";
                    remove.textContent = "移除";
                    remove.addEventListener("click", function() {
                        var nextFiles = new DataTransfer();

                        Array.prototype.forEach.call(selectedFiles.files, function(currentFile, currentIndex) {
                            if (currentIndex !== index) {
                                nextFiles.items.add(currentFile);
                            }
                        });

                        selectedFiles = nextFiles;
                        input.files = selectedFiles.files;
                        renderSelectedFiles();
                    });

                    item.appendChild(name);
                    item.appendChild(document.createTextNode(" "));
                    item.appendChild(remove);
                    selectedList.appendChild(item);
                });
            }

            input.addEventListener("change", function() {
                var existingFiles = {};
                Array.prototype.forEach.call(selectedFiles.files, function(file) {
                    existingFiles[fileKey(file)] = true;
                });

                Array.prototype.forEach.call(input.files, function(file) {
                    var key = fileKey(file);
                    if (!existingFiles[key]) {
                        selectedFiles.items.add(file);
                        existingFiles[key] = true;
                    }
                });

                input.files = selectedFiles.files;
                renderSelectedFiles();
            });
        })();
    </script>
</main>
</body>
</html>
