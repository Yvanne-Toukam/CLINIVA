<?php
$contactEmail = trim((string) ($publicContact['email'] ?? ''));
$contactPhone = trim((string) ($publicContact['phone'] ?? ''));
$contactAddress = trim((string) ($publicContact['address'] ?? 'Douala, Cameroun'));
$contactHours = trim((string) ($publicContact['hours'] ?? 'Horaires à confirmer'));
$formErrors = form_errors();
$mapUrl = 'https://www.google.com/maps?q=' . rawurlencode($contactAddress) . '&output=embed';
$landingScriptVersion = is_file(BASE_PATH . '/public/assets/js/landing.js')
    ? (string) filemtime(BASE_PATH . '/public/assets/js/landing.js')
    : '1';
?>
<section class="landing-hero">
    <div class="container landing-hero-inner">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="landing-eyebrow"><span aria-hidden="true"></span> Centre de santé · Douala</span>
                <h1 class="landing-title">La santé, avec <span>attention.</span></h1>
                <p class="landing-lead">
                    Un accueil humain et un accompagnement clair, de la prise de rendez-vous au suivi de vos soins.
                </p>
                <div class="d-flex flex-wrap gap-3 landing-actions">
                    <a class="btn btn-landing-primary" href="<?= e(url('/register')) ?>">
                        Prendre rendez-vous <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </a>
                    <a class="btn btn-landing-secondary" href="<?= e(url('/login')) ?>">Se connecter</a>
                </div>
                <div class="landing-trustline">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    Vos informations sont réservées à votre accompagnement par Cliniva.
                </div>
            </div>
            <div class="col-lg-6">
                <div class="landing-hero-image">
                    <picture>
                        <source srcset="<?= e(asset('img/cliniva-hero.webp')) ?>" type="image/webp">
                        <img
                            src="<?= e(asset('images/image2.png')) ?>"
                            alt="Professionnel de santé utilisant un outil numérique de suivi médical"
                            width="1300"
                            height="650"
                            fetchpriority="high"
                        >
                    </picture>
                    <div class="landing-image-note">
                        <span class="landing-note-icon"><i class="bi bi-heart-pulse" aria-hidden="true"></i></span>
                        <span><strong>Un parcours plus simple</strong><small>Au service de votre santé</small></span>
                    </div>
                </div>
            </div>
        </div>
        <a class="landing-scroll" href="#about" aria-label="Découvrir Cliniva">
            <span>Découvrir</span><i class="bi bi-arrow-down" aria-hidden="true"></i>
        </a>
    </div>
</section>

<section class="landing-section landing-about" id="about">
    <div class="container">
        <div class="row align-items-end g-4 mb-5">
            <div class="col-lg-7">
                <span class="landing-kicker">À propos de Cliniva</span>
                <h2 class="landing-heading">Un suivi de santé plus proche, plus lisible.</h2>
            </div>
            <div class="col-lg-5">
                <p class="landing-copy mb-0">
                    Cliniva accompagne les patients et les équipes de santé avec un accueil attentif,
                    une organisation claire et un suivi respectueux de la confidentialité.
                </p>
            </div>
        </div>

        <div class="row g-3 landing-values">
            <div class="col-md-4">
                <article class="landing-value-card">
                    <span class="landing-value-number">01</span>
                    <i class="bi bi-person-heart" aria-hidden="true"></i>
                    <h3>Écoute</h3>
                    <p>Chaque demande commence par un accueil respectueux et une attention portée à la personne.</p>
                </article>
            </div>
            <div class="col-md-4">
                <article class="landing-value-card">
                    <span class="landing-value-number">02</span>
                    <i class="bi bi-calendar2-check" aria-hidden="true"></i>
                    <h3>Organisation</h3>
                    <p>Rendez-vous et informations sont rassemblés pour rendre le parcours plus facile à suivre.</p>
                </article>
            </div>
            <div class="col-md-4">
                <article class="landing-value-card">
                    <span class="landing-value-number">03</span>
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <h3>Confidentialité</h3>
                    <p>L’accès aux informations de santé est limité aux personnes autorisées dans le cadre des soins.</p>
                </article>
            </div>
        </div>

        <div class="landing-stat-strip" aria-label="Chiffres clés de Cliniva">
            <div>
                <strong class="landing-counter" data-count="<?= e((int) ($counts['doctors'] ?? 0)) ?>">0</strong>
                <span>médecins référencés</span>
            </div>
            <div>
                <strong class="landing-counter" data-count="<?= e((int) ($counts['specialties'] ?? 0)) ?>">0</strong>
                <span>spécialités proposées</span>
            </div>
            <div class="landing-stat-note">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                <span>À Douala, au Cameroun</span>
            </div>
        </div>
    </div>
</section>

<section class="landing-section landing-specialties" id="services">
    <div class="container">
        <div class="landing-section-heading">
            <div>
                <span class="landing-kicker">Nos services</span>
                <h2 class="landing-heading">Des soins organisés autour de vous.</h2>
            </div>
            <a class="landing-text-link" href="<?= e(url('/register')) ?>">
                Demander un rendez-vous <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <?php if ($specialties !== []): ?>
            <div class="row g-3">
                <?php foreach ($specialties as $index => $specialty): ?>
                    <div class="col-sm-6 col-lg-4">
                        <article class="landing-specialty-card">
                            <span class="landing-specialty-index"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                            <span class="landing-specialty-icon"><i class="bi bi-plus-square" aria-hidden="true"></i></span>
                            <h3><?= e($specialty['name']) ?></h3>
                            <p><?= e($specialty['description'] ?: 'Une équipe à votre écoute pour vous accompagner.') ?></p>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="landing-empty-state">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                Les spécialités seront affichées ici dès qu’elles seront configurées par le centre.
            </div>
        <?php endif; ?>

        <?php if ($doctors !== []): ?>
            <div class="landing-team">
                <div class="landing-team-heading">
                    <span class="landing-kicker">L’équipe</span>
                    <h3>Des professionnels à vos côtés.</h3>
                </div>
                <div class="row g-3">
                    <?php foreach ($doctors as $doctor): ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="landing-doctor">
                                <span class="landing-doctor-avatar" aria-hidden="true">
                                    <?= e(mb_strtoupper(mb_substr((string) $doctor['first_name'], 0, 1) . mb_substr((string) $doctor['last_name'], 0, 1))) ?>
                                </span>
                                <span>
                                    <strong>Dr <?= e($doctor['first_name'] . ' ' . $doctor['last_name']) ?></strong>
                                    <small><?= e($doctor['specialty']) ?></small>
                                </span>
                                <i class="bi bi-arrow-up-right ms-auto" aria-hidden="true"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="landing-gallery" aria-labelledby="gallery-title">
    <div class="container">
        <div class="landing-gallery-heading">
            <div>
                <span class="landing-kicker">L’esprit Cliniva</span>
                <h2 class="landing-heading" id="gallery-title">Une attention qui se vit à chaque étape.</h2>
            </div>
            <p>Visuels d’illustration du parcours de soins et de l’accompagnement médical.</p>
        </div>
        <div class="row g-3">
            <div class="col-md-7">
                <figure class="landing-gallery-item landing-gallery-wide">
                    <picture>
                        <source srcset="<?= e(asset('img/cliniva-team.webp')) ?>" type="image/webp">
                        <img src="<?= e(asset('images/image1.png')) ?>" alt="Deux professionnelles de santé échangent dans un établissement médical" loading="lazy" width="679" height="452">
                    </picture>
                    <figcaption>Une équipe qui prend le temps d’écouter.</figcaption>
                </figure>
            </div>
            <div class="col-md-5">
                <figure class="landing-gallery-item landing-gallery-tall">
                    <picture>
                        <source srcset="<?= e(asset('img/cliniva-hero.webp')) ?>" type="image/webp">
                        <img src="<?= e(asset('images/image2.png')) ?>" alt="Outil numérique illustrant le suivi et l’organisation des soins" loading="lazy" width="1300" height="650">
                    </picture>
                    <figcaption>Un parcours de soins mieux organisé.</figcaption>
                </figure>
            </div>
        </div>
    </div>
</section>

<section class="landing-section landing-process" id="how-it-works">
    <div class="container">
        <div class="text-center landing-process-intro">
            <span class="landing-kicker">Comment ça marche</span>
            <h2 class="landing-heading">Votre parcours, en quatre étapes.</h2>
        </div>
        <div class="row g-3">
            <?php
            $steps = [
                ['1', 'Créez votre espace', 'Inscrivez-vous et renseignez les informations nécessaires à votre dossier.'],
                ['2', 'Choisissez un créneau', 'Sélectionnez une spécialité, un médecin et une disponibilité.'],
                ['3', 'Recevez la confirmation', 'La secrétaire examine votre demande et vous confirme le rendez-vous.'],
                ['4', 'Soyez accompagné', 'Retrouvez vos rendez-vous et les informations de suivi dans votre espace.'],
            ];
            foreach ($steps as $step):
            ?>
                <div class="col-sm-6 col-lg-3">
                    <article class="landing-step">
                        <span><?= e($step[0]) ?></span>
                        <h3><?= e($step[1]) ?></h3>
                        <p><?= e($step[2]) ?></p>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="landing-section landing-contact" id="contact">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <span class="landing-kicker">Nous contacter</span>
                <h2 class="landing-heading">Parlons de votre demande.</h2>
                <p class="landing-copy">Pour une question administrative, écrivez-nous ou contactez le centre.</p>
                <ul class="landing-contact-list">
                    <?php if ($contactEmail !== ''): ?>
                        <li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></li>
                    <?php endif; ?>
                    <?php if ($contactPhone !== ''): ?>
                        <li><i class="bi bi-telephone" aria-hidden="true"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>"><?= e($contactPhone) ?></a></li>
                    <?php endif; ?>
                    <li><i class="bi bi-geo-alt" aria-hidden="true"></i><span><?= e($contactAddress) ?></span></li>
                    <li><i class="bi bi-clock" aria-hidden="true"></i><span><?= e($contactHours) ?></span></li>
                </ul>
                <div class="landing-map">
                    <iframe
                        src="<?= e($mapUrl) ?>"
                        title="Carte de localisation à <?= e($contactAddress) ?>"
                        loading="lazy"
                        referrerpolicy="no-referrer"
                    ></iframe>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="landing-contact-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                        <div>
                            <h3>Envoyer un message</h3>
                            <p class="mb-0">Les champs marqués d’un astérisque sont obligatoires.</p>
                        </div>
                        <span class="landing-contact-mark" aria-hidden="true"><i class="bi bi-chat-dots"></i></span>
                    </div>
                    <?php if ($formErrors !== []): ?>
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($formErrors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/contact')) ?>" class="row g-3" autocomplete="on">
                        <?= csrf_field() ?>
                        <div class="col-md-6">
                            <label class="form-label" for="contact-name">Nom <span aria-hidden="true">*</span></label>
                            <input class="form-control" id="contact-name" name="name" autocomplete="name" maxlength="120" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact-email">E-mail <span aria-hidden="true">*</span></label>
                            <input class="form-control" id="contact-email" type="email" name="email" autocomplete="email" maxlength="190" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="contact-phone">Téléphone <span class="text-secondary">(facultatif)</span></label>
                            <input class="form-control" id="contact-phone" type="tel" name="phone" autocomplete="tel" maxlength="40">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="contact-message">Votre message <span aria-hidden="true">*</span></label>
                            <textarea class="form-control" id="contact-message" name="message" rows="5" minlength="10" maxlength="5000" required></textarea>
                        </div>
                        <div class="landing-honeypot" aria-hidden="true">
                            <label for="contact-website">Ne pas remplir</label>
                            <input id="contact-website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <small class="landing-privacy-note">N’incluez pas d’informations médicales sensibles dans ce formulaire.</small>
                            <button class="btn btn-landing-primary" type="submit">
                                Envoyer le message <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="landing-legal" id="privacy">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6" id="legal">
                <h2>Informations légales</h2>
                <p>Cliniva — centre de santé à Douala, Cameroun. Les coordonnées de l’établissement sont celles affichées dans la section contact.</p>
            </div>
            <div class="col-md-6">
                <h2>Confidentialité</h2>
                <p>Les messages du formulaire sont utilisés pour répondre aux demandes. N’y transmettez pas de diagnostic, de résultat d’examen ou d’autre donnée médicale. La carte intégrée est fournie par Google Maps et peut transmettre des données techniques à ce fournisseur lorsqu’elle est chargée.</p>
            </div>
        </div>
    </div>
</section>

<footer class="landing-footer">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <a class="landing-footer-brand" href="<?= e(url('/')) ?>">Cliniva<span>Centre de santé · Douala</span></a>
        <nav class="landing-footer-links" aria-label="Liens de pied de page">
            <a href="#about">À propos</a>
            <a href="#services">Services</a>
            <a href="#contact">Contact</a>
            <a href="#legal">Mentions légales</a>
            <a href="#privacy">Confidentialité</a>
        </nav>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-footer-outline" href="<?= e(url('/login')) ?>">Connexion</a>
            <a class="btn btn-sm btn-footer-primary" href="<?= e(url('/register')) ?>">Inscription</a>
        </div>
    </div>
</footer>
<script src="<?= e(asset('js/landing.js?v=' . $landingScriptVersion)) ?>" defer></script>
