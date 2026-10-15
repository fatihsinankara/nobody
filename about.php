<?php
/**
 * Hakkımda & Blogroll (About Me & Blogroll)
 */
require_once __DIR__ . '/config/init.php';

$about_enabled = get_setting('about_enabled', '1');
$is_admin = is_admin_logged_in();

// Hakkımda sayfası devre dışıysa ve ziyaretçi admin değilse erişimi engelle
if ($about_enabled !== '1' && !$is_admin) {
    http_response_code(404);
    $page_title = 'Sayfa Yayında Değil';
    include __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-error"><strong>Bilgi:</strong> Hakkımda sayfası şu anda yayında değildir.</div>';
    echo '<p><a href="/">&laquo; Ana sayfaya dön</a></p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = 'Hakkımda & Bağlantılar';
$about_text = get_setting('about_text', '');
$about_interests = get_setting('about_interests', "Müzik: Radiohead, Pink Floyd, Depeche Mode, Duman\nKitaplar: Bilim Kurgu, Felsefe, Otostopçunun Galaksi Rehberi\nİşletim Sistemi: GNU/Linux & Windows XP Nostaljisi\nTarayıcı: Netscape / Firefox");
$webmaster = get_setting('webmaster_name', 'nobody');
$blogroll_json = get_setting('blogroll_links', '[]');
$blogroll = json_decode($blogroll_json, true) ?: [];

include __DIR__ . '/includes/header.php';
?>

<?php if ($about_enabled !== '1' && $is_admin): ?>
    <div class="alert alert-error" style="background:#fff3cd; border-color:#ffeeba; color:#856404; margin-bottom:18px;">
        <strong>[!] Taslak Modu:</strong> Bu sayfa şu anda yayında değildir ve ziyaretçilere kapalıdır. Yalnızca siz (yönetici) görüntüleyebiliyorsunuz. 
        Yayınlamak için <a href="/admin/settings.php"><strong>Site Ayarları</strong></a> veya <a href="/admin/about_edit.php"><strong>Hakkımda Düzenle</strong></a> bölümünden etkinleştirebilirsiniz.
    </div>
<?php endif; ?>

<article>
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #bbb; padding-bottom: 4px; margin-bottom: 12px;">
        <h2 style="font-family: Georgia, serif; font-size: 20px; margin: 0;">
            Hakkımda: <?= e($webmaster) ?>
        </h2>
        <?php if ($is_admin): ?>
            <a href="/admin/about_edit.php" class="btn btn-sm">[✎ Bu Sayfayı Düzenle]</a>
        <?php endif; ?>
    </div>

    <!-- Biyografi -->
    <div style="font-size: 13px; line-height: 1.7; margin-bottom: 24px;">
        <?= render_content($about_text) ?>
    </div>

    <!-- İlgi Alanları ve Sevilen Şeyler -->
    <?php if (!empty($about_interests)): ?>
        <h3 style="font-family: Georgia, serif; font-size: 16px; margin-bottom: 8px; border-bottom: 1px dotted #888; padding-bottom: 3px;">
            İlgi Alanları &amp; Sevdiğim Şeyler
        </h3>
        <div style="background-color: #f7f6f0; border: 1px solid #d5d2c7; padding: 12px; margin-bottom: 24px; font-size: 12px; line-height: 1.7;">
            <?= render_content($about_interests) ?>
        </div>
    <?php endif; ?>

    <!-- Dost Siteler & Ağ Halkası (Blogroll / Webring) -->
    <h3 style="font-family: Georgia, serif; font-size: 16px; margin-bottom: 8px; border-bottom: 1px dotted #888; padding-bottom: 3px;">
        Dost Bağlantılar &amp; Ağ Halkası (Blogroll)
    </h3>

    <p style="font-size: 12px; color: #555; margin-bottom: 12px;">
        İnternet bir zamanlar algoritmalar tarafından değil, insanların birbirlerinin sitelerine verdikleri samimi linkler sayesinde keşfedilirdi:
    </p>

    <?php if (!empty($blogroll)): ?>
        <ul style="padding-left: 20px; line-height: 1.8;">
            <?php foreach ($blogroll as $link): ?>
                <li>
                    <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer" style="font-weight: bold;">
                        <?= e($link['title']) ?>
                    </a>
                    &mdash; <span style="font-family: 'Courier New', monospace; font-size: 11px; color: #555;"><?= e($link['url']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p style="font-size: 12px; color: #777;">Henüz bağlantı eklenmedi.</p>
    <?php endif; ?>

    <div style="margin-top: 25px; background-color: #f4f3ec; border: 1px solid #dcd8cc; padding: 12px; font-size: 11px; font-family: 'Courier New', monospace;">
        <strong>İletişim &amp; Takas:</strong> Kendi kişisel siteniz varsa ve link değişimi yapmak isterseniz, 
        <a href="/defter">ziyaretçi defterine</a> bir not bırakabilirsiniz!
    </div>
</article>

<?php include __DIR__ . '/includes/footer.php'; ?>
