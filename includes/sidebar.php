<?php
/**
 * Kenar Çubuğu (Sidebar) Şablonu
 */
if (!defined('APP_INIT')) die('Doğrudan erişim engellendi.');

$categories = get_categories();
$recent_posts = get_recent_posts(5);
$webmaster = get_setting('webmaster_name', 'nobody');
$blogroll_json = get_setting('blogroll_links', '[]');
$blogroll = json_decode($blogroll_json, true) ?: [];
?>
<aside id="sidebar">

    <!-- Arama Kutusu -->
    <div class="sidebar-box">
        <div class="sidebar-title">Sitede Ara</div>
        <form action="/" method="GET" class="search-form">
            <input type="text" name="q" placeholder="Kelime yazın..." value="<?= isset($_GET['q']) ? e($_GET['q']) : '' ?>" required>
            <button type="submit" class="btn btn-sm">Ara</button>
        </form>
    </div>

    <!-- Webmaster Köşesi -->
    <div class="sidebar-box">
        <div class="sidebar-title">Webmaster</div>
        <div style="font-family: 'Courier New', monospace; font-size: 11px; margin-bottom: 6px;">
            <strong>Sahibi:</strong> <?= e($webmaster) ?><br>
            <strong>Durum:</strong> Çevrimiçi ●<br>
            <?php if (get_setting('guestbook_enabled', '1') === '1' || is_admin_logged_in()): ?>
                <strong>İletişim:</strong> <a href="/defter">Deftere Yaz</a>
            <?php endif; ?>
        </div>
        <p style="font-size: 11px; color: #555555;">
            "Gereksiz kod silindikçe hafifler, samimi kelimeler paylaşıldıkça yaşar."
        </p>
        <?php if (get_setting('about_enabled', '1') === '1' || is_admin_logged_in()): ?>
            <div style="margin-top: 6px; font-size: 11px;">
                <a href="/hakkimda">&raquo; Biyografi &amp; Detaylar</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Son Eklenen Yazılar -->
    <div class="sidebar-box">
        <div class="sidebar-title">Son Yazılar</div>
        <?php if (!empty($recent_posts)): ?>
            <ul>
                <?php foreach ($recent_posts as $rp): ?>
                    <li>
                        <a href="<?= post_url($rp['slug']) ?>"><?= e($rp['title']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="font-size: 11px; color: #777;">Henüz yazı bulunmuyor.</p>
        <?php endif; ?>
    </div>

    <!-- Kategoriler -->
    <div class="sidebar-box">
        <div class="sidebar-title" style="display: flex; justify-content: space-between; align-items: baseline;">
            <span>Kategoriler</span>
            <?php if (is_admin_logged_in()): ?>
                <a href="/admin/categories.php" style="font-size: 10px; font-weight: normal; text-transform: none;">[Yönet]</a>
            <?php endif; ?>
        </div>
        <?php if (!empty($categories)): ?>
            <ul>
                <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="<?= category_url($cat['slug']) ?>">
                            <?= e($cat['name']) ?>
                        </a>
                        <span style="font-size: 10px; color: #666666;">(<?= (int)$cat['count'] ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="font-size: 11px; color: #777;">Kategori yok.</p>
        <?php endif; ?>
    </div>

    <!-- Dost Siteler (Blogroll / Webring) -->
    <div class="sidebar-box">
        <div class="sidebar-title">Dost Bağlantılar</div>
        <?php if (!empty($blogroll)): ?>
            <ul>
                <?php foreach ($blogroll as $link): 
                    $s_url = safe_url($link['url'] ?? '');
                    if (empty($s_url)) continue;
                ?>
                    <li>
                        <a href="<?= e($s_url) ?>" target="_blank" rel="noopener noreferrer">
                            <?= e($link['title']) ?> ↗
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="font-size: 11px; color: #777;">Bağlantı eklenmedi.</p>
        <?php endif; ?>
    </div>

</aside>
