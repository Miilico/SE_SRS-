<?php
require_once __DIR__ . "/scholarship/config.php";
require_once __DIR__ . "/scholarship/file_helpers.php";

function public_detail_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function public_detail_category_label($category)
{
    if ((int)$category === 1) {
        return "獎學金審查結果";
    } else if ((int)$category === 2) {
        return "獎助單位訊息";
    } else {
        return "獎學金公告";
    }    
}

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit("無效的公告編號。");
}

try {
    $stmt = $pdo->prepare("
        SELECT id, title, ADATE, ATIME, CONTENT, AID, CATEGORY, scholarship_id
        FROM announcement
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute(array($id));
    $post = $stmt->fetch();

    if (!$post) {
        http_response_code(404);
        exit("找不到該公告。");
    }

    $files = fetch_uploaded_files($pdo, 1, "announcement_id", $post["id"]);
} catch (PDOException $e) {
    http_response_code(500);
    exit("公告載入失敗：" . public_detail_h($e->getMessage()));
}

$pageTitle = $post["title"];
$activeNav = "/index.php";
$isLoggedIn = !empty($_SESSION["user"]);
if ($isLoggedIn) {
    $siteHeaderBootstrapOnly = true;
    require __DIR__ . "/scholarship/header.php";
    unset($siteHeaderBootstrapOnly);

    $role = isset($_SESSION["user"]["role"]) ? (int)$_SESSION["user"]["role"] : 0;
    $breadcrumbs = array(
        array(site_header_dashboard_url($role), "總覽"),
        array("/index.php", "獎學金公告"),
        array("", $post["title"]),
    );
} else {
    $breadcrumbs = array(
        array("/index.php", "首頁"),
        array("", $post["title"]),
    );
}
$siteHeaderMainClass = "site-shell pt-4";
if (!$isLoggedIn) {
    $siteHeaderBrandHref = "/index.php";
}
require __DIR__ . "/scholarship/header.php";
?>

<article class="card border-0 shadow-sm">
    <header class="card-header bg-white p-4 p-md-5">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 text-secondary fw-semibold">
            <span class="badge rounded-pill <?php echo (int)$post["CATEGORY"] === 1 ? "text-bg-success" : "text-bg-primary"; ?>">
                <?php echo public_detail_h(public_detail_category_label($post["CATEGORY"])); ?>
            </span>
            <span><?php echo public_detail_h($post["ADATE"]); ?> <?php echo public_detail_h($post["ATIME"]); ?></span>
        </div>
        <h1 class="h2 fw-bold lh-base mb-0"><?php echo public_detail_h($post["title"]); ?></h1>
    </header>

    <div class="card-body p-4 p-md-5">
        <div class="fs-5 lh-lg"><?php echo nl2br(public_detail_h($post["CONTENT"])); ?></div>

        <?php if (!empty($files)): ?>
            <section class="border-top mt-4 pt-4" aria-labelledby="filesTitle">
                <h2 id="filesTitle" class="h5 fw-bold mb-0">附件</h2>
                <ul class="list-group list-group-flush mt-3">
                    <?php foreach ($files as $file): ?>
                        <li class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span><?php echo public_detail_h($file["original_name"]); ?></span>
                            <a href="/scholarship/file_view.php?id=<?php echo urlencode((string)$file["id"]); ?>" class="btn btn-sm btn-outline-primary">下載</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <?php if (!empty($post["scholarship_id"])): ?>
            <section class="mt-5 text-center bg-light p-4 rounded border border-primary-subtle">
                <h2 class="h5 fw-bold mb-3 text-dark">符合資格嗎？馬上提出申請！</h2>
                <p class="text-secondary small mb-3">點擊下方按鈕，系統將為您自動帶入本獎助學金的申請表單。</p>
                
                <?php if ($isLoggedIn && isset($_SESSION["user"]["role"]) && (int)$_SESSION["user"]["role"] === 1): ?>
                    <a href="/scholarship/student/apply.php?scid=<?php echo urlencode((string)$post["scholarship_id"]); ?>" class="btn btn-primary btn-lg px-5 shadow-sm">
                        🚀 立即前往申請
                    </a>
                <?php else: ?>
                    <a href="/scholarship/login.php" class="btn btn-outline-primary btn-lg px-5 shadow-sm">
                        請先登入學生帳號再申請
                    </a>
                <?php endif; ?>
            </section>
        <?php endif; ?>                
    </div>
</article>

</main>
</body>
</html>
