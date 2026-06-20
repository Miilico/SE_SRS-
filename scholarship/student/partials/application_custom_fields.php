<?php
$customFields = isset($customFields) && is_array($customFields) ? $customFields : array();
$customValues = isset($customValues) && is_array($customValues) ? $customValues : array();
$customFieldsSectionTitle = isset($customFieldsSectionTitle)
    ? (string)$customFieldsSectionTitle
    : "獎助單位自訂申請資料";
?>

<?php if (!empty($customFields)): ?>
  <section class="border-top pt-4 mt-4" id="custom-application-fields">
    <h2 class="h5 fw-bold mb-3"><?= htmlspecialchars($customFieldsSectionTitle, ENT_QUOTES, "UTF-8") ?></h2>

    <div class="vstack gap-3">
      <?php foreach ($customFields as $field): ?>
        <?php
          $fieldId = (int)$field["id"];
          $fieldType = $field["field_type"];
          $required = !empty($field["is_required"]);
          $value = isset($customValues[$fieldId]) ? $customValues[$fieldId] : "";
        ?>
        <div>
          <label class="form-label fw-semibold" for="custom-field-<?= $fieldId ?>">
            <?= htmlspecialchars($field["field_label"], ENT_QUOTES, "UTF-8") ?>
            <?php if ($required): ?><span class="text-danger" aria-label="必填">*</span><?php endif; ?>
          </label>

          <?php if ($fieldType === "textarea"): ?>
            <textarea class="form-control" id="custom-field-<?= $fieldId ?>"
                      name="CUSTOM_FIELDS[<?= $fieldId ?>]" rows="4"
                      <?= $required ? "required" : "" ?>><?= htmlspecialchars($value, ENT_QUOTES, "UTF-8") ?></textarea>
          <?php elseif ($fieldType === "file"): ?>
            <input class="form-control" id="custom-field-<?= $fieldId ?>" type="file"
                   name="CUSTOM_FILES[<?= $fieldId ?>]" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                   <?= $required && $value === "" ? "required" : "" ?>>
            <div class="form-text">允許 PDF、Word、JPG、PNG，單檔上限 10MB。</div>
          <?php else: ?>
            <input class="form-control" id="custom-field-<?= $fieldId ?>"
                   type="<?= $fieldType === "number" ? "number" : "text" ?>"
                   name="CUSTOM_FIELDS[<?= $fieldId ?>]"
                   value="<?= htmlspecialchars($value, ENT_QUOTES, "UTF-8") ?>"
                   <?= $required ? "required" : "" ?>>
          <?php endif; ?>
          <?php if (!empty($field["field_note"])): ?>
            <div class="form-text"><?= htmlspecialchars($field["field_note"], ENT_QUOTES, "UTF-8") ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
