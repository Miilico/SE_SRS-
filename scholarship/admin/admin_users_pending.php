<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(3);


// 核准帳號
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["approve_id"])) {
  $id = trim($_POST["approve_id"]);
  $stmt = $pdo->prepare("
    UPDATE users
    SET status = 'active'
    WHERE id = ? AND status = 'pending'
  ");
  $stmt->execute([$id]);
  header("Location: admin_users_pending.php");
  exit;
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
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>帳號審核｜管理員</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container py-4">

  <h1 class="h4 fw-bold mb-3">待審核帳號</h1>

  <div class="card">
    <div class="card-body">

      <?php if (!$rows): ?>
        <div class="text-muted">目前沒有待審核帳號。</div>
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
                  <form method="post" class="d-inline">
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
</div>
<div style="text-align:center;">
		<a href="admin_dashboard.php" style="background:#6c757d; 
		color:white; padding:10px; text-decoration:none; border-radius:4px;">← 回到管理主頁</a>
</div>
</body>
</html>
