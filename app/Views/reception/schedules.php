<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Horaires medecins</h1>
                <p class="text-secondary mb-0">Plages recurrentes et exceptions.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/reception')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <form method="post" action="<?= e(url('/reception/schedules')) ?>" class="bg-white border rounded-3 p-3 p-lg-4 row g-3">
                    <?= csrf_field() ?>
                    <h2 class="h5 fw-bold">Ajouter une plage</h2>
                    <div class="col-12">
                        <label class="form-label" for="doctor_id">Medecin</label>
                        <select class="form-select" id="doctor_id" name="doctor_id" required>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= e($doctor['id']) ?>">Dr <?= e($doctor['first_name'] . ' ' . $doctor['last_name']) ?> - <?= e($doctor['specialty']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="weekday">Jour</label>
                        <select class="form-select" id="weekday" name="weekday" required>
                            <?php foreach ([1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'] as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="start_time">Debut</label>
                        <input class="form-control" type="time" id="start_time" name="start_time" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="end_time">Fin</label>
                        <input class="form-control" type="time" id="end_time" name="end_time" required>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Ajouter</button>
                    </div>
                </form>

                <form method="post" action="<?= e(url('/reception/time-off')) ?>" class="bg-white border rounded-3 p-3 p-lg-4 row g-3 mt-3">
                    <?= csrf_field() ?>
                    <h2 class="h5 fw-bold">Exception ou conge</h2>
                    <div class="col-12">
                        <label class="form-label" for="off_doctor_id">Medecin</label>
                        <select class="form-select" id="off_doctor_id" name="doctor_id" required>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?= e($doctor['id']) ?>">Dr <?= e($doctor['first_name'] . ' ' . $doctor['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="starts_at">Debut</label>
                        <input class="form-control" type="datetime-local" id="starts_at" name="starts_at" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ends_at">Fin</label>
                        <input class="form-control" type="datetime-local" id="ends_at" name="ends_at" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="reason">Motif</label>
                        <input class="form-control" id="reason" name="reason">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-calendar-x" aria-hidden="true"></i> Enregistrer</button>
                    </div>
                </form>
            </div>
            <div class="col-lg-7">
                <div class="bg-white border rounded-3 p-3 p-lg-4">
                    <h2 class="h5 fw-bold mb-3">Plages existantes</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Medecin</th><th>Jour</th><th>Horaire</th></tr></thead>
                            <tbody>
                            <?php $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche']; ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td data-label="Médecin">Dr <?= e($schedule['first_name'] . ' ' . $schedule['last_name']) ?></td>
                                    <td data-label="Jour"><?= e($days[(int) $schedule['weekday']] ?? '') ?></td>
                                    <td data-label="Horaire"><?= e(substr((string) $schedule['start_time'], 0, 5)) ?> - <?= e(substr((string) $schedule['end_time'], 0, 5)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
