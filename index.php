<?php
require_once __DIR__ . "/scholarship/config.php";

function public_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function public_category_label($category)
{
    return (int)$category === 1 ? "獎學金審查結果" : "獎學金公告";
}

function public_excerpt($content, $limit)
{
    $content = trim((string)$content);
    if ($content === "") {
        return "";
    }

    if (function_exists("mb_strlen") && function_exists("mb_substr")) {
        $excerpt = mb_substr($content, 0, $limit, "UTF-8");
        return mb_strlen($content, "UTF-8") > $limit ? $excerpt . "..." : $excerpt;
    }

    $excerpt = substr($content, 0, $limit);
    return strlen($content) > $limit ? $excerpt . "..." : $excerpt;
}

$announcements = array();

try {
    $stmt = $pdo->prepare("
        SELECT id, title, ADATE, ATIME, CONTENT, CATEGORY
        FROM announcement
        ORDER BY ADATE DESC, ATIME DESC, id DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    exit("公告載入失敗：" . public_h($e->getMessage()));
}

$pageTitle = "獎學金公告";
$activeNav = "/index.php";
$isLoggedIn = !empty($_SESSION["user"]);
if (!$isLoggedIn) {
    $breadcrumbs = array();
}
$siteHeaderMainClass = "site-shell pt-4";
if (!$isLoggedIn) {
    $siteHeaderBrandHref = "/index.php";
}
require __DIR__ . "/scholarship/header.php";
?>

<section id="announcements" <?php echo $isLoggedIn ? 'aria-label="獎學金公告"' : 'aria-labelledby="announcementTitle"'; ?>>
    <?php if (!$isLoggedIn): ?>
        <div class="d-flex justify-content-between align-items-end gap-3 mb-3">
            <div>
                <h2 id="announcementTitle" class="h4 fw-bold mb-0">最新公告</h2>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($announcements)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-5 text-center text-secondary">目前尚無任何公告</div>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-lg-2 g-3">
            <?php foreach ($announcements as $announcement): ?>
                <?php
                $category = (int)$announcement["CATEGORY"];
                $excerpt = public_excerpt($announcement["CONTENT"], 90);
                $badgeClass = $category === 1 ? "text-bg-success" : "text-bg-primary";
                ?>
                <div class="col">
                <a class="card border-0 shadow-sm h-100 text-body text-decoration-none" href="/announcement_detail.php?id=<?php echo urlencode((string)$announcement["id"]); ?>">
                    <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 text-secondary small fw-semibold">
                        <span class="badge rounded-pill <?php echo $badgeClass; ?>"><?php echo public_h(public_category_label($category)); ?></span>
                        <span><?php echo public_h($announcement["ADATE"]); ?></span>
                    </div>
                    <h3 class="h5 fw-bold lh-base mb-0"><?php echo public_h($announcement["title"]); ?></h3>
                    <?php if ($excerpt !== ""): ?>
                        <p class="text-secondary lh-lg mt-3 mb-0"><?php echo public_h($excerpt); ?></p>
                    <?php endif; ?>
                    <div class="mt-auto pt-3 fw-bold text-primary">查看詳情</div>
                    </div>
                </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

</main>
</body>
</html>
