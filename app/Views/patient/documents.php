<?php
$groups = [
    ['title' => 'Ordonnances', 'icon' => 'bi-prescription2', 'type' => 'prescription', 'items' => $prescriptions],
    ['title' => 'Certificats', 'icon' => 'bi-file-earmark-text', 'type' => 'certificate', 'items' => $certificates],
    ['title' => 'Résultats d’examens', 'icon' => 'bi-clipboard2-pulse', 'type' => 'exam-result', 'items' => $examResults],
    ['title' => 'Factures', 'icon' => 'bi-receipt', 'type' => 'invoice', 'items' => $invoices],
    ['title' => 'Autres documents', 'icon' => 'bi-folder2-open', 'type' => 'document', 'items' => $documents],
];
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div><h1 class="h3 fw-bold text-primary mb-1">Mes documents</h1><p class="text-secondary mb-0">Documents de santé disponibles dans votre dossier.</p></div>
            <a class="btn btn-outline-primary" href="<?= e(url('/patient')) ?>">Retour à l’accueil</a>
        </div>
        <div class="row g-3">
            <?php foreach ($groups as $group): ?>
                <div class="col-12 col-lg-6">
                    <section class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                        <h2 class="h5 fw-bold mb-3"><i class="bi <?= e($group['icon']) ?> text-primary me-2" aria-hidden="true"></i><?= e($group['title']) ?></h2>
                        <?php if ($group['items'] === []): ?>
                            <p class="text-secondary mb-0">Aucun document disponible.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($group['items'] as $item): ?>
                                    <?php
                                    if ($group['type'] === 'prescription') {
                                        $label = 'Ordonnance ' . $item['prescription_number'];
                                        $date = $item['created_at'];
                                        $detail = 'Dr ' . $item['doctor_first_name'] . ' ' . $item['doctor_last_name'];
                                    } elseif ($group['type'] === 'certificate') {
                                        $label = 'Certificat ' . $item['certificate_number'];
                                        $date = $item['created_at'];
                                        $detail = $item['type'];
                                    } elseif ($group['type'] === 'exam-result') {
                                        $label = $item['label'];
                                        $date = $item['validated_at'] ?: $item['created_at'];
                                        $detail = 'Résultat ' . $item['type'];
                                    } elseif ($group['type'] === 'invoice') {
                                        $label = 'Facture ' . $item['invoice_number'];
                                        $date = $item['issued_at'] ?: $item['created_at'];
                                        $detail = number_format((int) $item['total_xaf'], 0, ',', ' ') . ' FCFA · ' . $item['status'];
                                    } else {
                                        $label = $item['original_name'];
                                        $date = $item['created_at'];
                                        $detail = $item['category'];
                                    }
                                    ?>
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-3">
                                        <div class="min-w-0">
                                            <strong class="d-block text-break"><?= e($label) ?></strong>
                                            <span class="small text-secondary"><?= e($detail) ?> · <?= e(date('d/m/Y', strtotime((string) $date))) ?></span>
                                            <?php if ($group['type'] === 'prescription'): ?>
                                                <ul class="small text-secondary mb-0 mt-2 ps-3">
                                                    <?php foreach ($item['items'] as $prescriptionItem): ?>
                                                        <li>
                                                            <?php if (($prescriptionItem['status'] ?? 'active') === 'annulee'): ?><strong class="text-secondary">Annulée — </strong><?php endif; ?>
                                                            <?= e($prescriptionItem['medicine_name']) ?>
                                                            <?php if (!empty($prescriptionItem['strength'])): ?> · <?= e($prescriptionItem['strength']) ?><?php endif; ?>
                                                            <?php if (!empty($prescriptionItem['dosage_form'])): ?> · <?= e($prescriptionItem['dosage_form']) ?><?php endif; ?>
                                                            — <?= e($prescriptionItem['dosage']) ?>, <?= e($prescriptionItem['frequency']) ?>, <?= e($prescriptionItem['duration']) ?>
                                                            <?php if ($prescriptionItem['quantity'] !== null): ?> · Quantité : <?= e($prescriptionItem['quantity']) ?> <?= e($prescriptionItem['quantity_unit']) ?><?php endif; ?>
                                                            <?php if (!empty($prescriptionItem['instructions'])): ?> · <?= e($prescriptionItem['instructions']) ?><?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        <a class="btn btn-sm btn-outline-primary flex-shrink-0" href="<?= e(url('/patient/documents/download?type=' . rawurlencode($group['type']) . '&id=' . (int) $item['id'])) ?>" aria-label="<?= e('Télécharger ' . $label) ?>">
                                            <i class="bi bi-download" aria-hidden="true"></i><span class="d-none d-sm-inline"> Télécharger</span>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
