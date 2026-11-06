<?php $errors = form_errors(); ?>
<section class="py-4 py-lg-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="auth-card p-4 p-lg-5">
                    <h1 class="h3 fw-bold text-primary mb-1">Inscription patient</h1>
                    <p class="text-secondary mb-4">Creez votre espace patient Cliniva.</p>

                    <?php if ($errors !== []): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('/register')) ?>" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-6">
                            <label class="form-label" for="first_name">Prenom</label>
                            <input class="form-control" id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="last_name">Nom</label>
                            <input class="form-control" id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="birth_date">Date de naissance</label>
                            <input class="form-control" id="birth_date" type="date" name="birth_date" value="<?= e(old('birth_date')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sex">Sexe</label>
                            <select class="form-select" id="sex" name="sex" required>
                                <?php foreach (['F' => 'Femme', 'M' => 'Homme', 'Autre' => 'Autre'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= old('sex') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Telephone</label>
                            <input class="form-control" id="phone" name="phone" value="<?= e(old('phone')) ?>" placeholder="+237..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">E-mail</label>
                            <input class="form-control" id="email" type="email" name="email" value="<?= e(old('email')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Mot de passe</label>
                            <input class="form-control" id="password" type="password" name="password" minlength="10" required>
                            <div class="form-hint mt-1">10 caracteres minimum, majuscule, minuscule, chiffre et symbole.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirmation">Confirmation</label>
                            <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" minlength="10" required>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="consent" name="consent" <?= old('consent') ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="consent">
                                    J'accepte le traitement de mes donnees personnelles de sante par Cliniva.
                                </label>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                            <a href="<?= e(url('/login')) ?>">J'ai deja un compte</a>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-person-plus" aria-hidden="true"></i>
                                Creer le compte
                            </button>
                        </div>
                    </form>
                    <?php forget_old(); ?>
                </div>
            </div>
        </div>
    </div>
</section>
