    </main>
</div>
<div class="toast-stack" id="toastStack">
<?php foreach (all_flashes() as $flash): ?>
    <div class="toast <?= e($flash['type'] ?? 'info') ?>"><div>●</div><div><strong>MediFlow</strong><p><?= e($flash['message'] ?? '') ?></p></div></div>
<?php endforeach; ?>
</div>
<script>window.MediFlowConfig={baseUrl:<?= json_encode(url()) ?>,apiUrl:<?= json_encode(url('api.php')) ?>};</script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
