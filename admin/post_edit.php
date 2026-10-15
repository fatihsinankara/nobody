<?php
/**
 * Yeni Yazı Ekle / Düzenle (Create / Edit Post)
 */
define('IN_ADMIN', true);
define('NO_SIDEBAR', true);
require_once __DIR__ . '/../config/init.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
$post = null;
$is_edit = false;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if ($post) {
        $is_edit = true;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen formu tekrar gönderin.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $summary = trim($_POST['summary'] ?? '');
        $content = trim($_POST['content'] ?? '');

        // Kategori adını bul
        $selectedCat = get_category_by_id($category_id);
        $category_name = $selectedCat['name'] ?? 'Genel';
        if (!$selectedCat) {
            $genel = $pdo->query("SELECT id FROM categories WHERE slug = 'genel' LIMIT 1")->fetch();
            $category_id = $genel['id'] ?? 1;
        }

        if (empty($title)) {
            $error = 'Yazı başlığı boş bırakılamaz.';
        } elseif (empty($content)) {
            $error = 'Yazı içeriği boş bırakılamaz.';
        } else {
            if (empty($slug)) {
                $slug = slugify($title);
            } else {
                $slug = slugify($slug);
            }

            // Slug çakışma kontrolü
            $check_sql = "SELECT COUNT(*) FROM posts WHERE slug = ?" . ($is_edit ? " AND id != ?" : "");
            $check_params = $is_edit ? [$slug, $id] : [$slug];
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute($check_params);

            if ((int)$check_stmt->fetchColumn() > 0) {
                // Slug sonuna benzersiz sayı ekle
                $slug .= '-' . rand(10, 99);
            }

            if ($is_edit) {
                $upd = $pdo->prepare("UPDATE posts SET title = ?, slug = ?, category_id = ?, category = ?, summary = ?, content = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$title, $slug, $category_id, $category_name, $summary, $content, $id]);
                $success = 'Yazı başarıyla güncellendi!';
                // Güncel veriyi yeniden çek
                $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
                $stmt->execute([$id]);
                $post = $stmt->fetch();
            } else {
                $ins = $pdo->prepare("INSERT INTO posts (title, slug, category_id, category, summary, content, views, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())");
                $ins->execute([$title, $slug, $category_id, $category_name, $summary, $content]);
                $new_id = (int)$pdo->lastInsertId();
                header("Location: /admin/post_edit.php?id=$new_id&created=1");
                exit;
            }
        }
    }
}

if (isset($_GET['created'])) {
    $success = 'Yeni blog yazısı başarıyla yayınlandı!';
}

$all_categories = get_all_categories();

$page_title = $is_edit ? 'Yazıyı Düzenle: ' . $post['title'] : 'Yeni Yazı Ekle';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-family: Georgia, serif; font-size: 19px; margin-bottom: 4px;">
        <?= $is_edit ? 'Yazıyı Düzenle' : 'Yeni Blog Yazısı Yaz' ?>
    </h2>
    <p style="font-size: 12px; color: #555;">
        Düşüncelerinizi, kodlarınızı veya anılarınızı saf metinle yazın.
    </p>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success">
        <?= e($success) ?>
        <?php if ($is_edit): ?>
            &nbsp; <a href="<?= post_url($post['slug']) ?>" target="_blank">[Yazıyı Görüntüle ↗]</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form action="/admin/post_edit.php<?= $is_edit ? '?id=' . $post['id'] : '' ?>" method="POST" style="background-color: #f8f7f2; border: 1px solid #ccc; padding: 16px;">
    <?= csrf_field() ?>

    <div class="form-group">
        <label for="title">Yazı Başlığı: *</label>
        <input type="text" id="title" name="title" required value="<?= isset($_POST['title']) ? e($_POST['title']) : ($post['title'] ?? '') ?>" placeholder="örn. Gece Yarısı Kodlama Günlükleri">
    </div>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <div class="form-group" style="flex: 2; min-width: 220px;">
            <label for="slug">Modern URL Slug (İsteğe bağlı, boş bırakılırsa başlıktan üretilir):</label>
            <input type="text" id="slug" name="slug" value="<?= isset($_POST['slug']) ? e($_POST['slug']) : ($post['slug'] ?? '') ?>" placeholder="gece-yarisi-kodlama-gunlukleri">
        </div>

        <div class="form-group" style="flex: 1; min-width: 170px;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 3px;">
                <label for="category_id" style="margin: 0;">Kategori: *</label>
                <a href="/admin/categories.php" target="_blank" style="font-size: 11px;">[+ Kategori Yönet]</a>
            </div>
            <select id="category_id" name="category_id" required style="width: 100%; padding: 6px 8px; font-size: 12px; background: #fff; border: 1px solid #7f9db9;">
                <?php foreach ($all_categories as $cat): ?>
                    <?php
                    $isSelected = false;
                    if (isset($_POST['category_id'])) {
                        $isSelected = ((int)$_POST['category_id'] === (int)$cat['id']);
                    } elseif (isset($post['category_id']) && $post['category_id']) {
                        $isSelected = ((int)$post['category_id'] === (int)$cat['id']);
                    } elseif (isset($post['category'])) {
                        $isSelected = ($post['category'] === $cat['name']);
                    }
                    ?>
                    <option value="<?= $cat['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="summary">Özet (Ana sayfada görünecek kısa giriş metni, isteğe bağlı):</label>
        <textarea id="summary" name="summary" rows="2" placeholder="Yazının 1-2 cümlelik kısa özeti..."><?= isset($_POST['summary']) ? e($_POST['summary']) : ($post['summary'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="content">Yazı İçeriği: *</label>
        <div style="font-size: 11px; font-family: monospace; color: #555; margin-bottom: 4px;">
            Biçimlendirme İpuçları: <strong>**kalın**</strong>, <em>*italik*</em>, <code>`kod`</code> veya <code>[code]blok kod[/code]</code>. Paragraflar için Enter ile satır atlamanız yeterlidir.
        </div>
        <textarea id="content" name="content" rows="16" required style="font-family: 'Courier New', Courier, monospace; font-size: 13px;"><?= isset($_POST['content']) ? e($_POST['content']) : ($post['content'] ?? '') ?></textarea>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
        <button type="submit" class="btn" style="padding: 6px 18px; font-size: 13px;">
            <?= $is_edit ? 'Değişiklikleri Kaydet &raquo;' : 'Yazıyı Yayınla &raquo;' ?>
        </button>
        <a href="/admin/index.php">[İptal ve Geri Dön]</a>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
