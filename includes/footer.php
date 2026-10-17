    </main>
</div>

<?php
// Cuối mỗi trang, tuỳ chọn: $pageData = [...] (PHP → JS: window.PAGE_DATA), $pageJs = ['ten.js']
$pageData = $pageData ?? null;
$pageJs   = $pageJs   ?? [];
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/layout.js') ?>"></script>
<script src="<?= asset('js/charts-default.js') ?>"></script>
<?php if ($pageData !== null): ?>
<script>window.PAGE_DATA = <?= json_encode($pageData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php endif; ?>
<?php foreach ($pageJs as $js): ?>
<script src="<?= asset('js/pages/' . $js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
