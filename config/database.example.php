<?php
/**
 * Veritabanı Yapılandırması ve PDO Bağlantısı (Örnek Şablon)
 *
 * NOT: Bu dosya GitHub reposuna örnek teşkil etmesi için eklenmiştir.
 * Kurulum sihirbazı (/install.php) çalıştırıldığında bu yapılandırma 
 * otomatik olarak 'config/database.php' içine oluşturulur.
 *
 * Dilerseniz bu dosyayı 'config/database.php' olarak kopyalayıp
 * veritabanı bilgilerinizi elle de girebilirsiniz.
 */

if (!defined('APP_INIT')) {
    exit;
}

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'nobody';
const DB_USER = 'nobody';
const DB_PASS = 'CHANGE_ME';

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
