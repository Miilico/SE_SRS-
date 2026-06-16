<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

require_role(1);

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
$pageTitle = "學生主頁";
$activeNav = "student-dashboard.php";
$siteHeaderRequiredRole = 1;
require __DIR__ . "/../header.php";
?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-4">
    <h1 class="h3 fw-bold mb-1">歡迎回來，<?= htmlspecialchars($userName) ?></h1>
    <div class="text-secondary">查看近期申請狀態與需要處理的通知。</div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary">審核中案件</div>
        <div class="display-6 fw-bold"><?= $pending ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary">本學期已通過</div>
        <div class="display-6 fw-bold"><?= $approvedThisYear ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="text-secondary">已發放金額</div>
        <div class="display-6 fw-bold">NT$ <?= number_format($paid) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">最新通知</h2>
        <?php if (empty($notis)): ?>
          <div class="text-secondary">目前沒有需要處理的通知。</div>
        <?php else: ?>
          <div class="list-group list-group-flush">
          <?php foreach ($notis as $n): ?>
            <div class="list-group-item px-0">
              <div>
                <span class="badge rounded-pill text-bg-warning"><?= htmlspecialchars($n["RESULT"]) ?></span>
                申請編號 <?= htmlspecialchars($n["APNO"]) ?>
              </div>
              <div class="text-secondary small mt-1"><?= htmlspecialchars($n["APDATE"]) ?></div>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-3">我的申請</h2>
        <?php if (empty($apps)): ?>
          <div class="text-secondary">目前尚無申請紀錄。</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>獎助學金名稱</th>
                <th>申請日期</th>
                <th>申請金額</th>
                <th>狀態</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($apps as $a): ?>
              <tr>
                <td><?= htmlspecialchars(isset($a["SCH_NAME"]) ? $a["SCH_NAME"] : ("APNO ".$a["APNO"])) ?></td>
                <td><?= htmlspecialchars($a["APDATE"]) ?></td>
                <td>NT$ <?= number_format((int)$a["AMOUNT"]) ?></td>
                <td><span class="badge rounded-pill text-bg-light border"><?= htmlspecialchars($a["RESULT"]) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</main>
</body>
</html>
