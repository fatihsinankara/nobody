<?php
/**
 * Yönetici Ana Paneli (Dashboard & Posts Management)
 */
define('IN_ADMIN', true);
define('NO_SIDEBAR', true);
require_once __DIR__ . '/../config/init.php';

require_admin();

$msg = '';
$error = '';

// Hızlı Ruh Hali / Winamp Güncellemesi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu.';
    } else {
        $mood = trim($_POST['current_mood'] ?? '');
        $playing = trim($_POST['now_playing'] ?? '');
        set_setting('current_mood', $mood);
        set_setting('now_playing', $playing);
        $msg = 'Durum ve Winamp bilgisi başarıyla güncellendi!';
    }
}

// Yazı Silme İşlemi (CSRF korumalı)
if (isset($_GET['delete'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        $error = 'Güvenlik hatası: Silme isteği doğrulanamadı.';
    } else {
        $del_id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$del_id]);
        $pdo->prepare("DELETE FROM comments WHERE post_id = ?")->execute([$del_id]);
        $msg = 'Yazı ve ilgili yorumları başarıyla silindi.';
    }
}

// İstatistikler
$post_count = (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$comment_count = (int)$pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$gb_count = (int)$pdo->query("SELECT COUNT(*) FROM guestbook")->fetchColumn();
$total_views = (int)$pdo->query("SELECT COALESCE(SUM(views), 0) FROM posts")->fetchColumn();

// Tekil Ziyaretçi İstatistikleri
$counter_row = $pdo->query("SELECT total_hits, today_hits FROM hit_counter WHERE id = 1")->fetch();
$total_hits = (int)($counter_row['total_hits'] ?? 0);
$today_hits = (int)($counter_row['today_hits'] ?? 0);

// Yazıları çek
$stmt = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count 
                     FROM posts p 
                     ORDER BY p.created_at DESC");
$posts = $stmt->fetchAll();

$current_mood = get_setting('current_mood', '');
$now_playing = get_setting('now_playing', '');

$page_title = 'Yönetim Paneli';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-family: Georgia, serif; font-size: 19px; margin-bottom: 4px;">
        Webmaster Kontrol Masası
    </h2>
    <p style="font-size: 12px; color: #555;">
        Hoş geldin, <strong><?= e($_SESSION['admin_display_name'] ?? 'Webmaster') ?></strong>! Kişisel ağ günlüğünü buradan yönetebilirsin.
    </p>
</div>

<?php if ($msg !== ''): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- İstatistik Kutucukları -->
<div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 120px; background: #faf9f4; border: 1px solid #ccc; padding: 10px; text-align: center;">
        <div style="font-size: 20px; font-weight: bold; color: #004488;"><?= $post_count ?></div>
        <div style="font-size: 11px; color: #666;">Yazı</div>
    </div>
    <div style="flex: 1; min-width: 120px; background: #faf9f4; border: 1px solid #ccc; padding: 10px; text-align: center;">
        <div style="font-size: 20px; font-weight: bold; color: #004488;"><?= $comment_count ?></div>
        <div style="font-size: 11px; color: #666;">Yorum</div>
    </div>
    <div style="flex: 1; min-width: 120px; background: #faf9f4; border: 1px solid #ccc; padding: 10px; text-align: center;">
        <div style="font-size: 20px; font-weight: bold; color: #004488;"><?= $gb_count ?></div>
        <div style="font-size: 11px; color: #666;">Ziyaretçi Defteri</div>
    </div>
    <div style="flex: 1; min-width: 120px; background: #faf9f4; border: 1px solid #ccc; padding: 10px; text-align: center;">
        <div style="font-size: 20px; font-weight: bold; color: #004488;"><?= number_format($total_views) ?></div>
        <div style="font-size: 11px; color: #666;">Yazı Okunması</div>
    </div>
    <div style="flex: 1; min-width: 120px; background: #faf9f4; border: 1px solid #ccc; padding: 10px; text-align: center;">
        <div style="font-size: 20px; font-weight: bold; color: #004488;"><?= number_format($total_hits) ?></div>
        <div style="font-size: 11px; color: #666;">Tekil Ziyaret</div>
    </div>
    <div style="flex: 1; min-width: 120px; background: #faf9f4; border: 1px solid #ccc; padding: 10px; text-align: center;">
        <div style="font-size: 20px; font-weight: bold; color: #228800;"><?= number_format($today_hits) ?></div>
        <div style="font-size: 11px; color: #666;">Bugün Tekil</div>
    </div>
</div>

<!-- Hızlı Winamp & Ruh Hali Kutusu -->
<div style="background-color: #f5f3ec; border: 1px solid #bbb; padding: 12px; margin-bottom: 24px;">
    <h3 style="font-family: Georgia, serif; font-size: 14px; margin-bottom: 8px;">
        [♫] Hızlı Ruh Hali &amp; Winamp Güncellemesi
    </h3>
    <form action="/admin/index.php" method="POST" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px;">
        <?= csrf_field() ?>
        <div style="flex: 1; min-width: 200px;">
            <label for="current_mood" style="font-size: 11px;">Ruh Hali:</label>
            <input type="text" id="current_mood" name="current_mood" value="<?= e($current_mood) ?>" required>
        </div>
        <div style="flex: 1; min-width: 200px;">
            <label for="now_playing" style="font-size: 11px;">Winamp (Dinlenen Parça):</label>
            <input type="text" id="now_playing" name="now_playing" value="<?= e($now_playing) ?>" required>
        </div>
        <div style="align-self: flex-end;">
            <button type="submit" name="update_status" class="btn">Güncelle</button>
        </div>
    </form>
</div>

<!-- Yazı Listesi -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
    <h3 style="font-family: Georgia, serif; font-size: 16px;">Tüm Blog Yazıları</h3>
    <a href="/admin/post_edit.php" class="btn">[+ Yeni Yazı Ekle]</a>
</div>

<?php if (empty($posts)): ?>
    <p>Henüz yazı eklenmemiş. Hemen yeni bir yazı yazın!</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Başlık</th>
                <th style="width: 90px;">Kategori</th>
                <th style="width: 60px;">Okunma</th>
                <th style="width: 60px;">Yorum</th>
                <th style="width: 120px;">Tarih</th>
                <th style="width: 120px;">İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $p): ?>
                <tr>
                    <td style="font-family: monospace;"><?= $p['id'] ?></td>
                    <td>
                        <a href="<?= post_url($p['slug']) ?>" target="_blank" title="Yazıyı gör">
                            <strong><?= e($p['title']) ?></strong>
                        </a>
                    </td>
                    <td><?= e($p['category']) ?></td>
                    <td style="font-family: monospace; text-align: right;"><?= (int)$p['views'] ?></td>
                    <td style="font-family: monospace; text-align: right;"><?= (int)$p['comment_count'] ?></td>
                    <td style="font-family: monospace; font-size: 11px;"><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></td>
                    <td>
                        <a href="/admin/post_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm">Düzenle</a>
                        <a href="/admin/index.php?delete=<?= $p['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu yazıyı silmek istediğinizden emin misiniz?');">Sil</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
