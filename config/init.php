<?php
/**
 * Uygulama Başlatıcı (Init)
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Sayfa üretim süresi ölçümü için başlangıç zamanı
$page_start_time = microtime(true);

// Hata raporlama ayarları (Üretim ortamında hataları gizle, arka planda logla)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Yakalanmayan hatalarda iç ayrıntı sızdırmadan genel bir 500 sayfası göster
set_exception_handler(static function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
});

$is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

// HTTP Güvenlik Başlıkları (Security Headers)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Satır içi script yok: tüm JS /assets/app.js içinde. Stil için inline izinli (retro şablon).
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if ($is_https) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// Oturum çerez güvenliği ayarları
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'use_strict_mode'  => 1,
        'use_only_cookies' => 1,
        'cookie_httponly'  => 1,
        'cookie_samesite'  => 'Lax',
        'cookie_secure'    => $is_https ? 1 : 0,
    ]);
}

// Kurulum kontrolü
$lock_file = __DIR__ . '/installed.lock';
if (!file_exists($lock_file) || !file_exists(__DIR__ . '/database.php')) {
    header('Location: /install.php');
    exit;
}

// Veritabanı ve yardımcı fonksiyonlar
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Veritabanı bağlantısı
$pdo = get_db_connection();

// Şema sürüm kontrolü (ayarlar istek başına zaten tek sorguda önbelleğe alınır)
try {
    $schema_version = (int)get_setting('schema_version', '1');
} catch (PDOException $e) {
    error_log('Schema check failed: ' . $e->getMessage());
    http_response_code(503);
    die('Veritabanı tabloları bulunamadı. Yeniden kurmak için config/installed.lock dosyasını silip /install.php adresini açın.');
}

require_once __DIR__ . '/../includes/schema.php';
if ($schema_version < SCHEMA_VERSION) {
    migrate_schema($pdo, $schema_version);
    // Ayar önbelleğini tazele
    $settings_ref = &settings_store();
    $settings_ref = $pdo->query("SELECT setting_key, setting_val FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    unset($settings_ref);
}

// Tekil Ziyaretçi Sayacı (Unique Visitors)
$counter_data = increment_hit_counter();
$current_hits = $counter_data['total'];
$today_hits = $counter_data['today'];
