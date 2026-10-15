<?php
/**
 * Modern Router & Front Controller (PHP Yerleşik Sunucu İçin)
 * Temiz URL (Clean Slugs) Yönlendiricisi
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = rawurldecode($uri);

// Statik varlıklar (CSS, Görseller, İkonlar)
$file_path = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file_path) && !is_dir($file_path)) {
    $ext = pathinfo($file_path, PATHINFO_EXTENSION);
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
    if (file_exists($admin_file) && is_file($admin_file)) {
        require $admin_file;
        exit;
    }
}

// Modern Yazı Slug'ı (/yazi/slug-metni veya doğrudan /slug-metni)
if (str_starts_with($uri, '/yazi/')) {
    $slug = substr($uri, 6);
    $_GET['slug'] = $slug;
    require __DIR__ . '/post.php';
    exit;
}

// Doğrudan .php dosyası çağrıldıysa
if (file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    require __DIR__ . $uri;
    exit;
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
