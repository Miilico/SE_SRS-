<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";
require_once __DIR__ . "/../custom_form_helpers.php";
require_role(1); // 1=學生

ensure_teachers_table($pdo);

$stId = $_SESSION["user"]["id"];
//$userName = $_SESSION["user"]["name"] ?? "";
$userName = isset($_SESSION["user"]["name"]) ? $_SESSION["user"]["name"] : "";

// 獎學金清單
$activeScholarshipSql = table_has_column($pdo, "scholarship", "is_active")
        ? " AND is_active = 1"
        : "";
$schs = $pdo->query("SELECT id, NAME, DEADLINE, AMOUNT 
        FROM scholarship 
        WHERE start_date <= CURDATE() AND DEADLINE >= CURDATE()" . $activeScholarshipSql . "
        ORDER BY DEADLINE ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

$old = isset($_SESSION["application_old"]) && is_array($_SESSION["application_old"])
        ? $_SESSION["application_old"]
        : array();
unset($_SESSION["application_old"]);

function application_old_value($old, $key)
{
    return isset($old[$key]) && !is_array($old[$key]) ? (string)$old[$key] : "";
}

$selectedScholarshipId = isset($_GET["scid"]) ? (int)$_GET["scid"] : 0;
if ($selectedScholarshipId <= 0 && isset($_GET["id"])) {
    $selectedScholarshipId = (int)$_GET["id"];
}
if ($selectedScholarshipId <= 0 && !empty($old["SCID"])) {
    $selectedScholarshipId = (int)$old["SCID"];
}

$customFields = $selectedScholarshipId > 0
        ? custom_form_fields_for_scholarship($pdo, $selectedScholarshipId)
        : array();
$customValues = isset($old["CUSTOM_FIELDS"]) && is_array($old["CUSTOM_FIELDS"])
        ? $old["CUSTOM_FIELDS"]
        : array();

$stmt = $pdo->query(" 
  SELECT t.ID, u.NAME AS teacher_name, u.EMAIL, t.DNAME AS dept_name, t.UNIT_NAME, t.JOB_TITLE
  FROM teachers t 
  JOIN users u ON t.ID = u.ID 
  ORDER BY COALESCE(t.UNIT_NAME, ''), t.DNAME, u.NAME
");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);


$recommendLink = isset($_SESSION["recommend_link"]) ? $_SESSION["recommend_link"] : "";
unset($_SESSION["recommend_link"]);


$pageTitle = "申請獎助學金";
$activeNav = "apply.php";
$siteHeaderRequiredRole = 1;
require __DIR__ . "/../header.php";
?>

<div class="card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="text-muted small">Application Form</div>
            <h1 class="h4 fw-bold mb-1">獎助學金申請表</h1>
            <div class="text-secondary small">學生 ID：<?= htmlspecialchars($stId) ?></div>
        </div>
        <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">
      Scholarship
    </span>
    </div>

    <?php if ($recommendLink): ?>
        <div class="alert alert-info mt-3 mb-0">
            <!--<div class="fw-semibold">教授推薦連結（Demo 用）</div>-->
            <div class="fw-semibold">已傳送推薦信連結至推薦人的信箱</div>
            <!--<div class="small muted">把這個連結貼給教授，他不用註冊也能填推薦信。</div>-->
            <!--<div class="mt-2">
          <a href="<?= htmlspecialchars($recommendLink) ?>" target="_blank" class="text-decoration-none">
            <?= htmlspecialchars($recommendLink) ?>
          </a>
        </div>-->
        </div>
    <?php endif; ?>
</div>

<form class="card p-4" id="application-form" action="/scholarship/student/apply_submit.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="STID" value="<?= htmlspecialchars($stId) ?>">

    <!-- 1. 選擇獎學金 -->
    <div class="mb-4">
        <div class="fw-bold mb-2">1. 選擇獎助學金</div>
        <label class="form-label fw-semibold">獎助學金 <span class="text-danger" aria-label="必填">*</span></label>
        <select class="form-select" name="SCID" id="scholarship-select" required>
            <option value="" disabled <?= $selectedScholarshipId <= 0 ? "selected" : "" ?>>請選擇</option>
            <?php foreach ($schs as $s): ?>
                <!--<option value="?= htmlspecialchars($s["ID"]."|".$s["NAME"]."|".$s["AMOUNT"]) ?>">
                    ?= htmlspecialchars($s["NAME"]) ?>（截止：?= htmlspecialchars($s["DEADLINE"]) ?>，金額：?= htmlspecialchars($s["AMOUNT"]) ?>）
                  </option>-->
                <option value="<?= htmlspecialchars($s["id"]) ?>" <?= (int)$s["id"] === $selectedScholarshipId ? "selected" : "" ?>>
                    <!--<option value="?= (int)($s["id"]) ?>">-->
                    <?= htmlspecialchars($s["NAME"]) ?>（截止：<?= htmlspecialchars($s["DEADLINE"]) ?>，金額：<?= htmlspecialchars($s["AMOUNT"]) ?>）
                </option>
            <?php endforeach; ?>
        </select>
        <!--<div class="form-text">送出後寫入 application.SCID / SCNAME / AMOUNT</div>-->
    </div>

    <!-- 2. 基本資料 -->
    <div class="mb-4">
        <div class="fw-bold mb-2">2. 基本資料</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">學號</label>
                <input class="form-control" value="<?= htmlspecialchars($stId) ?>" disabled>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">姓名</label>
                <input class="form-control" value="<?= htmlspecialchars($userName) ?>" disabled>
            </div>
            <!--<div class="col-md-4">
                <label class="form-label fw-semibold">申請方式</label>
                <select class="form-select" name="METHOD">
                  <option value="線上">線上</option>
                  <option value="紙本">紙本</option>
                </select>
              </div>-->
        </div>
        <!--<div class="form-text">電話/Email 建議之後從 users/students 表自動帶入</div>-->
    </div>

    <!-- 3. 學業/資格 -->
    <div class="mb-4">
        <div class="fw-bold mb-2">3. 學業/資格資料</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">GPA / 成績</label>
                <input class="form-control" type="number" name="GRADE" step="0.01" min="0" max="100"
                       value="<?= htmlspecialchars(application_old_value($old, "GRADE")) ?>" placeholder="例如：85.5">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">班排/系排</label>
                <input class="form-control" name="RANK" maxlength="50"
                       value="<?= htmlspecialchars(application_old_value($old, "RANK")) ?>" placeholder="例如：班排 3/45">
            </div>
        </div>
    </div>

    <!-- 4. 推薦信（推薦人免註冊） -->
    <div class="mb-4">
        <div class="fw-bold mb-2">4. 推薦信</div>
        <div class="text-secondary small mb-3"><span class="text-danger" aria-label="條件式必填">*</span> 如需推薦信，請選擇已註冊推薦人，或填寫推薦人姓名、單位名稱、職稱與 Email。</div>
        <!--<div class="section-title mb-2">4. 推薦信（教授免註冊填寫）</div>-->
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">推薦人（已註冊帳號可選）</label>
                <select class="form-select" name="teacher_id" id="teacher_id">
                    <option value="">不從帳號選擇，改填外部推薦人</option>
                    <?php foreach ($teachers as $t): ?>
                        <?php
                        $unit = $t["UNIT_NAME"] ?: $t["dept_name"];
                        $teacherId = (string)($t["ID"] ?? "");
                        $teacherName = (string)($t["teacher_name"] ?? "");
                        $teacherEmail = (string)($t["EMAIL"] ?? "");
                        $teacherUnit = (string)($unit ?? "");
                        $teacherTitle = (string)($t["JOB_TITLE"] ?? "");
                        $labelParts = array_filter(array($teacherUnit, $teacherTitle));
                        ?>
                        <option
                                value="<?= htmlspecialchars($teacherId) ?>"
                                <?= application_old_value($old, "teacher_id") === $teacherId ? "selected" : "" ?>
                                data-name="<?= htmlspecialchars($teacherName) ?>"
                                data-email="<?= htmlspecialchars($teacherEmail) ?>"
                                data-unit="<?= htmlspecialchars($teacherUnit) ?>"
                                data-title="<?= htmlspecialchars($teacherTitle) ?>">
                            <?= htmlspecialchars($teacherName) ?><?= $labelParts ? "（" . htmlspecialchars(implode(" / ", $labelParts)) . "）" : "" ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">推薦人姓名</label>
                <input class="form-control" id="rec_name" name="REC_NAME" maxlength="100"
                       value="<?= htmlspecialchars(application_old_value($old, "REC_NAME")) ?>" placeholder="例如：王小明">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">單位名稱</label>
                <input class="form-control" id="rec_unit" name="REC_UNIT" maxlength="100"
                       value="<?= htmlspecialchars(application_old_value($old, "REC_UNIT")) ?>" placeholder="例如：國立成功大學、XX科技股份有限公司">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">職稱</label>
                <input class="form-control" id="rec_title" name="REC_TITLE" maxlength="100"
                       value="<?= htmlspecialchars(application_old_value($old, "REC_TITLE")) ?>" placeholder="例如：副教授、講師、高級工程師">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">推薦人 Email</label>
                <input class="form-control" id="rec_email" type="email" name="REC_EMAIL" maxlength="100"
                       value="<?= htmlspecialchars(application_old_value($old, "REC_EMAIL")) ?>" placeholder="xxx@example.com">
            </div>

            <div class="col-md-12">
                <label class="form-label fw-semibold">關係</label>
                <input class="form-control" name="REC_REL" maxlength="50"
                       value="<?= htmlspecialchars(application_old_value($old, "REC_REL")) ?>" placeholder="例如：專題指導教授、實習主管">
            </div>
        </div>
    </div>

    <?php require __DIR__ . "/partials/application_custom_fields.php"; ?>

    <!-- 5. 自傳/讀書計畫 -->
    <div class="mb-4">
        <div class="fw-bold mb-2">5. 自傳 <span class="text-danger" aria-label="必填">*</span></div>
        <div class="border border-2 rounded bg-white p-3">
            <input class="form-control" type="file" name="AUTOBI_FILE" accept=".pdf,.doc,.docx" required>
            <div class="text-muted small mt-2">允許格式：PDF、DOC、DOCX；單檔上限 20MB。</div>
        </div>
    </div>
    <!--<div class="mb-4">
        <div class="section-title mb-2">5. 自傳（最多1000字）</div>
        <div class="section-title mb-2">5. 自傳 / 讀書計畫（必傳）</div>
        <textarea class="form-control" name="AUTOBI_TEXT" rows="8" placeholder="請在此輸入自傳…" required></textarea>
        <div class="text-muted small mt-2">請直接輸入文字內容，建議 500–1000 字。</div>
      </div>-->

    <!-- 6. 其他有利審查資料 -->
    <div class="mb-2">
        <div class="fw-bold mb-2">6. 其他有利審查資料（可多檔）</div>
        <div class="border border-2 rounded bg-white p-3">
            <input class="form-control" type="file" name="OTHER_FILES[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
            <div class="text-muted small mt-2">獎狀、證照、作品集、證明等可多檔上傳；允許格式：PDF、DOC、DOCX、JPG、JPEG、PNG；單檔上限 20MB。</div>
        </div>
    </div>

    <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" value="1" id="agree" required>
        <label class="form-check-label text-secondary" for="agree">
            本人保證以上資料及文件皆屬實。<span class="text-danger" aria-label="必填">*</span>
        </label>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-primary fw-semibold" type="submit">送出申請</button>
    </div>
</form>

<script>
    document.getElementById("scholarship-select").addEventListener("change", function () {
        window.location.href = "/scholarship/student/apply.php?scid=" + encodeURIComponent(this.value);
    });

    (function() {
        var teacherSelect = document.getElementById("teacher_id");
        if (!teacherSelect) return;

        var fields = {
            name: document.getElementById("rec_name"),
            email: document.getElementById("rec_email"),
            unit: document.getElementById("rec_unit"),
            title: document.getElementById("rec_title")
        };

        teacherSelect.addEventListener("change", function() {
            var option = teacherSelect.options[teacherSelect.selectedIndex];
            if (!option || !option.value) return;

            Object.keys(fields).forEach(function(key) {
                if (fields[key]) {
                    fields[key].value = option.getAttribute("data-" + key) || "";
                }
            });
        });
    }());
</script>
</main>
</body>

</html>
