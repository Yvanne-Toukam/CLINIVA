<?php $errors = form_errors(); ?>
<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Nouveau patient</h1>
                <p class="text-secondary mb-0">Creation de dossier avec detection de doublons.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/reception')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour</a>
        </div>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/reception/patients')) ?>" class="bg-white border rounded-3 p-3 p-lg-4 row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label" for="first_name">Prenom</label>
                <input class="form-control" id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="last_name">Nom</label>
                <input class="form-control" id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="birth_date">Date de naissance</label>
                <input class="form-control" type="date" id="birth_date" name="birth_date" value="<?= e(old('birth_date')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="sex">Sexe</label>
                <select class="form-select" id="sex" name="sex" required>
                    <?php foreach (['F' => 'Femme', 'M' => 'Homme', 'Autre' => 'Autre'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= old('sex') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="phone">Telephone</label>
                <input class="form-control" id="phone" name="phone" value="<?= e(old('phone')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">E-mail</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= e(old('email')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="emergency_contact">Contact d'urgence</label>
                <input class="form-control" id="emergency_contact" name="emergency_contact" value="<?= e(old('emergency_contact')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="address">Adresse</label>
                <input class="form-control" id="address" name="address" value="<?= e(old('address')) ?>">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="consent" name="consent" <?= old('consent') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="consent">Consentement recueilli au guichet.</label>
                </div>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-primary" type="submit"><i class="bi bi-person-check" aria-hidden="true"></i> Creer le dossier</button>
            </div>
        </form>
        <?php forget_old(); ?>
    </div>
</section>
