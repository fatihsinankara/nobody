<?php
/**
 * Tekil Yazı Sayfası ve Yorumlar (Single Post & Comments)
 */
require_once __DIR__ . '/config/init.php';

$slug = input('slug', 255, 'GET');
$id = (int)($_GET['id'] ?? 0);

if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ?");
    $stmt->execute([$slug]);
} elseif ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$id]);
} else {
    header('Location: /');
    exit;
}

$post = $stmt->fetch();

if (!$post) {
    header("HTTP/1.0 404 Not Found");
    $page_title = 'Yazı Bulunamadı';
    include __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-error">Aradığınız blog yazısı bulunamadı veya silinmiş olabilir.</div>';
    echo '<p><a href="/">&laquo; Ana sayfaya dön</a></p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Okunma sayısını oturum bazlı artır
if (!isset($_SESSION['viewed_posts'])) {
    $_SESSION['viewed_posts'] = [];
}
if (!in_array($post['id'], $_SESSION['viewed_posts'], true)) {
    $_SESSION['viewed_posts'][] = $post['id'];
    $upd = $pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?");
    $upd->execute([$post['id']]);
    $post['views']++;
}

// Yorum gönderme işlemi
$comment_error = '';
$comment_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $comment_error = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen formu yenileyin.';
    } elseif (!empty($_POST['website_hp'])) {
        // Honeypot yakalandı - bot tespit edildi
        $comment_error = 'İşlem güvenlik nedeniyle durduruldu.';
    } elseif (!check_rate_limit('comment', 5, 300)) {
        $comment_error = 'Çok sık yorum gönderiyorsunuz. Lütfen birkaç dakika sonra tekrar deneyin.';
    } else {
        $author_name = input('author_name', 80);
        $author_email = input('author_email', 120);
        $author_url = safe_url(input('author_url', 200));
        $comment_text = input('content', 3000);
        $captcha_input = $_POST['captcha_answer'] ?? '';

        if ($author_name === '') {
            $comment_error = 'Lütfen adınızı veya takma adınızı belirtin.';
        } elseif ($comment_text === '') {
            $comment_error = 'Lütfen bir yorum mesajı yazın.';
        } elseif (!verify_and_refresh_captcha($captcha_input)) {
            record_rate_limit_hit('comment');
            $comment_error = 'Güvenlik sorusunun cevabı hatalı. Lütfen tekrar deneyin.';
        } else {
            record_rate_limit_hit('comment');

            if ($author_email !== '' && !filter_var($author_email, FILTER_VALIDATE_EMAIL)) {
                $author_email = null;
            }

            $is_admin = is_admin_logged_in();
            $requires_mod = (get_setting('comment_moderation', '1') === '1') && !$is_admin;
            $is_approved = $requires_mod ? 0 : 1;

            $ins_c = $pdo->prepare("INSERT INTO comments (post_id, author_name, author_email, author_url, content, created_at, is_approved) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
            $ins_c->execute([$post['id'], $author_name, $author_email, $author_url, $comment_text, $is_approved]);

            $comment_success = $requires_mod
                ? 'Yorumunuz gönderildi. Yönetici onayının ardından yayınlanacaktır. Teşekkürler!'
                : 'Yorumunuz başarıyla eklendi. Teşekkürler!';
        }
    }
}

// Yeni captcha sorusu hazırla
$captcha = get_math_captcha();

// Yorumları çek
$c_stmt = $pdo->prepare("SELECT * FROM comments WHERE post_id = ? AND is_approved = 1 ORDER BY created_at ASC");
$c_stmt->execute([$post['id']]);
$comments = $c_stmt->fetchAll();

// Önceki ve Sonraki Yazılar
$prev_stmt = $pdo->prepare("SELECT slug, title FROM posts WHERE created_at < ? ORDER BY created_at DESC LIMIT 1");
$prev_stmt->execute([$post['created_at']]);
$prev_post = $prev_stmt->fetch();

$next_stmt = $pdo->prepare("SELECT slug, title FROM posts WHERE created_at > ? ORDER BY created_at ASC LIMIT 1");
$next_stmt->execute([$post['created_at']]);
$next_post = $next_stmt->fetch();

$page_title = $post['title'];
include __DIR__ . '/includes/header.php';
?>

<article class="post-entry" style="border-bottom: none;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
        <h1 class="post-title" style="font-size: 22px; flex: 1;">
            <?= e($post['title']) ?>
        </h1>
        <?php if (is_admin_logged_in()): ?>
            <a href="/admin/post_edit.php?id=<?= $post['id'] ?>" class="btn btn-sm">[✎ Düzenle]</a>
        <?php endif; ?>
    </div>

    <div class="post-meta">
        Tarih: <?= format_date($post['created_at']) ?> |
        Kategori: <a href="<?= category_url(slugify($post['category'])) ?>"><?= e($post['category']) ?></a> |
        Okunma: <?= (int)$post['views'] ?> |
        Yorum: <?= count($comments) ?>
    </div>

    <div class="post-body">
        <?= render_content($post['content']) ?>
    </div>
</article>

<!-- Önceki / Sonraki Yazı Gezintisi (Temiz URL'ler) -->
<div class="pagination" style="margin-bottom: 25px;">
    <div>
        <?php if ($prev_post): ?>
            &laquo; <a href="<?= post_url($prev_post['slug']) ?>" title="<?= e($prev_post['title']) ?>">
                Önceki: <?= e(mb_substr($prev_post['title'], 0, 24)) ?>...
            </a>
        <?php else: ?>
            <span style="color: #999;">&laquo; İlk Yazı</span>
        <?php endif; ?>
    </div>
    <div>
        <a href="/">[Ana Sayfa]</a>
    </div>
    <div>
        <?php if ($next_post): ?>
            <a href="<?= post_url($next_post['slug']) ?>" title="<?= e($next_post['title']) ?>">
                Sonraki: <?= e(mb_substr($next_post['title'], 0, 24)) ?>...
            </a> &raquo;
        <?php else: ?>
            <span style="color: #999;">Son Yazı &raquo;</span>
        <?php endif; ?>
    </div>
</div>

<!-- Yorumlar Bölümü -->
<section id="comments">
    <h3 style="font-family: Georgia, serif; font-size: 16px; margin-bottom: 12px; border-bottom: 1px solid #ccc; padding-bottom: 4px;">
        Yorumlar (<?= count($comments) ?>)
    </h3>

    <?php if ($comment_success !== ''): ?>
        <div class="alert alert-success"><?= e($comment_success) ?></div>
    <?php endif; ?>

    <?php if ($comment_error !== ''): ?>
        <div class="alert alert-error"><?= e($comment_error) ?></div>
    <?php endif; ?>

    <?php if (empty($comments)): ?>
        <p style="font-size: 12px; color: #666; margin-bottom: 20px;">
            Bu yazıya henüz yorum yapılmamış. İlk yorumu siz yapın!
        </p>
    <?php else: ?>
        <?php foreach ($comments as $idx => $com): ?>
            <div class="comment-box" id="comment-<?= $com['id'] ?>">
                <div class="comment-header">
                    #<?= $idx + 1 ?> &mdash; 
                    <span class="comment-author">
                        <?php 
                        $com_url = safe_url($com['author_url'] ?? '');
                        if (!empty($com_url)): 
                        ?>
                            <a href="<?= e($com_url) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= e($com['author_name']) ?></a>
                        <?php else: ?>
                            <?= e($com['author_name']) ?>
                        <?php endif; ?>
                    </span>
                    tarafından <?= format_date($com['created_at']) ?> tarihinde yazıldı.
                </div>
                <div style="font-size: 12px; line-height: 1.6;">
                    <?= render_content($com['content']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Yorum Formu -->
    <div style="background-color: #f5f4ee; border: 1px solid #cccccc; padding: 14px; margin-top: 15px;">
        <h4 style="font-family: Georgia, serif; font-size: 14px; margin-bottom: 10px;">Bir Yorum Bırakın</h4>
        
        <form action="<?= post_url($post['slug']) ?>#comments" method="POST">
            <?= csrf_field() ?>

            <!-- Anti-Spam Honeypot (Botlar için gizli tuzak alan) -->
            <div style="display:none;" aria-hidden="true">
                <label for="website_hp">Bu alanı boş bırakın:</label>
                <input type="text" id="website_hp" name="website_hp" value="" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="author_name">İsim veya Takma Ad: *</label>
                <input type="text" id="author_name" name="author_name" maxlength="80" required value="<?= e(input('author_name', 80)) ?>">
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 140px;">
                    <label for="author_email">E-posta (Yayınlanmaz):</label>
                    <input type="email" id="author_email" name="author_email" maxlength="120" value="<?= e(input('author_email', 120)) ?>">
                </div>

                <div class="form-group" style="flex: 1; min-width: 140px;">
                    <label for="author_url">Web Siteniz (Varsa):</label>
                    <input type="url" id="author_url" name="author_url" maxlength="200" placeholder="https://..." value="<?= e(input('author_url', 200)) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="content">Yorumunuz: *</label>
                <textarea id="content" name="content" rows="4" maxlength="3000" required><?= e(input('content', 3000)) ?></textarea>
            </div>

            <div class="form-group" style="max-width: 250px;">
                <label for="captcha_answer">Robot Değilim (<?= $captcha['question'] ?>): *</label>
                <input type="text" id="captcha_answer" name="captcha_answer" required placeholder="Cevap...">
            </div>

            <button type="submit" name="submit_comment" class="btn">Yorumu Gönder &raquo;</button>
        </form>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
