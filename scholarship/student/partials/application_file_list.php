<?php
$applicationFiles = isset($applicationFiles) && is_array($applicationFiles)
    ? $applicationFiles
    : array();
$emptyFilesMessage = isset($emptyFilesMessage)
    ? (string)$emptyFilesMessage
    : "目前沒有檔案。";
$allowFileDeletion = !empty($allowFileDeletion);

if (!function_exists("application_file_size_label")) {
    function application_file_size_label($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes <= 0) {
            return "大小未記錄";
        }
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2) . " MB";
        }
        return number_format($bytes / 1024, 1) . " KB";
    }
}
?>

<?php if (empty($applicationFiles)): ?>
  <div class="border rounded p-3 text-secondary"><?= h($emptyFilesMessage) ?></div>
<?php else: ?>
  <div class="list-group">
    <?php foreach ($applicationFiles as $file): ?>
      <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
        <div class="text-truncate">
          <div class="fw-semibold text-truncate"><?= h($file["original_name"]) ?></div>
          <div class="small text-secondary">
            <?= h(application_file_size_label(isset($file["file_size"]) ? $file["file_size"] : 0)) ?>
            <?php if (!empty($file["created_at"])): ?>
              · <?= h($file["created_at"]) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
          <a class="btn btn-sm btn-outline-primary"
             href="<?= h($file["path"]) ?>" target="_blank" rel="noopener">查看</a>

          <?php if ($allowFileDeletion && !empty($file["id"])): ?>
            <label class="btn btn-sm btn-outline-danger mb-0">
              <input class="form-check-input me-1"
                     type="checkbox"
                     name="delete_file_ids[]"
                     value="<?= h($file["id"]) ?>">
              刪除
            </label>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
