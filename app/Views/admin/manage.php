<section class="py-4 admin-management">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Gestion administrative</h1>
                <p class="text-secondary mb-0">Comptes, catalogue, demandes de contact et activité.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/admin')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Tableau de bord</a>
        </div>

        <section class="mb-5" aria-labelledby="staffTitle">
            <h2 class="h5 fw-bold mb-3" id="staffTitle">Comptes du personnel</h2>
            <div class="row g-3">
                <div class="col-lg-4">
                    <form method="post" action="<?= e(url('/admin/users')) ?>" class="bg-white border rounded-3 p-3">
                        <?= csrf_field() ?>
                        <h3 class="h6 fw-bold">Créer un compte</h3>
                        <label class="form-label" for="user-first-name">Prénom</label>
                        <input class="form-control mb-2" id="user-first-name" name="first_name" maxlength="100" required>
                        <label class="form-label" for="user-last-name">Nom</label>
                        <input class="form-control mb-2" id="user-last-name" name="last_name" maxlength="100" required>
                        <label class="form-label" for="user-email">E-mail</label>
                        <input class="form-control mb-2" id="user-email" name="email" type="email" maxlength="190" required>
                        <label class="form-label" for="user-role">Rôle</label>
                        <select class="form-select mb-2" id="user-role" name="role" required>
                            <option value="secretaire">Secrétaire</option>
                            <option value="medecin">Médecin</option>
                            <option value="admin">Administrateur</option>
                        </select>
                        <div id="doctor-fields">
                            <label class="form-label" for="user-specialty">Spécialité médecin</label>
                            <select class="form-select mb-2" id="user-specialty" name="specialty_id">
                                <option value="">Choisir une spécialité</option>
                                <?php foreach (array_filter($specialties, static fn(array $specialty): bool => $specialty['deleted_at'] === null) as $specialty): ?>
                                    <option value="<?= e($specialty['id']) ?>"><?= e($specialty['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="form-label" for="user-license">Licence médecin</label>
                            <input class="form-control mb-2" id="user-license" name="license_number" maxlength="100">
                            <p class="form-text">La spécialité et le numéro de licence sont requis pour un médecin.</p>
                        </div>
                        <label class="form-label" for="user-password">Mot de passe initial</label>
                        <input class="form-control mb-2" id="user-password" name="password" type="password" autocomplete="new-password" required>
                        <label class="form-label" for="user-password-confirmation">Confirmation</label>
                        <input class="form-control mb-3" id="user-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i> Créer</button>
                    </form>
                </div>
                <div class="col-lg-8">
                    <div class="table-responsive bg-white border rounded-3 admin-staff-table-wrap">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Compte</th><th>Rôle</th><th>Statut</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td data-label="Compte"><?= e($user['first_name'] . ' ' . $user['last_name']) ?><br><small class="text-secondary"><?= e($user['email'] ?? '') ?></small></td>
                                    <td data-label="Rôle"><?= e($user['role']) ?></td>
                                    <td data-label="Statut"><?= (int) $user['is_active'] === 1 ? 'Actif' : 'Désactivé' ?></td>
                                    <td data-label="Actions">
                                        <form method="post" action="<?= e(url('/admin/users/status')) ?>">
                                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $user['is_active'] === 1 ? 'Désactiver' : 'Réactiver' ?></button>
                                        </form>
                                        <details class="mt-2">
                                            <summary class="btn btn-sm btn-outline-primary">Modifier</summary>
                                            <form method="post" action="<?= e(url('/admin/users/update')) ?>" class="mt-2">
                                                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                                <label class="form-label small" for="edit-first-<?= e($user['id']) ?>">Prénom</label>
                                                <input class="form-control form-control-sm mb-2" id="edit-first-<?= e($user['id']) ?>" name="first_name" value="<?= e($user['first_name']) ?>" maxlength="100" required>
                                                <label class="form-label small" for="edit-last-<?= e($user['id']) ?>">Nom</label>
                                                <input class="form-control form-control-sm mb-2" id="edit-last-<?= e($user['id']) ?>" name="last_name" value="<?= e($user['last_name']) ?>" maxlength="100" required>
                                                <label class="form-label small" for="edit-email-<?= e($user['id']) ?>">E-mail</label>
                                                <input class="form-control form-control-sm mb-2" id="edit-email-<?= e($user['id']) ?>" name="email" type="email" value="<?= e($user['email']) ?>" maxlength="190" required>
                                                <label class="form-label small" for="edit-role-<?= e($user['id']) ?>">Rôle</label>
                                                <select class="form-select form-select-sm mb-2" id="edit-role-<?= e($user['id']) ?>" name="role">
                                                    <?php foreach (['admin' => 'Administrateur', 'medecin' => 'Médecin', 'secretaire' => 'Secrétaire'] as $role => $roleLabel): ?>
                                                        <option value="<?= e($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= e($roleLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label class="form-label small" for="edit-specialty-<?= e($user['id']) ?>">Spécialité médecin</label>
                                                <select class="form-select form-select-sm mb-2" id="edit-specialty-<?= e($user['id']) ?>" name="specialty_id">
                                                    <option value="">Non applicable</option>
                                                    <?php foreach (array_filter($specialties, static fn(array $specialty): bool => $specialty['deleted_at'] === null) as $specialty): ?>
                                                        <option value="<?= e($specialty['id']) ?>" <?= (int) ($user['specialty_id'] ?? 0) === (int) $specialty['id'] ? 'selected' : '' ?>><?= e($specialty['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label class="form-label small" for="edit-license-<?= e($user['id']) ?>">Licence médecin</label>
                                                <input class="form-control form-control-sm mb-2" id="edit-license-<?= e($user['id']) ?>" name="license_number" value="<?= e($user['license_number'] ?? '') ?>" maxlength="100">
                                                <button class="btn btn-sm btn-primary" type="submit">Enregistrer</button>
                                            </form>
                                        </details>
                                        <form method="post" action="<?= e(url('/admin/users/password-reset')) ?>" class="mt-2">
                                            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                            <button class="btn btn-sm btn-outline-warning" type="submit">Envoyer un reset</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        <script src="<?= e(asset('js/admin-manage.js?v=' . (string) filemtime(BASE_PATH . '/public/assets/js/admin-manage.js'))) ?>" defer></script>

        <div class="row g-4 mb-5">
            <section class="col-lg-6" aria-labelledby="specialtiesTitle">
                <h2 class="h5 fw-bold mb-3" id="specialtiesTitle">Spécialités</h2>
                <form method="post" action="<?= e(url('/admin/specialties')) ?>" class="bg-white border rounded-3 p-3 mb-3">
                    <?= csrf_field() ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5"><label class="form-label" for="specialty-name">Nom</label><input class="form-control" id="specialty-name" name="name" maxlength="150" required></div>
                        <div class="col-md-5"><label class="form-label" for="specialty-description">Description</label><input class="form-control" id="specialty-description" name="description" maxlength="2000"></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Ajouter</button></div>
                    </div>
                </form>
                <div class="table-responsive bg-white border rounded-3 admin-catalog-table-wrap">
                    <table class="table align-middle mb-0"><thead><tr><th>Spécialité</th><th>Statut</th><th></th></tr></thead><tbody>
                    <?php foreach ($specialties as $specialty): ?>
                        <tr>
                            <td data-label="Spécialité">
                                <form method="post" action="<?= e(url('/admin/specialties/update')) ?>" class="d-flex flex-wrap gap-2">
                                    <?= csrf_field() ?><input type="hidden" name="specialty_id" value="<?= e($specialty['id']) ?>">
                                    <input class="form-control form-control-sm" name="name" value="<?= e($specialty['name']) ?>" maxlength="150" required aria-label="Nom de spécialité">
                                    <input class="form-control form-control-sm" name="description" value="<?= e($specialty['description'] ?? '') ?>" maxlength="2000" aria-label="Description">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Enregistrer</button>
                                </form>
                            </td>
                            <td data-label="Statut"><?= $specialty['deleted_at'] === null ? 'Active' : 'Désactivée' ?></td>
                            <td data-label="Actions"><form method="post" action="<?= e(url('/admin/specialties/status')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="specialty_id" value="<?= e($specialty['id']) ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $specialty['deleted_at'] === null ? 'Désactiver' : 'Réactiver' ?></button>
                            </form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
            </section>
            <section class="col-lg-6" aria-labelledby="servicesTitle">
                <h2 class="h5 fw-bold mb-3" id="servicesTitle">Tarifs et actes</h2>
                <form method="post" action="<?= e(url('/admin/services')) ?>" class="bg-white border rounded-3 p-3 mb-3">
                    <?= csrf_field() ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3"><label class="form-label" for="service-code">Code</label><input class="form-control" id="service-code" name="code" maxlength="40" required></div>
                        <div class="col-md-5"><label class="form-label" for="service-name">Acte</label><input class="form-control" id="service-name" name="name" maxlength="190" required></div>
                        <div class="col-md-2"><label class="form-label" for="service-price">XAF</label><input class="form-control" id="service-price" name="price_xaf" type="number" min="0" step="1" required></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Ajouter</button></div>
                    </div>
                </form>
                <div class="table-responsive bg-white border rounded-3 admin-catalog-table-wrap">
                    <table class="table align-middle mb-0"><thead><tr><th>Tarif</th><th>Statut</th><th></th></tr></thead><tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td data-label="Tarif">
                                <form method="post" action="<?= e(url('/admin/services/update')) ?>" class="d-flex flex-wrap gap-2">
                                    <?= csrf_field() ?><input type="hidden" name="service_id" value="<?= e($service['id']) ?>">
                                    <input class="form-control form-control-sm" name="code" value="<?= e($service['code']) ?>" maxlength="40" required aria-label="Code du tarif">
                                    <input class="form-control form-control-sm" name="name" value="<?= e($service['name']) ?>" maxlength="190" required aria-label="Nom de l’acte">
                                    <input class="form-control form-control-sm" name="price_xaf" type="number" min="0" step="1" value="<?= e($service['price_xaf']) ?>" required aria-label="Montant XAF">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Enregistrer</button>
                                </form>
                            </td>
                            <td data-label="Statut"><?= (int) $service['is_active'] === 1 ? 'Actif' : 'Inactif' ?></td>
                            <td data-label="Actions"><form method="post" action="<?= e(url('/admin/services/status')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="service_id" value="<?= e($service['id']) ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $service['is_active'] === 1 ? 'Désactiver' : 'Réactiver' ?></button>
                            </form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
            </section>
        </div>

        <section class="mb-5" id="public-settings" aria-labelledby="publicSettingsTitle">
            <h2 class="h5 fw-bold mb-3" id="publicSettingsTitle">Coordonnées publiques</h2>
            <?php $settingsMap = array_column($settings, 'setting_value', 'setting_key'); ?>
            <form method="post" action="<?= e(url('/admin/settings')) ?>" class="bg-white border rounded-3 p-3 p-lg-4">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="clinic-email">E-mail public</label><input class="form-control" id="clinic-email" name="settings[clinic_email]" type="email" maxlength="190" value="<?= e($settingsMap['clinic_email'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="clinic-phone">Téléphone</label><input class="form-control" id="clinic-phone" name="settings[clinic_phone]" maxlength="40" value="<?= e($settingsMap['clinic_phone'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="clinic-address">Adresse</label><input class="form-control" id="clinic-address" name="settings[clinic_address]" maxlength="255" value="<?= e($settingsMap['clinic_address'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="clinic-hours">Horaires d’ouverture</label><input class="form-control" id="clinic-hours" name="settings[clinic_opening_hours]" maxlength="500" value="<?= e($settingsMap['clinic_opening_hours'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="cancel-hours">Délai minimum d’annulation (heures)</label><input class="form-control" id="cancel-hours" name="settings[appointment_cancel_min_hours]" type="number" min="0" max="720" step="1" value="<?= e($settingsMap['appointment_cancel_min_hours'] ?? env('APPOINTMENT_CANCEL_MIN_HOURS', 6)) ?>" required></div>
                    <div class="col-md-6"><label class="form-label" for="validation-hours">Expiration des demandes (heures)</label><input class="form-control" id="validation-hours" name="settings[appointment_validation_hours]" type="number" min="1" max="168" step="1" value="<?= e($settingsMap['appointment_validation_hours'] ?? env('APPOINTMENT_VALIDATION_HOURS', 24)) ?>" required></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
                </div>
            </form>
        </section>

        <section class="mb-5" id="contact-messages" aria-labelledby="contactMessagesTitle">
            <h2 class="h5 fw-bold mb-3" id="contactMessagesTitle">Messages de contact</h2>
            <?php if ($contactMessages === []): ?>
                <p class="text-secondary">Aucun message reçu.</p>
            <?php else: ?>
                <div class="table-responsive bg-white border rounded-3 admin-contact-table-wrap">
                    <table class="table align-middle mb-0"><thead><tr><th>Expéditeur</th><th>Message</th><th>Reçu</th><th>Statut</th><th></th></tr></thead><tbody>
                    <?php foreach ($contactMessages as $message): ?>
                        <tr>
                            <td data-label="Expéditeur"><?= e($message['name']) ?><br><a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a><br><small><?= e($message['phone'] ?? '') ?></small></td>
                            <td data-label="Message"><?= nl2br(e($message['message']), false) ?></td>
                            <td data-label="Reçu"><?= e(date('d/m/Y H:i', strtotime((string) $message['created_at']))) ?></td>
                            <td data-label="Statut"><?= e($message['status']) ?></td>
                            <td data-label="Actions">
                                <form method="post" action="<?= e(url('/admin/contact-messages/status')) ?>" class="d-flex gap-2">
                                    <?= csrf_field() ?><input type="hidden" name="message_id" value="<?= e($message['id']) ?>">
                                    <select class="form-select form-select-sm" name="status" aria-label="Statut du message">
                                        <?php foreach (['nouveau', 'lu', 'traite'] as $status): ?><option value="<?= e($status) ?>" <?= $message['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Enregistrer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
            <?php endif; ?>
        </section>

        <section aria-labelledby="auditTitle">
            <h2 class="h5 fw-bold mb-1" id="auditTitle">Historique des actions</h2>
            <p class="small text-secondary mb-3">Qui a fait quoi, sur quel élément et à quel moment. Les anciennes actions ne contiennent pas toujours le détail des changements.</p>
            <form method="get" action="<?= e(url('/admin/manage')) ?>" class="row g-2 align-items-end mb-3">
                <div class="col-md-2"><label class="form-label" for="audit-user">ID de l’utilisateur</label><input class="form-control" id="audit-user" name="audit_user" type="number" min="1" value="<?= e($filters['audit_user']) ?>"></div>
                <div class="col-md-3"><label class="form-label" for="audit-action">Type d’action</label><input class="form-control" id="audit-action" name="audit_action" value="<?= e($filters['audit_action']) ?>" maxlength="100" placeholder="Ex. rendez-vous"></div>
                <div class="col-md-2"><label class="form-label" for="audit-from">Du</label><input class="form-control" id="audit-from" name="audit_from" type="date" value="<?= e($filters['audit_from']) ?>"></div>
                <div class="col-md-2"><label class="form-label" for="audit-to">Au</label><input class="form-control" id="audit-to" name="audit_to" type="date" value="<?= e($filters['audit_to']) ?>"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary" type="submit">Filtrer</button></div>
            </form>
            <div class="table-responsive bg-white border rounded-3 admin-audit-table-wrap">
                <table class="table table-hover align-middle mb-0 admin-audit-table">
                    <thead><tr><th>Action</th><th>Détails</th><th>Effectuée par</th><th>Élément concerné</th><th>Adresse IP</th><th>Date et heure</th></tr></thead><tbody>
                <?php foreach ($auditLogs as $log): ?>
                    <tr>
                        <td data-label="Action" class="audit-action-cell"><?= e($log['action_label']) ?></td>
                        <td data-label="Détails" class="audit-details-cell"><?= e($log['details_label']) ?></td>
                        <td data-label="Effectuée par"><?= e(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'Système') ?></td>
                        <td data-label="Élément concerné"><?= e($log['entity_label']) ?></td>
                        <td data-label="Adresse IP" class="text-nowrap"><?= e($log['ip_address'] ?? '') ?></td>
                        <td data-label="Date et heure"><time datetime="<?= e(date(DATE_ATOM, strtotime((string) $log['created_at']))) ?>"><?= e(date('d/m/Y', strtotime((string) $log['created_at']))) ?><br><span class="text-secondary"><?= e(date('H:i', strtotime((string) $log['created_at']))) ?></span></time></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($auditLogs === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">Aucune action ne correspond à ces filtres.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </section>

        <section class="mt-5" aria-labelledby="loginAttemptsTitle">
            <h2 class="h5 fw-bold mb-1" id="loginAttemptsTitle">Historique des connexions</h2>
            <p class="small text-secondary mb-3">Tentatives réussies ou échouées, avec le compte visé et l’éventuel blocage.</p>
            <form method="get" action="<?= e(url('/admin/manage')) ?>" class="row g-2 align-items-end mb-3">
                <div class="col-md-4"><label class="form-label" for="login-email-filter">E-mail</label><input class="form-control" id="login-email-filter" name="login_email" type="search" value="<?= e($filters['login_email']) ?>" maxlength="190"></div>
                <div class="col-md-2"><label class="form-label" for="login-success-filter">Résultat</label><select class="form-select" id="login-success-filter" name="login_success"><option value="">Tous</option><option value="1" <?= $filters['login_success'] === '1' ? 'selected' : '' ?>>Réussies</option><option value="0" <?= $filters['login_success'] === '0' ? 'selected' : '' ?>>Échouées</option></select></div>
                <div class="col-md-2"><label class="form-label" for="login-from">Du</label><input class="form-control" id="login-from" name="login_from" type="date" value="<?= e($filters['login_from']) ?>"></div>
                <div class="col-md-2"><label class="form-label" for="login-to">Au</label><input class="form-control" id="login-to" name="login_to" type="date" value="<?= e($filters['login_to']) ?>"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary" type="submit">Filtrer</button></div>
            </form>
            <div class="table-responsive bg-white border rounded-3 admin-login-table-wrap">
                <table class="table table-hover align-middle mb-0 admin-audit-table"><thead><tr><th>Date et heure</th><th>Adresse e-mail saisie</th><th>Compte associé</th><th>Résultat</th><th>Adresse IP</th><th>Blocage jusqu’au</th></tr></thead><tbody>
                <?php foreach ($loginAttempts as $attempt): ?>
                    <tr>
                        <td data-label="Date et heure"><time datetime="<?= e(date(DATE_ATOM, strtotime((string) $attempt['created_at']))) ?>"><?= e(date('d/m/Y', strtotime((string) $attempt['created_at']))) ?><br><span class="text-secondary"><?= e(date('H:i:s', strtotime((string) $attempt['created_at']))) ?></span></time></td>
                        <td data-label="Adresse e-mail saisie"><?= e($attempt['email'] ?? 'Non renseignée') ?></td>
                        <td data-label="Compte associé"><?= e(trim(($attempt['first_name'] ?? '') . ' ' . ($attempt['last_name'] ?? '')) ?: 'Aucun compte correspondant') ?></td>
                        <td data-label="Résultat"><span class="badge <?= (int) $attempt['success'] === 1 ? 'text-bg-success' : 'text-bg-danger' ?>"><?= (int) $attempt['success'] === 1 ? 'Réussie' : 'Échouée' ?></span></td>
                        <td data-label="Adresse IP" class="text-nowrap"><?= e($attempt['ip_address'] ?? '') ?></td>
                        <td data-label="Blocage jusqu’au"><?= !empty($attempt['locked_until']) ? e(date('d/m/Y H:i', strtotime((string) $attempt['locked_until']))) : 'Aucun blocage' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($loginAttempts === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">Aucune tentative ne correspond à ces filtres.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </section>
    </div>
</section>