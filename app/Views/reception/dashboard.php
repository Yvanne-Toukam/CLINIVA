<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Bienvenue <?= e(trim((string) current_user()['first_name'] . ' ' . (string) current_user()['last_name'])) ?></h1>
                <p class="text-secondary mb-0">Accueil, triage, horaires et encaissements.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary" href="<?= e(url('/reception/patients/new')) ?>"><i class="bi bi-person-plus" aria-hidden="true"></i> Patient</a>
                <a class="btn btn-outline-primary" href="<?= e(url('/reception/appointments/new')) ?>"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Rendez-vous</a>
                <a class="btn btn-outline-primary" href="<?= e(url('/reception/triage')) ?>"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Triage</a>
                <a class="btn btn-outline-primary" href="<?= e(url('/reception/billing')) ?>"><i class="bi bi-receipt" aria-hidden="true"></i> Facturation</a>
                <a class="btn btn-outline-primary" href="<?= e(url('/reception/medicines')) ?>"><i class="bi bi-capsule" aria-hidden="true"></i> Médicaments et stock</a>
                <a class="btn btn-outline-primary" href="<?= e(url('/reception/history')) ?>"><i class="bi bi-clock-history" aria-hidden="true"></i> Historique</a>
            </div>
        </div>

        <?php if ($doctorAlerts !== []): ?>
            <section class="mb-4" aria-labelledby="doctorAlertsTitle">
                <h2 class="h5 fw-bold mb-3" id="doctorAlertsTitle">Alertes médecins</h2>
                <div class="table-responsive bg-white border rounded-3">
                    <table class="table align-middle mb-0"><thead><tr><th>Médecin</th><th>Rendez-vous</th><th>Motif</th><th>Reçu</th><th></th></tr></thead><tbody>
                    <?php foreach ($doctorAlerts as $alert): ?>
                        <tr>
                            <td data-label="Médecin">Dr <?= e($alert['doctor_first_name'] . ' ' . $alert['doctor_last_name']) ?></td>
                            <td data-label="Rendez-vous"><?= e(date('d/m/Y H:i', strtotime((string) $alert['start_datetime']))) ?><br><small><?= e($alert['record_number'] . ' · ' . $alert['patient_first_name'] . ' ' . $alert['patient_last_name']) ?></small></td>
                            <td data-label="Motif"><?= nl2br(e($alert['message']), false) ?></td>
                            <td data-label="Reçu"><?= e(date('d/m/Y H:i', strtotime((string) $alert['created_at']))) ?></td>
                            <td data-label="Actions"><form method="post" action="<?= e(url('/reception/doctor-alerts/resolve')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="alert_id" value="<?= e($alert['id']) ?>">
                                <button class="btn btn-sm btn-outline-success" type="submit">Traiter</button>
                            </form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
            </section>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="hero-visual reception-dashboard-hero">
                    <img src="<?= e(asset('images/image.png')) ?>" alt="Outils numériques utilisés dans un environnement médical" class="img-fluid">
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="stat-card"><span class="stat-label">Rendez-vous à valider</span><div class="stat-value"><?= e(count($pendingValidations)) ?></div></div></div>
            <div class="col-md-4"><div class="stat-card accent"><span class="stat-label">Patients du jour</span><div class="stat-value"><?= e(count(array_unique(array_column($todayAppointments, 'patient_id')))) ?></div></div></div>
            <div class="col-md-4"><div class="stat-card warning"><span class="stat-label">File d’attente</span><div class="stat-value"><?= e(count($queue)) ?></div></div></div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 fw-bold mb-0">File d'attente</h2>
                        <span class="status-badge bg-light text-primary border"><?= count($queue) ?></span>
                    </div>
                    <div id="queueList" data-url="<?= e(url('/reception/queue')) ?>">
                        <?php require BASE_PATH . '/app/Views/reception/partials_queue.php'; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <h2 class="h5 fw-bold mb-3">Rendez-vous du jour</h2>
                    <?php if ($todayAppointments === []): ?>
                        <p class="text-secondary mb-0">Aucun rendez-vous aujourd'hui.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($todayAppointments as $appointment): ?>
                                <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                    <span><?= e(date('H:i', strtotime((string) $appointment['start_datetime']))) ?> - <?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></span>
                                    <span class="badge text-bg-light border"><?= e($appointment['status']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <h2 class="h5 fw-bold mb-3">Patients recents</h2>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentPatients as $patient): ?>
                            <li class="list-group-item px-0">
                                <strong><?= e($patient['record_number']) ?></strong> - <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?>
                                <span class="text-secondary small"><?= e($patient['phone']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <h2 class="h5 fw-bold mb-3">Factures recentes</h2>
                    <?php if ($recentInvoices === []): ?>
                        <p class="text-secondary mb-0">Aucune facture recente.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentInvoices as $invoice): ?>
                                <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                    <span><?= e($invoice['invoice_number']) ?> - <?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?></span>
                                    <strong><?= e(number_format((int) $invoice['total_xaf'], 0, ',', ' ')) ?> FCFA</strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="<?= e(asset('js/reception.js')) ?>"></script>
