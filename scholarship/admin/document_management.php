<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/../header.php";
unset($adminHeaderBootstrapOnly);
require_once __DIR__ . "/../file_helpers.php";

ensure_application_files_table($pdo);

function document_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function document_format_label($file)
{
    $ext = uploaded_file_extension($file);
    return $ext === "" ? "無副檔名" : strtoupper($ext);
}

function document_size_label($bytes)
{
    $bytes = (int)$bytes;
    if ($bytes <= 0) {
        return "未知";
    }

    $units = array("B", "KB", "MB", "GB");
    $size = (float)$bytes;
    $unitIndex = 0;
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size = $size / 1024;
        $unitIndex++;
    }

    return ($unitIndex === 0 ? (string)(int)$size : number_format($size, 1)) . " " . $units[$unitIndex];
}

function document_uploader_label($file)
{
    $uploaderId = isset($file["uploader_id"]) ? trim((string)$file["uploader_id"]) : "";
    $uploaderName = isset($file["uploader_name"]) ? trim((string)$file["uploader_name"]) : "";

    if ($uploaderName !== "" && $uploaderId !== "") {
        return $uploaderName . "（" . $uploaderId . "）";
    }

    if ($uploaderName !== "") {
        return $uploaderName;
    }

    if ($uploaderId !== "") {
        return $uploaderId;
    }

    return "未知上傳人";
}

function document_uploader_role_label($role)
{
    switch ((int)$role) {
        case 1:
            return "學生";
        case 2:
            return "推薦人";
        case 3:
            return "管理員";
        case 4:
            return "獎助單位";
        default:
            return "未知";
    }
}

function document_detail_type_label($file)
{
    $category = isset($file["file_category"]) ? (int)$file["file_category"] : 0;
    $fileType = isset($file["file_type"]) ? trim((string)$file["file_type"]) : "";
    $fileTypeKey = strtolower($fileType);

    if ($category === 1) {
        return "公告附件";
    }

    if ($category === 2) {
        $applicationTypes = array(
            "autobi" => "申請自傳",
            "support" => "申請佐證資料",
            "application" => "申請附件",
        );

        return isset($applicationTypes[$fileTypeKey]) ? $applicationTypes[$fileTypeKey] : "申請檔案";
    }

    if ($category === 3) {
        return "工單附件";
    }

    if ($category === 4) {
        return "推薦信附件";
    }

    return $fileType !== "" ? "其他檔案：" . $fileType : "其他檔案";
}

function document_context_label($file)
{
    $parts = array();
    if (!empty($file["announcement_id"])) {
        $parts[] = "公告 #" . $file["announcement_id"];
    }
    if (!empty($file["application_id"])) {
        $parts[] = "申請 #" . $file["application_id"];
    }
    if (!empty($file["scholarship_id"])) {
        $parts[] = "獎學金 #" . $file["scholarship_id"];
    }
    if (!empty($file["ticket_id"])) {
        $parts[] = "工單 #" . $file["ticket_id"];
    }
    if (!empty($file["recommendation_id"])) {
        $parts[] = "推薦 #" . $file["recommendation_id"];
    }

    return empty($parts) ? "未關聯" : implode(" / ", $parts);
}

$files = array();
$formats = array();
$uploaders = array();
$detailTypes = array();

try {
    $stmt = $pdo->prepare("
        SELECT f.*, u.NAME AS uploader_name, u.EMAIL AS uploader_email, u.ROLE AS uploader_role
        FROM application_files f
        LEFT JOIN users u ON f.uploader_id = u.ID
        ORDER BY f.created_at DESC, f.id DESC
    ");
    $stmt->execute();
    $files = $stmt->fetchAll();

    foreach ($files as $file) {
        $format = document_format_label($file);
        $uploader = document_uploader_label($file);
        $category = uploaded_file_category_label(isset($file["file_category"]) ? $file["file_category"] : 0);
        $detailType = document_detail_type_label($file);

        $formats[$format] = $format;
        $uploaders[$uploader] = $uploader;
        $detailTypes[$detailType] = $detailType;
    }

    ksort($formats);
    ksort($uploaders);
    ksort($detailTypes);
} catch (PDOException $e) {
    die("查詢失敗：" . $e->getMessage());
}

$pageTitle = "檔案管理";
$activeNav = "document_management.php";
$siteHeaderExtraHead = '
<style>
    .document-toolbar {
        display: grid;
        grid-template-columns: repeat(4, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 12px;
    }

    .document-search-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .document-filter-section {
        margin-bottom: 18px;
        padding-bottom: 18px;
        border-bottom: 1px solid var(--site-border);
    }

    .document-filter-heading {
        margin: 0 0 10px;
        color: #475569;
        font-size: 14px;
        font-weight: 800;
    }

    .document-bulkbar {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        margin-bottom: 14px;
    }

    .document-name {
        min-width: 220px;
        max-width: 340px;
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    .document-meta {
        display: block;
        margin-top: 4px;
        color: var(--site-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .document-uploader-email {
        display: block;
        margin-top: 4px;
        color: var(--site-muted);
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .document-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .document-check {
        width: 18px;
        height: 18px;
    }

    .document-preview-frame {
        width: 100%;
        height: min(72vh, 760px);
        border: 1px solid var(--site-border);
        border-radius: 6px;
        background: #f8fafc;
    }

    .document-docx-preview {
        width: 100%;
        min-height: min(72vh, 760px);
        max-height: min(72vh, 760px);
        overflow: auto;
        padding: 24px;
        border: 1px solid var(--site-border);
        border-radius: 6px;
        background: #fff;
    }

    .document-docx-preview img {
        max-width: 100%;
        height: auto;
    }

    @media (max-width: 900px) {
        .document-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .document-search-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .document-bulkbar {
            display: block;
        }

        .document-bulkbar .btn {
            margin-top: 10px;
        }
    }

    @media (max-width: 640px) {
        .document-toolbar,
        .document-search-grid {
            grid-template-columns: 1fr;
        }
    }
</style>';
?>
<?php require __DIR__ . "/../header.php"; ?>

<div class="admin-page-head">
    <div>
        <h1 class="admin-page-title">檔案管理</h1>
        <div class="admin-page-subtitle">管理系統使用者上傳的檔案</div>
    </div>
</div>

<div class="admin-card">
    <div class="document-filter-section">
        <h2 class="document-filter-heading">篩選</h2>
        <div class="document-toolbar" aria-label="檔案篩選">
            <div>
                <label for="documentFilterFormat">格式</label>
                <select id="documentFilterFormat">
                    <option value="all">全部格式</option>
                    <?php foreach ($formats as $format): ?>
                        <option value="<?php echo document_h($format); ?>"><?php echo document_h($format); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="documentFilterDetailType">檔案歸屬</label>
                <select id="documentFilterDetailType">
                    <option value="all">全部檔案歸屬</option>
                    <?php foreach ($detailTypes as $detailType): ?>
                        <option value="<?php echo document_h($detailType); ?>"><?php echo document_h($detailType); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="documentFilterUploader">上傳人</label>
                <select id="documentFilterUploader">
                    <option value="all">全部上傳人</option>
                    <?php foreach ($uploaders as $uploader): ?>
                        <option value="<?php echo document_h($uploader); ?>"><?php echo document_h($uploader); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="documentFilterUploaderRole">上傳人身分</label>
                <select id="documentFilterUploaderRole">
                    <option value="all">全部身分</option>
                    <option value="學生">學生</option>
                    <option value="推薦人">推薦人</option>
                    <option value="管理員">管理員</option>
                    <option value="獎助單位">獎助單位</option>
                    <option value="未知">未知</option>
                </select>
            </div>
        </div>
    </div>

    <div class="document-filter-section">
        <h2 class="document-filter-heading">搜尋</h2>
        <div class="document-search-grid" aria-label="檔案搜尋">
            <div>
                <label for="documentSearchName">檔案名</label>
                <input id="documentSearchName" type="text" placeholder="原始檔名或儲存檔名">
            </div>
            <div>
                <label for="documentSearchUploaderId">上傳人 ID</label>
                <input id="documentSearchUploaderId" type="text" placeholder="帳號 ID">
            </div>
            <div>
                <label for="documentSearchUploaderName">上傳人姓名</label>
                <input id="documentSearchUploaderName" type="text" placeholder="姓名或單位名稱">
            </div>
            <div>
                <label for="documentSearchUploaderEmail">上傳人 Email</label>
                <input id="documentSearchUploaderEmail" type="text" placeholder="email">
            </div>
            <div>
                <label for="documentSearchContext">關聯編號</label>
                <input id="documentSearchContext" type="text" placeholder="公告、申請、獎學金、工單、推薦">
            </div>
            <div>
                <label for="documentSearchSystem">系統欄位</label>
                <input id="documentSearchSystem" type="text" placeholder="檔案 ID、MIME、子類型">
            </div>
        </div>
    </div>

    <form method="post" action="document_process.php" data-document-form>
        <div class="document-bulkbar">
            <div class="muted">
                顯示 <span id="documentVisibleCount"><?php echo count($files); ?></span> / <?php echo count($files); ?> 份檔案，
                已選 <span id="documentSelectedCount">0</span> 份
            </div>
            <button type="submit" name="bulk_delete" value="1" class="btn btn-danger" id="documentBulkDelete" disabled>批量刪除</button>
        </div>

        <div class="admin-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><input class="document-check" type="checkbox" id="documentSelectAll" aria-label="全選"></th>
                        <th>檔案</th>
                        <th>格式</th>
                        <th>類型</th>
                        <th>上傳人</th>
                        <th>關聯</th>
                        <th>大小</th>
                        <th>上傳時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="documentRows">
                    <?php foreach ($files as $file): ?>
                        <?php
                        $format = document_format_label($file);
                        $category = uploaded_file_category_label(isset($file["file_category"]) ? $file["file_category"] : 0);
                        $detailType = document_detail_type_label($file);
                        $uploader = document_uploader_label($file);
                        $uploaderId = isset($file["uploader_id"]) ? trim((string)$file["uploader_id"]) : "";
                        $uploaderName = isset($file["uploader_name"]) ? trim((string)$file["uploader_name"]) : "";
                        $uploaderEmail = isset($file["uploader_email"]) ? trim((string)$file["uploader_email"]) : "";
                        $uploaderRole = document_uploader_role_label(isset($file["uploader_role"]) ? $file["uploader_role"] : 0);
                        $context = document_context_label($file);
                        $previewKind = uploaded_file_preview_kind($file);
                        $previewUrl = uploaded_file_preview_url($file["id"]);
                        $downloadUrl = uploaded_file_view_url($file["id"]);
                        $createdAt = empty($file["created_at"]) ? "未知" : $file["created_at"];
                        $storedName = isset($file["stored_name"]) ? (string)$file["stored_name"] : "";
                        $mimeType = isset($file["mime_type"]) ? (string)$file["mime_type"] : "";
                        $fileType = isset($file["file_type"]) ? (string)$file["file_type"] : "";
                        $announcementId = isset($file["announcement_id"]) ? (string)$file["announcement_id"] : "";
                        $applicationId = isset($file["application_id"]) ? (string)$file["application_id"] : "";
                        $scholarshipId = isset($file["scholarship_id"]) ? (string)$file["scholarship_id"] : "";
                        $ticketId = isset($file["ticket_id"]) ? (string)$file["ticket_id"] : "";
                        $recommendationId = isset($file["recommendation_id"]) ? (string)$file["recommendation_id"] : "";
                        $nameSearch = strtolower($file["original_name"] . " " . $storedName);
                        $contextSearch = strtolower($context . " " . $announcementId . " " . $applicationId . " " . $scholarshipId . " " . $ticketId . " " . $recommendationId);
                        $systemSearch = strtolower($file["id"] . " " . $mimeType . " " . $fileType . " " . $format . " " . $createdAt);
                        ?>
                        <tr
                            data-document-row
                            data-format="<?php echo document_h($format); ?>"
                            data-uploader="<?php echo document_h($uploader); ?>"
                            data-uploader-id="<?php echo document_h(strtolower($uploaderId)); ?>"
                            data-uploader-name="<?php echo document_h(strtolower($uploaderName)); ?>"
                            data-uploader-email="<?php echo document_h(strtolower($uploaderEmail)); ?>"
                            data-uploader-role="<?php echo document_h($uploaderRole); ?>"
                            data-category="<?php echo document_h($category); ?>"
                            data-detail-type="<?php echo document_h($detailType); ?>"
                            data-name-search="<?php echo document_h($nameSearch); ?>"
                            data-context-search="<?php echo document_h($contextSearch); ?>"
                            data-system-search="<?php echo document_h($systemSearch); ?>">
                            <td>
                                <input class="document-check document-row-check" type="checkbox" name="selected_ids[]" value="<?php echo (int)$file["id"]; ?>" aria-label="選擇檔案 <?php echo (int)$file["id"]; ?>">
                            </td>
                            <td>
                                <div class="document-name">
                                    <?php echo document_h($file["original_name"]); ?>
                                    <span class="document-meta">#<?php echo (int)$file["id"]; ?> / <?php echo document_h($file["stored_name"] ?: "舊資料"); ?></span>
                                </div>
                            </td>
                            <td><?php echo document_h($format); ?></td>
                            <td>
                                <?php echo document_h($category); ?>
                                <span class="document-meta"><?php echo document_h($detailType); ?></span>
                            </td>
                            <td>
                                <?php echo document_h($uploader); ?>
                                <span class="document-meta"><?php echo document_h($uploaderRole); ?></span>
                                <?php if ($uploaderEmail !== ""): ?>
                                    <span class="document-uploader-email"><?php echo document_h($uploaderEmail); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo document_h($context); ?></td>
                            <td><?php echo document_h(document_size_label($file["file_size"])); ?></td>
                            <td><?php echo document_h($createdAt); ?></td>
                            <td>
                                <div class="document-actions">
                                    <?php if ($previewKind !== "download"): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-edit document-preview-btn"
                                            data-preview-kind="<?php echo document_h($previewKind); ?>"
                                            data-preview-url="<?php echo document_h($previewUrl); ?>"
                                            data-download-url="<?php echo document_h($downloadUrl); ?>"
                                            data-file-name="<?php echo document_h($file["original_name"]); ?>">
                                            預覽
                                        </button>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-secondary" href="<?php echo document_h($downloadUrl); ?>">下載</a>
                                    <button type="submit" name="delete_one" value="<?php echo (int)$file["id"]; ?>" class="btn btn-sm btn-danger document-delete-one">刪除</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="9" class="text-center muted">目前沒有檔案。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-labelledby="documentPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="documentPreviewTitle">檔案預覽</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                <div id="documentPreviewBody"></div>
            </div>
            <div class="modal-footer">
                <a id="documentPreviewDownload" class="btn btn-secondary" href="#">下載</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">關閉</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/mammoth@1.8.0/mammoth.browser.min.js"></script>
<script>
    (function() {
        var formatFilter = document.getElementById("documentFilterFormat");
        var uploaderFilter = document.getElementById("documentFilterUploader");
        var detailTypeFilter = document.getElementById("documentFilterDetailType");
        var uploaderRoleFilter = document.getElementById("documentFilterUploaderRole");
        var nameSearch = document.getElementById("documentSearchName");
        var uploaderIdSearch = document.getElementById("documentSearchUploaderId");
        var uploaderNameSearch = document.getElementById("documentSearchUploaderName");
        var uploaderEmailSearch = document.getElementById("documentSearchUploaderEmail");
        var contextSearch = document.getElementById("documentSearchContext");
        var systemSearch = document.getElementById("documentSearchSystem");
        var visibleCount = document.getElementById("documentVisibleCount");
        var selectedCount = document.getElementById("documentSelectedCount");
        var bulkDelete = document.getElementById("documentBulkDelete");
        var selectAll = document.getElementById("documentSelectAll");
        var form = document.querySelector("[data-document-form]");
        var rows = Array.prototype.slice.call(document.querySelectorAll("[data-document-row]"));

        function normalize(value) {
            return String(value || "").toLowerCase();
        }

        function includesField(row, attribute, input) {
            var query = normalize(input.value).trim();
            if (query === "") {
                return true;
            }

            return normalize(row.getAttribute(attribute)).indexOf(query) !== -1;
        }

        function rowVisible(row) {
            var format = formatFilter.value;
            var uploader = uploaderFilter.value;
            var detailType = detailTypeFilter.value;
            var uploaderRole = uploaderRoleFilter.value;

            if (format !== "all" && row.getAttribute("data-format") !== format) {
                return false;
            }
            if (uploader !== "all" && row.getAttribute("data-uploader") !== uploader) {
                return false;
            }
            if (detailType !== "all" && row.getAttribute("data-detail-type") !== detailType) {
                return false;
            }
            if (uploaderRole !== "all" && row.getAttribute("data-uploader-role") !== uploaderRole) {
                return false;
            }
            if (!includesField(row, "data-name-search", nameSearch)) {
                return false;
            }
            if (!includesField(row, "data-uploader-id", uploaderIdSearch)) {
                return false;
            }
            if (!includesField(row, "data-uploader-name", uploaderNameSearch)) {
                return false;
            }
            if (!includesField(row, "data-uploader-email", uploaderEmailSearch)) {
                return false;
            }
            if (!includesField(row, "data-context-search", contextSearch)) {
                return false;
            }
            if (!includesField(row, "data-system-search", systemSearch)) {
                return false;
            }

            return true;
        }

        function updateSelectionState() {
            var visibleRows = rows.filter(function(row) {
                return row.style.display !== "none";
            });
            var checkedRows = visibleRows.filter(function(row) {
                var checkbox = row.querySelector(".document-row-check");
                return checkbox && checkbox.checked;
            });
            var totalChecked = document.querySelectorAll(".document-row-check:checked").length;

            visibleCount.textContent = String(visibleRows.length);
            selectedCount.textContent = String(totalChecked);
            bulkDelete.disabled = totalChecked === 0;

            if (selectAll) {
                selectAll.checked = visibleRows.length > 0 && checkedRows.length === visibleRows.length;
                selectAll.indeterminate = checkedRows.length > 0 && checkedRows.length < visibleRows.length;
            }
        }

        function applyFilters() {
            rows.forEach(function(row) {
                row.style.display = rowVisible(row) ? "" : "none";
            });
            updateSelectionState();
        }

        [
            formatFilter,
            uploaderFilter,
            detailTypeFilter,
            uploaderRoleFilter,
            nameSearch,
            uploaderIdSearch,
            uploaderNameSearch,
            uploaderEmailSearch,
            contextSearch,
            systemSearch
        ].forEach(function(control) {
            control.addEventListener("input", applyFilters);
            control.addEventListener("change", applyFilters);
        });

        document.addEventListener("change", function(event) {
            if (event.target && event.target.classList.contains("document-row-check")) {
                updateSelectionState();
            }
        });

        if (selectAll) {
            selectAll.addEventListener("change", function() {
                rows.forEach(function(row) {
                    if (row.style.display !== "none") {
                        var checkbox = row.querySelector(".document-row-check");
                        if (checkbox) {
                            checkbox.checked = selectAll.checked;
                        }
                    }
                });
                updateSelectionState();
            });
        }

        if (form) {
            form.addEventListener("submit", function(event) {
                var submitter = event.submitter;
                var message = submitter && submitter.classList.contains("document-delete-one")
                    ? "確定要刪除此檔案嗎？此操作無法復原。"
                    : "確定要刪除已選檔案嗎？此操作無法復原。";

                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        }

        document.querySelectorAll(".document-preview-btn").forEach(function(button) {
            button.addEventListener("click", function() {
                var kind = button.getAttribute("data-preview-kind");
                var previewUrl = button.getAttribute("data-preview-url");
                var downloadUrl = button.getAttribute("data-download-url");
                var fileName = button.getAttribute("data-file-name") || "檔案預覽";
                var modalEl = document.getElementById("documentPreviewModal");
                var body = document.getElementById("documentPreviewBody");
                var title = document.getElementById("documentPreviewTitle");
                var download = document.getElementById("documentPreviewDownload");

                title.textContent = fileName;
                download.href = downloadUrl;
                body.innerHTML = "";

                if (kind === "image") {
                    var image = document.createElement("img");
                    image.src = previewUrl;
                    image.alt = fileName;
                    image.style.maxWidth = "100%";
                    image.style.height = "auto";
                    body.appendChild(image);
                } else if (kind === "pdf" || kind === "text" || kind === "doc") {
                    var frame = document.createElement("iframe");
                    frame.className = "document-preview-frame";
                    frame.src = previewUrl;
                    frame.title = fileName;
                    body.appendChild(frame);
                } else if (kind === "docx") {
                    var container = document.createElement("div");
                    container.className = "document-docx-preview";
                    container.textContent = "載入中...";
                    body.appendChild(container);

                    fetch(previewUrl, { credentials: "same-origin" })
                        .then(function(response) {
                            if (!response.ok) {
                                throw new Error("preview failed");
                            }
                            return response.arrayBuffer();
                        })
                        .then(function(buffer) {
                            return mammoth.convertToHtml({ arrayBuffer: buffer });
                        })
                        .then(function(result) {
                            container.innerHTML = result.value || "此檔案沒有可顯示的內容。";
                        })
                        .catch(function() {
                            container.innerHTML = "此檔案暫時無法預覽，請下載查看。";
                        });
                }

                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        });

        applyFilters();
    })();
</script>
</main>
</body>
</html>
