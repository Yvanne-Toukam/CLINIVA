<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Médicaments et stock</h1>
                <p class="text-secondary mb-0">Catalogue partagé par l’administration et la réception. Les prescriptions déduisent le stock automatiquement.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/reception')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Accueil réception</a>
        </div>

        <section class="mb-5" aria-labelledby="medicineCreateTitle">
            <h2 class="h5 fw-bold mb-3" id="medicineCreateTitle">Ajouter un médicament</h2>
            <form method="post" action="<?= e(url('/reception/medicines')) ?>" class="bg-white border rounded-3 p-3 p-lg-4">
                <?= csrf_field() ?>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="medicine-name">Nom du médicament</label>
                        <input class="form-control" id="medicine-name" name="name" maxlength="190" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="medicine-form">Forme</label>
                        <input class="form-control" id="medicine-form" name="dosage_form" maxlength="100" placeholder="Comprimé, sirop, gélule..." required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="medicine-strength">Dosage / concentration</label>
                        <input class="form-control" id="medicine-strength" name="strength" maxlength="100" placeholder="Ex. 500 mg">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="medicine-unit">Unité de stock</label>
                        <input class="form-control" id="medicine-unit" name="stock_unit" maxlength="50" placeholder="boîte, comprimé..." required>
                    </div>
                    <div class="col-md-1 d-grid">
                        <button class="btn btn-primary" type="submit">Ajouter</button>
                    </div>
                </div>
                <p class="form-text mb-0 mt-2">Le nouveau médicament commence avec un stock nul. Enregistrez ensuite une entrée de stock.</p>
            </form>
        </section>

        <section class="mb-5" aria-labelledby="medicineCatalogTitle">
            <h2 class="h5 fw-bold mb-3" id="medicineCatalogTitle">Catalogue et stock actuel</h2>
            <?php if ($medicines === []): ?>
                <p class="text-secondary">Aucun médicament dans le catalogue.</p>
            <?php else: ?>
                <div class="table-responsive bg-white border rounded-3">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Médicament</th><th>Stock</th><th>Mouvement</th><th>Statut</th></tr></thead>
                        <tbody>
                        <?php foreach ($medicines as $medicine): ?>
                            <tr>
                                <td data-label="Médicament">
                                    <form method="post" action="<?= e(url('/reception/medicines/update')) ?>" class="d-flex flex-wrap gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="medicine_id" value="<?= e($medicine['id']) ?>">
                                        <input type="hidden" name="stock_unit" value="<?= e($medicine['stock_unit']) ?>">
                                        <input class="form-control form-control-sm" name="name" value="<?= e($medicine['name']) ?>" maxlength="190" required aria-label="Nom du médicament">
                                        <input class="form-control form-control-sm" name="dosage_form" value="<?= e($medicine['dosage_form']) ?>" maxlength="100" required aria-label="Forme du médicament">
                                        <input class="form-control form-control-sm" name="strength" value="<?= e($medicine['strength'] ?? '') ?>" maxlength="100" aria-label="Dosage ou concentration">
                                        <span class="small text-secondary align-self-center">Unité : <?= e($medicine['stock_unit']) ?></span>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Enregistrer</button>
                                    </form>
                                </td>
                                <td data-label="Stock">
                                    <strong><?= e($medicine['stock_quantity']) ?> <?= e($medicine['stock_unit']) ?></strong>
                                    <?php if ((int) $medicine['stock_quantity'] === 0): ?><span class="badge text-bg-warning ms-1">Épuisé</span><?php endif; ?>
                                </td>
                                <td data-label="Mouvement">
                                    <?php if ((int) $medicine['is_active'] === 1): ?>
                                        <form method="post" action="<?= e(url('/reception/medicines/stock')) ?>" class="d-flex flex-wrap gap-2">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="medicine_id" value="<?= e($medicine['id']) ?>">
                                            <select class="form-select form-select-sm" name="movement_type" aria-label="Type de mouvement">
                                                <option value="entree">Entrée</option>
                                                <option value="sortie">Sortie</option>
                                            </select>
                                            <input class="form-control form-control-sm" name="quantity" type="number" min="1" max="1000000" step="1" required aria-label="Quantité">
                                            <input class="form-control form-control-sm" name="reason" maxlength="255" placeholder="Motif (facultatif)" aria-label="Motif du mouvement">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Valider</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-secondary">Réactivez le médicament pour modifier son stock.</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Statut">
                                    <span class="badge <?= (int) $medicine['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                        <?= (int) $medicine['is_active'] === 1 ? 'Actif' : 'Désactivé' ?>
                                    </span>
                                    <form method="post" action="<?= e(url('/reception/medicines/status')) ?>" class="mt-2">
                                        <?= csrf_field() ?><input type="hidden" name="medicine_id" value="<?= e($medicine['id']) ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $medicine['is_active'] === 1 ? 'Désactiver' : 'Réactiver' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section aria-labelledby="medicineMovementsTitle">
            <h2 class="h5 fw-bold mb-3" id="medicineMovementsTitle">Mouvements récents</h2>
            <?php if ($stockMovements === []): ?>
                <p class="text-secondary">Aucun mouvement de stock enregistré.</p>
            <?php else: ?>
                <div class="table-responsive bg-white border rounded-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Date</th><th>Médicament</th><th>Type</th><th>Quantité</th><th>Stock après</th><th>Motif</th><th>Enregistré par</th></tr></thead>
                        <tbody>
                        <?php foreach ($stockMovements as $movement): ?>
                            <?php
                            $movementLabel = [
                                'entree' => 'Entrée',
                                'sortie' => 'Sortie manuelle',
                                'ordonnance' => 'Prescription médecin',
                                'retour' => 'Retour après annulation',
                            ][$movement['movement_type']] ?? $movement['movement_type'];
                            ?>
                            <tr>
                                <td data-label="Date"><?= e(date('d/m/Y H:i', strtotime((string) $movement['created_at']))) ?></td>
                                <td data-label="Médicament"><?= e($movement['medicine_name']) ?></td>
                                <td data-label="Type"><?= e($movementLabel) ?></td>
                                <td data-label="Quantité"><?= e($movement['quantity']) ?> <?= e($movement['quantity_unit']) ?></td>
                                <td data-label="Stock après"><?= e($movement['stock_after']) ?> <?= e($movement['quantity_unit']) ?></td>
                                <td data-label="Motif"><?= e($movement['reason'] ?: '—') ?></td>
                                <td data-label="Enregistré par"><?= e(trim(($movement['first_name'] ?? '') . ' ' . ($movement['last_name'] ?? '')) ?: 'Système') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
