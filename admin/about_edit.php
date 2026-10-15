<?php
/**
 * Hakkımda Sayfası Düzenleyici (About Page & Blogroll Editor)
 */
define('IN_ADMIN', true);
define('NO_SIDEBAR', true);
require_once __DIR__ . '/../config/init.php';

require_admin();

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_about'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen sayfayı yenileyin.';
    } else {
        $about_enabled = isset($_POST['about_enabled']) ? '1' : '0';
        $webmaster_name = trim($_POST['webmaster_name'] ?? '');
        $about_text = trim($_POST['about_text'] ?? '');
        $about_interests = trim($_POST['about_interests'] ?? '');
        $blogroll_raw = trim($_POST['blogroll_raw'] ?? '');

        // Blogroll ayrıştırma (Başlık | URL)
        $links = [];
        $lines = explode("\n", str_replace("\r", "", $blogroll_raw));
        foreach ($lines as $line) {
            $parts = explode('|', $line, 2);
            if (count($parts) === 2) {
                $t = trim($parts[0]);
                $u = trim($parts[1]);
                if ($t !== '' && $u !== '') {
                    $links[] = ['title' => $t, 'url' => $u];
                }
            }
        }

        set_setting('about_enabled', $about_enabled);
        set_setting('webmaster_name', $webmaster_name);
        set_setting('about_text', $about_text);
        set_setting('about_interests', $about_interests);
        set_setting('blogroll_links', json_encode($links, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Yönetici display_name'i de güncelle
        $upd = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
        $upd->execute([$webmaster_name, $_SESSION['admin_user_id']]);
        $_SESSION['admin_display_name'] = $webmaster_name;

        $msg = 'Hakkımda sayfası ve dost bağlantılar başarıyla güncellendi!';
    }
}

// Mevcut değerler
$about_enabled = get_setting('about_enabled', '1');
$webmaster_name = get_setting('webmaster_name', 'nobody');
$about_text = get_setting('about_text', '');
$about_interests = get_setting('about_interests', "Müzik: Radiohead, Pink Floyd, Depeche Mode, Duman\nKitaplar: Bilim Kurgu, Felsefe, Otostopçunun Galaksi Rehberi\nİşletim Sistemi: GNU/Linux & Windows XP Nostaljisi\nTarayıcı: Netscape / Firefox");
$blogroll_json = get_setting('blogroll_links', '[]');
$blogroll_arr = json_decode($blogroll_json, true) ?: [];

$blogroll_lines = [];
foreach ($blogroll_arr as $item) {
    $blogroll_lines[] = ($item['title'] ?? '') . ' | ' . ($item['url'] ?? '');
}
$blogroll_raw = implode("\n", $blogroll_lines);

$page_title = 'Hakkımda Sayfasını Düzenle';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-family: Georgia, serif; font-size: 19px; margin-bottom: 4px;">
        Hakkımda Sayfasını Düzenle
    </h2>
    <p style="font-size: 12px; color: #555;">
        Ziyaretçilerinizin sizi tanıyacağı biyografinizi, ilgi alanlarınızı ve dost siteleri (blogroll) buradan düzenleyebilirsiniz.
    </p>
</div>

<?php if ($msg !== ''): ?>
    <div class="alert alert-success">
        <?= e($msg) ?> &nbsp;
        <a href="/hakkimda" target="_blank">[Hakkımda Sayfasını Gör ↗]</a>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form action="about_edit.php" method="POST" style="background-color: #f8f7f2; border: 1px solid #ccc; padding: 18px;">
    <?= csrf_field() ?>

    <!-- Yayında Tut / Pasife Al Kutusu -->
    <div style="background-color: #fff9e6; border: 1px solid #e0c870; padding: 10px 14px; margin-bottom: 16px;">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
            <input type="checkbox" name="about_enabled" value="1" <?= $about_enabled === '1' ? 'checked' : '' ?>>
            <span><strong>Hakkımda Sayfasını Yayında Tut</strong> (İşareti kaldırırsanız sayfa ziyaretçilere kapanır, sadece siz taslak olarak görebilirsiniz)</span>
        </label>
    </div>

    <div class="form-group" style="max-width: 320px;">
        <label for="webmaster_name">Adınız veya Takma Adınız (Webmaster):</label>
        <input type="text" id="webmaster_name" name="webmaster_name" required value="<?= e($webmaster_name) ?>">
    </div>

    <div class="form-group">
        <label for="about_text">Hakkımda / Biyografi Metni:</label>
        <div style="font-size: 11px; color: #666; margin-bottom: 4px;">
            Kimsiniz, neler yaparsınız, bu blogu neden açtınız? (Satır sonları otomatik paragrafa dönüşür).
        </div>
        <textarea id="about_text" name="about_text" rows="8" required><?= e($about_text) ?></textarea>
    </div>

    <div class="form-group">
        <label for="about_interests">İlgi Alanları &amp; Sevdiğim Şeyler (Her satıra bir başlık veya liste):</label>
        <div style="font-size: 11px; color: #666; margin-bottom: 4px;">
            Örnek: Dinlediğim müzikler, okuduğum kitaplar, kullandığım araçlar...
        </div>
        <textarea id="about_interests" name="about_interests" rows="5"><?= e($about_interests) ?></textarea>
    </div>

    <div class="form-group">
        <label for="blogroll_raw">Dost Siteler / Blogroll &amp; Ağ Halkası (Başlık | URL):</label>
        <div style="font-size: 11px; font-family: monospace; color: #666; margin-bottom: 4px;">
            Her satıra bir site yazın. Biçim: <code>Site Adı | https://ornek.com</code>
        </div>
        <textarea id="blogroll_raw" name="blogroll_raw" rows="6" style="font-family: monospace; font-size: 11px;"><?= e($blogroll_raw) ?></textarea>
    </div>

    <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
        <button type="submit" name="save_about" class="btn" style="padding: 6px 18px; font-size: 13px;">
            Hakkımda Bilgilerini Kaydet &raquo;
        </button>
        <a href="/hakkimda" target="_blank">[Sayfayı Canlı Önizle ↗]</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
