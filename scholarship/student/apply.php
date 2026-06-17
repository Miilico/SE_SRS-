<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_role(1); // 1=學生

$stId = $_SESSION["user"]["id"];
//$userName = $_SESSION["user"]["name"] ?? "";
$userName = isset($_SESSION["user"]["name"]) ? $_SESSION["user"]["name"] : "";

// 獎學金清單
$schs = $pdo->query("SELECT id, NAME, DEADLINE, AMOUNT 
        FROM scholarship 
        WHERE start_date <= CURDATE() AND DEADLINE >= CURDATE()
        ORDER BY DEADLINE ASC")
  ->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query(" 
  SELECT t.ID, u.NAME AS teacher_name, t.DNAME AS dept_name 
  FROM teachers t 
  JOIN users u ON t.ID = u.ID 
  ORDER BY t.DNAME, u.NAME 
");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);


// demo：顯示教授連結（apply_submit.php 會帶回來）
//$recommendLink = $_GET["recommend_link"] ?? "";
$recommendLink = isset($_GET["recommend_link"]) ? $_GET["recommend_link"] : "";


$pageTitle = "申請獎助學金";
$activeNav = "apply.php";
$siteHeaderRequiredRole = 1;
$siteHeaderMaxWidth = "980px";
$siteHeaderExtraHead = '<style>.section-title{font-weight:800}.upload-box{border:2px dashed #d7dce5;border-radius:14px;padding:16px;background:#fff}.upload-box:hover{border-color:#2563eb}.muted{color:#667085}</style>';
require __DIR__ . "/../header.php";
?>

<div class="card p-4 mb-3">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <div class="text-muted small">Application Form</div>
      <h1 class="h4 fw-bold mb-1">獎助學金申請表</h1>
      <div class="muted small">學生 ID：<?= htmlspecialchars($stId) ?></div>
    </div>
    <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">
      Scholarship
    </span>
  </div>

  <?php if ($recommendLink): ?>
    <div class="alert alert-info mt-3 mb-0">
      <!--<div class="fw-semibold">教授推薦連結（Demo 用）</div>-->
      <div class="fw-semibold">已傳送推薦信連結至教授的信箱</div>
      <!--<div class="small muted">把這個連結貼給教授，他不用註冊也能填推薦信。</div>-->
      <!--<div class="mt-2">
          <a href="<?= htmlspecialchars($recommendLink) ?>" target="_blank" class="text-decoration-none">
            <?= htmlspecialchars($recommendLink) ?>
          </a>
        </div>-->
    </div>
  <?php endif; ?>
</div>

<form class="card p-4" action="/scholarship/student/apply_submit.php" method="post" enctype="multipart/form-data">
  <input type="hidden" name="STID" value="<?= htmlspecialchars($stId) ?>">

  <!-- 1. 選擇獎學金 -->
  <div class="mb-4">
    <div class="section-title mb-2">1. 選擇獎助學金</div>
    <label class="form-label fw-semibold">獎助學金</label>
    <select class="form-select" name="SCID" required>
      <option value="" disabled selected>請選擇</option>
      <?php foreach ($schs as $s): ?>
        <!--<option value="?= htmlspecialchars($s["ID"]."|".$s["NAME"]."|".$s["AMOUNT"]) ?>">
            ?= htmlspecialchars($s["NAME"]) ?>（截止：?= htmlspecialchars($s["DEADLINE"]) ?>，金額：?= htmlspecialchars($s["AMOUNT"]) ?>）
          </option>-->
        <option value="<?= htmlspecialchars($s["id"]) ?>">
          <!--<option value="?= (int)($s["id"]) ?>">-->
          <?= htmlspecialchars($s["NAME"]) ?>（截止：<?= htmlspecialchars($s["DEADLINE"]) ?>，金額：<?= htmlspecialchars($s["AMOUNT"]) ?>）
        </option>
      <?php endforeach; ?>
    </select>
    <!--<div class="form-text">送出後寫入 application.SCID / SCNAME / AMOUNT</div>-->
  </div>

  <!-- 2. 基本資料 -->
  <div class="mb-4">
    <div class="section-title mb-2">2. 基本資料</div>
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
    <div class="section-title mb-2">3. 學業/資格資料</div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label fw-semibold">GPA / 成績（可空）</label>
        <input class="form-control" type="number" name="GRADE" step="0.01" min="0" max="100" placeholder="例如：85.5">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold">班排/系排（可空）</label>
        <input class="form-control" name="RANK" maxlength="50" placeholder="例如：班排 3/45">
      </div>
    </div>
  </div>

  <!-- 4. 推薦信（教授免註冊） -->
  <div class="mb-4">
    <div class="section-title mb-2">4. 推薦信</div>
    <!--<div class="section-title mb-2">4. 推薦信（教授免註冊填寫）</div>-->
    <div class="row g-3">
      <!--<div class="col-md-4">
          <label class="form-label fw-semibold">推薦人姓名（可空）</label>
          <input class="form-control" name="REC_NAME" maxlength="50" placeholder="例如：王小明">
        </div>-->
      <div class="col-md-4">
        <label class="form-label fw-semibold">推薦教授</label>
        <select class="form-select" name="teacher_id">
          <option value="">請選擇教授（可空）</option>
          <?php foreach ($teachers as $t): ?>
            <option value="<?= htmlspecialchars($t['ID']) ?>">
              <?= htmlspecialchars($t['teacher_name']) ?>（<?= htmlspecialchars($t['dept_name']) ?>）
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-semibold">推薦人 Email（可空）</label>
        <input class="form-control" type="email" name="REC_EMAIL" maxlength="100" placeholder="xxx@mail.nuk.edu.tw">
        <!--<div class="form-text">填了才會建立推薦連結</div>-->
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">關係（可空）</label>
        <input class="form-control" name="REC_REL" maxlength="50" placeholder="例如：專題指導教授">
      </div>
    </div>
  </div>

  <!-- 5. 自傳/讀書計畫 -->
  <div class="mb-4">
    <div class="section-title mb-2">5. 自傳（必傳）</div>
    <div class="upload-box">
      <input class="form-control" type="file" name="AUTOBI_FILE" accept=".pdf,.doc,.docx" required>
      <div class="text-muted small mt-2">PDF / DOC / DOCX（建議 10MB 內）</div>
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
    <div class="section-title mb-2">6. 其他有利審查資料（可多檔）</div>
    <div class="upload-box">
      <input class="form-control" type="file" name="OTHER_FILES[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
      <div class="text-muted small mt-2">獎狀、證照、作品集、證明…可多個檔案</div>
    </div>
  </div>

  <div class="form-check mt-4">
    <input class="form-check-input" type="checkbox" value="1" id="agree" required>
    <label class="form-check-label muted" for="agree">
      本人保證以上資料及文件皆屬實。
    </label>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-outline-secondary" href="/scholarship/student/student-dashboard.php">返回</a>
    <button class="btn btn-primary fw-semibold" type="submit">送出申請</button>
  </div>
</form>

</main>
</body>

</html>