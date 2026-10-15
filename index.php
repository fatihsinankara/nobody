<?php
/**
 * Ana Sayfa & Arşiv (Index & Archive)
 */
require_once __DIR__ . '/config/init.php';

$search_query = input('q', 100, 'GET');
$category_slug = input('category_slug', 100, 'GET');
$category_filter = input('category', 100, 'GET');
$matched_category = null;

if ($category_slug !== '') {
    $matched_category = get_category_by_slug($category_slug);
    $category_filter = $matched_category ? $matched_category['name'] : $category_slug;
} elseif ($category_filter !== '') {
    $stmtC = $pdo->prepare("SELECT * FROM categories WHERE name = ?");
    $stmtC->execute([$category_filter]);
    $matched_category = $stmtC->fetch() ?: null;
}

$is_archive = isset($_GET['archive']);

$page_title = 'Ana Sayfa';
if ($is_archive) {
    $page_title = 'Yazı Arşivi';
} elseif ($category_filter !== '') {
    $page_title = 'Kategori: ' . $category_filter;
} elseif ($search_query !== '') {
    $page_title = 'Arama: ' . $search_query;
}

// Sayfalama parametreleri (aşırı offset ve tip hataları engellendi)
$per_page = 5;
$page = max(1, min(100000, (int)($_GET['p'] ?? 1)));
$offset = ($page - 1) * $per_page;

// Sorgu hazırlama
$where_clauses = [];
$params = [];

if ($search_query !== '') {
    $where_clauses[] = "(p.title LIKE ? OR p.content LIKE ? OR p.summary LIKE ?)";
    $like = '%' . $search_query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($matched_category) {
    $where_clauses[] = "(p.category_id = ? OR p.category = ?)";
    $params[] = $matched_category['id'];
    $params[] = $matched_category['name'];
} elseif ($category_filter !== '') {
    $where_clauses[] = "p.category = ?";
    $params[] = $category_filter;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Toplam yazı sayısı
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM posts p $where_sql");
$count_stmt->execute($params);
$total_posts = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_posts / $per_page));

// Yazıları çek
if ($is_archive) {
    // Arşiv görünümü: Tüm yazılar kronolojik
    $stmt = $pdo->query("SELECT id, title, slug, category, created_at, views FROM posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll();
} else {
    // Normal liste: Sayfalı ve tek sorguda yorum sayısıyla birlikte (N+1 engellendi)
    $query = "SELECT p.id, p.title, p.slug, p.category, p.summary, p.content, p.views, p.created_at,
                     COUNT(c.id) AS comment_count
              FROM posts p
              LEFT JOIN comments c ON c.post_id = p.id AND c.is_approved = 1
              $where_sql 
              GROUP BY p.id
              ORDER BY p.created_at DESC 
              LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($search_query !== ''): ?>
    <div class="alert alert-success">
        <strong>Arama Sonucu:</strong> "<em><?= e($search_query) ?></em>" için <?= $total_posts ?> yazı bulundu. 
        <a href="/">[Filtreyi Temizle]</a>
    </div>
<?php elseif ($category_filter !== ''): ?>
    <div class="alert alert-success">
        <strong>Kategori:</strong> "<em><?= e($category_filter) ?></em>" etiketli <?= $total_posts ?> yazı gösteriliyor.
        <a href="/">[Tüm Yazılar]</a>
    </div>
<?php endif; ?>

<?php if ($is_archive): ?>
    <!-- Klasik Arşiv Görünümü -->
    <h2 style="font-family: Georgia, serif; font-size: 18px; margin-bottom: 15px; border-bottom: 1px solid #aaa; padding-bottom: 4px;">
        Yazı Arşivi (Toplam <?= count($posts) ?> yazı)
    </h2>
    
    <?php if (empty($posts)): ?>
        <p>Arşivde henüz kayıtlı bir yazı yok.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 130px;">Tarih</th>
                    <th>Yazı Başlığı</th>
                    <th style="width: 100px;">Kategori</th>
                    <th style="width: 70px;">Okunma</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td style="font-family: 'Courier New', monospace; font-size: 11px;">
                            <?= format_date($post['created_at'], false) ?>
                        </td>
                        <td>
                            <a href="<?= post_url($post['slug']) ?>"><strong><?= e($post['title']) ?></strong></a>
                        </td>
                        <td>
                            <span class="badge"><?= e($post['category']) ?></span>
                        </td>
                        <td style="font-family: 'Courier New', monospace; text-align: right;">
                            <?= (int)$post['views'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php else: ?>
    <!-- Normal Blog Yazı Akışı -->
    <?php if (empty($posts)): ?>
        <div style="padding: 20px 0; text-align: center; color: #666;">
            <p>Aradığınız kriterlere uygun yazı bulunamadı.</p>
            <p><a href="/">&laquo; Ana sayfaya dön</a></p>
        </div>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php $comment_count = (int)($post['comment_count'] ?? 0); ?>
            <article class="post-entry">
                <h2 class="post-title">
                    <a href="<?= post_url($post['slug']) ?>"><?= e($post['title']) ?></a>
                </h2>
                
                <div class="post-meta">
                    Tarih: <?= format_date($post['created_at']) ?> |
                    Kategori: <a href="<?= category_url(slugify($post['category'])) ?>"><?= e($post['category']) ?></a> |
                    Okunma: <?= (int)$post['views'] ?> |
                    Yorumlar: <a href="<?= post_url($post['slug']) ?>#comments"><?= $comment_count ?></a>
                </div>

                <div class="post-body">
                    <?php if (!empty($post['summary'])): ?>
                        <p><?= render_content($post['summary']) ?></p>
                    <?php else: ?>
                        <p><?= render_content(mb_substr($post['content'], 0, 280) . '...') ?></p>
                    <?php endif; ?>
                </div>

                <a href="<?= post_url($post['slug']) ?>" class="read-more">Devamını Oku &raquo;</a>
            </article>
        <?php endforeach; ?>

        <!-- Sayfalama (Pagination) -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div>
                    <?php if ($page > 1): ?>
                        <a href="/?<?= http_build_query(array_merge($_GET, ['p' => $page - 1])) ?>">&laquo; Önceki Sayfa</a>
                    <?php else: ?>
                        <span style="color: #999;">&laquo; Önceki Sayfa</span>
                    <?php endif; ?>
                </div>
                <div>
                    Sayfa <?= $page ?> / <?= $total_pages ?>
                </div>
                <div>
                    <?php if ($page < $total_pages): ?>
                        <a href="/?<?= http_build_query(array_merge($_GET, ['p' => $page + 1])) ?>">Sonraki Sayfa &raquo;</a>
                    <?php else: ?>
                        <span style="color: #999;">Sonraki Sayfa &raquo;</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
