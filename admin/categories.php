<?php
/**
 * Dinamik Kategori Yönetimi (Category Management)
 */
define('IN_ADMIN', true);
define('NO_SIDEBAR', true);
require_once __DIR__ . '/../config/init.php';

require_admin();

$msg = '';
$error = '';

$edit_id = (int)($_GET['edit'] ?? 0);
$edit_category = null;
if ($edit_id > 0) {
    $edit_category = get_category_by_id($edit_id);
}

// Yeni Kategori Ekleme / Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $cat_id = (int)($_POST['cat_id'] ?? 0);

        if (empty($name)) {
            $error = 'Kategori adı boş bırakılamaz.';
        } else {
            if (empty($slug)) {
                $slug = slugify($name);
            } else {
                $slug = slugify($slug);
            }

            // Slug çakışma kontrolü
            $check_sql = "SELECT COUNT(*) FROM categories WHERE slug = ?" . ($cat_id > 0 ? " AND id != ?" : "");
            $check_params = $cat_id > 0 ? [$slug, $cat_id] : [$slug];
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute($check_params);

            if ((int)$check_stmt->fetchColumn() > 0) {
                $slug .= '-' . rand(10, 99);
            }

            if ($cat_id > 0) {
                $old = get_category_by_id($cat_id);
                $upd = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?");
                $upd->execute([$name, $slug, $description, $cat_id]);

                // posts tablosundaki category metnini de güncelle
                if ($old) {
                    $pdo->prepare("UPDATE posts SET category = ? WHERE category_id = ? OR category = ?")->execute([$name, $cat_id, $old['name']]);
                }

                $msg = 'Kategori başarıyla güncellendi!';
                $edit_category = null;
                $edit_id = 0;
            } else {
                // İsim çakışma kontrolü
                $check_name = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
                $check_name->execute([$name]);
                if ((int)$check_name->fetchColumn() > 0) {
                    $error = 'Bu isimde bir kategori zaten mevcut.';
                } else {
                    $ins = $pdo->prepare("INSERT INTO categories (name, slug, description, created_at) VALUES (?, ?, ?, NOW())");
                    $ins->execute([$name, $slug, $description]);
                    $msg = 'Yeni kategori başarıyla eklendi!';
                }
            }
        }
    }
}

// Kategori Silme (CSRF korumalı)
if (isset($_GET['delete'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        $error = 'Güvenlik hatası: Silme isteği doğrulanamadı.';
    } else {
        $del_id = (int)$_GET['delete'];
        $catToDelete = get_category_by_id($del_id);

        if ($catToDelete) {
            if (mb_strtolower($catToDelete['name']) === 'genel' || mb_strtolower($catToDelete['slug']) === 'genel') {
                $error = "'Genel' varsayılan kategorisi silinemez.";
            } else {
                // Genel kategorisini bul veya oluştur
                $genel = $pdo->query("SELECT id, name FROM categories WHERE slug = 'genel' OR name = 'Genel' LIMIT 1")->fetch();
                $genelId = $genel['id'] ?? 1;

                // Bu kategorideki yazıları Genel'e taşı
                $updPosts = $pdo->prepare("UPDATE posts SET category_id = ?, category = 'Genel' WHERE category_id = ? OR category = ?");
                $updPosts->execute([$genelId, $del_id, $catToDelete['name']]);

                // Kategoriyi sil
                $delStmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $delStmt->execute([$del_id]);

                $msg = "Kategori silindi. Bu kategoriye ait yazılar 'Genel' kategorisine aktarıldı.";
            }
        }
    }
}

$categories = get_all_categories();

$page_title = 'Kategori Yönetimi';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-family: Georgia, serif; font-size: 19px; margin-bottom: 4px;">
        Kategori Yönetimi
    </h2>
    <p style="font-size: 12px; color: #555;">
        Blog yazılarınızı gruplandıracağınız kategorileri dinamik olarak oluşturun, düzenleyin veya kaldırın.
    </p>
</div>

<?php if ($msg !== ''): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- Kategori Ekleme / Düzenleme Formu -->
<div style="background-color: #f7f6f0; border: 1px solid #ccc; padding: 16px; margin-bottom: 24px;">
    <h3 style="font-family: Georgia, serif; font-size: 14px; margin-bottom: 10px; color: #003366;">
        <?= $edit_category ? 'Kategoriyi Düzenle: ' . e($edit_category['name']) : '[+] Yeni Kategori Ekle' ?>
    </h3>

    <form action="/admin/categories.php" method="POST">
        <?= csrf_field() ?>
        <?php if ($edit_category): ?>
            <input type="hidden" name="cat_id" value="<?= $edit_category['id'] ?>">
        <?php endif; ?>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 180px;">
                <label for="name">Kategori Adı: *</label>
                <input type="text" id="name" name="name" required value="<?= e($edit_category['name'] ?? '') ?>" placeholder="örn. Donanım &amp; Retro">
            </div>

            <div class="form-group" style="flex: 1; min-width: 180px;">
                <label for="slug">URL Slug (İsteğe bağlı):</label>
                <input type="text" id="slug" name="slug" value="<?= e($edit_category['slug'] ?? '') ?>" placeholder="örn. donanim-retro">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Kısa Açıklama (İsteğe bağlı):</label>
            <input type="text" id="description" name="description" value="<?= e($edit_category['description'] ?? '') ?>" placeholder="Kategori hakkında kısa not...">
        </div>

        <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
            <button type="submit" name="save_category" class="btn">
                <?= $edit_category ? 'Değişiklikleri Kaydet &raquo;' : 'Kategoriyi Oluştur &raquo;' ?>
            </button>
            <?php if ($edit_category): ?>
                <a href="/admin/categories.php">[İptal]</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Mevcut Kategoriler Tablosu -->
<h3 style="font-family: Georgia, serif; font-size: 15px; margin-bottom: 8px;">
    Mevcut Kategoriler (<?= count($categories) ?>)
</h3>

<table class="admin-table">
    <thead>
        <tr>
            <th style="width: 40px;">#</th>
            <th>Kategori Adı</th>
            <th>URL Slug</th>
            <th>Açıklama</th>
            <th style="width: 80px;">Yazı Sayısı</th>
            <th style="width: 120px;">İşlemler</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td style="font-family: monospace;"><?= $cat['id'] ?></td>
                <td>
                    <a href="/kategori/<?= urlencode($cat['slug']) ?>" target="_blank" style="font-weight: bold;">
                        <?= e($cat['name']) ?> ↗
                    </a>
                </td>
                <td style="font-family: monospace; font-size: 11px; color: #555;">
                    /kategori/<?= e($cat['slug']) ?>
                </td>
                <td style="font-size: 11px; color: #666;">
                    <?= e($cat['description'] ?? '') ?>
                </td>
                <td style="font-family: monospace; text-align: right;">
                    <strong><?= (int)$cat['post_count'] ?></strong>
                </td>
                <td>
                    <a href="/admin/categories.php?edit=<?= $cat['id'] ?>" class="btn btn-sm">Düzenle</a>
                    <?php if (mb_strtolower($cat['slug']) !== 'genel'): ?>
                        <a href="/admin/categories.php?delete=<?= $cat['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz? Kategorideki yazılar Genel kategorisine aktarılacaktır.');">Sil</a>
                    <?php else: ?>
                        <span style="font-size: 10px; color: #888;">[Sabit]</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../includes/footer.php'; ?>
