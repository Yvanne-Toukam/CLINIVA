<?php
$errors = form_errors();
$firstName = old('first_name', $patient['first_name']);
$lastName = old('last_name', $patient['last_name']);
$email = old('email', $patient['account_email']);
$phone = old('phone', $patient['phone']);
$initials = mb_strtoupper(mb_substr((string) $patient['first_name'], 0, 1) . mb_substr((string) $patient['last_name'], 0, 1));
?>
<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Mon profil</h1>
                <p class="text-secondary mb-0">Mettez à jour vos coordonnées et votre photo de profil.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/patient')) ?>">Retour à l’accueil</a>
        </div>
        <?php if ($errors !== []): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="bg-white border rounded-3 p-3 p-lg-4">
            <form method="post" action="<?= e(url('/patient/profile')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="row g-4 align-items-start">
                    <div class="col-12 col-md-4">
                        <div class="text-center">
                            <?php if (!empty($patient['photo_path'])): ?>
                                <img src="<?= e(url('/patient/profile/photo')) ?>" alt="Photo de profil actuelle" class="patient-avatar-photo patient-avatar-large rounded-circle border mb-3">
                            <?php else: ?>
                                <span class="patient-avatar patient-avatar-large rounded-circle mb-3" aria-label="<?= e('Initiales ' . $initials) ?>"><?= e($initials) ?></span>
                            <?php endif; ?>
                            <label class="form-label fw-semibold d-block" for="photo">Photo de profil</label>
                            <input class="form-control" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">JPEG, PNG ou WebP, 5 Mo maximum. La photo est redimensionnée et stockée hors du dossier public.</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-8">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="first_name">Prénom</label>
                                <input class="form-control" type="text" id="first_name" name="first_name" maxlength="100" required value="<?= e($firstName) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="last_name">Nom</label>
                                <input class="form-control" type="text" id="last_name" name="last_name" maxlength="100" required value="<?= e($lastName) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="phone">Téléphone</label>
                                <input class="form-control" type="tel" id="phone" name="phone" maxlength="40" value="<?= e($phone) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="email">E-mail</label>
                                <input class="form-control" type="email" id="email" name="email" maxlength="190" required value="<?= e($email) ?>">
                                <?php if (empty($patient['email_verified_at'])): ?><div class="form-text text-warning">Adresse à vérifier.</div><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <p class="small text-secondary mb-0">La modification de l’adresse e-mail requiert une nouvelle vérification. La date de naissance et le numéro de dossier ne sont pas modifiables depuis cet espace.</p>
                            </div>
                            <div class="col-12 text-end">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Enregistrer mon profil</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php forget_old(); ?>
    </div>
</section>
