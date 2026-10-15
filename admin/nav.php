<?php
/**
 * Yönetim Paneli Alt Menüsü
 */
if (!defined('APP_INIT')) die('Doğrudan erişim engellendi.');

$unapproved_comments = (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE is_approved = 0")->fetchColumn();
$unapproved_gb = (int)$pdo->query("SELECT COUNT(*) FROM guestbook WHERE is_approved = 0")->fetchColumn();
$current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<div style="background-color: #e5e2d6; border: 1px solid #999; padding: 8px 12px; margin-bottom: 20px; font-size: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
    <strong>Menü:</strong>
    <a href="/admin/index.php" style="<?= $current_page === 'index.php' ? 'font-weight:bold; color:#a00;' : '' ?>">[Yazılar]</a>
    <a href="/admin/post_edit.php" style="<?= $current_page === 'post_edit.php' && !isset($_GET['id']) ? 'font-weight:bold; color:#a00;' : '' ?>">[+ Yeni Yazı]</a>
    <a href="/admin/categories.php" style="<?= $current_page === 'categories.php' ? 'font-weight:bold; color:#a00;' : '' ?>">[★ Kategoriler]</a>
    <a href="/admin/about_edit.php" style="<?= $current_page === 'about_edit.php' ? 'font-weight:bold; color:#a00;' : '' ?>">[★ Hakkımda Düzenle]</a>
    <a href="/admin/cv_edit.php" style="<?= $current_page === 'cv_edit.php' ? 'font-weight:bold; color:#a00;' : '' ?>">[★ CV Düzenle]</a>
    <a href="/admin/comments.php" style="<?= $current_page === 'comments.php' ? 'font-weight:bold; color:#a00;' : '' ?>">[Yorumlar <?= $unapproved_comments > 0 ? "($unapproved_comments)" : '' ?>]</a>
    <a href="/admin/guestbook.php" style="<?= $current_page === 'guestbook.php' ? 'font-weight:bold; color:#a00;' : '' ?>">[Ziyaretçi Defteri <?= $unapproved_gb > 0 ? "($unapproved_gb)" : '' ?>]</a>
    <a href="/admin/settings.php" style="<?= $current_page === 'settings.php' ? 'font-weight:bold; color:#a00;' : '' ?>">[Site Ayarları]</a>
    <a href="/" target="_blank">[Siteyi Gör ↗]</a>
    <a href="/admin/logout.php?token=<?= csrf_token() ?>" style="color: #990000; margin-left: auto;">[Çıkış Yap]</a>
</div>
