<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Dashboard administrateur</h1>
                <p class="text-secondary mb-0">Suivi des consultations, de la performance et de la sécurité.</p>
            </div>
            <span class="status-badge bg-white text-primary border">Admin</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <div class="small text-secondary">Patients</div>
                    <div class="h3 mb-0 fw-bold"><?= e((int) $stats['patients']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <div class="small text-secondary">Médecins</div>
                    <div class="h3 mb-0 fw-bold"><?= e((int) $stats['doctors']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bg-white border rounded-3 p-3 h-100">
                    <div class="small text-secondary">Consultations aujourd'hui</div>
                    <div class="h3 mb-0 fw-bold"><?= e((int) $stats['consultations_today']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
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
                    <canvas id="adminChart" height="120"></canvas>
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

        <div class="bg-white border rounded-3 p-3 p-lg-4">
            <h2 class="h5 fw-bold mb-3">Journal d'audit</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Utilisateur</th>
                            <th>Entité</th>
                            <th>Heure</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><?= e($log['action']) ?></td>
                                <td><?= e(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'Système' ?></td>
                                <td><?= e($log['entity_type']) ?> #<?= e((string) ($log['entity_id'] ?? '')) ?></td>
                                <td><?= e(date('d/m/Y H:i', strtotime((string) $log['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
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
</script>
