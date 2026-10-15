<?php
/**
 * Site Ayarları & Şifre Değiştirme (Settings & Password)
 */
define('IN_ADMIN', true);
define('NO_SIDEBAR', true);
require_once __DIR__ . '/../config/init.php';

require_admin();

$msg = '';
$error = '';

// Ayarları Kaydetme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu.';
    } else {
        $site_title = input('site_title', 150) ?: 'Kişisel Ağ Günlüğü';
        $site_subtitle = input('site_subtitle', 250);
        $webmaster_name = input('webmaster_name', 100) ?: 'nobody';
        $site_url = safe_url(input('site_url', 255));

        $about_enabled = isset($_POST['about_enabled']) ? '1' : '0';
        $guestbook_enabled = isset($_POST['guestbook_enabled']) ? '1' : '0';
        $cv_enabled = isset($_POST['cv_enabled']) ? '1' : '0';
        $comment_moderation = isset($_POST['comment_moderation']) ? '1' : '0';

        set_setting('site_title', $site_title);
        set_setting('site_subtitle', $site_subtitle);
        set_setting('webmaster_name', $webmaster_name);
        set_setting('site_url', $site_url);
        set_setting('about_enabled', $about_enabled);
        set_setting('guestbook_enabled', $guestbook_enabled);
        set_setting('cv_enabled', $cv_enabled);
        set_setting('comment_moderation', $comment_moderation);

        // Yönetici display_name'i de senkronize et
        $upd = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
        $upd->execute([$webmaster_name, $_SESSION['admin_user_id']]);
        $_SESSION['admin_display_name'] = $webmaster_name;

        $msg = 'Site temel ve modül ayarları başarıyla güncellendi!';
    }
}

// Şifre Değiştirme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu.';
    } else {
        $current_pass = is_string($_POST['current_pass'] ?? null) ? $_POST['current_pass'] : '';
        $new_pass = is_string($_POST['new_pass'] ?? null) ? $_POST['new_pass'] : '';
        $new_pass_confirm = is_string($_POST['new_pass_confirm'] ?? null) ? $_POST['new_pass_confirm'] : '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_pass, $user['password_hash'])) {
            $error = 'Mevcut şifrenizi yanlış girdiniz.';
        } elseif (strlen($new_pass) < 10) {
            $error = 'Yeni şifre en az 10 karakter olmalıdır.';
        } elseif ($new_pass !== $new_pass_confirm) {
            $error = 'Yeni şifreler birbiriyle uyuşmuyor.';
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd->execute([$new_hash, $user['id']]);
            $msg = 'Yönetici şifreniz başarıyla değiştirildi!';
        }
    }
}

// Mevcut ayarları al
$site_title = get_setting('site_title', 'Kişisel Ağ Günlüğü');
$site_subtitle = get_setting('site_subtitle', 'Düşünceler, notlar ve dijital hatıralar...');
$webmaster_name = get_setting('webmaster_name', 'nobody');
$site_url = get_setting('site_url', '');
$about_enabled = get_setting('about_enabled', '1');
$guestbook_enabled = get_setting('guestbook_enabled', '1');
$cv_enabled = get_setting('cv_enabled', '1');
$comment_moderation = get_setting('comment_moderation', '1');

$page_title = 'Site Ayarları';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-family: Georgia, serif; font-size: 19px; margin-bottom: 4px;">
        Site Genel Ayarları
    </h2>
    <p style="font-size: 12px; color: #555;">
        Blog başlığı, modül görünürlükleri (Hakkımda, Defter, CV) ve yönetici şifrenizi buradan düzenleyebilirsiniz.
    </p>
</div>

<?php if ($msg !== ''): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- Genel Ayarlar Formu -->
<form action="/admin/settings.php" method="POST" style="background-color: #f8f7f2; border: 1px solid #ccc; padding: 16px; margin-bottom: 30px;">
    <?= csrf_field() ?>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <div class="form-group" style="flex: 2; min-width: 220px;">
            <label for="site_title">Blog Ana Başlığı:</label>
            <input type="text" id="site_title" name="site_title" required maxlength="150" value="<?= e($site_title) ?>">
        </div>

        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="webmaster_name">Webmaster Adı / Takma Ad:</label>
            <input type="text" id="webmaster_name" name="webmaster_name" required maxlength="100" value="<?= e($webmaster_name) ?>">
        </div>
    </div>

    <div class="form-group">
        <label for="site_subtitle">Blog Alt Başlığı / Slogan:</label>
        <input type="text" id="site_subtitle" name="site_subtitle" maxlength="250" value="<?= e($site_subtitle) ?>">
    </div>

    <div class="form-group">
        <label for="site_url">Canlı Site Adresi (URL - Boş bırakılırsa otomatik algılanır):</label>
        <input type="url" id="site_url" name="site_url" placeholder="https://example.com" value="<?= e($site_url) ?>">
        <div style="font-size: 11px; color: #666; margin-top: 3px;">
            RSS beslemeleri ve mutlak bağlantılar için güvenli taban adresi belirler.
        </div>
    </div>

    <!-- Modül ve Sayfa Görünürlükleri (Aktif / Pasif) -->
    <div style="background-color: #f2efe4; border: 1px solid #cfcbbe; padding: 12px 14px; margin-top: 15px; margin-bottom: 15px;">
        <h4 style="font-family: Georgia, serif; font-size: 14px; margin-bottom: 8px; color: #333;">
            [⚙] Sayfa Görünürlükleri ve Moderasyon
        </h4>
        <div style="font-size: 11px; color: #666; margin-bottom: 12px;">
            Pasif yapılan sayfalar ziyaretçilere kapatılır. Yönetici oturumunuz açıkken sayfaları taslak modunda incelemeye devam edebilirsiniz.
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-size: 12px;">
                <input type="checkbox" name="about_enabled" value="1" <?= $about_enabled === '1' ? 'checked' : '' ?> style="margin-top: 2px;">
                <div>
                    <strong>Hakkımda &amp; Blogroll Sayfası (/hakkimda)</strong>
                    <div style="color: #666; font-size: 11px;">Aktif olduğunda menüde ve kenar çubuğunda görünür.</div>
                </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-size: 12px;">
                <input type="checkbox" name="guestbook_enabled" value="1" <?= $guestbook_enabled === '1' ? 'checked' : '' ?> style="margin-top: 2px;">
                <div>
                    <strong>Ziyaretçi Defteri (/defter)</strong>
                    <div style="color: #666; font-size: 11px;">Aktif olduğunda ziyaretçiler hatıra/selam mesajı yazabilir.</div>
                </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-size: 12px;">
                <input type="checkbox" name="cv_enabled" value="1" <?= $cv_enabled === '1' ? 'checked' : '' ?> style="margin-top: 2px;">
                <div>
                    <strong>CV / Özgeçmiş Sayfası (/cv)</strong>
                    <div style="color: #666; font-size: 11px;">Aktif olduğunda çevrimiçi CV sayfanız menüde ve doğrudan URL'de yayında olur.</div>
                </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; font-size: 12px; border-top: 1px dotted #ccc; padding-top: 8px;">
                <input type="checkbox" name="comment_moderation" value="1" <?= $comment_moderation === '1' ? 'checked' : '' ?> style="margin-top: 2px;">
                <div>
                    <strong>Yorum ve Defter Moderasyonu (Ön Onay)</strong>
                    <div style="color: #666; font-size: 11px;">İşaretliyse, ziyaretçilerin gönderdiği yeni yorum ve hatıra notları yönetici onaylayana kadar yayına girmez (Spam koruması).</div>
                </div>
            </label>
        </div>
    </div>

    <div style="margin-top: 10px;">
        <button type="submit" name="save_settings" class="btn" style="padding: 6px 16px;">Ayarları Kaydet &raquo;</button>
    </div>
</form>

<!-- Şifre Değiştirme Formu -->
<div style="background-color: #f4f2ec; border: 1px solid #bbb; padding: 16px;">
    <h3 style="font-family: Georgia, serif; font-size: 15px; margin-bottom: 8px;">
        [🔑] Webmaster Şifresini Değiştir
    </h3>

    <form action="/admin/settings.php" method="POST" style="max-width: 320px;">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="current_pass">Mevcut Şifre:</label>
            <input type="password" id="current_pass" name="current_pass" required autocomplete="current-password">
        </div>

        <div class="form-group">
            <label for="new_pass">Yeni Şifre (En az 10 karakter):</label>
            <input type="password" id="new_pass" name="new_pass" required minlength="10" autocomplete="new-password">
        </div>

        <div class="form-group">
            <label for="new_pass_confirm">Yeni Şifre (Tekrar):</label>
            <input type="password" id="new_pass_confirm" name="new_pass_confirm" required minlength="10" autocomplete="new-password">
        </div>

        <button type="submit" name="change_password" class="btn">Şifreyi Güncelle &raquo;</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
