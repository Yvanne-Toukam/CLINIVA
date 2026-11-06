<section class="py-4 py-lg-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="auth-card p-4 p-lg-5">
                    <h1 class="h3 fw-bold text-primary mb-1">Mot de passe oublie</h1>
                    <p class="text-secondary mb-4">Un lien a usage unique sera prepare si l'adresse existe.</p>
                    <form method="post" action="<?= e(url('/password/forgot')) ?>" class="d-grid gap-3">
                        <?= csrf_field() ?>
                        <div>
                            <label class="form-label" for="email">E-mail</label>
                            <input class="form-control" id="email" type="email" name="email" required autofocus>
                        </div>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            Envoyer le lien
                        </button>
                    </form>
                    <a class="d-inline-block mt-4" href="<?= e(url('/login')) ?>">Retour a la connexion</a>
                </div>
            </div>
        </div>
    </div>
</section>
