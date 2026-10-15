<?php
/**
 * Kurulum Sihirbazı (Installation Wizard)
 * Veritabanı ve ilk yönetici ayarlarını yapılandırır.
 */

define('APP_INIT', true);
$page_start_time = microtime(true);

ini_set('display_errors', '0');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'use_strict_mode' => 1,
        'cookie_httponly' => 1,
        'cookie_samesite' => 'Strict',
        'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 1 : 0,
    ]);
}

$lock_file = __DIR__ . '/config/installed.lock';
$is_installed = file_exists($lock_file);

require_once __DIR__ . '/includes/functions.php';

$error = '';
$success = false;

// Zaten kuruluysa kesin olarak kilitle ve durdur
if ($is_installed) {
    $page_title = 'Kurulum Zaten Tamamlandı';
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <title>Sistem Zaten Kurulu</title>
        <link rel="stylesheet" href="/assets/style.css">
    </head>
    <body style="background:#edebe4; padding:30px 10px;">
        <div id="container" style="max-width:550px; margin:0 auto; padding:25px;">
            <h2 style="font-family:Georgia,serif; color:#990000; margin-bottom:10px;">Sistem Zaten Kurulu</h2>
            <p style="font-size:13px; line-height:1.6; margin-bottom:15px;">
                Bu blog sistemi daha önce başarıyla kurulmuş görünüyor. Güvenlik nedeniyle kurulum sihirbazı kilitlenmiştir.
            </p>
            <p style="font-size:12px; color:#555; margin-bottom:15px;">
                Yeniden sıfırdan kurmak isterseniz lütfen sunucunuzdaki <code>config/installed.lock</code> dosyasını silin.
            </p>
            <div>
                <a href="/" class="btn">&laquo; Ana Sayfaya Git</a>
                <a href="/admin/login.php" class="btn">[Yönetici Girişi]</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_install'])) {
    $db_host = input('db_host') ?: '127.0.0.1';
    $db_port = (int)(input('db_port') ?: 3306);
    $db_name = input('db_name');
    $db_user = input('db_user');
    $db_pass = is_string($_POST['db_pass'] ?? null) ? $_POST['db_pass'] : '';

    $site_title = input('site_title', 150) ?: 'Kişisel Ağ Günlüğü';
    $site_subtitle = input('site_subtitle', 250);
    $webmaster_name = input('webmaster_name', 100) ?: 'webmaster';

    $admin_user = input('admin_user');
    $admin_pass = is_string($_POST['admin_pass'] ?? null) ? $_POST['admin_pass'] : '';
    $admin_pass_confirm = is_string($_POST['admin_pass_confirm'] ?? null) ? $_POST['admin_pass_confirm'] : '';
    $load_samples = isset($_POST['load_samples']);

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik doğrulaması (CSRF) başarısız oldu. Lütfen formu yenileyip tekrar deneyin.';
    } elseif (!preg_match('/^[A-Za-z0-9.\-]{1,253}$/', $db_host)) {
        $error = 'Geçersiz veritabanı sunucu adresi.';
    } elseif ($db_port < 1 || $db_port > 65535) {
        $error = 'Geçersiz port numarası.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db_name)) {
        $error = 'Veritabanı adı yalnızca harf, rakam ve alt çizgi içerebilir.';
    } elseif (!preg_match('/^[A-Za-z0-9_.\-]{1,32}$/', $db_user)) {
        $error = 'Veritabanı kullanıcı adı yalnızca harf, rakam, nokta, tire ve alt çizgi içerebilir.';
    } elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $admin_user)) {
        $error = 'Yönetici kullanıcı adı 3-50 karakter olmalı; yalnızca harf, rakam, nokta, tire ve alt çizgi içerebilir.';
    } elseif (strlen($admin_pass) < 10) {
        $error = 'Yönetici şifresi en az 10 karakter olmalıdır.';
    } elseif ($admin_pass !== $admin_pass_confirm) {
        $error = 'Yönetici şifreleri eşleşmiyor.';
    } else {
        try {
            $pdoOptions = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // 1. MySQL Bağlantısını Test Et ve veritabanını oluştur (ad yukarıda beyaz listeyle doğrulandı)
            $pdo_test = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db_host, $db_port), $db_user, $db_pass, $pdoOptions);
            $pdo_test->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo_test = null;

            // 2. Veritabanına bağlan ve şemayı kur
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db_host, $db_port, $db_name), $db_user, $db_pass, $pdoOptions);
            require_once __DIR__ . '/includes/schema.php';
            install_schema($pdo, $load_samples);

            // 3. Yönetici Hesabını Oluştur
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password_hash, display_name, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name)");
            $stmtUser->execute([$admin_user, password_hash($admin_pass, PASSWORD_DEFAULT), $webmaster_name]);

            // 4. Site Ayarlarını Kaydet
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $site_url = preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)
                ? (is_https() ? 'https' : 'http') . '://' . $host
                : '';
            $settings = [
                'site_title'     => $site_title,
                'site_subtitle'  => $site_subtitle,
                'webmaster_name' => $webmaster_name,
                'cv_fullname'    => $webmaster_name,
                'site_url'       => $site_url,
            ];
            $insSetting = $pdo->prepare("INSERT INTO settings (setting_key, setting_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)");
            foreach ($settings as $k => $v) {
                $insSetting->execute([$k, $v]);
            }

            // 5. config/database.php dosyasını güvenli biçimde üret (var_export ile kod enjeksiyonu imkânsız)
            $dbFileContent = "<?php\n"
                . "/**\n * Veritabanı Yapılandırması ve PDO Bağlantısı (Kurulum sihirbazı tarafından üretildi)\n */\n\n"
                . "if (!defined('APP_INIT')) {\n    exit;\n}\n\n"
                . 'const DB_HOST = ' . var_export($db_host, true) . ";\n"
                . 'const DB_PORT = ' . var_export($db_port, true) . ";\n"
                . 'const DB_NAME = ' . var_export($db_name, true) . ";\n"
                . 'const DB_USER = ' . var_export($db_user, true) . ";\n"
                . 'const DB_PASS = ' . var_export($db_pass, true) . ";\n\n"
                . <<<'PHP'
function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            http_response_code(503);
            die('Veritabanına bağlanılamadı. Lütfen yapılandırma ayarlarını kontrol ediniz.');
        }
    }
    return $pdo;
}

PHP;

            $configPath = __DIR__ . '/config/database.php';
            $tmpPath = $configPath . '.tmp';
            if (file_put_contents($tmpPath, $dbFileContent, LOCK_EX) === false || !rename($tmpPath, $configPath)) {
                throw new RuntimeException('config/database.php yazılamadı. Dizin yazma izinlerini kontrol edin.');
            }
            @chmod($configPath, 0640);

            // 6. Kurulum kilit dosyasını oluştur
            if (file_put_contents($lock_file, date('Y-m-d H:i:s') . " - Installed\n", LOCK_EX) === false) {
                throw new RuntimeException('config/installed.lock oluşturulamadı.');
            }

            unset($_SESSION['csrf_token']);
            $success = true;

        } catch (Throwable $e) {
            error_log('Install error: ' . $e->getMessage());
            $error = $e instanceof PDOException
                ? 'Veritabanı hatası: Bağlantı bilgilerini ve kullanıcı yetkilerini kontrol edin.'
                : 'Kurulum Hatası: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kişisel Web Günlüğü - Kurulum Sihirbazı</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .install-box {
            max-width: 620px;
            margin: 20px auto;
            background: #ffffff;
            border: 2px solid #555555;
            box-shadow: 4px 4px 0px #aaaaaa;
            padding: 24px;
        }
        .section-title {
            font-family: Georgia, serif;
            font-size: 15px;
            font-weight: bold;
            color: #003366;
            border-bottom: 1px solid #cccccc;
            padding-bottom: 4px;
            margin: 18px 0 10px 0;
        }
    </style>
</head>
<body style="background-color: #edebe4; padding: 20px 10px;">

<div class="install-box">

    <div style="text-align: center; margin-bottom: 18px; border-bottom: 2px solid #666; padding-bottom: 12px;">
        <h1 style="font-family: Georgia, serif; font-size: 22px; color: #111;">
            Kişisel Ağ Günlüğü &bull; Kurulum Sihirbazı
        </h1>
        <div style="font-size: 12px; color: #666; font-style: italic;">
            2000'lerin yalın ve samimi blog sistemine hoş geldiniz.
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="font-size: 14px; line-height: 1.6;">
            <strong>Tebrikler! Kurulum başarıyla tamamlandı. 🎉</strong><br>
            Veritabanı tabloları oluşturuldu, yönetici hesabınız ve ayarlarınız kaydedildi.
        </div>

        <div style="margin-top: 20px; text-align: center; display: flex; justify-content: center; gap: 15px;">
            <a href="/admin/login.php" class="btn" style="padding: 8px 18px; font-size: 13px;">[Yönetici Girişine Git &raquo;]</a>
            <a href="/" class="btn" style="padding: 8px 18px; font-size: 13px;">[Ana Sayfayı Gör &raquo;]</a>
        </div>
    <?php else: ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form action="/install.php" method="POST" autocomplete="off">
            <?= csrf_field() ?>

            <div class="section-title">1. Veritabanı Bağlantısı (MySQL)</div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 2; min-width: 180px;">
                    <label for="db_host">Sunucu (Host):</label>
                    <input type="text" id="db_host" name="db_host" required value="<?= e(input('db_host') ?: '127.0.0.1') ?>">
                </div>
                <div class="form-group" style="flex: 1; min-width: 100px;">
                    <label for="db_port">Port:</label>
                    <input type="number" id="db_port" name="db_port" required min="1" max="65535" value="<?= e(input('db_port') ?: '3306') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="db_name">Veritabanı Adı (Yoksa otomatik oluşturulur):</label>
                <input type="text" id="db_name" name="db_name" required pattern="[A-Za-z0-9_]{1,64}" value="<?= e(input('db_name') ?: 'nobody') ?>">
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 160px;">
                    <label for="db_user">Veritabanı Kullanıcısı:</label>
                    <input type="text" id="db_user" name="db_user" required value="<?= e(input('db_user')) ?>">
                </div>
                <div class="form-group" style="flex: 1; min-width: 160px;">
                    <label for="db_pass">Veritabanı Parolası:</label>
                    <input type="password" id="db_pass" name="db_pass" autocomplete="new-password">
                </div>
            </div>

            <div class="section-title">2. Blog Bilgileri</div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 2; min-width: 200px;">
                    <label for="site_title">Blog Başlığı:</label>
                    <input type="text" id="site_title" name="site_title" required maxlength="150" value="<?= e(input('site_title') ?: 'Kişisel Ağ Günlüğü') ?>">
                </div>
                <div class="form-group" style="flex: 1; min-width: 140px;">
                    <label for="webmaster_name">Adınız / Takma Ad:</label>
                    <input type="text" id="webmaster_name" name="webmaster_name" required maxlength="100" value="<?= e(input('webmaster_name')) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="site_subtitle">Blog Sloganı / Alt Başlık:</label>
                <input type="text" id="site_subtitle" name="site_subtitle" maxlength="250" value="<?= e(input('site_subtitle') ?: 'Düşünceler, notlar ve dijital hatıralar...') ?>">
            </div>

            <div class="section-title">3. Yönetici Hesabı (Webmaster)</div>
            <div class="form-group">
                <label for="admin_user">Yönetici Kullanıcı Adı:</label>
                <input type="text" id="admin_user" name="admin_user" required pattern="[A-Za-z0-9_.\-]{3,50}" value="<?= e(input('admin_user')) ?>">
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 160px;">
                    <label for="admin_pass">Yönetici Şifresi (en az 10 karakter):</label>
                    <input type="password" id="admin_pass" name="admin_pass" required minlength="10" autocomplete="new-password">
                </div>
                <div class="form-group" style="flex: 1; min-width: 160px;">
                    <label for="admin_pass_confirm">Şifre Tekrarı:</label>
                    <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" required minlength="10" autocomplete="new-password">
                </div>
            </div>

            <div style="margin: 15px 0; background: #faf9f5; border: 1px dotted #999; padding: 10px;">
                <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                    <input type="checkbox" name="load_samples" value="1" checked>
                    <span>2000'ler nostaljisini yansıtan örnek başlangıç yazılarını ve ilk defter kaydını yükle</span>
                </label>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="do_install" class="btn" style="width: 100%; padding: 8px; font-size: 14px;">
                    Kurulumu Başlat ve Tamamla &raquo;
                </button>
            </div>

        </form>
    <?php endif; ?>

</div>

</body>
</html>
