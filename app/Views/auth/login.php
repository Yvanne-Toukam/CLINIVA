<section class="py-4 py-lg-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="auth-card p-4 p-lg-5">
                    <h1 class="h3 fw-bold text-primary mb-1">Connexion</h1>
                    <p class="text-secondary mb-4">Accedez a votre espace Cliniva.</p>
                    <form method="post" action="<?= e(url('/login')) ?>" class="d-grid gap-3">
                        <?= csrf_field() ?>
                        <div>
                            <label class="form-label" for="email">E-mail</label>
                            <input class="form-control" id="email" type="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
                        </div>
                        <div>
                            <label class="form-label" for="password">Mot de passe</label>
                            <input class="form-control" id="password" type="password" name="password" required>
                        </div>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            Se connecter
                        </button>
                    </form>
                    <div class="d-flex justify-content-between gap-3 mt-4 flex-wrap">
                        <a href="<?= e(url('/password/forgot')) ?>">Mot de passe oublie</a>
                        <a href="<?= e(url('/register')) ?>">Inscription patient</a>
                    </div>
                    <?php forget_old(); ?>
                </div>
            </div>
        </div>
    </div>
</section>
