<?php $errors = form_errors(); ?>
<section class="py-4 py-lg-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="auth-card p-4 p-lg-5">
                    <h1 class="h3 fw-bold text-primary mb-1">Nouveau mot de passe</h1>
                    <p class="text-secondary mb-4">Choisissez un mot de passe robuste.</p>
                    <?php if ($errors !== []): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/password/reset')) ?>" class="d-grid gap-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="email" value="<?= e($email) ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <div>
                            <label class="form-label" for="password">Mot de passe</label>
                            <input class="form-control" id="password" type="password" name="password" minlength="10" required autofocus>
                        </div>
                        <div>
                            <label class="form-label" for="password_confirmation">Confirmation</label>
                            <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" minlength="10" required>
                        </div>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-key" aria-hidden="true"></i>
                            Mettre a jour
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
