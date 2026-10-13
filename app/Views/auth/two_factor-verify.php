<section class="py-4 py-lg-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-card p-4 p-lg-5">
                    <h1 class="h3 fw-bold text-primary mb-1">Verification 2FA</h1>
                    <p class="text-secondary mb-4">Saisissez le code de votre application d'authentification.</p>
                    <form method="post" action="<?= e(url('/2fa/verify')) ?>" class="d-grid gap-3">
                        <?= csrf_field() ?>
                        <div>
                            <label class="form-label" for="totp_code">Code 2FA</label>
                            <input class="form-control" id="totp_code" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                        </div>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                            Verifier
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
