<?php
/**
 * Site Başlığı (Header) Şablonu
 */
if (!defined('APP_INIT')) die('Doğrudan erişim engellendi.');

$site_title = get_setting('site_title', 'Kişisel Ağ Günlüğü');
$site_subtitle = get_setting('site_subtitle', 'Düşünceler, notlar ve dijital hatıralar...');
$current_mood = get_setting('current_mood', 'Sakin & Düşünceli');
$now_playing = get_setting('now_playing', 'Radiohead - Karma Police');

$page_title = isset($page_title) ? $page_title . ' ~ ' . $site_title : $site_title;

$req_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="alternate" type="application/rss+xml" title="<?= e($site_title) ?> RSS Beslemesi" href="/rss.xml">
    <meta name="generator" content="Personal Weblog 2000s">
</head>
<body>

<div id="container">

    <!-- Üst Başlık -->
    <header id="header">

        <div class="site-title">
            <a href="/"><?= e($site_title) ?></a>
        </div>
        <div class="site-subtitle"><?= e($site_subtitle) ?></div>

        <!-- Canlı Ruh Hali & Winamp -->
        <div class="status-bar">
            <div class="status-item">
                <strong>Ruh Hali:</strong> <?= e($current_mood) ?>
            </div>
            <div class="status-item">
                <strong>Winamp:</strong> ♫ <?= e($now_playing) ?>
            </div>
        </div>
    </header>

    <!-- Gezinme Menüsü (Modern Temiz URL'ler) -->
    <nav id="nav">
        <ul>
            <li><a href="/" class="<?= ($req_uri === '/' || $current_script === 'index.php') && !isset($_GET['archive']) ? 'active' : '' ?>">Ana Sayfa</a></li>
            <li><a href="/arsiv" class="<?= $req_uri === '/arsiv' || isset($_GET['archive']) ? 'active' : '' ?>">Arşiv</a></li>
            <?php 
            $nav_gb_enabled = get_setting('guestbook_enabled', '1');
            $nav_about_enabled = get_setting('about_enabled', '1');
            $nav_cv_enabled = get_setting('cv_enabled', '1');
            $nav_is_admin = is_admin_logged_in();
            ?>
            <?php if ($nav_gb_enabled === '1'): ?>
                <li><a href="/defter" class="<?= $req_uri === '/defter' || $current_script === 'guestbook.php' ? 'active' : '' ?>">Ziyaretçi Defteri</a></li>
            <?php elseif ($nav_is_admin): ?>
                <li><a href="/defter" style="color: #b86000;" class="<?= $req_uri === '/defter' || $current_script === 'guestbook.php' ? 'active' : '' ?>" title="Ziyaretçilere kapalı, sadece yöneticiye görünür">Defter (Taslak)</a></li>
            <?php endif; ?>

            <?php if ($nav_about_enabled === '1'): ?>
                <li><a href="/hakkimda" class="<?= $req_uri === '/hakkimda' || $current_script === 'about.php' ? 'active' : '' ?>">Hakkımda &amp; Blogroll</a></li>
            <?php elseif ($nav_is_admin): ?>
                <li><a href="/hakkimda" style="color: #b86000;" class="<?= $req_uri === '/hakkimda' || $current_script === 'about.php' ? 'active' : '' ?>" title="Ziyaretçilere kapalı, sadece yöneticiye görünür">Hakkımda (Taslak)</a></li>
            <?php endif; ?>

            <?php if ($nav_cv_enabled === '1'): ?>
                <li><a href="/cv" class="<?= $req_uri === '/cv' || $current_script === 'cv.php' ? 'active' : '' ?>">CV / Özgeçmiş</a></li>
            <?php elseif ($nav_is_admin): ?>
                <li><a href="/cv" style="color: #b86000;" class="<?= $req_uri === '/cv' || $current_script === 'cv.php' ? 'active' : '' ?>" title="Ziyaretçilere gizli, sadece yöneticiye görünür">CV (Taslak)</a></li>
            <?php endif; ?>
            <li><a href="/rss.xml" target="_blank">RSS 2.0</a></li>
            <?php if (is_admin_logged_in()): ?>
                <li><a href="/admin/index.php" style="color: #990000;">[★ Yönetim]</a></li>
            <?php else: ?>
                <li><a href="/admin/login.php" style="color: #666666;">Yönetici</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Ana Gövde Düzeni -->
    <div id="main-layout">
        <main id="content">
