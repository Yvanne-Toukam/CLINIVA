<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Historique réception</h1>
                <p class="text-secondary mb-0">Actions récentes de votre compte.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/reception')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Accueil réception</a>
        </div>

        <?php if ($actions === []): ?>
            <p class="text-secondary">Aucune action enregistrée pour le moment.</p>
        <?php else: ?>
            <div class="table-responsive bg-white border rounded-3">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Date</th><th>Action</th><th>Élément</th><th>Adresse IP</th></tr></thead>
                    <tbody>
                    <?php foreach ($actions as $action): ?>
                        <tr>
                            <td data-label="Date"><?= e(date('d/m/Y H:i', strtotime((string) $action['created_at']))) ?></td>
                            <td data-label="Action"><?= e(str_replace('_', ' ', (string) $action['action'])) ?></td>
                            <td data-label="Élément"><?= e($action['entity_type']) ?><?= $action['entity_id'] !== null ? ' #' . e((string) $action['entity_id']) : '' ?></td>
                            <td data-label="Adresse IP"><?= e($action['ip_address'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>