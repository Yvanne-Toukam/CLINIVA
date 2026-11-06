<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h1 class="h3 fw-bold text-primary mb-1">Mon historique</h1><p class="text-secondary mb-0">Rendez-vous passés et consultations de votre dossier.</p></div>
            <a class="btn btn-outline-primary" href="<?= e(url('/patient')) ?>">Retour à l’accueil</a>
        </div>
        <div class="bg-white border rounded-3 p-3 p-lg-4">
            <?php if ($items === []): ?>
                <p class="text-secondary mb-0">Aucun rendez-vous ou consultation antérieur.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Date</th><th>Type</th><th>Objet</th><th>Détail</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td data-label="Date"><time datetime="<?= e(date(DATE_ATOM, strtotime((string) $item['happened_at']))) ?>"><?= e(date('d/m/Y H:i', strtotime((string) $item['happened_at']))) ?></time></td>
                                <td data-label="Type"><span class="badge text-bg-light border"><?= e($item['item_type']) ?></span></td>
                                <td data-label="Objet"><?= e($item['title'] ?: '—') ?></td>
                                <td data-label="Détail"><?= e($item['detail']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Pagination de l’historique" class="d-flex justify-content-between align-items-center">
                    <span class="small text-secondary">Page <?= e($page) ?> sur <?= e($pageCount) ?> · <?= e($total) ?> entrée(s)</span>
                    <div class="btn-group">
                        <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/patient/history?page=' . ($page - 1))) ?>">Précédent</a><?php endif; ?>
                        <?php if ($page < $pageCount): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/patient/history?page=' . ($page + 1))) ?>">Suivant</a><?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</section>
