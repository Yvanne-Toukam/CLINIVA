<section class="py-4 py-lg-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="auth-card p-4 p-lg-5">
                    <h1 class="h3 fw-bold text-primary mb-1">Double authentification</h1>
                    <p class="text-secondary">Scannez ce QR code avec votre application d’authentification, puis saisissez le code à 6 chiffres.</p>
                    <div class="d-flex justify-content-center bg-light border rounded-3 p-3 mb-4">
                        <img src="<?= e($qrCode) ?>" width="240" height="240" alt="QR code de configuration 2FA">
                    </div>
                    <form method="post" action="<?= e(url('/2fa/setup')) ?>" class="d-grid gap-3">
                        <?= csrf_field() ?>
                        <div>
                            <label class="form-label" for="totp_code">Code 2FA</label>
                            <input class="form-control" id="totp_code" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                        </div>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            Activer et continuer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
