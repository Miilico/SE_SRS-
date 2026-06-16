<?php
session_start();

// 檢查是否有成功或錯誤訊息
$success = isset($_GET['success']);
$error   = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<title>新增獎助學金</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Noto Sans TC', sans-serif;
    background: #f5f6f8;
    margin: 0;
    padding: 40px 0;
}
.page-title {
    text-align: center;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 20px;
}
.card {
    width: 420px;
    margin: auto;
    background: #fff;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}
.form-group {
    margin-bottom: 20px;
}
.label {
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
}
.label::before {
    content: "";
    width: 16px;
    height: 3px;
    background: #0000CD;
    margin-right: 8px;
    border-radius: 2px;
}
input, textarea {
    width: 90%;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid #e0e0e0;
    font-size: 15px;
    outline: none;
}
input:focus, textarea:focus {
    border-color: #0000CD;
}
textarea {
    resize: none;
    height: 90px;
}
.btn-group {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}
.btn-submit, .btn-back {
    flex: 1;
    margin: 0 5px;
    text-align: center;
    padding: 14px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}
.btn-submit {
    background-color: #0000CD;
    color: white;
    border: none;
}
.btn-submit:hover {
    background-color: #00008B;
}
.btn-back {
    background-color: #6c757d;
    color: white;
    border: none;
}
.btn-back:hover {
    background-color: #5a6268;
}
</style>
</head>

<body>

<div class="page-title">新增獎助學金</div>

<div class="card">
    <form action="insert_scholarship.php" method="post">
        <div class="form-group">
            <div class="label">獎助學金名稱</div>
            <input type="text" name="scholarship_name" placeholder="請輸入獎助學金名稱" required>
        </div>

        <div class="form-group">
            <div class="label">獎助金額</div>
            <input type="number" name="amount" placeholder="請輸入獎助金額" required>
        </div>

        <div class="form-group">
            <div class="label">申請條件</div>
            <textarea name="conditions" placeholder="請輸入申請限制條件"></textarea>
        </div>

        <div class="form-group">
            <div class="label">申請開始日期</div>
            <input type="date" name="start_date" required>
        </div>

        <div class="form-group">
            <div class="label">申請截止日期</div>
            <input type="date" name="deadline" required>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn-submit">新增獎助學金</button>
            <a href="org-dashboard.php" class="btn-back">返回主頁</a>
        </div>
    </form>
</div>

<!-- Bootstrap Modal -->
<div class="modal fade" id="msgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">系統提示</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if ($error): ?>
            <p class="text-danger">錯誤：<?php echo $error; ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="text-success">新增成功！</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- 前端檢查 -->
<script>
document.querySelector("form").addEventListener("submit", function(e) {
    const start = new Date(document.querySelector("[name=start_date]").value);
    const end   = new Date(document.querySelector("[name=deadline]").value);
    const amount = document.querySelector("[name=amount]").value;

    if (start > end) {
        e.preventDefault();
        alert("開始日期不能晚於截止日期！");
        return;
    }
    if (!amount || isNaN(amount) || Number(amount) <= 0) {
        e.preventDefault();
        alert("金額必須為正數！");
    }
});

// 顯示 Modal 提示
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($error || $success): ?>
        var myModal = new bootstrap.Modal(document.getElementById('msgModal'));
        myModal.show();
    <?php endif; ?>
});
</script>

</body>
</html>
