<?php $errors = form_errors(); ?>
<section class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="bg-white border rounded-3 p-4 p-lg-5">
                    <h1 class="h3 fw-bold text-primary mb-2">Choisissez votre mot de passe</h1>
                    <p class="text-secondary mb-4">Un nouveau mot de passe est requis avant de continuer.</p>
                    <?php if ($errors !== []): ?>
                        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/password/change')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="password">Nouveau mot de passe</label>
                            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required minlength="10">
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="password-confirmation">Confirmer le mot de passe</label>
                            <input class="form-control" id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="10">
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Enregistrer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>