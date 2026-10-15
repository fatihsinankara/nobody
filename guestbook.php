<?php
/**
 * 2000'lerin Efsanesi: Ziyaretçi Defteri (Guestbook)
 */
require_once __DIR__ . '/config/init.php';

$guestbook_enabled = get_setting('guestbook_enabled', '1');
$is_admin = is_admin_logged_in();

// Ziyaretçi defteri devre dışıysa ve ziyaretçi admin değilse erişimi engelle
if ($guestbook_enabled !== '1' && !$is_admin) {
    http_response_code(404);
    $page_title = 'Ziyaretçi Defteri Kapalı';
    include __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-error"><strong>Bilgi:</strong> Ziyaretçi defteri şu anda kapalıdır ve yeni mesaj kabul edilmemektedir.</div>';
    echo '<p><a href="/">&laquo; Ana sayfaya dön</a></p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = 'Ziyaretçi Defteri';
$error = '';
$success = '';

// Yeni kayıt gönderme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_guestbook'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen formu yenileyin.';
    } elseif (!empty($_POST['website_hp'])) {
        // Honeypot yakalandı - bot tespit edildi
        $error = 'İşlem güvenlik nedeniyle durduruldu.';
    } elseif (!check_rate_limit('guestbook', 3, 300)) {
        $error = 'Kısa sürede çok fazla mesaj gönderdiniz. Lütfen birkaç dakika bekleyin.';
    } else {
        $author_name = mb_substr(trim($_POST['author_name'] ?? ''), 0, 80);
        $author_city = mb_substr(trim($_POST['author_city'] ?? ''), 0, 60);
        $author_url = safe_url(trim($_POST['author_url'] ?? ''));
        $message = mb_substr(trim($_POST['message'] ?? ''), 0, 3000);
        $captcha_input = $_POST['captcha_answer'] ?? '';

        if (empty($author_name)) {
            $error = 'Lütfen adınızı veya takma adınızı belirtin.';
        } elseif (empty($message)) {
            $error = 'Lütfen bir hatıra / selam mesajı yazın.';
        } elseif (!verify_and_refresh_captcha($captcha_input)) {
            $error = 'Güvenlik sorusu hatalı girildi. Lütfen tekrar deneyin.';
        } else {
            record_rate_limit_hit('guestbook');

            $ins = $pdo->prepare("INSERT INTO guestbook (author_name, author_city, author_url, message, created_at, is_approved) VALUES (?, ?, ?, ?, NOW(), 1)");
            $ins->execute([$author_name, $author_city, $author_url, $message]);
            $success = 'Ziyaretçi defterine yazdığınız not kaydedildi. Teşekkürler!';
        }
    }
}

// Yeni captcha sorusu
$captcha = get_math_captcha();

// Sayfalama
$per_page = 10;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $per_page;

$count_stmt = $pdo->query("SELECT COUNT(*) FROM guestbook WHERE is_approved = 1");
$total_entries = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_entries / $per_page);

$stmt = $pdo->prepare("SELECT * FROM guestbook WHERE is_approved = 1 ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute();
$entries = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($guestbook_enabled !== '1' && $is_admin): ?>
    <div class="alert alert-error" style="background:#fff3cd; border-color:#ffeeba; color:#856404; margin-bottom:18px;">
        <strong>[!] Taslak Modu:</strong> Ziyaretçi defteri şu anda ziyaretçilere kapalıdır. Yalnızca siz (yönetici) görüntüleyebiliyorsunuz. 
        Yayınlamak için <a href="/admin/settings.php"><strong>Site Ayarları</strong></a> masasından etkinleştirebilirsiniz.
    </div>
<?php endif; ?>

<div style="margin-bottom: 20px;">
    <h2 style="font-family: Georgia, serif; font-size: 19px; margin-bottom: 6px;">
        Ziyaretçi Defteri (Guestbook)
    </h2>
    <p style="font-size: 12px; color: #555;">
        Eski internetin o samimi havasını yaşatmak adına buradasınız! Bir selam bırakabilir, nereden bağlandığınızı veya düşüncelerinizi paylaşabilirsiniz.
    </p>
</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- Defteri İmzala Formu -->
<div style="background-color: #f7f6f0; border: 1px solid #c8c5ba; padding: 14px; margin-bottom: 24px;">
    <h3 style="font-family: Georgia, serif; font-size: 14px; margin-bottom: 10px; color: #222;">
        [✎] Deftere Not Bırak
    </h3>

    <form action="/defter" method="POST">
        <?= csrf_field() ?>

        <!-- Anti-Spam Honeypot (Botlar için gizli tuzak alan) -->
        <div style="display:none;" aria-hidden="true">
            <label for="website_hp">Bu alanı boş bırakın:</label>
            <input type="text" id="website_hp" name="website_hp" value="" tabindex="-1" autocomplete="off">
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 140px;">
                <label for="author_name">İsminiz: *</label>
                <input type="text" id="author_name" name="author_name" maxlength="80" required value="<?= isset($_POST['author_name']) ? e($_POST['author_name']) : '' ?>">
            </div>

            <div class="form-group" style="flex: 1; min-width: 140px;">
                <label for="author_city">Şehir / Ülke:</label>
                <input type="text" id="author_city" name="author_city" maxlength="60" placeholder="örn. İstanbul veya Berlin" value="<?= isset($_POST['author_city']) ? e($_POST['author_city']) : '' ?>">
            </div>

            <div class="form-group" style="flex: 1; min-width: 140px;">
                <label for="author_url">Web Siteniz (Varsa):</label>
                <input type="url" id="author_url" name="author_url" placeholder="https://..." value="<?= isset($_POST['author_url']) ? e($_POST['author_url']) : '' ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="message">Mesajınız / Notunuz: *</label>
            <textarea id="message" name="message" rows="3" maxlength="3000" required><?= isset($_POST['message']) ? e($_POST['message']) : '' ?></textarea>
        </div>

        <div class="form-group" style="max-width: 250px;">
            <label for="captcha_answer">Güvenlik Sorusu (<?= $captcha['question'] ?>): *</label>
            <input type="text" id="captcha_answer" name="captcha_answer" required placeholder="Sonucu yazınız...">
        </div>

        <button type="submit" name="submit_guestbook" class="btn">Defteri İmzala &raquo;</button>
    </form>
</div>

<!-- Defter Kayıtları -->
<div class="guestbook-list">
    <h3 style="font-family: Georgia, serif; font-size: 15px; margin-bottom: 12px; border-bottom: 1px dotted #888; padding-bottom: 4px;">
        Yazılan Hatıralar (<?= $total_entries ?> Kayıt)
    </h3>

    <?php if (empty($entries)): ?>
        <p style="font-size: 12px; color: #777;">Henüz kimse defteri imzalamamış. İlk imzayı siz atın!</p>
    <?php else: ?>
        <?php foreach ($entries as $item): ?>
            <div class="guestbook-entry">
                <div class="gb-header">
                    <span class="gb-author">
                        <?php 
                        $item_url = safe_url($item['author_url'] ?? '');
                        if (!empty($item_url)): 
                        ?>
                            <a href="<?= e($item_url) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= e($item['author_name']) ?></a>
                        <?php else: ?>
                            <?= e($item['author_name']) ?>
                        <?php endif; ?>
                    </span>
                    <?php if (!empty($item['author_city'])): ?>
                        <span class="comment-city">(<?= e($item['author_city']) ?>)</span>
                    <?php endif; ?>
                    &bull; <?= format_date($item['created_at']) ?>
                </div>
                <div style="font-size: 12px; line-height: 1.6;">
                    <?= render_content($item['message']) ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div>
                    <?php if ($page > 1): ?>
                        <a href="/defter?p=<?= $page - 1 ?>">&laquo; Önceki Sayfa</a>
                    <?php endif; ?>
                </div>
                <div>Sayfa <?= $page ?> / <?= $total_pages ?></div>
                <div>
                    <?php if ($page < $total_pages): ?>
                        <a href="/defter?p=<?= $page + 1 ?>">Sonraki Sayfa &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
