<?php
require_once __DIR__ . "/scholarship/config.php";
require_once __DIR__ . "/scholarship/file_helpers.php";

function public_detail_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function public_detail_category_label($category)
{
    return (int)$category === 1 ? "獎學金審查結果" : "獎學金公告";
}

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit("無效的公告編號。");
}

try {
    $stmt = $pdo->prepare("
        SELECT id, title, ADATE, ATIME, CONTENT, AID, CATEGORY
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
$siteHeaderMaxWidth = "900px";
$siteHeaderMainClass = "site-shell announcement-detail-shell";
if (!$isLoggedIn) {
    $siteHeaderBrandHref = "/index.php";
}
$siteHeaderExtraHead = <<<HTML
<style>
    .announcement-detail-shell {
        padding-top: 24px;
    }

    .detail-article {
        background: #fff;
        border: 1px solid var(--site-border);
        border-radius: 8px;
        box-shadow: var(--site-shadow);
        overflow: hidden;
    }

    .detail-head {
        padding: 30px 34px 22px;
        border-bottom: 1px solid var(--site-border);
    }

    .detail-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        color: var(--site-muted);
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 14px;
    }

    .detail-badge {
        display: inline-flex;
        align-items: center;
        min-height: 26px;
        padding: 4px 9px;
        border-radius: 999px;
        background: #eaf1ff;
        color: var(--site-primary-dark);
        font-size: 12px;
        font-weight: 850;
    }

    .detail-badge.result {
        background: #e8f8ee;
        color: #15803d;
    }

    .detail-title {
        margin: 0;
        font-size: 30px;
        font-weight: 850;
        line-height: 1.35;
        letter-spacing: 0;
    }

    .detail-body {
        padding: 30px 34px 34px;
    }

    .detail-content {
        color: var(--site-text);
        font-size: 18px;
        line-height: 1.9;
        white-space: pre-wrap;
    }

    .detail-files {
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid var(--site-border);
    }

    .detail-file-list {
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
    }

    .detail-file-item {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #edf2f7;
    }

    .detail-file-item:last-child {
        border-bottom: 0;
    }

    .detail-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 24px;
    }

    @media (max-width: 700px) {
        .detail-head,
        .detail-body {
            padding-left: 22px;
            padding-right: 22px;
        }

        .detail-title {
            font-size: 24px;
        }

        .detail-content {
            font-size: 16px;
        }

        .detail-file-item {
            display: block;
        }

        .detail-file-item a {
            display: inline-block;
            margin-top: 8px;
        }
    }
</style>
HTML;
require __DIR__ . "/scholarship/header.php";
?>

<article class="detail-article">
    <header class="detail-head">
        <div class="detail-meta">
            <span class="detail-badge <?php echo (int)$post["CATEGORY"] === 1 ? "result" : ""; ?>">
                <?php echo public_detail_h(public_detail_category_label($post["CATEGORY"])); ?>
            </span>
            <span><?php echo public_detail_h($post["ADATE"]); ?> <?php echo public_detail_h($post["ATIME"]); ?></span>
        </div>
        <h1 class="detail-title"><?php echo public_detail_h($post["title"]); ?></h1>
    </header>

    <div class="detail-body">
        <div class="detail-content"><?php echo public_detail_h($post["CONTENT"]); ?></div>

        <?php if (!empty($files)): ?>
            <section class="detail-files" aria-labelledby="filesTitle">
                <h2 id="filesTitle" class="h5 fw-bold mb-0">附件</h2>
                <ul class="detail-file-list">
                    <?php foreach ($files as $file): ?>
                        <li class="detail-file-item">
                            <span><?php echo public_detail_h($file["original_name"]); ?></span>
                            <a href="/scholarship/file_view.php?id=<?php echo urlencode((string)$file["id"]); ?>" class="btn btn-sm btn-outline-primary">下載</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <div class="detail-actions">
            <a href="/index.php#announcements" class="btn btn-outline-secondary">返回公告列表</a>
        </div>
    </div>
</article>

</main>
</body>
</html>
