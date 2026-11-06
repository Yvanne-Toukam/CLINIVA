<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Mes rendez-vous</h1>
                <p class="text-secondary mb-0">Dr <?= e($doctor['first_name'] . ' ' . $doctor['last_name']) ?> · <?= e($doctor['specialty']) ?></p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/doctor')) ?>"><i class="bi bi-calendar3" aria-hidden="true"></i> Agenda du jour</a>
        </div>

        <form method="get" action="<?= e(url('/doctor/appointments')) ?>" class="row g-3 align-items-end mb-4">
            <div class="col-sm-5 col-lg-3">
                <label class="form-label" for="appointment-date-filter">Date</label>
                <input class="form-control" id="appointment-date-filter" name="date" type="date" value="<?= e($dateFilter) ?>">
            </div>
            <div class="col-sm-5 col-lg-3">
                <label class="form-label" for="appointment-status-filter">Statut</label>
                <select class="form-select" id="appointment-status-filter" name="status">
                    <option value="">Tous les statuts</option>
                    <?php foreach (['en_attente_validation', 'reporte', 'confirme', 'arrive', 'en_consultation', 'termine', 'annule', 'absent'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2"><button class="btn btn-primary" type="submit">Filtrer</button></div>
        </form>

        <?php if ($appointments === []): ?>
            <p class="text-secondary">Aucun rendez-vous ne correspond à ces filtres.</p>
        <?php else: ?>
            <div class="table-responsive bg-white border rounded-3">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Date</th><th>Patient</th><th>Spécialité</th><th>Motif</th><th>Statut</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td data-label="Date"><?= e(date('d/m/Y H:i', strtotime((string) $appointment['start_datetime']))) ?></td>
                            <td data-label="Patient"><strong><?= e($appointment['record_number']) ?></strong><br><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></td>
                            <td data-label="Spécialité"><?= e($appointment['specialty']) ?></td>
                            <td data-label="Motif"><?= e($appointment['reason'] ?? '') ?></td>
                            <td data-label="Statut"><?= e(str_replace('_', ' ', $appointment['status'])) ?></td>
                            <td data-label="Actions">
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/doctor/record?patient_id=' . (int) $appointment['patient_id'])) ?>">Dossier</a>
                                    <?php if (in_array($appointment['status'], ['confirme', 'arrive'], true)): ?>
                                        <form method="post" action="<?= e(url('/doctor/consultations/start')) ?>">
                                            <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                            <button class="btn btn-sm btn-primary" type="submit">Commencer</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($appointment['status'], ['en_attente_validation', 'reporte', 'confirme'], true)): ?>
                                        <details>
                                            <summary class="btn btn-sm btn-outline-warning">Signaler</summary>
                                            <form method="post" action="<?= e(url('/doctor/appointments/impediment')) ?>" class="mt-2">
                                                <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                                <label class="form-label small" for="impediment-filtered-<?= e($appointment['id']) ?>">Motif</label>
                                                <textarea class="form-control form-control-sm mb-2" id="impediment-filtered-<?= e($appointment['id']) ?>" name="message" rows="2" maxlength="1000" required></textarea>
                                                <button class="btn btn-sm btn-warning" type="submit">Transmettre</button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>