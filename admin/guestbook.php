<?php
/**
 * Ziyaretçi Defteri Yönetimi (Guestbook Management)
 */
define('IN_ADMIN', true);
define('NO_SIDEBAR', true);
require_once __DIR__ . '/../config/init.php';

require_admin();

$msg = '';
$error = '';

// Kayıt Silme (CSRF korumalı)
if (isset($_GET['delete'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        $error = 'Güvenlik hatası: Silme işlemi doğrulanamadı.';
    } else {
        $del_id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM guestbook WHERE id = ?");
        $stmt->execute([$del_id]);
        $msg = 'Ziyaretçi defteri kaydı başarıyla silindi.';
    }
}

// Onay Durumu Değiştirme (CSRF korumalı)
if (isset($_GET['toggle_approve'])) {
    if (!csrf_verify($_GET['token'] ?? '')) {
        $error = 'Güvenlik hatası: Onay işlemi doğrulanamadı.';
    } else {
        $g_id = (int)$_GET['toggle_approve'];
        $stmt = $pdo->prepare("UPDATE guestbook SET is_approved = 1 - is_approved WHERE id = ?");
        $stmt->execute([$g_id]);
        $msg = 'Kayıt onay durumu güncellendi.';
    }
}

$stmt = $pdo->query("SELECT * FROM guestbook ORDER BY created_at DESC");
$entries = $stmt->fetchAll();

$page_title = 'Ziyaretçi Defteri Yönetimi';
$gb_enabled = get_setting('guestbook_enabled', '1');
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<div style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap;">
        <h2 style="font-family: Georgia, serif; font-size: 19px; margin-bottom: 4px;">
            Ziyaretçi Defteri Kayıtları (Toplam <?= count($entries) ?> Kayıt)
        </h2>
        <div style="font-size: 12px;">
            Durum: 
            <?php if ($gb_enabled === '1'): ?>
                <span style="color: #007700; font-weight: bold;">[● Yayında / Aktif]</span>
            <?php else: ?>
                <span style="color: #aa0000; font-weight: bold;">[○ Kapalı / Pasif]</span>
            <?php endif; ?>
            &nbsp; <a href="/admin/settings.php" style="font-size: 11px;">[Ayarlardan Değiştir]</a>
        </div>
    </div>
    <p style="font-size: 12px; color: #555;">
        Ziyaretçilerin hatıra defterine yazdığı mesajları buradan inceleyebilir ve yönetebilirsiniz.
    </p>
</div>

<?php if ($msg !== ''): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if (empty($entries)): ?>
    <p>Henüz defter kaydı bulunmuyor.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th style="width: 140px;">Yazar &amp; Konum</th>
                <th>Mesaj</th>
                <th style="width: 120px;">Tarih</th>
                <th style="width: 70px;">Durum</th>
                <th style="width: 120px;">İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $item): ?>
                <tr>
                    <td style="font-family: monospace;"><?= $item['id'] ?></td>
                    <td>
                        <strong><?= e($item['author_name']) ?></strong><br>
                        <?php if (!empty($item['author_city'])): ?>
                            <span style="font-size: 11px; color: #555;">(<?= e($item['author_city']) ?>)</span><br>
                        <?php endif; ?>
                        <?php if (!empty($item['author_url'])): ?>
                            <a href="<?= e($item['author_url']) ?>" target="_blank" style="font-size: 10px;">[Web Sitesi ↗]</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size: 12px; background: #fff; border: 1px solid #ddd; padding: 6px;">
                            <?= nl2br(e($item['message'])) ?>
                        </div>
                    </td>
                    <td style="font-family: monospace; font-size: 11px;"><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></td>
                    <td>
                        <?php if ($item['is_approved']): ?>
                            <span style="color: #008800; font-weight: bold;">Yayında</span>
                        <?php else: ?>
                            <span style="color: #cc0000; font-weight: bold;">Gizli</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/admin/guestbook.php?toggle_approve=<?= $item['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm">
                            <?= $item['is_approved'] ? 'Gizle' : 'Onayla' ?>
                        </a>
                        <a href="/admin/guestbook.php?delete=<?= $item['id'] ?>&token=<?= csrf_token() ?>" class="btn btn-sm btn-danger" data-confirm="Bu mesajı silmek istediğinizden emin misiniz?">Sil</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
