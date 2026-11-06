<?php

declare(strict_types=1);

/** @var string $content */
$title = $title ?? 'Cliniva';
$metaDescription = $metaDescription ?? 'Cliniva, plateforme de gestion et de suivi de santé à Douala.';
$canonicalUrl = $canonicalUrl ?? url('/');
$ogImage = $ogImage ?? asset('images/logo.jpeg');
$stylesheetVersion = is_file(BASE_PATH . '/public/assets/css/cliniva.css')
    ? (string) filemtime(BASE_PATH . '/public/assets/css/cliniva.css')
    : '1';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_CM">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('favicon.svg')) ?>">
    <title><?= e($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/cliniva.css?v=' . $stylesheetVersion)) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-cliniva">
    <div class="container">
        <a class="navbar-brand brand-cliniva" href="<?= e(url(current_role() === 'patient' ? '/patient' : '/')) ?>">
            <span class="brand-logo-frame">
                <img src="<?= e(asset('images/logo.jpeg')) ?>" alt="Cliniva" class="brand-mark" width="152" height="152">
            </span>
            <span class="brand-text">
                <span class="brand-title">Cliniva</span>
                <small class="brand-subtitle">Centre de santé</small>
            </span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <?php if (current_user() !== null): ?>
                    <?php if (!empty(current_user()['must_change_password'])): ?>
                        <a class="nav-link nav-pill" href="<?= e(url('/password/change')) ?>">Changer le mot de passe</a>
                    <?php elseif (current_role() === 'patient'): ?>
                        <a class="nav-link nav-pill" href="<?= e(url('/patient')) ?>">Accueil</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/patient/profile')) ?>">Profil</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/patient/appointments/new')) ?>">Nouveau rendez-vous</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/patient/documents')) ?>">Documents</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/patient/history')) ?>">Historique</a>
                    <?php elseif (current_role() === 'secretaire'): ?>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception')) ?>">Accueil réception</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception/patients/new')) ?>">Ajouter un patient</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception/appointments/new')) ?>">Rendez-vous</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception/schedules')) ?>">Disponibilités</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception/medicines')) ?>">Médicaments et stock</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception/triage')) ?>">File d’attente</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception/billing')) ?>">Facturation</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception/history')) ?>">Historique</a>
                    <?php elseif (current_role() === 'medecin'): ?>
                        <a class="nav-link nav-pill" href="<?= e(url('/dashboard')) ?>">Tableau de bord</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/doctor')) ?>">Agenda</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/doctor/appointments')) ?>">Rendez-vous</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/doctor/schedules')) ?>">Disponibilités</a>
                    <?php elseif (current_role() === 'admin'): ?>
                        <a class="nav-link nav-pill" href="<?= e(url('/dashboard')) ?>">Tableau de bord</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/admin')) ?>">Administration</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/admin/manage')) ?>">Gestion</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception/medicines')) ?>">Médicaments et stock</a>
                        <a class="nav-link nav-pill" href="<?= e(url('/reception')) ?>">Réception</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/logout')) ?>" class="m-0 ms-lg-2">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-logout" type="submit">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                            Déconnexion
                        </button>
                    </form>
                <?php else: ?>
                    <a class="nav-link nav-pill" href="<?= e(url('/')) ?>#about">À propos</a>
                    <a class="nav-link nav-pill" href="<?= e(url('/')) ?>#services">Services</a>
                    <a class="nav-link nav-pill" href="<?= e(url('/')) ?>#contact">Nous contacter</a>
                    <a class="nav-link nav-pill" href="<?= e(url('/register')) ?>">Inscription patient</a>
                    <a class="btn btn-sm btn-warning fw-semibold btn-login" href="<?= e(url('/login')) ?>">
                        <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                        Connexion
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="app-shell">
    <div class="container pt-3">
        <?php foreach (flashes() as $type => $messages): ?>
            <?php foreach ($messages as $message): ?>
                <div class="alert alert-<?= e($type) ?> alert-dismissible fade show flash-alert" role="alert" data-flash-timeout="5000">
                    <?php if (strpos($message, 'http') !== false): ?>
                        <?php list($prefix, $link) = array_pad(explode('http', $message, 2), 2, ''); ?>
                        <?= e($prefix) ?><a href="<?= e('http' . $link) ?>"><?= e('http' . $link) ?></a>
                    <?php else: ?>
                        <?= e($message) ?>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?= $content ?>
</main>
<button class="back-to-top" id="back-to-top" type="button" aria-label="Remonter en haut de la page" title="Remonter en haut" hidden>
    <i class="bi bi-arrow-up" aria-hidden="true"></i>
</button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/app.js?v=' . (string) filemtime(BASE_PATH . '/public/assets/js/app.js'))) ?>"></script>
</body>
</html>
