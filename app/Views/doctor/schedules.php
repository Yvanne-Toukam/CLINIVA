<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Mes disponibilites</h1>
                <p class="text-secondary mb-0">Dr <?= e($doctor['first_name'] . ' ' . $doctor['last_name']) ?></p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/doctor')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Agenda</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <form method="post" action="<?= e(url('/doctor/schedules')) ?>" class="bg-white border rounded-3 p-3 p-lg-4 row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label" for="weekday">Jour</label>
                        <select class="form-select" id="weekday" name="weekday" required>
                            <?php foreach ([1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'] as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label" for="start_time">Debut</label><input class="form-control" type="time" id="start_time" name="start_time" required></div>
                    <div class="col-6"><label class="form-label" for="end_time">Fin</label><input class="form-control" type="time" id="end_time" name="end_time" required></div>
                    <div class="col-12 text-end"><button class="btn btn-primary" type="submit"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Ajouter</button></div>
                </form>
            </div>
            <div class="col-lg-7">
                <div class="bg-white border rounded-3 p-3 p-lg-4">
                    <h2 class="h5 fw-bold mb-3">Plages actuelles</h2>
                    <?php $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche']; ?>
                    <div class="table-responsive"><table class="table"><thead><tr><th>Jour</th><th>Horaire</th></tr></thead><tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr><td data-label="Jour"><?= e($days[(int) $schedule['weekday']] ?? '') ?></td><td data-label="Horaire"><?= e(substr((string) $schedule['start_time'], 0, 5)) ?> - <?= e(substr((string) $schedule['end_time'], 0, 5)) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                </div>
            </div>
        </div>
    </div>
</section>
