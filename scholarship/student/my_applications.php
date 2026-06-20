<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../application_helpers.php";

require_role(1);

$stId = isset($_SESSION["user"]["stid"]) ? $_SESSION["user"]["stid"] : "";

if ($stId === "") {
    header("Location: /scholarship/logout.php");
    exit;
}

$sql = "
    SELECT
        a.APNO,
        a.APDATE,
        a.GRADE,
        a.RANK,
        a.AMOUNT,
        a.RESULT,
        a.SCID,
        a.SCNAME,
        s.NAME AS scholarship_name,
        s.DEADLINE
    FROM application a
    LEFT JOIN scholarship s ON a.SCID = s.id
    WHERE a.STID = :stid
    ORDER BY a.APDATE DESC, a.APNO DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(array(":stid" => $stId));
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

function can_edit_application($result) {
    return application_status_can_edit($result);
}

function can_upload_supplement($result) {
    return $result === "需補件";
}

$pageTitle = "我的申請";
$activeNav = "my_applications.php";
$siteHeaderRequiredRole = 1;

require __DIR__ . "/../header.php";
?>

<div class="card border-0 shadow-sm">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1 class="h3 fw-bold mb-1">我的申請</h1>
        <div class="text-secondary">查看申請狀態、審核結果與後續操作</div>
      </div>

      <a class="btn btn-primary" href="/scholarship/student/browse_scholarships.php">
        申請獎學金
      </a>
    </div>

    <?php if (empty($applications)): ?>
      <div class="alert alert-info mb-0">
        目前沒有任何申請紀錄。
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>申請編號</th>
              <th>獎學金名稱</th>
              <th>申請日期</th>
              <th>金額</th>
              <th>狀態/結果</th>
              <th class="text-end">操作</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($applications as $app): ?>
              <?php
                $result = isset($app["RESULT"]) ? $app["RESULT"] : "";
                $name = !empty($app["scholarship_name"])
                    ? $app["scholarship_name"]
                    : $app["SCNAME"];
              ?>

              <tr>
                <td><?php echo htmlspecialchars($app["APNO"]); ?></td>
                <td><?php echo htmlspecialchars($name); ?></td>
                <td><?php echo htmlspecialchars($app["APDATE"]); ?></td>
                <td>NT$ <?php echo number_format((int)$app["AMOUNT"]); ?></td>
                <td>
                  <span class="badge rounded-pill text-bg-light border">
                    <?php echo htmlspecialchars($result); ?>
                  </span>
                </td>

                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary"
                     href="/scholarship/student/application_detail.php?apno=<?php echo urlencode($app["APNO"]); ?>">
                    查看
                  </a>

                  <?php if (can_edit_application($result)): ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="/scholarship/student/edit_application.php?apno=<?php echo urlencode($app["APNO"]); ?>">
                      修改
                    </a>
                  <?php endif; ?>

                  <?php if (can_upload_supplement($result)): ?>
                    <a class="btn btn-sm btn-warning"
                       href="/scholarship/student/upload_supplement.php?apno=<?php echo urlencode($app["APNO"]); ?>">
                      補件
                    </a>
                  <?php endif; ?>
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
