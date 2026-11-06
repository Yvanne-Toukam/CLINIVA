<?php
$errors = form_errors();
$editId = (int) old('appointment_id', $editAppointment['id'] ?? 0);
$selectedSpecialty = old('specialty_id', $editAppointment['specialty_id'] ?? '');
$selectedDoctor = old('doctor_id', $editAppointment['doctor_id'] ?? '');
$selectedStart = old('start_datetime', $editAppointment['start_datetime'] ?? '');
$selectedReason = old('reason', $editAppointment['reason'] ?? '');
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1"><?= $editId > 0 ? 'Modifier mon rendez-vous' : 'Prendre rendez-vous' ?></h1>
                <p class="text-secondary mb-0">Choisissez une spécialité, un médecin disponible puis un créneau.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/patient')) ?>">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Retour
            </a>
        </div>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url($editId > 0 ? '/patient/appointments/reschedule' : '/patient/appointments')) ?>" id="appointmentForm" class="bg-white border rounded-3 p-3 p-lg-4" data-doctors-url="<?= e(relative_url('/patient/appointments/doctors')) ?>" data-slots-url="<?= e(relative_url('/patient/appointments/slots')) ?>" data-selected-doctor="<?= e($selectedDoctor) ?>" data-selected-start="<?= e($selectedStart) ?>" data-ignore-appointment-id="<?= $editId ?>" data-selected-date="<?= e($selectedStart !== '' ? date('Y-m-d', strtotime((string) $selectedStart)) : '') ?>">
            <?= csrf_field() ?>
            <?php if ($editId > 0): ?><input type="hidden" name="appointment_id" value="<?= $editId ?>"><?php endif; ?>
            <input type="hidden" name="start_datetime" id="start_datetime" value="<?= e($selectedStart) ?>">

            <div class="row g-4">
                <div class="col-lg-4">
                    <label class="form-label fw-semibold" for="specialty_id">Specialite</label>
                    <select class="form-select" id="specialty_id" name="specialty_id" required>
                        <option value="">Choisir</option>
                        <?php foreach ($specialties as $specialty): ?>
                            <option value="<?= e($specialty['id']) ?>" <?= (string) $selectedSpecialty === (string) $specialty['id'] ? 'selected' : '' ?>>
                                <?= e($specialty['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-4">
                    <label class="form-label fw-semibold" for="doctor_id">Medecin</label>
                    <select class="form-select" id="doctor_id" name="doctor_id" required disabled>
                        <option value="">Choisir une specialite</option>
                    </select>
                    <div id="doctorAvailability" class="form-hint mt-2"></div>
                </div>

                <div class="col-lg-4">
                    <label class="form-label fw-semibold" for="appointment_date">Date</label>
                    <input class="form-control" type="date" id="appointment_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Creneaux disponibles</label>
                    <div id="slots" class="slot-grid" aria-live="polite">
                        <div class="text-secondary">Selectionnez un medecin.</div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="reason">Motif</label>
                    <textarea class="form-control" id="reason" name="reason" rows="3" maxlength="255" required><?= e($selectedReason) ?></textarea>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                        Valider la demande
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="bg-white border rounded-3 p-3 p-lg-4">
            <h2 class="h5 fw-bold mb-3">Mes rendez-vous</h2>
            <?php if ($upcoming === []): ?>
                <p class="text-secondary mb-0">Aucun rendez-vous à venir.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Date et heure</th><th>Spécialité</th><th>Médecin</th><th>Statut</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($upcoming as $appointment): ?>
                            <?php
                            $changeHours = max(0, (int) app_setting('appointment_cancel_min_hours', 6));
                            $canChange = in_array($appointment['status'], ['en_attente_confirmation', 'confirme'], true)
                                && strtotime((string) $appointment['start_datetime']) >= time() + ($changeHours * 3600);
                            ?>
                            <tr>
                                <td data-label="Date et heure"><?= e(date('d/m/Y H:i', strtotime((string) $appointment['start_datetime']))) ?></td>
                                <td data-label="Spécialité"><?= e($appointment['specialty']) ?></td>
                                <td data-label="Médecin">Dr <?= e($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?></td>
                                <td data-label="Statut"><span class="badge <?= $appointment['status'] === 'confirme' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(str_replace('_', ' ', $appointment['status'])) ?></span></td>
                                <td data-label="Actions">
                                    <?php if ($canChange): ?>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/patient/appointments/new?edit_id=' . (int) $appointment['id'])) ?>">Modifier</a>
                                            <form method="post" action="<?= e(url('/patient/appointments/cancel')) ?>" class="m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Annuler</button>
                                            </form>
                                        </div>
                                    <?php else: ?><span class="small text-secondary">Action indisponible</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php forget_old(); ?>
<script src="<?= e(asset('js/appointments.js?v=' . (string) filemtime(BASE_PATH . '/public/assets/js/appointments.js'))) ?>"></script>
