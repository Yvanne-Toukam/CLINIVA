<section class="py-4">
    <div class="container">
        <div class="doctor-header card-cliniva mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <span class="eyebrow">Espace médecin</span>
                    <h1 class="h3 fw-bold text-primary mb-1">Agenda du jour</h1>
                    <p class="text-secondary mb-0">Dr <?= e($doctor['first_name'] . ' ' . $doctor['last_name']) ?> - <?= e($doctor['specialty']) ?></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary" href="<?= e(url('/doctor/schedules')) ?>"><i class="bi bi-clock" aria-hidden="true"></i> Mes disponibilites</a>
                </div>
            </div>
        </div>

        <?php if ($activeConsultations !== []): ?>
            <div class="alert alert-info mb-4">
                <strong>Consultation(s) non clôturée(s)</strong>
                <p class="mb-2">Une seule consultation peut être menée à la fois. Clôturez une consultation en cours avant d’en démarrer une autre.</p>
                <ul class="mb-0">
                    <?php foreach ($activeConsultations as $activeConsultation): ?>
                        <li>
                            <?= e($activeConsultation['record_number'] . ' - ' . $activeConsultation['first_name'] . ' ' . $activeConsultation['last_name']) ?>
                            · démarrée le <?= e(date('d/m/Y H:i', strtotime((string) $activeConsultation['created_at']))) ?>
                            · <a href="<?= e(url('/doctor/consultation?id=' . $activeConsultation['id'])) ?>">Reprendre ou clôturer</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <span class="stat-label">Rendez-vous aujourd’hui</span>
                    <div class="stat-value"><?= e((int) $stats['today_total']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card accent">
                    <span class="stat-label">Patients traités</span>
                    <div class="stat-value"><?= e((int) $stats['completed_total']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <span class="stat-label">Rendez-vous à venir</span>
                    <div class="stat-value"><?= e((int) $stats['upcoming_total']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <span class="stat-label">File d'attente</span>
                    <div class="stat-value"><?= e(count($queue)) ?></div>
                </div>
            </div>
        </div>

        <div class="bg-white border rounded-3 p-3 p-lg-4 mb-4">
            <h2 class="h5 fw-bold mb-3">Consultations sur 7 jours</h2>
            <canvas id="doctorConsultationTrend" height="100"></canvas>
        </div>

        <div class="quick-nav card-cliniva mb-4">
            <div class="d-flex flex-wrap gap-2">
                <span class="chip active">Agenda</span>
                <a class="chip" href="<?= e(url('/doctor/schedules')) ?>">Disponibilités</a>
                <a class="chip" href="<?= e(url('/doctor')) ?>">Rendez-vous</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card-cliniva h-100 p-4">
                    <h2 class="h5 fw-bold mb-3">Patients prevus</h2>
                    <?php if ($appointments === []): ?>
                        <p class="text-secondary mb-0">Aucun rendez-vous aujourd'hui.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle clinician-table">
                                <thead><tr><th>Heure</th><th>Patient</th><th>Statut</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td data-label="Heure"><?= e(date('H:i', strtotime((string) $appointment['start_datetime']))) ?></td>
                                        <td data-label="Patient">
                                            <strong><?= e($appointment['record_number']) ?></strong><br>
                                            <?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?>
                                        </td>
                                        <td data-label="Statut"><span class="status-badge bg-light text-primary border"><?= e($appointment['status']) ?></span></td>
                                        <td data-label="Actions" class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/doctor/record?patient_id=' . $appointment['patient_id'])) ?>"><i class="bi bi-folder2-open" aria-hidden="true"></i></a>
                                            <?php $appointmentHasOpenConsultation = in_array((int) $appointment['id'], array_map(static fn(array $item): int => (int) $item['appointment_id'], $activeConsultations), true); ?>
                                            <?php $appointmentHasStarted = strtotime((string) $appointment['start_datetime']) <= time(); ?>
                                            <form method="post" action="<?= e(url('/doctor/consultations/start')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                                <button class="btn btn-sm btn-primary" type="submit" <?= !$appointmentHasStarted ? 'disabled title="Le rendez-vous ne peut pas commencer avant l’heure prévue."' : ($activeConsultations !== [] && !$appointmentHasOpenConsultation ? 'disabled title="Clôturez la consultation en cours avant d’en démarrer une autre."' : '') ?>><i class="bi bi-stethoscope" aria-hidden="true"></i></button>
                                                <?php if (!$appointmentHasStarted): ?>
                                                    <span class="d-block small text-secondary mt-1">Démarrage à <?= e(date('H:i', strtotime((string) $appointment['start_datetime']))) ?></span>
                                                <?php endif; ?>
                                            </form>
                                            <?php if (in_array($appointment['status'], ['en_attente_validation', 'reporte', 'confirme'], true)): ?>
                                                <details class="mt-2 text-start">
                                                    <summary class="btn btn-sm btn-outline-warning">Signaler un empêchement</summary>
                                                    <form method="post" action="<?= e(url('/doctor/appointments/impediment')) ?>" class="mt-2">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                                        <label class="form-label small" for="impediment-<?= e($appointment['id']) ?>">Motif transmis à la réception</label>
                                                        <textarea class="form-control form-control-sm mb-2" id="impediment-<?= e($appointment['id']) ?>" name="message" rows="2" maxlength="1000" required></textarea>
                                                        <button class="btn btn-sm btn-warning" type="submit">Transmettre</button>
                                                    </form>
                                                </details>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card-cliniva h-100 p-4">
                    <h2 class="h5 fw-bold mb-3">Calendrier</h2>
                    <div id="doctorCalendar" data-events-url="<?= e(url('/doctor/calendar/events')) ?>" data-move-url="<?= e(url('/doctor/calendar/move')) ?>"></div>
                </div>
            </div>
        </div>
    </div>
</section>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
    const doctorConsultationTrend = document.getElementById('doctorConsultationTrend');
    if (doctorConsultationTrend) {
        new Chart(doctorConsultationTrend, {
            type: 'bar',
            data: {
                labels: <?= json_encode($consultationTrend['labels']) ?>,
                datasets: [{
                    label: 'Consultations',
                    data: <?= json_encode($consultationTrend['values']) ?>,
                    backgroundColor: '#C9A227'
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }
</script>
<script src="<?= e(asset('js/doctor.js')) ?>"></script>
