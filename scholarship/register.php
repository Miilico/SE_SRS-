<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/department_options.php";
require_once __DIR__ . "/file_helpers.php";

ensure_teachers_table($pdo);

$old = isset($_SESSION["register_old"]) && is_array($_SESSION["register_old"]) ? $_SESSION["register_old"] : array();
$errorField = isset($_SESSION["register_error_field"]) ? $_SESSION["register_error_field"] : "";
$errorMessage = isset($_SESSION["register_error_message"]) ? $_SESSION["register_error_message"] : "請確認密碼。";
unset($_SESSION["register_old"], $_SESSION["register_error_field"], $_SESSION["register_error_message"]);

function h($value)
{
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function old_value($old, $key, $default = "")
{
  return isset($old[$key]) ? $old[$key] : $default;
}

function field_invalid_class($field, $errorField)
{
  return $field === $errorField ? " is-invalid" : "";
}

function selected_attr($old, $key, $value, $default = "")
{
  $current = old_value($old, $key, $default);
  return (string)$current === (string)$value ? " selected" : "";
}

$departments = scholarship_departments_by_college();

$pageTitle = "註冊";
$activeNav = "register.php";
$siteHeaderMainClass = "site-shell py-4";
require __DIR__ . "/header.php";
?>
<div class="row justify-content-center">
<div class="col-12 col-lg-7">
<div class="card border-0 shadow-sm overflow-hidden">
  <div class="card-header bg-white p-4">
    <div class="text-muted small">Create account</div>
    <h1 class="h4 mb-0 fw-bold">註冊</h1>
  </div>

  <div class="p-4">
    <form method="post" action="register_submit.php" class="vstack gap-3" novalidate>
      <div>
        <label class="form-label fw-semibold" for="role">身分</label>
        <select class="form-select<?= field_invalid_class("role", $errorField) ?>" name="role" id="role" required>
          <option value="1" <?= selected_attr($old, "role", "1", "1") ?>>學生</option>
          <option value="2" <?= selected_attr($old, "role", "2", "1") ?>>推薦人</option>
          <option value="4" <?= selected_attr($old, "role", "4", "1") ?>>獎助單位</option>
        </select>
        <div class="form-text" id="roleHelp">學生與推薦人註冊後可直接登入；獎助單位需管理員審核通過。</div>
      </div>

      <div>
        <label class="form-label fw-semibold" id="idLabel" for="id">使用者 ID（學號/教職員編號）</label>
        <input class="form-control<?= field_invalid_class("id", $errorField) ?>" id="id" name="id" maxlength="10" value="<?= h(old_value($old, "id")) ?>" required>
      </div>

      <div>
        <label class="form-label fw-semibold" id="nameLabel" for="name">姓名</label>
        <input class="form-control<?= field_invalid_class("name", $errorField) ?>" id="name" name="name" maxlength="50" value="<?= h(old_value($old, "name")) ?>" required>
      </div>

      <div data-role-section="student">
        <label class="form-label fw-semibold" for="dept">科系</label>
        <select class="form-select<?= field_invalid_class("dept", $errorField) ?>" name="dept" id="dept">
          <option value="">請選擇科系</option>
          <?php foreach ($departments as $college => $deptOptions): ?>
            <optgroup label="<?= h($college) ?>">
              <?php foreach ($deptOptions as $department): ?>
                <option value="<?= h($department) ?>" <?= selected_attr($old, "dept", $department) ?>><?= h($department) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <div data-role-section="recommender" class="d-none">
        <label class="form-label fw-semibold" for="teacher_unit">單位名稱</label>
        <input class="form-control<?= field_invalid_class("teacher_unit", $errorField) ?>" id="teacher_unit" name="teacher_unit" maxlength="100" placeholder="例如：國立成功大學、XX科技股份有限公司" value="<?= h(old_value($old, "teacher_unit")) ?>">
      </div>

      <div data-role-section="recommender" class="d-none">
        <label class="form-label fw-semibold" for="teacher_title">職稱</label>
        <input class="form-control<?= field_invalid_class("teacher_title", $errorField) ?>" id="teacher_title" name="teacher_title" maxlength="100" placeholder="例如：副教授、講師、高級工程師" value="<?= h(old_value($old, "teacher_title")) ?>">
      </div>

      <div data-role-section="recommender" class="d-none">
        <label class="form-label fw-semibold" for="teacher_dept">系所 / 部門（選填）</label>
        <input class="form-control<?= field_invalid_class("dept", $errorField) ?>" id="teacher_dept" name="teacher_dept" maxlength="50" placeholder="例如：資訊工程學系、研發部" value="<?= h(old_value($old, "teacher_dept", old_value($old, "dept"))) ?>">
      </div>

      <div data-role-section="organization" class="d-none">
        <label class="form-label fw-semibold" for="contact_person">單位聯絡人姓名</label>
        <input class="form-control<?= field_invalid_class("contact_person", $errorField) ?>" id="contact_person" name="contact_person" maxlength="10" value="<?= h(old_value($old, "contact_person")) ?>">
      </div>

      <div data-role-section="organization" class="d-none">
        <label class="form-label fw-semibold" for="org_phones">其他單位電話（可多筆，請用逗號隔開）</label>
        <input class="form-control<?= field_invalid_class("org_phones", $errorField) ?>" id="org_phones" name="org_phones" maxlength="100" placeholder="例如：02-1234567, 0912345678" value="<?= h(old_value($old, "org_phones")) ?>">
      </div>

      <div>
        <label class="form-label fw-semibold" for="email">Email</label>
        <input class="form-control<?= field_invalid_class("email", $errorField) ?>" id="email" type="email" name="email" maxlength="100" placeholder="example@mail.nuk.edu.tw" value="<?= h(old_value($old, "email")) ?>" required>
        <div class="form-text" id="emailHelp">學生請使用學校信箱，格式為 @mail.nuk.edu.tw。</div>
      </div>

      <div>
        <label class="form-label fw-semibold" for="tel">電話</label>
        <input class="form-control<?= field_invalid_class("tel", $errorField) ?>" id="tel" name="tel" maxlength="10" placeholder="例如 0912345678" value="<?= h(old_value($old, "tel")) ?>" required>
        <div class="form-text">電話需為 6 到 10 碼，可使用數字、空白、+、-、括號。</div>
      </div>

      <div>
        <label class="form-label fw-semibold" for="pwd">密碼</label>
        <input class="form-control<?= field_invalid_class("pwd", $errorField) ?>" id="pwd" type="password" name="pwd" maxlength="64" required>
        <div class="form-text">密碼至少 6 碼。</div>
        <?php if ($errorField === "pwd"): ?>
          <div class="invalid-feedback d-block"><?= h($errorMessage) ?></div>
        <?php endif; ?>
      </div>

      <div>
        <label class="form-label fw-semibold" for="pwd2">確認密碼</label>
        <input class="form-control<?= field_invalid_class("pwd2", $errorField) ?>" id="pwd2" type="password" name="pwd2" maxlength="64" required>
        <?php if ($errorField === "pwd2"): ?>
          <div class="invalid-feedback d-block"><?= h($errorMessage) ?></div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-semibold">送出註冊</button>

      <div class="text-center text-muted small">
        已經有帳號？
        <a href="login.php" class="text-decoration-none">前往登入</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<script>
  (function() {
    var role = document.getElementById("role");
    var idLabel = document.getElementById("idLabel");
    var nameLabel = document.getElementById("nameLabel");
    var deptInput = document.querySelector('select[name="dept"]');
    var teacherUnitInput = document.querySelector('input[name="teacher_unit"]');
    var teacherTitleInput = document.querySelector('input[name="teacher_title"]');
    var contactInput = document.querySelector('input[name="contact_person"]');
    var studentSections = document.querySelectorAll('[data-role-section="student"]');
    var recommenderSections = document.querySelectorAll('[data-role-section="recommender"]');
    var orgSections = document.querySelectorAll('[data-role-section="organization"]');
    var emailHelp = document.getElementById("emailHelp");
    var errorField = <?= json_encode($errorField, JSON_UNESCAPED_UNICODE) ?>;

    function setSectionVisible(sections, visible) {
      sections.forEach(function(section) {
        section.classList.toggle("d-none", !visible);
      });
    }

    function syncRoleFields() {
      var isOrg = role.value === "4";
      var isStudent = role.value === "1";
      var isRecommender = role.value === "2";
      idLabel.textContent = isOrg ? "使用者 ID（單位帳號）" : "使用者 ID（學號/教職員編號）";
      nameLabel.textContent = isOrg ? "單位名稱" : "姓名";
      setSectionVisible(studentSections, isStudent);
      setSectionVisible(recommenderSections, isRecommender);
      setSectionVisible(orgSections, isOrg);
      deptInput.required = isStudent;
      teacherUnitInput.required = isRecommender;
      teacherTitleInput.required = isRecommender;
      contactInput.required = isOrg;
      emailHelp.textContent = isStudent ? "學生請使用學校信箱，格式為 @mail.nuk.edu.tw。" : "請填寫可收信的 Email。";
    }

    function focusErrorField() {
      if (!errorField) return;
      var target = document.querySelector('[name="' + errorField.replace(/"/g, '\\"') + '"]');
      if (!target) return;
      target.scrollIntoView({
        behavior: "smooth",
        block: "center"
      });
      target.focus({
        preventScroll: true
      });
    }

    role.addEventListener("change", syncRoleFields);
    syncRoleFields();
    focusErrorField();
  })();
</script>
</main>
</body>

</html>
