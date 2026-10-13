<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Dossier medical</h1>
                <p class="text-secondary mb-0"><?= e($patient['record_number']) ?> - <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/doctor')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Agenda</a>
        </div>

        <?php if ($allergies !== []): ?>
            <div class="alert alert-warning"><strong>Allergies :</strong> <?= e(implode(', ', array_map(function (array $a): string { return $a['allergen'] . ' (' . $a['severity'] . ')'; }, $allergies))) ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <h2 class="h5 fw-bold">Identite</h2>
                    <dl class="mb-0">
                        <dt class="small text-secondary">Naissance</dt><dd><?= e(date('d/m/Y', strtotime((string) $patient['birth_date']))) ?></dd>
                        <dt class="small text-secondary">Sexe</dt><dd><?= e($patient['sex']) ?></dd>
                        <dt class="small text-secondary">Telephone</dt><dd><?= e($patient['phone']) ?></dd>
                    </dl>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <h2 class="h5 fw-bold">Constantes recentes</h2>
                    <?php if ($vitals === []): ?>
                        <p class="text-secondary mb-0">Aucune constante.</p>
                    <?php else: ?>
                        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>TA</th><th>T</th><th>Pouls</th><th>SpO2</th><th>Poids</th></tr></thead><tbody>
                        <?php foreach ($vitals as $vital): ?>
                            <tr><td><?= e(date('d/m H:i', strtotime((string) $vital['recorded_at']))) ?></td><td><?= e($vital['systolic'] . '/' . $vital['diastolic']) ?></td><td><?= e($vital['temperature']) ?></td><td><?= e($vital['pulse']) ?></td><td><?= e($vital['oxygen_saturation']) ?></td><td><?= e($vital['weight_kg']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <h2 class="h5 fw-bold">Antecedents</h2>
                    <?php if ($history === []): ?><p class="text-secondary mb-0">Aucun antecedent.</p><?php endif; ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($history as $item): ?><li class="list-group-item px-0"><strong><?= e($item['type']) ?></strong> - <?= e($item['label']) ?><br><span class="text-secondary small"><?= e($item['notes'] ?? '') ?></span></li><?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <h2 class="h5 fw-bold">Consultations</h2>
                    <?php if ($consultations === []): ?><p class="text-secondary mb-0">Aucune consultation.</p><?php endif; ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($consultations as $consult): ?><li class="list-group-item px-0"><?= e(date('d/m/Y', strtotime((string) $consult['created_at']))) ?> - <?= e($consult['motive']) ?><br><span class="text-secondary small"><?= e($consult['diagnosis'] ?? '') ?></span></li><?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
