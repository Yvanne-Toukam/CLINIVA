<?php
$nextAppointment = $upcoming[0] ?? null;
$greeting = $patient['sex'] === 'F' ? 'Mme' : ($patient['sex'] === 'M' ? 'M.' : '');
$cancelAfter = max(0, (int) app_setting('appointment_cancel_min_hours', 6));
$initials = mb_strtoupper(mb_substr((string) $patient['first_name'], 0, 1) . mb_substr((string) $patient['last_name'], 0, 1));
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Bienvenue <?= $greeting !== '' ? e($greeting) . ' ' : '' ?><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></h1>
                <p class="text-secondary mb-0">Dossier <?= e($patient['record_number']) ?> · Votre espace de suivi santé</p>
            </div>
            <a class="btn btn-primary" href="<?= e(url('/patient/appointments/new')) ?>">
                <i class="bi bi-calendar-plus" aria-hidden="true"></i> Nouveau rendez-vous
            </a>
        </div>

        <?php if ($allergies !== []): ?>
            <div class="alert alert-warning border-warning-subtle">
                <strong>Allergies connues :</strong>
                <?= e(implode(', ', array_map(static function (array $allergy): string { return $allergy['allergen'] . ' (' . $allergy['severity'] . ')'; }, $allergies))) ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Prochain rendez-vous</h2>
                            <?php if ($nextAppointment !== null): ?>
                                <p class="text-secondary mb-0">Dr <?= e($nextAppointment['doctor_first_name'] . ' ' . $nextAppointment['doctor_last_name']) ?> · <?= e($nextAppointment['specialty']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($nextAppointment !== null): ?>
                            <span class="status-badge bg-light text-primary border"><?= e($nextAppointment['status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($nextAppointment === null): ?>
                        <p class="text-secondary mb-0">Aucun rendez-vous à venir.</p>
                    <?php else: ?>
                        <p class="fs-5 fw-semibold mb-2"><?= e(date('d/m/Y à H:i', strtotime((string) $nextAppointment['start_datetime']))) ?></p>
                        <div class="d-flex flex-wrap gap-2 align-items-center" aria-live="polite">
                            <span class="text-secondary">Dans</span>
                            <span class="badge text-bg-primary"><span id="countdownDays">--</span> j</span>
                            <span class="badge text-bg-primary"><span id="countdownHours">--</span> h</span>
                            <span class="badge text-bg-primary"><span id="countdownMinutes">--</span> min</span>
                            <span class="badge text-bg-primary"><span id="countdownSeconds">--</span> s</span>
                        </div>
                        <time id="nextAppointmentCountdown" datetime="<?= e(date(DATE_ATOM, strtotime((string) $nextAppointment['start_datetime']))) ?>" class="visually-hidden"></time>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <h2 class="h5 fw-bold">Mon profil</h2>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php if (!empty($patient['photo_path'])): ?>
                            <img src="<?= e(url('/patient/profile/photo')) ?>" alt="" class="patient-avatar-photo patient-avatar-photo-small rounded-circle border">
                        <?php else: ?>
                            <span class="patient-avatar patient-avatar-small rounded-circle" aria-label="<?= e('Initiales ' . $initials) ?>"><?= e($initials) ?></span>
                        <?php endif; ?>
                        <div>
                            <strong><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></strong>
                            <div class="small text-secondary"><?= e($patient['email'] ?: 'E-mail non renseigné') ?></div>
                        </div>
                    </div>
                    <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/patient/profile')) ?>">Gérer mon profil</a>
                </div>
            </div>
        </div>

        <div class="bg-white border rounded-3 p-3 p-lg-4 mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Mes rendez-vous</h2>
                    <p class="small text-secondary mb-0">Les modifications et annulations sont soumises au délai de <?= $cancelAfter ?> heure(s).</p>
                </div>
                <span class="status-badge bg-light text-primary border"><?= count($upcoming) ?> à venir</span>
            </div>
            <?php if ($upcoming === []): ?>
                <p class="text-secondary mb-0">Aucun rendez-vous planifié.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Date et heure</th><th>Spécialité</th><th>Médecin</th><th>Statut</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($upcoming as $appointment): ?>
                            <?php
                            $canChange = in_array($appointment['status'], ['en_attente_confirmation', 'en_attente_validation', 'reporte', 'confirme'], true)
                                && strtotime((string) $appointment['start_datetime']) >= time() + ($cancelAfter * 3600);
                            $statusClass = $appointment['status'] === 'confirme'
                                ? 'text-bg-success'
                                : (in_array($appointment['status'], ['en_attente_confirmation', 'en_attente_validation', 'reporte'], true) ? 'text-bg-warning' : 'text-bg-secondary');
                            ?>
                            <tr>
                                <td data-label="Date et heure"><time datetime="<?= e(date(DATE_ATOM, strtotime((string) $appointment['start_datetime']))) ?>"><?= e(date('d/m/Y H:i', strtotime((string) $appointment['start_datetime']))) ?></time></td>
                                <td data-label="Spécialité"><?= e($appointment['specialty']) ?></td>
                                <td data-label="Médecin">Dr <?= e($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']) ?></td>
                                <td data-label="Statut"><span class="badge <?= $statusClass ?>"><?= e(str_replace('_', ' ', $appointment['status'])) ?></span></td>
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
                                    <?php endif; ?>
                                    <?php if (in_array($appointment['status'], ['reporte', 'confirme'], true)): ?>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <form method="post" action="<?= e(url('/patient/appointments/respond')) ?>">
                                                <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                                <input type="hidden" name="response" value="confirm">
                                                <button class="btn btn-sm btn-outline-success" type="submit">Je confirme</button>
                                            </form>
                                            <form method="post" action="<?= e(url('/patient/appointments/respond')) ?>">
                                                <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= e($appointment['id']) ?>">
                                                <input type="hidden" name="response" value="decline">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Je ne viendrai pas</button>
                                            </form>
                                        </div>
                                    <?php elseif (!$canChange): ?>
                                        <span class="small text-secondary">Action indisponible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 fw-bold mb-0">Messages du centre</h2>
                        <span class="badge text-bg-light border"><?= count(array_filter($messages, static function (array $message): bool { return $message['lu_le'] === null; })) ?> non lu(s)</span>
                    </div>
                    <?php if ($messages === []): ?>
                        <p class="text-secondary mb-0">Aucun message du centre pour le moment.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($messages as $message): ?>
                                <article class="list-group-item px-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong><?= e($message['sujet']) ?></strong>
                                        <?php if ($message['lu_le'] === null): ?><span class="badge text-bg-primary">Nouveau</span><?php endif; ?>
                                    </div>
                                    <p class="mb-1"><?= nl2br(e($message['corps']), false) ?></p>
                                    <small class="text-secondary"><?= e($message['sender_first_name'] . ' ' . $message['sender_last_name']) ?> · <?= e(date('d/m/Y H:i', strtotime((string) $message['created_at']))) ?></small>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="bg-white border rounded-3 p-3 p-lg-4 h-100">
                    <h2 class="h5 fw-bold">Mon suivi</h2>
                    <p class="text-secondary">Retrouvez vos documents de santé et les visites précédentes dans votre espace.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/patient/documents')) ?>"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i> Documents</a>
                        <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/patient/history')) ?>"><i class="bi bi-clock-history" aria-hidden="true"></i> Historique</a>
                        <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/patient/profile')) ?>"><i class="bi bi-person" aria-hidden="true"></i> Profil</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php if ($nextAppointment !== null): ?><script src="<?= e(asset('js/patient-dashboard.js')) ?>" defer></script><?php endif; ?>
