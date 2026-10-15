<?php
/**
 * Veritabanı Şeması & Sürüm Geçişleri (Schema & Migrations)
 * Tek kaynak: hem kurulum sihirbazı hem de çalışma zamanı geçişleri burayı kullanır.
 * Fonksiyonlar yalnızca kurulumda veya şema sürümü eskiyse çalıştırılır.
 */

if (!defined('APP_INIT')) {
    die('Doğrudan erişim engellendi.');
}

const SCHEMA_VERSION = 2;

/**
 * Tüm tabloları oluşturur ve temel başlangıç verilerini ekler.
 * Yönetici hesabı burada OLUŞTURULMAZ (kurulum sihirbazı oluşturur).
 */
function install_schema(PDO $pdo, bool $withSamples = false): void {
    $tables = [
        "CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(64) PRIMARY KEY,
            setting_val TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            slug VARCHAR(100) NOT NULL UNIQUE,
            description TEXT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS posts (
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
            INDEX idx_cat_id (category_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            author_name VARCHAR(100) NOT NULL,
            author_email VARCHAR(150) NULL,
            author_url VARCHAR(200) NULL,
            content TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            is_approved TINYINT(1) NOT NULL DEFAULT 0,
            INDEX idx_post_approved (post_id, is_approved, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS guestbook (
            id INT AUTO_INCREMENT PRIMARY KEY,
            author_name VARCHAR(100) NOT NULL,
            author_city VARCHAR(100) NULL,
            author_url VARCHAR(200) NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            is_approved TINYINT(1) NOT NULL DEFAULT 0,
            INDEX idx_approved_created (is_approved, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS hit_counter (
            id INT PRIMARY KEY,
            total_hits INT UNSIGNED NOT NULL DEFAULT 0,
            today_date DATE NULL,
            today_hits INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS unique_visitors (
            ip_hash VARCHAR(64) NOT NULL,
            visit_date DATE NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (ip_hash, visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_hash VARCHAR(64) NOT NULL,
            action_key VARCHAR(50) NOT NULL,
            created_at INT UNSIGNED NOT NULL,
            INDEX idx_rate_lookup (ip_hash, action_key, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    // Başlangıç kategorileri
    if ((int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn() === 0) {
        $ins = $pdo->prepare("INSERT INTO categories (name, slug, description, created_at) VALUES (?, ?, ?, NOW())");
        $ins->execute(['Genel', 'genel', 'Genel yazılar ve düşünceler.']);
        $ins->execute(['Günlük', 'gunluk', 'Gündelik notlar ve anılar.']);
        $ins->execute(['Yazılım', 'yazilim', 'Kodlar, mimari ve yazılım notları.']);
        $ins->execute(['Nostalji', 'nostalji', 'Eski internet, 2000ler ve retro hatıralar.']);
        $ins->execute(['Müzik', 'muzik', 'Albümler, şarkılar ve dinleme notları.']);
    }

    // Sayaç başlangıç satırı
    $pdo->exec("INSERT IGNORE INTO hit_counter (id, total_hits, today_date, today_hits) VALUES (1, 1024, CURDATE(), 0)");

    // Varsayılan ayarlar (mevcut olanların üzerine yazılmaz)
    $defaults = [
        'site_title'         => 'Kişisel Ağ Günlüğü',
        'site_subtitle'      => 'Düşünceler, notlar ve dijital hatıralar...',
        'webmaster_name'     => 'nobody',
        'about_enabled'      => '1',
        'guestbook_enabled'  => '1',
        'cv_enabled'         => '1',
        'comment_moderation' => '1',
        'current_mood'       => 'Kahveli & Melankolik ☕',
        'now_playing'        => 'Radiohead - Karma Police (Winamp v2.91)',
        'about_text'         => "Merhaba! 2000'lerin başındaki yalın, samimi ve gürültüsüz internet ruhunu yaşatmak için bu kişisel köşeyi açtım. Sonsuz kaydırmalara ve bildirim çılgınlığına inat; sadece metin, fikir ve samimiyet.",
        'blogroll_links'     => json_encode([
            ['title' => 'W3Schools (Referans)', 'url' => 'https://www.w3schools.com'],
            ['title' => 'Internet Archive (Geçmişe Yolculuk)', 'url' => 'https://archive.org'],
            ['title' => 'Hacker News', 'url' => 'https://news.ycombinator.com'],
        ], JSON_UNESCAPED_UNICODE),
        'cv_fullname'        => 'nobody',
        'cv_headline'        => 'Yazılım Geliştirici & Sistem Meraklısı',
        'cv_summary'         => 'Karmaşık mimarileri sadeleştiren, yüksek performanslı ve yalın web çözümleri geliştiren bir yazılımcı.',
        'cv_location'        => 'İstanbul, Türkiye',
        'cv_email'           => 'nobody@example.com',
        'cv_website'         => 'https://example.com',
        'cv_experience'      => '[]',
        'cv_education'       => '[]',
        'cv_skills'          => '[]',
        'cv_projects'        => '[]',
        'app_secret'         => bin2hex(random_bytes(32)),
        'schema_version'     => (string)SCHEMA_VERSION,
    ];
    $insSetting = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_val) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $insSetting->execute([$k, $v]);
    }

    if ($withSamples && (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn() === 0) {
        $samples = [
            ['Merhaba Dünya: Kişisel Web Günlüğüm Yayında!', 'merhaba-dunya-kisisel-web-gunlugum-yayinda', 'gunluk',
             'Modern internetin gürültüsünden kaçıp kendi küçük, huzurlu HTML adamı inşa ettim.',
             "Göz alıcı animasyonlar, her köşeden fırlayan bildirimler ve sonsuz algoritmik akışlar...\n\nBazen 2000'lerin o sade internetini çok özlüyorum. Notepad ile açıp yazdığımız sayfalar, Winamp'tan çalan MP3'ler ve gerçekten 'okumak' için ziyaret edilen kişisel günlükler.\n\nİşte bu blog tam olarak o günlerin ruhunu günümüze taşımak için var. Hoş geldiniz!"],
            ['Winamp, ICQ ve IRC: Dijital Çağın Altın Günleri', 'winamp-icq-ve-irc-dijital-cagin-altin-gunleri', 'nostalji',
             'Bir zamanlar internet sadece istediğimiz zaman girdiğimiz samimi bir odaydı.',
             "Eskiden internet 'içinde yaşadığımız' değil, 'ziyaret ettiğimiz' bir yerdi. Okuldan veya işten eve gelir, modemin bağlanmasını beklerdik.\n\nBu blogda o samimiyeti yaşatmak istiyorum. Ziyaretçi defterine bir selam bırakmayı unutmayın!"],
        ];
        $insPost = $pdo->prepare("INSERT INTO posts (title, slug, category_id, category, summary, content, views, created_at, updated_at)
                                  SELECT ?, ?, c.id, c.name, ?, ?, 0, NOW(), NOW() FROM categories c WHERE c.slug = ?");
        foreach ($samples as [$title, $slug, $catSlug, $summary, $content]) {
            $insPost->execute([$title, $slug, $summary, $content, $catSlug]);
        }
        $pdo->exec("INSERT INTO guestbook (author_name, author_city, author_url, message, created_at, is_approved)
                    VALUES ('Nostalji Sever', 'İstanbul', 'https://example.com', 'Siteniz hayırlı olsun! 2000''ler internetini çok özlemiştik.', NOW(), 1)");
    }
}

/**
 * Mevcut kurulumları güncel şemaya taşır (idempotent).
 */
function migrate_schema(PDO $pdo, int $fromVersion): void {
    if ($fromVersion < 2) {
        // Performans indeksleri (zaten varsa hata yutulur)
        $indexes = [
            "ALTER TABLE posts ADD INDEX idx_created (created_at)",
            "ALTER TABLE comments ADD INDEX idx_post_approved (post_id, is_approved, created_at)",
            "ALTER TABLE guestbook ADD INDEX idx_approved_created (is_approved, created_at)",
            "ALTER TABLE comments ALTER is_approved SET DEFAULT 0",
            "ALTER TABLE guestbook ALTER is_approved SET DEFAULT 0",
        ];
        foreach ($indexes as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                // Duplicate key name vb. — zaten uygulanmış
            }
        }

        // Eski metin tabanlı kategori bağlarını category_id'ye taşı
        $pdo->exec("UPDATE posts p JOIN categories c ON c.name = p.category SET p.category_id = c.id WHERE p.category_id IS NULL OR p.category_id = 0");
        $pdo->exec("UPDATE posts p JOIN categories c ON c.slug = 'genel' SET p.category_id = c.id, p.category = c.name
                    WHERE p.category_id IS NULL OR p.category_id NOT IN (SELECT id FROM (SELECT id FROM categories) t)");

        $ins = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_val) VALUES (?, ?)");
        $ins->execute(['app_secret', bin2hex(random_bytes(32))]);
        $ins->execute(['comment_moderation', '1']);
    }

    $pdo->prepare("INSERT INTO settings (setting_key, setting_val) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val)")
        ->execute([(string)SCHEMA_VERSION]);
}
