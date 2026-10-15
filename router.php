<?php
/**
 * Modern Router & Front Controller (PHP Yerleşik Sunucu İçin)
 * Temiz URL (Clean Slugs) Yönlendiricisi
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = rawurldecode($uri);

// Path traversal (Dizin atlatma '..') saldırılarını anında engelle
if (str_contains($uri, '..')) {
    header("HTTP/1.1 403 Forbidden");
    die('Erişim engellendi.');
}

// Gizli dosya ve dizinlere (.git, .env vb.) doğrudan erişimi engelle
if (preg_match('#(^|/)\.#', $uri)) {
    header("HTTP/1.1 403 Forbidden");
    die('Erişim engellendi.');
}

// router.php dosyasının doğrudan web üzerinden çağrılmasını engelle
if (basename($uri) === 'router.php') {
    header("HTTP/1.1 403 Forbidden");
    die('Erişim engellendi.');
}

// Statik varlıklar (CSS, Görseller, İkonlar)
$file_path = __DIR__ . $uri;
$real_file = realpath($file_path);
$base_dir = realpath(__DIR__);
if ($real_file && $base_dir && str_starts_with($real_file, $base_dir) && is_file($real_file)) {
    $ext = pathinfo($real_file, PATHINFO_EXTENSION);
    if (in_array(strtolower($ext), ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'txt', 'woff', 'woff2'])) {
        return false; // PHP yerleşik sunucusunun doğrudan servis etmesine izin ver
    }
}

// Ana Sayfa
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

// Arşiv
if ($uri === '/arsiv') {
    $_GET['archive'] = '1';
    require __DIR__ . '/index.php';
    exit;
}

// Ziyaretçi Defteri
if ($uri === '/defter' || $uri === '/ziyaretci-defteri') {
    require __DIR__ . '/guestbook.php';
    exit;
}

// Hakkımda
if ($uri === '/hakkimda') {
    require __DIR__ . '/about.php';
    exit;
}

// Çevrimiçi CV / Özgeçmiş
if ($uri === '/cv' || $uri === '/ozgecmis') {
    require __DIR__ . '/cv.php';
    exit;
}

// Kategori Sayfası (/kategori/slug)
if (str_starts_with($uri, '/kategori/')) {
    $cat_slug = substr($uri, 10);
    $_GET['category_slug'] = $cat_slug;
    require __DIR__ . '/index.php';
    exit;
}

// RSS Beslemesi
if ($uri === '/rss' || $uri === '/rss.xml') {
    require __DIR__ . '/rss.php';
    exit;
}

// Kurulum Sihirbazı
if ($uri === '/install' || $uri === '/install.php') {
    require __DIR__ . '/install.php';
    exit;
}

// Yönetim Paneli
if ($uri === '/admin' || $uri === '/admin/') {
    require __DIR__ . '/admin/index.php';
    exit;
}

if (str_starts_with($uri, '/admin/')) {
    $admin_file = __DIR__ . $uri;
    $real = realpath($admin_file);
    $admin_dir = realpath(__DIR__ . '/admin');
    if ($real && $admin_dir && str_starts_with($real, $admin_dir . DIRECTORY_SEPARATOR) && is_file($real) && pathinfo($real, PATHINFO_EXTENSION) === 'php') {
        require $real;
        exit;
    }
}

// Modern Yazı Slug'ı (/yazi/slug-metni)
if (str_starts_with($uri, '/yazi/')) {
    $slug = substr($uri, 6);
    $_GET['slug'] = $slug;
    require __DIR__ . '/post.php';
    exit;
}

// Doğrudan .php dosyası çağrıldıysa (config ve includes altı yasaklanır)
if ($real_file && $base_dir && str_starts_with($real_file, $base_dir) && is_file($real_file)) {
    $rel = ltrim(str_replace(['\\', $base_dir], ['/', ''], $real_file), '/');
    if (str_starts_with($rel, 'config/') || str_starts_with($rel, 'includes/')) {
        header("HTTP/1.1 403 Forbidden");
        die('Erişim engellendi.');
    }
    if (pathinfo($real_file, PATHINFO_EXTENSION) === 'php') {
        require $real_file;
        exit;
    }
}

// Kök dizinde doğrudan slug eşleşmesi (örn: /merhaba-dunya...)
$potential_slug = ltrim($uri, '/');
if (!empty($potential_slug) && preg_match('/^[a-z0-9-]+$/', $potential_slug)) {
    $_GET['slug'] = $potential_slug;
    require __DIR__ . '/post.php';
    exit;
}

// 404 Sayfası
header("HTTP/1.0 404 Not Found");
define('APP_INIT', true);
require_once __DIR__ . '/config/init.php';
$page_title = '404 Sayfa Bulunamadı';
include __DIR__ . '/includes/header.php';
echo '<div class="alert alert-error"><strong>404 &ndash; Sayfa Bulunamadı:</strong> Aradığınız adres mevcut değil veya taşınmış olabilir.</div>';
echo '<p><a href="/">&laquo; Ana sayfaya dön</a></p>';
include __DIR__ . '/includes/footer.php';
exit;
