<?php
/**
 * Uygulama Başlatıcı (Init)
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Sayfa üretim süresi ölçümü için başlangıç zamanı
$page_start_time = microtime(true);

// Oturum çerez güvenliği ayarları
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Kurulum kontrolü
$lock_file = __DIR__ . '/installed.lock';
$current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!file_exists($lock_file) && $current_script !== 'install.php') {
    header('Location: /install.php');
    exit;
}

// Veritabanı ve yardımcı fonksiyonlar
if (!file_exists(__DIR__ . '/database.php') && file_exists(__DIR__ . '/database.example.php')) {
    @copy(__DIR__ . '/database.example.php', __DIR__ . '/database.php');
}
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Veritabanı bağlantısı
$pdo = get_db_connection();

// Tablolar mevcut değilse otomatik olarak şemayı yükle
try {
    $pdo->query("SELECT 1 FROM settings LIMIT 1");
} catch (Throwable $e) {
    init_database_schema($pdo);
}

// Tekil Ziyaretçi Sayacı (Unique Visitors)
$counter_data = increment_hit_counter();
$current_hits = $counter_data['total'];
$today_hits = $counter_data['today'];
