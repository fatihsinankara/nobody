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
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * İstek parametresini her zaman string olarak döndürür.
 * Dizi gönderilerek (?q[]=x) TypeError / 500 hatası üretilmesini engeller.
 * $maxLen > 0 ise değer karakter bazında kısaltılır.
 */
function input(string $key, int $maxLen = 0, string $source = 'POST'): string {
    $bag = $source === 'GET' ? $_GET : $_POST;
    $val = $bag[$key] ?? '';
    if (!is_string($val)) {
        return '';
    }
    $val = trim($val);
    return $maxLen > 0 ? mb_substr($val, 0, $maxLen) : $val;
}

/**
 * URL Güvenliği (XSS, javascript:, data: enjeksiyonu engelleme)
 * Yalnızca geçerli http:// ve https:// protokollerine izin verir.
 */
function safe_url(?string $url): string {
    if (empty($url)) {
        return '';
    }
    $url = trim($url);
    // Görünmeyen kontrol karakterlerini temizle
    $url = preg_replace('/[\x00-\x1F\x7F]/u', '', $url) ?? '';

    // Protokol belirtilmemişse https:// varsay
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower($parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }

    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

/**
 * İstek başına bir kez yüklenen ayar önbelleği (referans döndürür)
 */
function &settings_store(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = get_db_connection()
            ->query("SELECT setting_key, setting_val FROM settings")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache;
}

/**
 * Veritabanından ayar getirir (Bellek içi önbellekli)
 */
function get_setting(string $key, string $default = ''): string {
    $settings = &settings_store();
    return $settings[$key] ?? $default;
}

/**
 * Ayar kaydeder veya günceller (önbellek de güncellenir)
 */
function set_setting(string $key, string $value): void {
    $stmt = get_db_connection()->prepare("INSERT INTO settings (setting_key, setting_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)");
    $stmt->execute([$key, $value]);
    $settings = &settings_store();
    $settings[$key] = $value;
}

/**
 * Uygulamaya özel gizli anahtar (IP hash'leri için HMAC tuzu)
 */
function app_secret(): string {
    $secret = get_setting('app_secret');
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        set_setting('app_secret', $secret);
    }
    return $secret;
}

/**
 * Ziyaretçi IP'sinin geri çevrilemez (HMAC) özeti
 */
function client_hash(string $extra = ''): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return hash_hmac('sha256', $ip . '|' . $extra, app_secret());
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
 * Tabloda benzersiz bir slug üretir (çakışmada -2, -3 ... eki ekler)
 * $table yalnızca kod içinden sabit değerlerle çağrılır.
 */
function unique_slug(string $table, string $slug, int $excludeId = 0): string {
    if (!in_array($table, ['posts', 'categories'], true)) {
        throw new InvalidArgumentException('Geçersiz tablo.');
    }
    $base = $slug !== '' ? $slug : 'yazi';
    $stmt = get_db_connection()->prepare("SELECT 1 FROM {$table} WHERE slug = ? AND id != ? LIMIT 1");
    $candidate = $base;
    for ($i = 2; $i < 1000; $i++) {
        $stmt->execute([$candidate, $excludeId]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $candidate = $base . '-' . $i;
    }
    return $base . '-' . bin2hex(random_bytes(3));
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
 * Sayaç değerlerini okur (gün değiştiyse bugünkü sayı 0 gösterilir)
 */
function get_hit_counter(): array {
    $stmt = get_db_connection()->prepare("SELECT total_hits, IF(today_date = ?, today_hits, 0) AS today_hits FROM hit_counter WHERE id = 1");
    $stmt->execute([date('Y-m-d')]);
    $res = $stmt->fetch() ?: [];
    return [
        'total' => (int)($res['total_hits'] ?? 0),
        'today' => (int)($res['today_hits'] ?? 0)
    ];
}

/**
 * Ziyaretçi sayacını TEKİL (Unique) olarak takip eder.
 * Sayfa yenilemelerinde (F5) veya admin oturumlarında kesinlikle artmaz.
 * Aynı ziyaretçi gün boyunca yalnızca 1 kez tekil olarak sayılır.
 */
function increment_hit_counter(): array {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $today = date('Y-m-d');
    $cookieName = 'retro_unique_v_' . date('Ymd');

    // Yönetici, admin/kurulum/feed URL'leri, botlar ve bugün zaten sayılmış tarayıcılar
    if (isset($_COOKIE[$cookieName])
        || is_admin_logged_in()
        || preg_match('#^/(admin|rss|install)#', $uri)
        || preg_match('/bot|crawl|slurp|spider|mediapartners|lighthouse/i', $ua)) {
        return get_hit_counter();
    }

    if (!headers_sent()) {
        setcookie($cookieName, '1', [
            'expires'  => time() + 86400,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $pdo = get_db_connection();

    // IP + Tarayıcı HMAC özeti (Kişisel veri gizliliği için geri çevrilemez)
    $ins = $pdo->prepare("INSERT IGNORE INTO unique_visitors (ip_hash, visit_date, created_at) VALUES (?, ?, NOW())");
    $ins->execute([client_hash(substr($ua, 0, 60)), $today]);

    // Veritabanına gerçekten YENİ bir tekil satır eklendiyse tek sorguda sayacı artır
    if ($ins->rowCount() > 0) {
        $upd = $pdo->prepare("UPDATE hit_counter
                              SET today_hits = IF(today_date = ?, today_hits + 1, 1),
                                  today_date = ?,
                                  total_hits = total_hits + 1
                              WHERE id = 1");
        $upd->execute([$today, $today]);

        // Olasılıksal temizlik: 2 günden eski tekillik kayıtlarına gerek yok
        if (mt_rand(1, 100) === 1) {
            $pdo->prepare("DELETE FROM unique_visitors WHERE visit_date < ?")
                ->execute([date('Y-m-d', strtotime('-2 days'))]);
        }
    }

    return get_hit_counter();
}

/**
 * HTTPS bağlantısı mı?
 */
function is_https(): bool {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

/**
 * Yönetici oturum anahtarlarını temizler
 */
function clear_admin_session(): void {
    unset($_SESSION['admin_user_id'], $_SESSION['admin_username'], $_SESSION['admin_display_name'], $_SESSION['admin_ua'], $_SESSION['admin_last_activity']);
}

/**
 * Yönetici oturumu kontrolü
 */
function is_admin_logged_in(): bool {
    if (empty($_SESSION['admin_user_id'])) {
        return false;
    }

    // Oturum çalma (Session Hijacking) koruması: Tarayıcı imzası doğrulaması
    if (($_SESSION['admin_ua'] ?? null) !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        clear_admin_session();
        return false;
    }

    // 2 saatlik hareketsizlik zaman aşımı (Inactivity timeout)
    if (time() - (int)($_SESSION['admin_last_activity'] ?? 0) > 7200) {
        clear_admin_session();
        return false;
    }
    $_SESSION['admin_last_activity'] = time();

    return true;
}

/**
 * Yönetici oturumu zorunlu kılma
 */
function require_admin(): void {
    if (!is_admin_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Kenar çubuğu ve filtreler için kategori listesi ve yazı sayıları
 */
function get_categories(): array {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT c.id, c.name, c.slug, COUNT(p.id) AS count
                         FROM categories c
                         LEFT JOIN posts p ON p.category_id = c.id
                         GROUP BY c.id, c.name, c.slug
                         ORDER BY count DESC, c.name ASC");
    return $stmt->fetchAll();
}

/**
 * Tüm kategorileri getirir (Yazı ekleme ve yönetim için)
 */
function get_all_categories(): array {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT c.*, COUNT(p.id) AS post_count
                         FROM categories c
                         LEFT JOIN posts p ON p.category_id = c.id
                         GROUP BY c.id
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
    if (!isset($_SESSION['math_captcha']) || !is_array($_SESSION['math_captcha'])) {
        $n1 = random_int(2, 9);
        $n2 = random_int(1, 8);
        $_SESSION['math_captcha'] = [
            'question' => "$n1 + $n2 = ?",
            'answer'   => $n1 + $n2
        ];
    }
    return $_SESSION['math_captcha'];
}

function verify_and_refresh_captcha(mixed $userAnswer): bool {
    if (!isset($_SESSION['math_captcha']['answer'])) {
        return false;
    }
    $correctAnswer = (int)$_SESSION['math_captcha']['answer'];
    unset($_SESSION['math_captcha']); // Tek kullanımlık

    if (!is_string($userAnswer) || $userAnswer === '' || !is_numeric($userAnswer)) {
        return false;
    }

    return (int)$userAnswer === $correctAnswer;
}

/**
 * Metin içeriğini paragraflar ve satır sonlarıyla temiz HTML'e dönüştürür
 */
function render_content(string $content): string {
    // Güvenlik için önce XSS temizliği (yer tutucu karakteri kullanıcıdan gelemez)
    $safe = e(str_replace("\x00", '', $content));

    // Kod blokları ve bağlantılar yer tutucuya alınır; içlerine biçimlendirme uygulanmaz
    $blocks = [];
    $hold = static function (string $html) use (&$blocks): string {
        $blocks[] = $html;
        return "\x00" . (count($blocks) - 1) . "\x00";
    };

    // [code]blok[/code] ve `kod`
    $safe = preg_replace_callback('/\[code\](.*?)\[\/code\]/is', fn($m) => $hold('<pre><code>' . trim($m[1], "\r\n") . '</code></pre>'), $safe);
    $safe = preg_replace_callback('/`([^`\n]+)`/', fn($m) => $hold('<code>' . $m[1] . '</code>'), $safe);

    // Güvenli BBCode Bağlantı: [url=https://...]Bağlantı[/url] veya [url]https://...[/url]
    $link = static function (string $escapedUrl, ?string $text) use ($hold): string {
        $url = safe_url(html_entity_decode($escapedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return $text ?? $escapedUrl;
        }
        return $hold('<a href="' . e($url) . '" target="_blank" rel="nofollow noopener noreferrer">' . ($text ?? e($url)) . '</a>');
    };
    $safe = preg_replace_callback('/\[url=(.*?)\](.*?)\[\/url\]/i', fn($m) => $link($m[1], $m[2]), $safe);
    $safe = preg_replace_callback('/\[url\](.*?)\[\/url\]/i', fn($m) => $link($m[1], null), $safe);

    // **kalın** / [b]kalın[/b], *italik* / [i]italik[/i]
    $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe);
    $safe = preg_replace('/\[b\](.*?)\[\/b\]/is', '<strong>$1</strong>', $safe);
    $safe = preg_replace('/\*([^\*\n]+)\*/', '<em>$1</em>', $safe);
    $safe = preg_replace('/\[i\](.*?)\[\/i\]/is', '<em>$1</em>', $safe);

    // Paragraf ve satır sonları (kod blokları hâlâ yer tutucu olduğu için etkilenmez)
    $safe = nl2br($safe);

    return preg_replace_callback('/\x00(\d+)\x00/', fn($m) => $blocks[(int)$m[1]] ?? '', $safe);
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

function csrf_verify(mixed $token): bool {
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Brute-force ve spam engelleme için ÇİFT KATMANLI (Oturum + IP Hash Veritabanı) Hız Sınırlayıcı
 * Ziyaretçi çerezlerini silse dahi IP üzerinden bloklama garantilenir.
 */
function check_rate_limit(string $action, int $maxAttempts = 5, int $decaySeconds = 600): bool {
    $now = time();

    // 1. Oturum (Session) Kontrolü
    $key = 'rate_limit_' . $action;
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key] = array_filter($_SESSION[$key], fn($timestamp) => ($now - $timestamp) < $decaySeconds);
    if (count($_SESSION[$key]) >= $maxAttempts) {
        return false;
    }

    // 2. IP Hash Veritabanı Kontrolü (Çerezsiz bot veya saldırganlara karşı)
    try {
        $stmt = get_db_connection()->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip_hash = ? AND action_key = ? AND created_at > ?");
        $stmt->execute([client_hash(), $action, $now - $decaySeconds]);
        if ((int)$stmt->fetchColumn() >= $maxAttempts) {
            return false;
        }
    } catch (Throwable $e) {
        // Veritabanı tablosu hazır değilse oturum kontrolüyle devam et
    }

    return true;
}

function record_rate_limit_hit(string $action): void {
    $now = time();

    // 1. Oturuma kaydet
    $key = 'rate_limit_' . $action;
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key][] = $now;

    // 2. Veritabanına kaydet
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_hash, action_key, created_at) VALUES (?, ?, ?)");
        $stmt->execute([client_hash(), $action, $now]);

        // Olasılıksal eski kayıt temizliği (Her 25 istekte 1 kez 1 saatten eski kayıtları temizler)
        if (mt_rand(1, 25) === 1) {
            $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?")->execute([$now - 3600]);
        }
    } catch (Throwable $e) {
        // Sessizce devam et
    }
}

function reset_rate_limit(string $action): void {
    unset($_SESSION['rate_limit_' . $action]);
    try {
        $stmt = get_db_connection()->prepare("DELETE FROM rate_limits WHERE ip_hash = ? AND action_key = ?");
        $stmt->execute([client_hash(), $action]);
    } catch (Throwable $e) {
        // Sessizce devam et
    }
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

/**
 * Mutlak site adresi (RSS vb. için). Kurulumda kaydedilen adres kullanılır;
 * Host başlığı yalnızca geri dönüş olarak ve doğrulanarak kullanılır.
 */
function base_url(): string {
    $saved = get_setting('site_url');
    if ($saved !== '' && safe_url($saved) !== '') {
        return rtrim($saved, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    return (is_https() ? 'https' : 'http') . '://' . $host;
}
