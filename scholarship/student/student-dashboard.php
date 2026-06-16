<?php
require_once __DIR__ . "/../config.php";

// ====== 基本保護：必須登入 ======
if (empty($_SESSION["user"]) || empty($_SESSION["user"]["id"])) {
  header("Location: /scholarship/login.php");
  exit;
}

// ====== 從 session 取值（✅ 全部用小寫） ======
$stId     = $_SESSION["user"]["id"];
$userName = $_SESSION["user"]["name"];

// ====== 設定區 ======
$TABLE_APP = "application";
$COL_STID  = "STID";
$COL_APNO  = "APNO";
$COL_DATE  = "APDATE";
$COL_AMT   = "AMOUNT";
$COL_RES   = "RESULT";

$STATUS_PENDING  = "審查中";
$STATUS_APPROVED = "通過";
$STATUS_FIX      = "需補件";
$STATUS_REJECTED = "不通過";

// ====== KPI: 四張卡 ======
/*function count_by_status(
  PDO $pdo,
  string $table,
  string $col_stid,
  string $col_res,
  string $stId,
  string $status
): int {
  $sql = "SELECT COUNT(*) c FROM {$table}
          WHERE {$col_stid} = :st AND {$col_res} = :res";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ":st"  => $stId,
    ":res" => $status
  ]);
  return (int)$stmt->fetchColumn();
}*/
function count_by_status($pdo, $table, $col_stid, $col_res, $stId, $status) {
  $sql = "SELECT COUNT(*) c FROM {$table}
          WHERE {$col_stid} = :st AND {$col_res} = :res";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ":st"  => $stId,
    ":res" => $status
  ]);
  return (int)$stmt->fetchColumn();
}

$pending = count_by_status($pdo, $TABLE_APP, $COL_STID, $COL_RES, $stId, $STATUS_PENDING);
$needFix = count_by_status($pdo, $TABLE_APP, $COL_STID, $COL_RES, $stId, $STATUS_FIX);

// ====== 本年度已通過 ======
$sqlApproved = "SELECT COUNT(*) c FROM {$TABLE_APP}
                WHERE {$COL_STID} = :st
                  AND {$COL_RES}  = :res
                  AND YEAR({$COL_DATE}) = YEAR(CURDATE())";
$stmt = $pdo->prepare($sqlApproved);
$stmt->execute([
  ":st"  => $stId,
  ":res" => $STATUS_APPROVED
]);
$approvedThisYear = (int)$stmt->fetchColumn();

// ====== 已發放金額 ======
$sqlPaid = "SELECT IFNULL(SUM({$COL_AMT}),0) s FROM {$TABLE_APP}
            WHERE {$COL_STID} = :st AND {$COL_RES} = :res";
$stmt = $pdo->prepare($sqlPaid);
$stmt->execute([
  ":st"  => $stId,
  ":res" => $STATUS_APPROVED
]);
$paid = (int)$stmt->fetchColumn();

// ====== 我的申請列表（最新 5 筆） ======
/*$sqlList = "SELECT {$COL_APNO} AS APNO,
                   {$COL_DATE} AS APDATE,
                   {$COL_AMT}  AS AMOUNT,
                   {$COL_RES}  AS RESULT
            FROM {$TABLE_APP}
            WHERE {$COL_STID} = :st
            ORDER BY {$COL_DATE} DESC
            LIMIT 5";
$stmt = $pdo->prepare($sqlList);
$stmt->execute([":st" => $stId]);
$apps = $stmt->fetchAll();*/

// ====== 我的申請列表（最新 5 筆） ====== 
$sqlList = "SELECT a.{$COL_APNO} AS APNO, 
                   a.{$COL_DATE} AS APDATE, 
                   a.{$COL_AMT} AS AMOUNT, 
                   a.{$COL_RES} AS RESULT, 
                   s.NAME AS SCH_NAME 
            FROM {$TABLE_APP} a 
            JOIN scholarship s 
            ON a.SCID = s.id 
            WHERE a.{$COL_STID} = :st 
            ORDER BY a.{$COL_DATE} DESC 
            LIMIT 5"; 
$stmt = $pdo->prepare($sqlList); 
$stmt->execute([":st" => $stId]); 
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====== 最新通知（需補件 / 退件） ======
$sqlNoti = "SELECT {$COL_APNO} AS APNO,
                   {$COL_DATE} AS APDATE,
                   {$COL_RES}  AS RESULT
            FROM {$TABLE_APP}
            WHERE {$COL_STID} = :st
              AND {$COL_RES} IN (:fix, :rej)
            ORDER BY {$COL_DATE} DESC
            LIMIT 3";
$stmt = $pdo->prepare($sqlNoti);
$stmt->execute([
  ":st"  => $stId,
  ":fix" => $STATUS_FIX,
  ":rej" => $STATUS_REJECTED
]);
$notis = $stmt->fetchAll();
?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <title>學生主頁</title>
  <style>
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans TC",sans-serif;margin:24px;background:#f6f7fb}
    .card{background:#fff;border-radius:14px;padding:16px;box-shadow:0 1px 8px rgba(0,0,0,.06)}
    .grid{display:grid;gap:14px}
    .kpi{grid-template-columns:repeat(3,minmax(0,1fr))}
    .row{display:grid;grid-template-columns:1fr 1.4fr;gap:14px;margin-top:14px}
    .muted{color:#667085}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;background:#f1f5f9}
    .btn{display:inline-block;padding:6px 12px;border-radius:999px;background:#ff7a00;color:#fff;text-decoration:none;font-size:12px}
    .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
    .tabs a{margin-right:14px;text-decoration:none;color:#111}
    .tabs a.active{font-weight:700;color:#2563eb}
  </style>
</head>
<body>

<div class="topbar">
  <div><strong>獎助學金系統</strong></div>
  <div class="tabs">
    <a class="active" href="/scholarship/student/student-dashboard.php">總覽</a>
    <a href="/scholarship/student/browse_scholarships.php">瀏覽獎助學金</a>
    <a href="/scholarship/student/apply.php">申請獎助學金</a>
    <!--<a href="/scholarship/student/apply.php">我的申請</a>-->
    <a href="/scholarship/profile.php">個人檔案</a>
    <a href="/scholarship/announcement_board.php">查看公告</a>
  </div>
  <div>
    <?= htmlspecialchars($userName) ?>｜
    <a href="/scholarship/logout.php">登出</a>
  </div>
</div>

<div class="card">
  <h2 style="margin:0;">歡迎回來，<?= htmlspecialchars($userName) ?> 👋</h2>
  <!--<div class="muted" style="margin-top:6px;">（學期/系級資訊之後可接 students 表）</div>-->
</div>

<div class="grid kpi" style="margin-top:14px;">
  <div class="card"><div class="muted">審核中案件</div><h2><?= $pending ?></h2></div>
  <div class="card"><div class="muted">本學期已通過</div><h2><?= $approvedThisYear ?></h2></div>
  <div class="card"><div class="muted">已發放金額</div><h2>NT$ <?= number_format($paid) ?></h2></div>
</div>

<div class="row">
  <!-- 最新通知 -->
  <div class="card">
    <h3 style="margin-top:0;">最新通知</h3>
    <?php if (empty($notis)): ?>
      <div class="muted">目前沒有需要處理的通知。</div>
    <?php else: ?>
      <?php foreach ($notis as $n): ?>
        <div style="padding:10px 0;border-bottom:1px solid #eee;">
          <div>
            <span class="badge"><?= htmlspecialchars($n["RESULT"]) ?></span>
            申請編號 <?= htmlspecialchars($n["APNO"]) ?>
          </div>
          <div class="muted" style="margin-top:4px;">
            <?= htmlspecialchars($n["APDATE"]) ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- 我的申請 -->
  <div class="card">
    <h3 style="margin-top:0;">我的申請</h3>
    <table>
      <thead>
        <tr>
          <th>獎助學金名稱</th>
          <th>申請日期</th>
          <th>申請金額</th>
          <th>狀態</th>
          <!--<th>操作</th>-->
        </tr>
      </thead>
      <tbody>
        <?php foreach ($apps as $a): ?>
        <tr>
          <!--<td>?= htmlspecialchars($a["SCH_NAME"] ?? ("APNO ".$a["APNO"])) ?></td>
          <td>?= htmlspecialchars($a["APDATE"]) ?></td>
          <td>NT$ ?= number_format((int)$a["AMOUNT"]) ?></td>
          <td><span class="badge"><?= htmlspecialchars($a["RESULT"]) ?></span></td>-->
          <td><?= htmlspecialchars(isset($a["SCH_NAME"]) ? $a["SCH_NAME"] : ("APNO ".$a["APNO"])) ?></td>
          <td><?= htmlspecialchars($a["APDATE"]) ?></td>
          <td>NT$ <?= number_format((int)$a["AMOUNT"]) ?></td>
          <td><span class="badge"><?= htmlspecialchars($a["RESULT"]) ?></span></td>

          <!--<td>
            ?php if (($a["RESULT"] ?? "") === $STATUS_FIX): ?>
            ?php if ((isset($a["RESULT"]) ? $a["RESULT"] : "") === $STATUS_FIX): ?>  
              <a class="btn" href="/scholarship/student/fix.php?apno=<?= urlencode($a["APNO"]) ?>">前往補件</a>
            ?php else: ?>
              <a class="btn" href="/scholarship/student/application_detail.php?apno=<?= urlencode($a["APNO"]) ?>">詳細狀態</a>
            ?php endif; ?>
          </td>-->
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
