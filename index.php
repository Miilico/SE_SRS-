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
$breadcrumbs = array();
$siteHeaderMaxWidth = "1120px";
$siteHeaderMainClass = "site-shell public-home-shell";
if (empty($_SESSION["user"])) {
    $siteHeaderBrandHref = "/index.php";
}
$siteHeaderExtraHead = <<<HTML
<style>
    .public-home-shell {
        padding-top: 24px;
    }

    .announcement-card,
    .empty-state {
        background: #fff;
        border: 1px solid var(--site-border);
        border-radius: 8px;
        box-shadow: var(--site-shadow);
    }

    .home-section-head {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        align-items: flex-end;
        margin: 0 0 14px;
    }

    .home-section-title {
        margin: 0;
        font-size: 22px;
        font-weight: 850;
        letter-spacing: 0;
    }

    .announcement-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .announcement-card {
        display: flex;
        flex-direction: column;
        min-height: 218px;
        padding: 22px;
        color: var(--site-text);
        text-decoration: none;
        transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
    }

    .announcement-card:hover {
        border-color: #a9bce5;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .11);
        color: var(--site-text);
        transform: translateY(-2px);
        text-decoration: none;
    }

    .announcement-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        margin-bottom: 14px;
        color: var(--site-muted);
        font-size: 13px;
        font-weight: 700;
    }

    .announcement-badge {
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

    .announcement-card.result .announcement-badge {
        background: #e8f8ee;
        color: #15803d;
    }

    .announcement-title {
        margin: 0;
        font-size: 19px;
        font-weight: 850;
        line-height: 1.45;
        letter-spacing: 0;
    }

    .announcement-excerpt {
        margin: 12px 0 0;
        color: var(--site-muted);
        line-height: 1.7;
    }

    .announcement-footer {
        margin-top: auto;
        padding-top: 18px;
        color: var(--site-primary-dark);
        font-weight: 850;
    }

    .empty-state {
        padding: 44px 20px;
        text-align: center;
        color: var(--site-muted);
    }

    @media (max-width: 860px) {
        .announcement-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
HTML;
require __DIR__ . "/scholarship/header.php";
?>

<section id="announcements" aria-labelledby="announcementTitle">
    <div class="home-section-head">
        <div>
            <h2 id="announcementTitle" class="home-section-title">最新公告</h2>
            <div class="text-secondary mt-1">所有公告皆可免登入查看。</div>
        </div>
    </div>

    <?php if (empty($announcements)): ?>
        <div class="empty-state">目前尚無任何公告</div>
    <?php else: ?>
        <div class="announcement-grid">
            <?php foreach ($announcements as $announcement): ?>
                <?php
                $category = (int)$announcement["CATEGORY"];
                $excerpt = public_excerpt($announcement["CONTENT"], 90);
                ?>
                <a class="announcement-card <?php echo $category === 1 ? "result" : ""; ?>" href="/announcement_detail.php?id=<?php echo urlencode((string)$announcement["id"]); ?>">
                    <div class="announcement-meta">
                        <span class="announcement-badge"><?php echo public_h(public_category_label($category)); ?></span>
                        <span><?php echo public_h($announcement["ADATE"]); ?></span>
                    </div>
                    <h3 class="announcement-title"><?php echo public_h($announcement["title"]); ?></h3>
                    <?php if ($excerpt !== ""): ?>
                        <p class="announcement-excerpt"><?php echo public_h($excerpt); ?></p>
                    <?php endif; ?>
                    <div class="announcement-footer">查看詳情</div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

</main>
</body>
</html>
