<?php
/**
 * Çevrimiçi CV / Özgeçmiş Yönetimi (CV / Resume Editor)
 */
define('IN_ADMIN', true);
define('NO_SIDEBAR', true);
require_once __DIR__ . '/../config/init.php';

require_admin();

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cv'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $cv_enabled = isset($_POST['cv_enabled']) ? '1' : '0';
        $cv_fullname = input('cv_fullname', 100);
        $cv_headline = input('cv_headline', 150);
        $cv_location = input('cv_location', 100);
        $cv_email = input('cv_email', 120);
        $cv_website = safe_url(input('cv_website', 200));
        $cv_summary = input('cv_summary');

        // 1. İş Deneyimleri Ayrıştırma
        $experiences = [];
        if (isset($_POST['exp']) && is_array($_POST['exp'])) {
            foreach ($_POST['exp'] as $item) {
                if (!is_array($item)) continue;
                $period = trim((string)($item['period'] ?? ''));
                $title = trim((string)($item['title'] ?? ''));
                $company = trim((string)($item['company'] ?? ''));
                $desc = trim((string)($item['description'] ?? ''));
                if ($title !== '' || $company !== '') {
                    $experiences[] = [
                        'period'      => $period,
                        'title'       => $title,
                        'company'     => $company,
                        'description' => $desc
                    ];
                }
            }
        }

        // 2. Eğitim Bilgileri Ayrıştırma
        $educations = [];
        if (isset($_POST['edu']) && is_array($_POST['edu'])) {
            foreach ($_POST['edu'] as $item) {
                if (!is_array($item)) continue;
                $period = trim((string)($item['period'] ?? ''));
                $degree = trim((string)($item['degree'] ?? ''));
                $school = trim((string)($item['school'] ?? ''));
                $desc = trim((string)($item['description'] ?? ''));
                if ($degree !== '' || $school !== '') {
                    $educations[] = [
                        'period'      => $period,
                        'degree'      => $degree,
                        'school'      => $school,
                        'description' => $desc
                    ];
                }
            }
        }

        // 3. Beceriler Ayrıştırma
        $skills = [];
        if (isset($_POST['skill']) && is_array($_POST['skill'])) {
            foreach ($_POST['skill'] as $item) {
                if (!is_array($item)) continue;
                $cat = trim((string)($item['category'] ?? ''));
                $items = trim((string)($item['items'] ?? ''));
                if ($cat !== '' || $items !== '') {
                    $skills[] = [
                        'category' => $cat,
                        'items'    => $items
                    ];
                }
            }
        }

        // 4. Projeler Ayrıştırma
        $projects = [];
        if (isset($_POST['proj']) && is_array($_POST['proj'])) {
            foreach ($_POST['proj'] as $item) {
                if (!is_array($item)) continue;
                $p_title = trim((string)($item['title'] ?? ''));
                $p_url = safe_url(trim((string)($item['url'] ?? '')));
                $p_desc = trim((string)($item['description'] ?? ''));
                if ($p_title !== '') {
                    $projects[] = [
                        'title'       => $p_title,
                        'url'         => $p_url,
                        'description' => $p_desc
                    ];
                }
            }
        }

        set_setting('cv_enabled', $cv_enabled);
        set_setting('cv_fullname', $cv_fullname);
        set_setting('cv_headline', $cv_headline);
        set_setting('cv_location', $cv_location);
        set_setting('cv_email', $cv_email);
        set_setting('cv_website', $cv_website);
        set_setting('cv_summary', $cv_summary);
        set_setting('cv_experience', json_encode($experiences, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        set_setting('cv_education', json_encode($educations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        set_setting('cv_skills', json_encode($skills, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        set_setting('cv_projects', json_encode($projects, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $msg = 'CV / Özgeçmiş bilgileri başarıyla güncellendi!';
    }
}

// Mevcut Ayarları Çek
$cv_enabled = get_setting('cv_enabled', '1');
$cv_fullname = get_setting('cv_fullname', 'nobody');
$cv_headline = get_setting('cv_headline', 'Yazılım Geliştirici');
$cv_location = get_setting('cv_location', 'İstanbul, Türkiye');
$cv_email = get_setting('cv_email', 'nobody@example.com');
$cv_website = get_setting('cv_website', 'https://example.com');
$cv_summary = get_setting('cv_summary', '');

$experiences = json_decode(get_setting('cv_experience', '[]'), true) ?: [];
$educations = json_decode(get_setting('cv_education', '[]'), true) ?: [];
$skills = json_decode(get_setting('cv_skills', '[]'), true) ?: [];
$projects = json_decode(get_setting('cv_projects', '[]'), true) ?: [];

// Kolay ekleme için listelerin sonuna birer boş girdi ekleyelim
$experiences[] = ['period' => '', 'title' => '', 'company' => '', 'description' => ''];
$educations[] = ['period' => '', 'degree' => '', 'school' => '', 'description' => ''];
$skills[] = ['category' => '', 'items' => ''];
$projects[] = ['title' => '', 'url' => '', 'description' => ''];

$page_title = 'CV / Özgeçmiş Yönetimi';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-family: Georgia, serif; font-size: 19px; margin-bottom: 4px;">
        Çevrimiçi CV / Özgeçmiş Yönetimi
    </h2>
    <p style="font-size: 12px; color: #555;">
        Kariyer notlarınızı, iş tecrübelerinizi, eğitiminizi ve becerilerinizi buradan düzenleyebilirsiniz.
    </p>
</div>

<?php if ($msg !== ''): ?>
    <div class="alert alert-success">
        <?= e($msg) ?> &nbsp;
        <a href="/cv" target="_blank">[CV Sayfasını Canlı Önizle ↗]</a>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form action="/admin/cv_edit.php" method="POST" style="background-color: #f8f7f2; border: 1px solid #ccc; padding: 18px;">
    <?= csrf_field() ?>

    <!-- 1. Yayın Durumu (Etkinleştirme / Devre Dışı Bırakma) -->
    <div style="background-color: #f0ede3; border: 1px solid #c0bcae; padding: 12px; margin-bottom: 20px;">
        <label style="font-size: 13px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <input type="checkbox" name="cv_enabled" value="1" <?= $cv_enabled === '1' ? 'checked' : '' ?>>
            <span>CV Sayfasını Sitede Yayınla (Menüde ve <code>/cv</code> adresinde ziyaretçilere açık olsun)</span>
        </label>
        <div style="font-size: 11px; color: #666; margin-top: 4px; margin-left: 24px;">
            İşaret kaldırılırsa CV sayfası ziyaretçilere gizlenir (yalnızca yönetici oturumunda taslak olarak görüntülenebilir).
        </div>
    </div>

    <!-- 2. Temel Kişisel ve İletişim Bilgileri -->
    <h3 style="font-family: Georgia, serif; font-size: 15px; border-bottom: 1px solid #bbb; padding-bottom: 4px; margin-bottom: 12px; color: #003366;">
        Temel Bilgiler &amp; İletişim
    </h3>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; min-width: 180px;">
            <label for="cv_fullname">Ad Soyad / Unvan Adı:</label>
            <input type="text" id="cv_fullname" name="cv_fullname" required value="<?= e($cv_fullname) ?>">
        </div>
        <div class="form-group" style="flex: 2; min-width: 220px;">
            <label for="cv_headline">Mesleki Unvan / Başlık:</label>
            <input type="text" id="cv_headline" name="cv_headline" required value="<?= e($cv_headline) ?>" placeholder="örn. Kıdemli Backend Geliştirici &amp; Sistem Mimarı">
        </div>
    </div>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="cv_location">Konum / Şehir:</label>
            <input type="text" id="cv_location" name="cv_location" value="<?= e($cv_location) ?>" placeholder="İstanbul, Türkiye">
        </div>
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="cv_email">İletişim E-posta:</label>
            <input type="email" id="cv_email" name="cv_email" value="<?= e($cv_email) ?>">
        </div>
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label for="cv_website">Web Sitesi / Portfolyo / GitHub:</label>
            <input type="url" id="cv_website" name="cv_website" value="<?= e($cv_website) ?>" placeholder="https://...">
        </div>
    </div>

    <div class="form-group">
        <label for="cv_summary">Profesyonel Özet / Tanıtım Yazısı:</label>
        <textarea id="cv_summary" name="cv_summary" rows="4"><?= e($cv_summary) ?></textarea>
    </div>

    <!-- 3. İş Deneyimleri -->
    <h3 style="font-family: Georgia, serif; font-size: 15px; border-bottom: 1px solid #bbb; padding-bottom: 4px; margin: 24px 0 12px 0; color: #003366;">
        İş ve Kariyer Deneyimleri
    </h3>

    <?php foreach ($experiences as $i => $exp): ?>
        <div style="background: #ffffff; border: 1px solid #ddd; padding: 12px; margin-bottom: 12px;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 6px;">
                <div style="width: 160px;">
                    <label style="font-size: 11px;">Dönem / Yıl:</label>
                    <input type="text" name="exp[<?= $i ?>][period]" value="<?= e($exp['period']) ?>" placeholder="2022 - Günümüz">
                </div>
                <div style="flex: 1; min-width: 160px;">
                    <label style="font-size: 11px;">Pozisyon / Unvan:</label>
                    <input type="text" name="exp[<?= $i ?>][title]" value="<?= e($exp['title']) ?>" placeholder="Kıdemli Geliştirici">
                </div>
                <div style="flex: 1; min-width: 160px;">
                    <label style="font-size: 11px;">Şirket / Kurum:</label>
                    <input type="text" name="exp[<?= $i ?>][company]" value="<?= e($exp['company']) ?>" placeholder="Şirket Adı">
                </div>
            </div>
            <div>
                <label style="font-size: 11px;">Açıklama / Sorumluluklar:</label>
                <textarea name="exp[<?= $i ?>][description]" rows="2" style="font-size: 12px;"><?= e($exp['description']) ?></textarea>
            </div>
        </div>
    <?php endforeach; ?>
    <div style="font-size: 11px; color: #777; margin-bottom: 15px;">* Boş bıraktığınız deneyim satırları kaydedilmez. Yeni eklemek için en alttaki boş kutuyu doldurun.</div>

    <!-- 4. Eğitim -->
    <h3 style="font-family: Georgia, serif; font-size: 15px; border-bottom: 1px solid #bbb; padding-bottom: 4px; margin: 24px 0 12px 0; color: #003366;">
        Eğitim Bilgileri
    </h3>

    <?php foreach ($educations as $i => $edu): ?>
        <div style="background: #ffffff; border: 1px solid #ddd; padding: 12px; margin-bottom: 12px;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 6px;">
                <div style="width: 160px;">
                    <label style="font-size: 11px;">Dönem / Yıl:</label>
                    <input type="text" name="edu[<?= $i ?>][period]" value="<?= e($edu['period']) ?>" placeholder="2015 - 2019">
                </div>
                <div style="flex: 1; min-width: 160px;">
                    <label style="font-size: 11px;">Derece / Bölüm:</label>
                    <input type="text" name="edu[<?= $i ?>][degree]" value="<?= e($edu['degree']) ?>" placeholder="Bilgisayar Mühendisliği">
                </div>
                <div style="flex: 1; min-width: 160px;">
                    <label style="font-size: 11px;">Okul / Üniversite:</label>
                    <input type="text" name="edu[<?= $i ?>][school]" value="<?= e($edu['school']) ?>" placeholder="Üniversite Adı">
                </div>
            </div>
            <div>
                <label style="font-size: 11px;">Notlar / Başarılar (İsteğe bağlı):</label>
                <input type="text" name="edu[<?= $i ?>][description]" value="<?= e($edu['description']) ?>" placeholder="Bitirme tezi veya başarı notu...">
            </div>
        </div>
    <?php endforeach; ?>

    <!-- 5. Beceriler & Uzmanlıklar -->
    <h3 style="font-family: Georgia, serif; font-size: 15px; border-bottom: 1px solid #bbb; padding-bottom: 4px; margin: 24px 0 12px 0; color: #003366;">
        Beceriler &amp; Yetkinlikler
    </h3>

    <?php foreach ($skills as $i => $sk): ?>
        <div style="display: flex; gap: 8px; margin-bottom: 8px; background: #fff; border: 1px solid #ddd; padding: 8px;">
            <div style="width: 200px;">
                <label style="font-size: 11px;">Kategori:</label>
                <input type="text" name="skill[<?= $i ?>][category]" value="<?= e($sk['category']) ?>" placeholder="örn. Backend Dilleri">
            </div>
            <div style="flex: 1;">
                <label style="font-size: 11px;">Beceriler (Virgülle ayırın):</label>
                <input type="text" name="skill[<?= $i ?>][items]" value="<?= e($sk['items']) ?>" placeholder="PHP, MySQL, Linux, Git...">
            </div>
        </div>
    <?php endforeach; ?>

    <!-- 6. Önemli Projeler -->
    <h3 style="font-family: Georgia, serif; font-size: 15px; border-bottom: 1px solid #bbb; padding-bottom: 4px; margin: 24px 0 12px 0; color: #003366;">
        Öne Çıkan Projeler &amp; Çalışmalar
    </h3>

    <?php foreach ($projects as $i => $proj): ?>
        <div style="background: #ffffff; border: 1px solid #ddd; padding: 12px; margin-bottom: 12px;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 6px;">
                <div style="flex: 1; min-width: 180px;">
                    <label style="font-size: 11px;">Proje Adı:</label>
                    <input type="text" name="proj[<?= $i ?>][title]" value="<?= e($proj['title']) ?>" placeholder="Proje Başlığı">
                </div>
                <div style="flex: 1; min-width: 180px;">
                    <label style="font-size: 11px;">Proje Bağlantısı (URL):</label>
                    <input type="url" name="proj[<?= $i ?>][url]" value="<?= e($proj['url']) ?>" placeholder="https://...">
                </div>
            </div>
            <div>
                <label style="font-size: 11px;">Açıklama / Kullanılan Teknolojiler:</label>
                <textarea name="proj[<?= $i ?>][description]" rows="2" style="font-size: 12px;"><?= e($proj['description']) ?></textarea>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
        <button type="submit" name="save_cv" class="btn" style="padding: 7px 22px; font-size: 13px;">
            Tüm CV Bilgilerini Kaydet &raquo;
        </button>
        <a href="/cv" target="_blank">[CV Sayfasını Görüntüle ↗]</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
