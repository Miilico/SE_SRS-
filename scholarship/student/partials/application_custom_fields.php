<?php
$customFields = isset($customFields) && is_array($customFields) ? $customFields : array();
$customValues = isset($customValues) && is_array($customValues) ? $customValues : array();
$allowCustomFileDeletion = !empty($allowCustomFileDeletion);
$customFileDetails = isset($customFileDetails) && is_array($customFileDetails) ? $customFileDetails : array();
$customFieldsSectionTitle = isset($customFieldsSectionTitle)
    ? (string)$customFieldsSectionTitle
    : "獎助單位自訂申請資料";
?>

<?php if (!empty($customFields)): ?>
  <section class="border-top pt-4 mt-4" id="custom-application-fields">
    <div class="fw-bold mb-3"><?= htmlspecialchars($customFieldsSectionTitle, ENT_QUOTES, "UTF-8") ?></div>

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
            <?php if ($value !== ""): ?>
              <?php $fileDetail = isset($customFileDetails[$fieldId]) ? $customFileDetails[$fieldId] : array(); ?>
              <div class="list-group mb-2">
                <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                  <div class="text-truncate">
                    <div class="fw-semibold text-truncate"><?= htmlspecialchars(!empty($fileDetail["original_name"]) ? $fileDetail["original_name"] : "已上傳檔案", ENT_QUOTES, "UTF-8") ?></div>
                    <?php if (!empty($fileDetail)): ?>
                      <div class="small text-secondary">
                        <?= !empty($fileDetail["file_size"]) ? number_format(((int)$fileDetail["file_size"]) / 1024, 1) . " KB" : "" ?>
                        <?= !empty($fileDetail["created_at"]) ? " · " . htmlspecialchars($fileDetail["created_at"], ENT_QUOTES, "UTF-8") : "" ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($value, ENT_QUOTES, "UTF-8") ?>" target="_blank" rel="noopener">查看</a>
                    <?php if ($allowCustomFileDeletion): ?>
                      <label class="btn btn-sm btn-outline-danger mb-0">
                        <input class="form-check-input me-1" type="checkbox" name="DELETE_CUSTOM_FILES[]" value="<?= $fieldId ?>">
                        刪除
                      </label>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>
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
