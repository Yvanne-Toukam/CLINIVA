<section class="py-5">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 text-primary fw-bold mb-1">Tableau de bord</h1>
                <p class="text-secondary mb-0">Bienvenue <?= e(current_user()['first_name'] ?? '') ?>, role <?= e(current_role() ?? '') ?>.</p>
            </div>
            <span class="status-badge bg-white text-primary border">Phase 2 active</span>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="bg-white border rounded-3 p-4 h-100">
                    <h2 class="h6 fw-bold">Authentification</h2>
                    <p class="text-secondary mb-0">Connexion, CSRF, RBAC et expiration de session sont actifs.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bg-white border rounded-3 p-4 h-100">
                    <h2 class="h6 fw-bold">Notifications</h2>
                    <p class="text-secondary mb-0">Les e-mails sont places dans la table notifications.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bg-white border rounded-3 p-4 h-100">
                    <h2 class="h6 fw-bold">Modules metier</h2>
                    <p class="text-secondary mb-0">Le parcours rendez-vous patient arrive en Phase 3.</p>
                </div>
            </div>
        </div>
    </div>
</section>
