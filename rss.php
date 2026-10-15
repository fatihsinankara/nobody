<?php
/**
 * RSS 2.0 Akışı (RSS Feed)
 */
require_once __DIR__ . '/config/init.php';

header('Content-Type: application/rss+xml; charset=utf-8');

$site_title = get_setting('site_title', 'Kişisel Ağ Günlüğü');
$site_subtitle = get_setting('site_subtitle', '2000\'ler Ruhuyla Saf PHP');
$site_base = base_url();

$stmt = $pdo->query("SELECT title, slug, category, summary, content, created_at FROM posts ORDER BY created_at DESC LIMIT 20");
$posts = $stmt->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title><?= htmlspecialchars($site_title, ENT_XML1, 'UTF-8') ?></title>
        <link><?= htmlspecialchars($site_base . '/', ENT_XML1, 'UTF-8') ?></link>
        <description><?= htmlspecialchars($site_subtitle, ENT_XML1, 'UTF-8') ?></description>
        <language>tr</language>
        <atom:link href="<?= htmlspecialchars($site_base . '/rss.xml', ENT_XML1, 'UTF-8') ?>" rel="self" type="application/rss+xml" />
        <lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>

        <?php foreach ($posts as $p): ?>
            <?php
            $post_url = $site_base . post_url($p['slug']);
            $summary = !empty($p['summary']) ? $p['summary'] : mb_substr(strip_tags($p['content']), 0, 300) . '...';
            ?>
            <item>
                <title><?= htmlspecialchars($p['title'], ENT_XML1, 'UTF-8') ?></title>
                <link><?= htmlspecialchars($post_url, ENT_XML1, 'UTF-8') ?></link>
                <guid><?= htmlspecialchars($post_url, ENT_XML1, 'UTF-8') ?></guid>
                <pubDate><?= date(DATE_RSS, strtotime($p['created_at'])) ?></pubDate>
                <category><?= htmlspecialchars($p['category'], ENT_XML1, 'UTF-8') ?></category>
                <description><?= htmlspecialchars($summary, ENT_XML1, 'UTF-8') ?></description>
            </item>
        <?php endforeach; ?>
    </channel>
</rss>
