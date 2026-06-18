<?php
$adminHeaderBootstrapOnly = true;
require __DIR__ . "/../header.php";
unset($adminHeaderBootstrapOnly);

// 核准帳號
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["approve_id"])) {
  $id = trim($_POST["approve_id"]);
  $stmt = $pdo->prepare("
    UPDATE users
    SET status = 'active'
    WHERE id = ?  AND status = 'pending'
  ");
  $stmt->execute([$id]);
  site_flash_redirect("admin_users_pending.php", "帳號審核已通過", "success");
}

// 取得待審核帳號
$stmt = $pdo->prepare("
  SELECT id, role, name, email, tel, created_at
  FROM users
  WHERE status = 'pending' 
  ORDER BY created_at ASC
");
$stmt->execute();
$rows = $stmt->fetchAll();

// role 對照表（顯示用）
$role_map = [
  1 => "學生",
  2 => "教授",
  3 => "管理員",
  4 => "獎助學金單位"
];
$pageTitle = "帳號審核";
$activeNav = "admin_users_pending.php";
?>
<?php require __DIR__ . "/../header.php"; ?>

<div class="admin-page-head">
  <div>
    <h1 class="admin-page-title">待審核帳號</h1>
    <div class="admin-page-subtitle">審核獎助學金單位註冊申請；學生與教師註冊後可直接登入。</div>
  </div>
</div>

<div class="card">
  <div class="card-body">

    <?php if (!$rows): ?>
      <div class="text-muted">目前沒有待審核的獎助學金單位帳號。</div>
    <?php else: ?>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>使用者 ID</th>
              <th>角色</th>
              <th>姓名</th>
              <th>Email</th>
              <th>電話</th>
              <th>申請時間</th>
              <th class="text-end">操作</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r["id"]) ?></td>
                <!--<td> $role_map[$r["role"]] ?? "未知" ?></td>-->
                <td><?= isset($role_map[$r["role"]]) ? $role_map[$r["role"]] : "未知" ?></td>
                <td><?= htmlspecialchars($r["name"]) ?></td>
                <td><?= htmlspecialchars($r["email"]) ?></td>
                <td><?= htmlspecialchars($r["tel"]) ?></td>
                <td><?= htmlspecialchars($r["created_at"]) ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline" data-confirm="確定要通過這個帳號審核嗎？">
                    <input type="hidden" name="approve_id" value="<?= htmlspecialchars($r["id"]) ?>">
                    <button class="btn btn-success btn-sm">核准</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>

  </div>
</div>
</main>
</body>

</html>
