<?php
/**
 * Yardımcı Fonksiyonlar (Pure PHP Helper Functions)
 */

if (!defined('APP_INIT')) {
    die('Doğrudan erişim engellendi.');
}

/**
 * XSS Koruması için güvenli HTML çıktısı
 */
function e(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Veritabanından ayar getirir (Bellek içi önbellekli)
 */
function get_setting(string $key, string $default = ''): string {
    static $settingsCache = null;
    $pdo = get_db_connection();

    if ($settingsCache === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_val FROM settings");
        $settingsCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    return $settingsCache[$key] ?? $default;
}

/**
 * Ayar kaydeder veya günceller
 */
function set_setting(string $key, string $value): void {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)");
    $stmt->execute([$key, $value]);
}

/**
 * Türkçe tarih biçimlendirme
 */
function format_date(string $datetime, bool $includeTime = true): string {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return $datetime;

    $months = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
        5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
        9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
    ];

    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)] ?? date('m', $timestamp);
    $year = date('Y', $timestamp);

    if ($includeTime) {
        $time = date('H:i', $timestamp);
        return sprintf('%s %s %s, %s', $day, $month, $year, $time);
    }
    return sprintf('%s %s %s', $day, $month, $year);
}

/**
 * Türkçe karakter uyumlu URL dostu Slug üretici
 */
function slugify(string $text): string {
    $find = ['Ç', 'ç', 'Ğ', 'ğ', 'ı', 'I', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'];
    $replace = ['c', 'c', 'g', 'g', 'i', 'i', 'i', 'o', 'o', 's', 's', 'u', 'u'];
    $text = str_replace($find, $replace, $text);
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9-]+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

/**
 * 2000'lerin klasik mekanik/dijital sayaç kutucuğu HTML'i
 */
function render_counter(int $hits): string {
    $formatted = sprintf('%06d', $hits);
    $digits = str_split($formatted);
    $html = '<span class="hit-counter" title="Toplam Ziyaret Sayısı">';
    foreach ($digits as $d) {
        $html .= '<span class="digit">' . $d . '</span>';
    }
    $html .= '</span>';
    return $html;
}

/**
 * Ziyaretçi sayacını TEKİL (Unique) olarak takip eder.
 * Sayfa yenilemelerinde (F5) veya admin oturumlarında kesinlikle artmaz.
 * Aynı ziyaretçi gün boyunca yalnızca 1 kez tekil olarak sayılır.
 */
function increment_hit_counter(): array {
    $pdo = get_db_connection();
    
    // 1. Yönetici oturumu açıksa veya admin/kurulum/feed URL'siyse sayacı artırma
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (is_admin_logged_in() || str_starts_with($uri, '/admin') || str_starts_with($uri, '/rss') || str_starts_with($uri, '/install')) {
        $stmt = $pdo->query("SELECT total_hits, today_hits FROM hit_counter WHERE id = 1");
        $res = $stmt->fetch();
        return [
            'total' => (int)($res['total_hits'] ?? 0),
            'today' => (int)($res['today_hits'] ?? 0)
        ];
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // 2. Bot / Crawler filtreleme
    if (preg_match('/bot|crawl|slurp|spider|mediapartners|lighthouse/i', $ua)) {
        $stmt = $pdo->query("SELECT total_hits, today_hits FROM hit_counter WHERE id = 1");
        $res = $stmt->fetch();
        return [
            'total' => (int)($res['total_hits'] ?? 0),
            'today' => (int)($res['today_hits'] ?? 0)
        ];
    }

    $today = date('Y-m-d');

    // 3. Kalıcı çerez kontrolü (aynı tarayıcıda gün içi tekrar sayılmasın)
    $cookieName = 'retro_unique_v_' . date('Ymd');
    $hasVisitedTodayCookie = isset($_COOKIE[$cookieName]);

    if (!$hasVisitedTodayCookie) {
        if (!headers_sent()) {
            setcookie($cookieName, '1', time() + 86400, '/', '', false, true);
        }

        // 4. IP + Tarayıcı Hash'i (Kişisel veri gizliliği için hash'lenir)
        $ip_hash = hash('sha256', $ip . '|' . substr($ua, 0, 60));

        // Veritabanında (ip_hash, visit_date) PRIMARY KEY ile tekillik garantilenir
        $ins = $pdo->prepare("INSERT IGNORE INTO unique_visitors (ip_hash, visit_date, created_at) VALUES (?, ?, NOW())");
        $ins->execute([$ip_hash, $today]);

        // Veritabanına gerçekten YENİ bir tekil satır eklendiyse sayacı 1 artır!
        if ($ins->rowCount() > 0) {
            $checkDate = $pdo->query("SELECT today_date FROM hit_counter WHERE id = 1")->fetchColumn();
            if ($checkDate !== $today) {
                $upd = $pdo->prepare("UPDATE hit_counter SET today_date = ?, today_hits = 1, total_hits = total_hits + 1 WHERE id = 1");
                $upd->execute([$today]);
            } else {
                $pdo->exec("UPDATE hit_counter SET today_hits = today_hits + 1, total_hits = total_hits + 1 WHERE id = 1");
            }
        }
    }

    $stmt = $pdo->query("SELECT total_hits, today_hits FROM hit_counter WHERE id = 1");
    $res = $stmt->fetch();
    return [
        'total' => (int)($res['total_hits'] ?? 0),
        'today' => (int)($res['today_hits'] ?? 0)
    ];
}

/**
 * Yönetici oturumu kontrolü
 */
function is_admin_logged_in(): bool {
    return isset($_SESSION['admin_user_id']) && !empty($_SESSION['admin_user_id']);
}

/**
 * Yönetici oturumu zorunlu kılma
 */
function require_admin(): void {
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Kenar çubuğu ve filtreler için kategori listesi ve yazı sayıları
 */
function get_categories(): array {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT c.id, c.name, c.slug, COUNT(p.id) as count 
                         FROM categories c 
                         LEFT JOIN posts p ON (p.category_id = c.id OR p.category = c.name) 
                         GROUP BY c.id, c.name, c.slug 
                         ORDER BY count DESC, c.name ASC");
    return $stmt->fetchAll();
}

/**
 * Tüm kategorileri getirir (Yazı ekleme ve yönetim için)
 */
function get_all_categories(): array {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id OR p.category = c.name) as post_count 
                         FROM categories c 
                         ORDER BY c.name ASC");
    return $stmt->fetchAll();
}

function get_category_by_id(int $id): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_category_by_slug(string $slug): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function category_url(string $slug): string {
    return '/kategori/' . urlencode($slug);
}

/**
 * Son yazıları getirir
 */
function get_recent_posts(int $limit = 5): array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, title, slug, created_at FROM posts ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Yorum & Ziyaretçi defteri için basit matematik doğrulama (Anti-Spam)
 */
function get_math_captcha(): array {
    if (!isset($_SESSION['math_captcha'])) {
        $n1 = rand(2, 9);
        $n2 = rand(1, 8);
        $_SESSION['math_captcha'] = [
            'question' => "$n1 + $n2 = ?",
            'answer'   => $n1 + $n2
        ];
    }
    return $_SESSION['math_captcha'];
}

function verify_and_refresh_captcha(int $userAnswer): bool {
    $correct = isset($_SESSION['math_captcha']) && ((int)$_SESSION['math_captcha']['answer'] === $userAnswer);
    unset($_SESSION['math_captcha']); // Tek kullanımlık
    return $correct;
}

/**
 * Metin içeriğini paragraflar ve satır sonlarıyla temiz HTML'e dönüştürür
 */
function render_content(string $content): string {
    // Güvenlik için önce XSS temizliği
    $safe = e($content);

    // Basit ve nostaljik BBCode / biçimlendirme desteği
    // **kalın** veya [b]kalın[/b]
    $safe = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $safe);
    $safe = preg_replace('/\[b\](.*?)\[\/b\]/i', '<strong>$1</strong>', $safe);
    // *italik* veya [i]italik[/i]
    $safe = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $safe);
    $safe = preg_replace('/\[i\](.*?)\[\/i\]/i', '<em>$1</em>', $safe);
    // `kod` veya [code]kod[/code]
    $safe = preg_replace('/`([^`]+)`/', '<code>$1</code>', $safe);
    $safe = preg_replace('/\[code\](.*?)\[\/code\]/is', '<pre><code>$1</code></pre>', $safe);

    // Paragraf ve satır sonları
    return nl2br($safe);
}

/**
 * =========================================================================
 * GÜVENLİK YARDIMCILARI (CSRF & Rate Limiting)
 * =========================================================================
 */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Brute-force ve spam engelleme için oturum bazlı hız sınırlayıcı (Rate Limiter)
 */
function check_rate_limit(string $action, int $maxAttempts = 5, int $decaySeconds = 600): bool {
    $key = 'rate_limit_' . $action;
    $now = time();
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key] = array_filter($_SESSION[$key], fn($timestamp) => ($now - $timestamp) < $decaySeconds);
    return count($_SESSION[$key]) < $maxAttempts;
}

function record_rate_limit_hit(string $action): void {
    $key = 'rate_limit_' . $action;
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key][] = time();
}

function reset_rate_limit(string $action): void {
    unset($_SESSION['rate_limit_' . $action]);
}

/**
 * Modern Temiz URL Yardımcıları (Clean Slugs)
 */
function site_url(string $path = ''): string {
    return '/' . ltrim($path, '/');
}

function post_url(string $slug): string {
    return '/yazi/' . urlencode($slug);
}

