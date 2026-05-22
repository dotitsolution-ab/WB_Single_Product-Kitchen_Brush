<?php

declare(strict_types=1);

$hideFooter = $hideFooter ?? false;
?>
</main>
<?php if (!$hideFooter): ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <span>&copy; <?= e(date('Y')) ?> <?= e(setting('site_name', app_config('app.name', 'Store'))) ?></span>
        <span>Support: <?= e(setting('contact_phone', '01700000000')) ?></span>
    </div>
</footer>
<?php endif; ?>
<?php render_whatsapp_button(); ?>
<script src="<?= e(asset_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
