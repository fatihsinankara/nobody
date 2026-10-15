<?php
/**
 * Çevrimiçi CV / Özgeçmiş Sayfası (Online CV / Resume)
 */
require_once __DIR__ . '/config/init.php';

$cv_enabled = get_setting('cv_enabled', '1');
$is_admin = is_admin_logged_in();

// CV devre dışıysa ve ziyaretçi admin değilse erişimi engelle
if ($cv_enabled !== '1' && !$is_admin) {
    http_response_code(404);
    $page_title = 'Özgeçmiş Yayında Değil';
    include __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-error"><strong>Bilgi:</strong> Özgeçmiş (CV) sayfası şu anda yayında değildir.</div>';
    echo '<p><a href="/">&laquo; Ana sayfaya dön</a></p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$cv_fullname = get_setting('cv_fullname', 'nobody');
$cv_headline = get_setting('cv_headline', 'Yazılım Geliştirici');
$cv_location = get_setting('cv_location', '');
$cv_email = get_setting('cv_email', '');
$cv_website = get_setting('cv_website', '');
$cv_summary = get_setting('cv_summary', '');

$experiences = json_decode(get_setting('cv_experience', '[]'), true) ?: [];
$educations = json_decode(get_setting('cv_education', '[]'), true) ?: [];
$skills = json_decode(get_setting('cv_skills', '[]'), true) ?: [];
$projects = json_decode(get_setting('cv_projects', '[]'), true) ?: [];

$page_title = 'Özgeçmiş ~ ' . $cv_fullname;
include __DIR__ . '/includes/header.php';
?>

<?php if ($cv_enabled !== '1' && $is_admin): ?>
    <div class="alert alert-error" style="background:#fff3cd; border-color:#ffeeba; color:#856404; margin-bottom:18px;">
        <strong>[!] Taslak Modu:</strong> Bu CV sayfası şu anda yayında değildir ve ziyaretçilere kapalıdır. Yalnızca siz (yönetici) görüntüleyebiliyorsunuz. 
        Yayınlamak için <a href="/admin/settings.php"><strong>Site Ayarları</strong></a> veya <a href="/admin/cv_edit.php"><strong>CV Düzenleme Masası</strong></a>'ndan etkinleştirebilirsiniz.
    </div>
<?php endif; ?>

<div class="cv-document">

    <!-- Üst Başlık ve İletişim Bilgileri -->
    <div class="cv-header" style="border-bottom: 2px solid #555; padding-bottom: 12px; margin-bottom: 18px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
            <div>
                <h1 style="font-family: Georgia, serif; font-size: 24px; color: #111; margin-bottom: 4px;">
                    <?= e($cv_fullname) ?>
                </h1>
                <div style="font-size: 14px; font-weight: bold; color: #003366; margin-bottom: 8px;">
                    <?= e($cv_headline) ?>
                </div>
            </div>

            <!-- Eylemler (Yazdır & Düzenle) -->
            <div class="no-print" style="display: flex; gap: 8px;">
                <button type="button" data-action="print" class="btn btn-sm" title="Yazdır veya PDF Olarak Kaydet">
                    [🖨 Yazdır / PDF]
                </button>
                <?php if ($is_admin): ?>
                    <a href="/admin/cv_edit.php" class="btn btn-sm">[✎ CV Düzenle]</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- İletişim Şeridi -->
        <div style="font-family: 'Courier New', monospace; font-size: 11px; color: #444; display: flex; flex-wrap: wrap; gap: 14px;">
            <?php if (!empty($cv_location)): ?>
                <span><strong>Konum:</strong> <?= e($cv_location) ?></span>
            <?php endif; ?>
            <?php if (!empty($cv_email)): ?>
                <span><strong>E-posta:</strong> <a href="mailto:<?= e($cv_email) ?>"><?= e($cv_email) ?></a></span>
            <?php endif; ?>
            <?php if (!empty($cv_website) && safe_url($cv_website)): ?>
                <span><strong>Web:</strong> <a href="<?= e(safe_url($cv_website)) ?>" target="_blank" rel="noopener noreferrer"><?= e($cv_website) ?></a></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profesyonel Özet -->
    <?php if (!empty($cv_summary)): ?>
        <section class="cv-section" style="margin-bottom: 20px;">
            <h2 class="cv-section-title">
                Özet &amp; Profil
            </h2>
            <div style="font-size: 13px; line-height: 1.7; color: #222;">
                <?= render_content($cv_summary) ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- İş Deneyimleri -->
    <?php if (!empty($experiences)): ?>
        <section class="cv-section" style="margin-bottom: 20px;">
            <h2 class="cv-section-title">
                İş ve Kariyer Deneyimleri
            </h2>

            <?php foreach ($experiences as $exp): ?>
                <div style="margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px dotted #ccc;">
                    <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 2px;">
                        <div>
                            <strong style="font-size: 13px; color: #111;"><?= e($exp['title']) ?></strong>
                            <?php if (!empty($exp['company'])): ?>
                                &mdash; <span style="font-weight: bold; color: #004488;"><?= e($exp['company']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($exp['period'])): ?>
                            <span style="font-family: 'Courier New', monospace; font-size: 11px; color: #555;">
                                [<?= e($exp['period']) ?>]
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($exp['description'])): ?>
                        <div style="font-size: 12px; line-height: 1.6; color: #333; margin-top: 4px;">
                            <?= render_content($exp['description']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- Eğitim Bilgileri -->
    <?php if (!empty($educations)): ?>
        <section class="cv-section" style="margin-bottom: 20px;">
            <h2 class="cv-section-title">
                Eğitim
            </h2>

            <?php foreach ($educations as $edu): ?>
                <div style="margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px dotted #ccc;">
                    <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap;">
                        <div>
                            <strong style="font-size: 13px; color: #111;"><?= e($edu['degree']) ?></strong>
                            <?php if (!empty($edu['school'])): ?>
                                &bull; <span style="color: #004488;"><?= e($edu['school']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($edu['period'])): ?>
                            <span style="font-family: 'Courier New', monospace; font-size: 11px; color: #555;">
                                [<?= e($edu['period']) ?>]
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($edu['description'])): ?>
                        <div style="font-size: 12px; color: #555; margin-top: 3px;">
                            <?= e($edu['description']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- Beceriler & Yetkinlikler -->
    <?php if (!empty($skills)): ?>
        <section class="cv-section" style="margin-bottom: 20px;">
            <h2 class="cv-section-title">
                Beceriler &amp; Uzmanlıklar
            </h2>

            <table class="admin-table" style="margin-top: 6px;">
                <tbody>
                    <?php foreach ($skills as $sk): ?>
                        <tr>
                            <td style="width: 170px; font-weight: bold; background-color: #f7f6f1;">
                                <?= e($sk['category']) ?>
                            </td>
                            <td style="font-size: 12px;">
                                <?= e($sk['items']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <!-- Öne Çıkan Projeler -->
    <?php if (!empty($projects)): ?>
        <section class="cv-section" style="margin-bottom: 20px;">
            <h2 class="cv-section-title">
                Öne Çıkan Projeler &amp; Açık Kaynak
            </h2>

            <?php foreach ($projects as $proj): ?>
                <div style="margin-bottom: 12px;">
                    <div>
                        <strong style="font-size: 13px; color: #111;"><?= e($proj['title']) ?></strong>
                        <?php 
                        $p_url = safe_url($proj['url'] ?? '');
                        if (!empty($p_url)): 
                        ?>
                            &mdash; <a href="<?= e($p_url) ?>" target="_blank" rel="noopener noreferrer" style="font-size: 11px; font-family: monospace;">[<?= e($proj['url']) ?> ↗]</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($proj['description'])): ?>
                        <div style="font-size: 12px; color: #444; line-height: 1.5; margin-top: 2px;">
                            <?= render_content($proj['description']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</div>

<style>
.cv-section-title {
    font-family: Georgia, serif;
    font-size: 15px;
    font-weight: bold;
    color: #222;
    border-bottom: 1px solid #999;
    padding-bottom: 3px;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
