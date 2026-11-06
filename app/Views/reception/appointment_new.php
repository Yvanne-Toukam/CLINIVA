<?php $errors = form_errors(); ?>
<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Rendez-vous au guichet</h1>
                <p class="text-secondary mb-0">Planifier ou annuler un rendez-vous pour un patient.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/reception')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour</a>
        </div>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <section class="mb-4" aria-labelledby="pendingAppointmentsTitle">
            <h2 class="h5 fw-bold" id="pendingAppointmentsTitle">Rendez-vous à valider</h2>
            <?php if ($pendingAppointments === []): ?>
                <p class="text-secondary">Aucune demande en attente.</p>
            <?php else: ?>
                <div class="table-responsive bg-white border rounded-3">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Patient et créneau</th><th>Médecin</th><th>Motif</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($pendingAppointments as $appointment): ?>
                            <tr>
                                <td data-label="Patient et créneau">
                                    <strong><?= e($appointment['record_number'] . ' · ' . $appointment['first_name'] . ' ' . $appointment['last_name']) ?></strong><br>
                                    <?= e(date('d/m/Y H:i', strtotime((string) $appointment['start_datetime']))) ?>
                                    <?php if (!empty($appointment['phone'])): ?><br><small class="text-secondary"><?= e($appointment['phone']) ?></small><?php endif; ?>
                                </td>
                                <td data-label="Médecin">Dr <?= e($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?><br><small class="text-secondary"><?= e($appointment['specialty']) ?></small></td>
                                <td data-label="Motif"><?= e($appointment['reason'] ?? '') ?></td>
                                <td data-label="Actions">
                                    <div class="d-flex flex-wrap gap-2">
                                        <form method="post" action="<?= e(url('/reception/appointments/validate')) ?>">
                                            <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                            <button class="btn btn-sm btn-success" type="submit">Valider</button>
                                        </form>
                                        <details>
                                            <summary class="btn btn-sm btn-outline-primary">Reporter</summary>
                                            <form method="post" action="<?= e(url('/reception/appointments/reschedule')) ?>" class="mt-2">
                                                <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                                <label class="form-label small" for="new-start-<?= e($appointment['id']) ?>">Nouveau créneau disponible</label>
                                                <input class="form-control form-control-sm mb-2" type="datetime-local" id="new-start-<?= e($appointment['id']) ?>" name="new_start" required>
                                                <button class="btn btn-sm btn-primary" type="submit">Proposer au patient</button>
                                            </form>
                                        </details>
                                        <details>
                                            <summary class="btn btn-sm btn-outline-danger">Refuser</summary>
                                            <form method="post" action="<?= e(url('/reception/appointments/refuse')) ?>" class="mt-2">
                                                <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                                <label class="form-label small" for="refusal-<?= e($appointment['id']) ?>">Motif du refus</label>
                                                <input class="form-control form-control-sm mb-2" id="refusal-<?= e($appointment['id']) ?>" name="reason" maxlength="500" required>
                                                <button class="btn btn-sm btn-danger" type="submit">Confirmer le refus</button>
                                            </form>
                                        </details>
                                        <details>
                                            <summary class="btn btn-sm btn-outline-secondary">Message</summary>
                                            <form method="post" action="<?= e(url('/reception/appointments/message')) ?>" class="mt-2">
                                                <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                                <label class="form-label small" for="subject-<?= e($appointment['id']) ?>">Sujet</label>
                                                <input class="form-control form-control-sm mb-2" id="subject-<?= e($appointment['id']) ?>" name="subject" maxlength="190" required>
                                                <label class="form-label small" for="body-<?= e($appointment['id']) ?>">Message</label>
                                                <textarea class="form-control form-control-sm mb-2" id="body-<?= e($appointment['id']) ?>" name="body" rows="3" maxlength="4000" required></textarea>
                                                <button class="btn btn-sm btn-secondary" type="submit">Envoyer</button>
                                            </form>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div class="row g-3">
            <div class="col-lg-7">
                <form method="post" action="<?= e(url('/reception/appointments')) ?>" id="appointmentForm" class="bg-white border rounded-3 p-3 p-lg-4 row g-3" data-doctors-url="<?= e(relative_url('/reception/appointments/doctors')) ?>" data-slots-url="<?= e(relative_url('/reception/appointments/slots')) ?>" data-selected-doctor="" data-selected-start="<?= e(old('start_datetime')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="patient_id" id="appointment_patient_id" value="<?= e(old('patient_id')) ?>">
                    <input type="hidden" name="start_datetime" id="start_datetime" value="<?= e(old('start_datetime')) ?>">
                    <div class="col-12 position-relative">
                        <label class="form-label" for="appointmentPatientSearch">Patient</label>
                        <input class="form-control patient-search" id="appointmentPatientSearch" data-target="appointment_patient_id" data-url="<?= e(url('/reception/patients/search')) ?>" autocomplete="off" placeholder="Nom, dossier ou telephone">
                        <div class="search-results list-group shadow-sm"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="specialty_id">Specialite</label>
                        <select class="form-select" id="specialty_id" name="specialty_id" required>
                            <option value="">Choisir</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?= e($specialty['id']) ?>"><?= e($specialty['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="doctor_id">Medecin</label>
                        <select class="form-select" id="doctor_id" name="doctor_id" disabled required><option value="">Choisir une specialite</option></select>
                        <div id="doctorAvailability" class="form-hint mt-2" aria-live="polite"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="appointment_date">Date</label>
                        <input class="form-control" type="date" id="appointment_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Creneaux</label>
                        <div id="slots" class="slot-grid"><div class="text-secondary">Selectionnez un medecin.</div></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="reason">Motif</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required><?= e(old('reason')) ?></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-calendar-check" aria-hidden="true"></i> Confirmer</button>
                    </div>
                </form>
                <?php forget_old(); ?>
            </div>
            <div class="col-lg-5">
                <div class="bg-white border rounded-3 p-3 p-lg-4">
                    <h2 class="h5 fw-bold mb-3">Rendez-vous a venir</h2>
                    <?php if ($upcomingAppointments === []): ?>
                        <p class="text-secondary mb-0">Aucun rendez-vous.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex justify-content-between gap-3">
                                        <span>
                                            <strong><?= e(date('d/m/Y H:i', strtotime((string) $appointment['start_datetime']))) ?></strong><br>
                                            <?= e($appointment['record_number']) ?> - <?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?><br>
                                            <span class="text-secondary small">Dr <?= e($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?></span>
                                        </span>
                                        <form method="post" action="<?= e(url('/reception/appointments/cancel')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-calendar-x" aria-hidden="true"></i></button>
                                        </form>
                                    </div>
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
<script src="<?= e(asset('js/appointments.js?v=' . (string) filemtime(BASE_PATH . '/public/assets/js/appointments.js'))) ?>"></script>
