<?php
/**
 * Kurulum Sihirbazı (Installation Wizard)
 * Veritabanı ve ilk yönetici ayarlarını yapılandırır.
 */

define('APP_INIT', true);
$page_start_time = microtime(true);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    session_start();
}

$lock_file = __DIR__ . '/config/installed.lock';
$is_installed = file_exists($lock_file);

require_once __DIR__ . '/includes/functions.php';

$error = '';
$success = '';

// Zaten kuruluysa ve admin değilse uyar
if ($is_installed && empty($_SESSION['admin_user_id'])) {
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
    $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
    $db_port = (int)($_POST['db_port'] ?? 3306);
    $db_name = trim($_POST['db_name'] ?? 'nobody');
    $db_user = trim($_POST['db_user'] ?? 'nobody');
    $db_pass = $_POST['db_pass'] ?? '';

    $site_title = trim($_POST['site_title'] ?? 'Kişisel Ağ Günlüğü');
    $site_subtitle = trim($_POST['site_subtitle'] ?? 'Düşünceler, notlar ve anılar...');
    $webmaster_name = trim($_POST['webmaster_name'] ?? 'nobody');

    $admin_user = trim($_POST['admin_user'] ?? 'nobody');
    $admin_pass = $_POST['admin_pass'] ?? '';
    $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';
    $load_samples = isset($_POST['load_samples']);

    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error = 'Veritabanı sunucu, ad ve kullanıcı bilgileri zorunludur.';
    } elseif (empty($admin_user) || empty($admin_pass)) {
        $error = 'Yönetici kullanıcı adı ve şifresi boş bırakılamaz.';
    } elseif (strlen($admin_pass) < 4) {
        $error = 'Yönetici şifresi en az 4 karakter olmalıdır.';
    } elseif ($admin_pass !== $admin_pass_confirm) {
        $error = 'Yönetici şifreleri eşleşmiyor.';
    } else {
        try {
            // 1. MySQL Bağlantısını Test Et (Veritabanı olmadan)
            $dsn_no_db = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db_host, $db_port);
            $pdo_test = new PDO($dsn_no_db, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // 2. Veritabanını oluştur (varsa koru)
            $pdo_test->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

            // 3. Veritabanına bağlan
            $dsn_with_db = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db_host, $db_port, $db_name);
            $pdo = new PDO($dsn_with_db, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // 4. Tabloları Oluştur
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(64) PRIMARY KEY,
                setting_val TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                slug VARCHAR(100) NOT NULL UNIQUE,
                description TEXT NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                category_id INT NULL,
                category VARCHAR(100) NOT NULL DEFAULT 'Genel',
                summary TEXT NULL,
                content MEDIUMTEXT NOT NULL,
                views INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_cat_id (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Başlangıç Kategorileri
            $stmtC = $pdo->query("SELECT COUNT(*) FROM categories");
            if ((int)$stmtC->fetchColumn() === 0) {
                $insC = $pdo->prepare("INSERT INTO categories (name, slug, description, created_at) VALUES (?, ?, ?, NOW())");
                $insC->execute(['Genel', 'genel', 'Genel yazılar ve düşünceler.']);
                $insC->execute(['Günlük', 'gunluk', 'Gündelik notlar ve anılar.']);
                $insC->execute(['Yazılım', 'yazilim', 'Kodlar, mimari ve yazılım notları.']);
                $insC->execute(['Nostalji', 'nostalji', 'Eski internet, 2000ler ve retro hatıralar.']);
                $insC->execute(['Müzik', 'muzik', 'Albümler, şarkılar ve dinleme notları.']);
            }

            $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                author_name VARCHAR(100) NOT NULL,
                author_email VARCHAR(150) NULL,
                author_url VARCHAR(200) NULL,
                content TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                is_approved TINYINT(1) NOT NULL DEFAULT 1,
                INDEX idx_post (post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS guestbook (
                id INT AUTO_INCREMENT PRIMARY KEY,
                author_name VARCHAR(100) NOT NULL,
                author_city VARCHAR(100) NULL,
                author_url VARCHAR(200) NULL,
                message TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                is_approved TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS hit_counter (
                id INT PRIMARY KEY,
                total_hits INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Sayaç
            $pdo->exec("INSERT INTO hit_counter (id, total_hits) VALUES (1, 1024) ON DUPLICATE KEY UPDATE id=id");

            // 5. Yönetici Hesabını Oluştur
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password_hash, display_name, created_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name)");
            $stmtUser->execute([$admin_user, $hash, $webmaster_name]);

            // 6. Site Ayarlarını Kaydet
            $settings = [
                'site_title'        => $site_title,
                'site_subtitle'     => $site_subtitle,
                'webmaster_name'    => $webmaster_name,
                'about_enabled'     => '1',
                'guestbook_enabled' => '1',
                'cv_enabled'        => '1',
                'current_mood'      => 'Kahveli & Melankolik ☕',
                'now_playing'       => 'Radiohead - Karma Police (Winamp v2.91)',
                'about_text'        => "Merhaba! Ben " . e($webmaster_name) . ". 2000'lerin başındaki yalın, samimi ve gürültüsüz internet ruhunu yaşatmak için bu kişisel köşeyi açtım. Sonsuz kaydırmalara ve bildirim çılgınlığına inat; sadece metin, fikir ve samimiyet.",
                'blogroll_links'    => json_encode([
                    ['title' => 'W3Schools (Referans)', 'url' => 'https://www.w3schools.com'],
                    ['title' => 'Internet Archive (Geçmişe Yolculuk)', 'url' => 'https://archive.org'],
                    ['title' => 'Hacker News', 'url' => 'https://news.ycombinator.com'],
                ], JSON_UNESCAPED_UNICODE)
            ];

            foreach ($settings as $k => $v) {
                $insSetting = $pdo->prepare("INSERT INTO settings (setting_key, setting_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)");
                $insSetting->execute([$k, $v]);
            }

            // 7. Örnek İçerikler (İstenirse)
            if ($load_samples) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM posts");
                if ((int)$stmt->fetchColumn() === 0) {
                    $samplePosts = [
                        [
                            'title' => 'Merhaba Dünya: Kişisel Web Günlüğüm Yayında!',
                            'slug' => 'merhaba-dunya-kisisel-web-gunlugum-yayinda',
                            'category' => 'Günlük',
                            'summary' => 'Modern internetin gürültüsünden kaçıp kendi küçük, huzurlu HTML adamı inşa ettim.',
                            'content' => "Göz alıcı animasyonlar, her köşeden fırlayan bildirimler ve sonsuz algoritmik akışlar...\n\nBazen 2000'lerin o sade internetini çok özlüyorum. Notepad ile açıp yazdığımız sayfalar, Winamp'tan çalan MP3'ler ve gerçekten 'okumak' için ziyaret edilen kişisel günlükler.\n\nİşte bu blog tam olarak o günlerin ruhunu günümüze taşımak için var. Hoş geldiniz!"
                        ],
                        [
                            'title' => 'Winamp, ICQ ve IRC: Dijital Çağın Altın Günleri',
                            'slug' => 'winamp-icq-ve-irc-dijital-cagin-altin-gunleri',
                            'category' => 'Nostalji',
                            'summary' => 'Bir zamanlar internet sadece istediğimiz zaman girdiğimiz samimi bir odaydı.',
                            'content' => "Eskiden internet 'içinde yaşadığımız' değil, 'ziyaret ettiğimiz' bir yerdi. Okuldan veya işten eve gelir, modemin bağlanmasını beklerdik. ICQ'nun o meşhur 'Uh-oh!' sesi duyulduğunda ekrana hafif bir tebessüm yayılırdı.\n\nBu blogda o samimiyeti yaşatmak istiyorum. Ziyaretçi defterine bir selam bırakmayı unutmayın!"
                        ]
                    ];
                    $insP = $pdo->prepare("INSERT INTO posts (title, slug, category, summary, content, views, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 42, NOW(), NOW())");
                    foreach ($samplePosts as $sp) {
                        $insP->execute([$sp['title'], $sp['slug'], $sp['category'], $sp['summary'], $sp['content']]);
                    }

                    $pdo->exec("INSERT INTO guestbook (author_name, author_city, author_url, message, created_at, is_approved) 
                        VALUES ('Nostalji Sever', 'İstanbul', 'https://example.com', 'Siteniz hayırlı olsun! 2000\'ler internetini çok özlemiştik.', NOW(), 1)");
                }
            }

            // 8. config/database.php dosyasını yeni ayarlarla güncelle
            $dbFileContent = <<<PHP
<?php
/**
 * Veritabanı Yapılandırması ve PDO Bağlantısı
 */

const DB_HOST = '{$db_host}';
const DB_PORT = {$db_port};
const DB_NAME = '{$db_name}';
const DB_USER = '{$db_user}';
const DB_PASS = '{$db_pass}';

function get_db_connection(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        \$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        \$options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            die('Veritabanına bağlanılamadı: ' . htmlspecialchars(\$e->getMessage()));
        }
    }
    return \$pdo;
}
PHP;
            file_put_contents(__DIR__ . '/config/database.php', $dbFileContent);

            // 9. Kurulum kilit dosyasını oluştur
            file_put_contents($lock_file, date('Y-m-d H:i:s') . " - Installed successfully by $admin_user\n");

            $success = true;

        } catch (Exception $e) {
            $error = 'Kurulum Hatası: ' . $e->getMessage();
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

        <form action="/install.php" method="POST">
            
            <div class="section-title">1. Veritabanı Bağlantısı (MySQL)</div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 2; min-width: 180px;">
                    <label for="db_host">Sunucu (Host):</label>
                    <input type="text" id="db_host" name="db_host" required value="<?= e($_POST['db_host'] ?? '127.0.0.1') ?>">
                </div>
                <div class="form-group" style="flex: 1; min-width: 100px;">
                    <label for="db_port">Port:</label>
                    <input type="number" id="db_port" name="db_port" required value="<?= e($_POST['db_port'] ?? '3306') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="db_name">Veritabanı Adı (Yoksa otomatik oluşturulur):</label>
                <input type="text" id="db_name" name="db_name" required value="<?= e($_POST['db_name'] ?? 'nobody') ?>">
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 160px;">
                    <label for="db_user">Veritabanı Kullanıcısı:</label>
                    <input type="text" id="db_user" name="db_user" required value="<?= e($_POST['db_user'] ?? 'nobody') ?>">
                </div>
                <div class="form-group" style="flex: 1; min-width: 160px;">
                    <label for="db_pass">Veritabanı Parolası:</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?= e($_POST['db_pass'] ?? 'nobody') ?>">
                </div>
            </div>

            <div class="section-title">2. Blog Bilgileri</div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 2; min-width: 200px;">
                    <label for="site_title">Blog Başlığı:</label>
                    <input type="text" id="site_title" name="site_title" required value="<?= e($_POST['site_title'] ?? 'Kişisel Ağ Günlüğü') ?>">
                </div>
                <div class="form-group" style="flex: 1; min-width: 140px;">
                    <label for="webmaster_name">Adınız / Takma Ad:</label>
                    <input type="text" id="webmaster_name" name="webmaster_name" required value="<?= e($_POST['webmaster_name'] ?? 'nobody') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="site_subtitle">Blog Sloganı / Alt Başlık:</label>
                <input type="text" id="site_subtitle" name="site_subtitle" required value="<?= e($_POST['site_subtitle'] ?? 'Düşünceler, notlar ve dijital hatıralar...') ?>">
            </div>

            <div class="section-title">3. Yönetici Hesabı (Webmaster)</div>
            <div class="form-group">
                <label for="admin_user">Yönetici Kullanıcı Adı:</label>
                <input type="text" id="admin_user" name="admin_user" required value="<?= e($_POST['admin_user'] ?? 'nobody') ?>">
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 160px;">
                    <label for="admin_pass">Yönetici Şifresi:</label>
                    <input type="password" id="admin_pass" name="admin_pass" required value="nobody">
                </div>
                <div class="form-group" style="flex: 1; min-width: 160px;">
                    <label for="admin_pass_confirm">Şifre Tekrarı:</label>
                    <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" required value="nobody">
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
