<?php
$pageTitle = "管理員總覽";
$activeNav = "admin_dashboard.php";
?>
<?php require __DIR__ . "/../header.php"; ?>
<?php
// 取得待審核帳號數量
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending'");
$stmt->execute();
$pendingCount = (int)$stmt->fetchColumn();
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-4">
        <h1 class="h3 fw-bold mb-1">管理員<?= htmlspecialchars($userName) ?>，您好</h1>
        <div class="text-secondary">
            您可以在此管理帳號、公告、檔案、獎助學金與工單
        </div>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold d-flex align-items-center gap-2">
                    帳號審核
                    <?php if ($pendingCount > 0): ?>
                        <span class="badge bg-danger rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.75rem; padding: 0;">
              <?= $pendingCount ?>
            </span>
                    <?php endif; ?>
                </h2>
                <p class="text-secondary">審核獎助單位註冊申請</p>
                <a class="btn btn-primary" href="/scholarship/admin/admin_users_pending.php">前往</a>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold">帳號管理</h2>
                <p class="text-secondary">新增、編輯學生、推薦人與獎助單位帳號</p>
                <a class="btn btn-primary" href="/scholarship/admin/account_management.php">前往</a>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold">公告管理</h2>
                <p class="text-secondary">發布與管理系統公告</p>
                <a class="btn btn-primary" href="/scholarship/admin/post_management.php">前往</a>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold">檔案管理</h2>
                <p class="text-secondary">篩選、預覽與批量管理系統檔案</p>
                <a class="btn btn-primary" href="/scholarship/admin/document_management.php">前往</a>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold">獎助學金申請管理</h2>
                <p class="text-secondary">查看獎助學金申請情形、發布與管理獎助學金申請結果公告</p>
                <a class="btn btn-primary" href="/scholarship/admin/app_management.php">前往</a>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold">獎助學金表單管理</h2>
                <p class="text-secondary">查看、編輯所有獎助單位發布的獎學金收集表單</p>
                <a class="btn btn-primary" href="/scholarship/organization/my_scholarships.php">前往</a>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold">工單管理</h2>
                <p class="text-secondary">查看與處理使用者回報的問題工單</p>
                <a class="btn btn-primary" href="/scholarship/ticket_list.php">前往</a>
            </div>
        </div>
    </div>
</div>

</main>
</body>
</html>
