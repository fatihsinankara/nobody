<?php
/**
 * Altbilgi (Footer) Şablonu
 */
if (!defined('APP_INIT')) die('Doğrudan erişim engellendi.');

global $page_start_time, $current_hits;
$time_spent = microtime(true) - ($page_start_time ?? microtime(true));
$mem_used = round(memory_get_peak_usage() / 1024, 1);
?>
        </main> <!-- /#content -->

        <?php if (!defined('NO_SIDEBAR')): ?>
            <?php include __DIR__ . '/sidebar.php'; ?>
        <?php endif; ?>

    </div> <!-- /#main-layout -->

    <!-- Altbilgi Alanı -->
    <footer id="footer">
        
        <!-- Sayaç (Tekil Ziyaretçi) -->
        <div style="margin-bottom: 8px;">
            Tekil Ziyaret: <?= render_counter($current_hits ?? 1) ?>
            &nbsp;<span style="font-size: 11px; font-family: monospace; color: #555;">(Bugün: <?= (int)($today_hits ?? 1) ?>)</span>
        </div>

        <!-- 2000'ler İkonik Rozetleri (Retro Badges) -->
        <div class="footer-badges">
            <span class="retro-badge"><span class="badge-left">VIEWED IN</span> <span class="badge-right">1024x768</span></span>
            <span class="retro-badge"><span class="badge-left">AUDIO</span> <span class="badge-right">HEADPHONES ♫</span></span>
            <span class="retro-badge"><span class="badge-left">VALID</span> <span class="badge-right">HTML 4.01</span></span>
            <a href="/rss.xml" class="retro-badge" target="_blank"><span class="badge-left">XML</span> <span class="badge-right">RSS 2.0</span></a>
        </div>

        <div class="footer-info">
            &copy; 2000 &ndash; <?= date('Y') ?> <strong><?= e(get_setting('webmaster_name', 'nobody')) ?></strong>. Tüm hakları saklıdır.<br>
            Sayfa <span style="color:#006600; font-weight:bold;"><?= number_format($time_spent, 4) ?></span> saniyede oluşturuldu.
        </div>
    </footer>

</div> <!-- /#container -->

</body>
</html>
