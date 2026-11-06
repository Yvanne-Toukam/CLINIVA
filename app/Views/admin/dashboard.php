<section class="py-4 admin-dashboard">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Dashboard administrateur</h1>
                <p class="text-secondary mb-0">Suivi des consultations, de la performance et de la sécurité.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/admin/manage')) ?>"><i class="bi bi-gear" aria-hidden="true"></i> Gestion</a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <div class="small text-secondary">Patients</div>
                    <div class="h3 mb-0 fw-bold"><?= e((int) $stats['patients']) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <div class="small text-secondary">Médecins</div>
                    <div class="h3 mb-0 fw-bold"><?= e((int) $stats['doctors']) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <div class="small text-secondary">Consultations aujourd'hui</div>
                    <div class="h3 mb-0 fw-bold"><?= e((int) $stats['consultations_today']) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <div class="small text-secondary">Recettes du mois</div>
                    <div class="h3 mb-0 fw-bold"><?= e(number_format((int) $stats['revenue_month'], 0, ',', ' ')) ?> XAF</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <h2 class="h5 fw-bold mb-3">Consultations sur 7 jours</h2>
                    <div class="admin-consultation-chart">
                        <canvas id="adminChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <h2 class="h5 fw-bold mb-3">KPI</h2>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 d-flex justify-content-between"><span>Rendez-vous aujourd'hui</span><strong><?= e((int) $stats['appointments_today']) ?></strong></li>
                        <li class="list-group-item px-0 d-flex justify-content-between"><span>Factures en attente</span><strong><?= e((int) $stats['pending_payments']) ?></strong></li>
                    </ul>
                </div>
            </div>
        </div>

        <section class="mb-4" aria-labelledby="analyticsTitle">
            <h2 class="h5 fw-bold mb-3" id="analyticsTitle">Activité des 30 derniers jours</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="bg-white border rounded-3 p-3"><div class="small text-secondary">Attente moyenne avant appel</div><strong class="h4 mb-0"><?= e(number_format((float) $analytics['average_wait_minutes'], 1, ',', ' ')) ?> min</strong></div></div>
                <div class="col-md-4"><div class="bg-white border rounded-3 p-3"><div class="small text-secondary">Annulations</div><strong class="h4 mb-0"><?= e($analytics['appointments']['cancelled']) ?> / <?= e($analytics['appointments']['total']) ?></strong></div></div>
                <div class="col-md-4"><div class="bg-white border rounded-3 p-3"><div class="small text-secondary">Absences</div><strong class="h4 mb-0"><?= e($analytics['appointments']['missed']) ?> / <?= e($analytics['appointments']['total']) ?></strong></div></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <div class="bg-white border rounded-3 p-3 h-100">
                        <h3 class="h6 fw-bold">Médecins les plus consultés</h3>
                        <ol class="mb-0">
                            <?php foreach ($analytics['most_consulted'] as $doctor): ?>
                                <li><?= e('Dr ' . $doctor['first_name'] . ' ' . $doctor['last_name']) ?> <span class="text-secondary">· <?= e($doctor['consultations_30d']) ?> consultations</span></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="bg-white border rounded-3 p-3 h-100">
                        <h3 class="h6 fw-bold">Médecins les moins consultés</h3>
                        <ol class="mb-0">
                            <?php foreach ($analytics['least_consulted'] as $doctor): ?>
                                <li><?= e('Dr ' . $doctor['first_name'] . ' ' . $doctor['last_name']) ?> <span class="text-secondary">· <?= e($doctor['consultations_30d']) ?> consultations</span></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="bg-white border rounded-3 p-3 h-100">
                        <h3 class="h6 fw-bold">Occupation estimée par médecin</h3>
                        <p class="small text-secondary">Calcul sur les horaires récurrents; les congés ponctuels ne sont pas déduits.</p>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Médecin</th><th>Rendez-vous</th><th>Occupation</th></tr></thead><tbody>
                            <?php foreach ($analytics['occupancy'] as $doctor): ?>
                                <tr><td data-label="Médecin"><?= e('Dr ' . $doctor['first_name'] . ' ' . $doctor['last_name']) ?></td><td data-label="Rendez-vous"><?= e($doctor['appointments_30d']) ?></td><td data-label="Occupation"><?= $doctor['occupancy_percent'] === null ? 'Non calculable' : e(number_format((float) $doctor['occupancy_percent'], 1, ',', ' ') . ' %') ?></td></tr>
                            <?php endforeach; ?>
                        </tbody></table></div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="bg-white border rounded-3 p-3 h-100">
                        <h3 class="h6 fw-bold">Consultations par spécialité</h3>
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach ($analytics['specialties'] as $specialty): ?><li class="list-group-item px-0 d-flex justify-content-between"><span><?= e($specialty['name']) ?></span><strong><?= e($specialty['total']) ?></strong></li><?php endforeach; ?>
                        </ul>
                        <h3 class="h6 fw-bold">Motifs fréquents</h3>
                        <ol class="mb-0"><?php foreach ($analytics['motives'] as $motive): ?><li><?= e($motive['motive']) ?> <span class="text-secondary">(<?= e($motive['total']) ?>)</span></li><?php endforeach; ?></ol>
                    </div>
                </div>
            </div>
            <div class="bg-white border rounded-3 p-3 p-lg-4 mt-3">
                <h3 class="h6 fw-bold mb-3">Recettes mensuelles</h3>
                <canvas id="adminRevenueChart" height="100"></canvas>
            </div>
        </section>

        <div class="bg-white border rounded-3 p-3 p-lg-4">
            <h2 class="h5 fw-bold mb-1">Dernières actions administratives</h2>
            <p class="small text-secondary mb-3">Résumé des actions récentes et des éléments concernés.</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0 admin-audit-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Détails</th>
                            <th>Utilisateur</th>
                            <th>Entité</th>
                            <th>Heure</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td data-label="Action" class="audit-action-cell"><?= e($log['action_label']) ?></td>
                                <td data-label="Détails" class="audit-details-cell"><?= e($log['details_label']) ?></td>
                                <td data-label="Utilisateur"><?= e(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'Système' ?></td>
                                <td data-label="Entité"><?= e($log['entity_label']) ?></td>
                                <td data-label="Heure"><time datetime="<?= e(date(DATE_ATOM, strtotime((string) $log['created_at']))) ?>"><?= e(date('d/m/Y H:i', strtotime((string) $log['created_at']))) ?></time></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($auditLogs === []): ?><tr><td colspan="5" class="text-center text-secondary py-4">Aucune action n’a encore été enregistrée.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('adminChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart['labels']) ?>,
                datasets: [{
                    label: 'Consultations',
                    data: <?= json_encode($chart['values']) ?>,
                    borderColor: '#0B1F3A',
                    backgroundColor: 'rgba(11,31,58,0.15)',
                    tension: 0.35,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
    const revenueCtx = document.getElementById('adminRevenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($analytics['revenue']['labels']) ?>,
                datasets: [{
                    label: 'Recettes XAF',
                    data: <?= json_encode($analytics['revenue']['values']) ?>,
                    backgroundColor: '#C9A227'
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    }
</script>
