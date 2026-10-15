<?php
/**
 * Yorum Yönetimi (Comments Management)
 */
define('IN_ADMIN', true);
define('NO_SIDEBAR', true);
require_once __DIR__ . '/../config/init.php';

require_admin();

$msg = '';
$error = '';

// Yorum Silme (CSRF korumalı)
if (isset($_GET['delete'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        $error = 'Güvenlik hatası: Silme isteği doğrulanamadı.';
    } else {
        $del_id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$del_id]);
        $msg = 'Yorum başarıyla silindi.';
    }
}

// Onay Durumu Değiştirme (CSRF korumalı)
if (isset($_GET['toggle_approve'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        $error = 'Güvenlik hatası: İşlem doğrulanamadı.';
    } else {
        $c_id = (int)$_GET['toggle_approve'];
        $stmt = $pdo->prepare("UPDATE comments SET is_approved = 1 - is_approved WHERE id = ?");
        $stmt->execute([$c_id]);
        $msg = 'Yorum onay durumu güncellendi.';
    }
}

// Yorumları çek
$stmt = $pdo->query("SELECT c.*, p.title as post_title, p.slug as post_slug 
                     FROM comments c 
                     LEFT JOIN posts p ON c.post_id = p.id 
                     ORDER BY c.created_at DESC");
$comments = $stmt->fetchAll();

$page_title = 'Yorum Yönetimi';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-family: Georgia, serif; font-size: 19px; margin-bottom: 4px;">
        Yorum Yönetimi (Toplam <?= count($comments) ?> Yorum)
    </h2>
    <p style="font-size: 12px; color: #555;">
        Ziyaretçilerin blog yazılarına bıraktığı yorumları buradan inceleyebilir, onaylayabilir veya silebilirsiniz.
    </p>
</div>

<?php if ($msg !== ''): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if (empty($comments)): ?>
    <p>Henüz yorum yapılmamış.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th style="width: 140px;">Yazar</th>
                <th>Yorum &amp; İlgili Yazı</th>
                <th style="width: 120px;">Tarih</th>
                <th style="width: 70px;">Durum</th>
                <th style="width: 120px;">İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($comments as $c): ?>
                <tr>
                    <td style="font-family: monospace;"><?= $c['id'] ?></td>
                    <td>
                        <strong><?= e($c['author_name']) ?></strong><br>
                        <?php if (!empty($c['author_email'])): ?>
                            <span style="font-size: 10px; color: #666;"><?= e($c['author_email']) ?></span><br>
                        <?php endif; ?>
                        <?php if (!empty($c['author_url'])): ?>
                            <a href="<?= e($c['author_url']) ?>" target="_blank" style="font-size: 10px;">[Web Sitesi ↗]</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size: 11px; color: #555; margin-bottom: 4px;">
                            <strong>Yazı:</strong> 
                            <?php if ($c['post_title']): ?>
                                <a href="<?= post_url($c['post_slug']) ?>" target="_blank"><?= e($c['post_title']) ?></a>
                            <?php else: ?>
                                <em style="color:#999;">Silinmiş Yazı</em>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 12px; background: #fff; border: 1px solid #ddd; padding: 6px;">
                            <?= nl2br(e($c['content'])) ?>
                        </div>
                    </td>
                    <td style="font-family: monospace; font-size: 11px;"><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></td>
                    <td>
                        <?php if ($c['is_approved']): ?>
                            <span style="color: #008800; font-weight: bold;">Yayında</span>
                        <?php else: ?>
                            <span style="color: #cc0000; font-weight: bold;">Bekliyor</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/admin/comments.php?toggle_approve=<?= $c['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm">
                            <?= $c['is_approved'] ? 'Gizle' : 'Onayla' ?>
                        </a>
                        <a href="/admin/comments.php?delete=<?= $c['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm btn-danger" data-confirm="Bu yorumu silmek istediğinizden emin misiniz?">Sil</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
