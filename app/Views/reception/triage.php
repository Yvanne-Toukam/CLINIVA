<?php $errors = form_errors(); ?>
<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Accueil et triage</h1>
                <p class="text-secondary mb-0">Recherche patient, constantes et file d'attente.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/reception')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour</a>
        </div>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-7">
                <form method="post" action="<?= e(url('/reception/triage')) ?>" class="bg-white border rounded-3 p-3 p-lg-4 row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="patient_id" id="patient_id" value="<?= e(old('patient_id')) ?>">
                    <div class="col-12 position-relative">
                        <label class="form-label" for="patientSearch">Patient</label>
                        <input class="form-control patient-search" id="patientSearch" data-target="patient_id" data-url="<?= e(url('/reception/patients/search')) ?>" autocomplete="off" placeholder="Nom, dossier ou telephone">
                        <div class="search-results list-group shadow-sm"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="priority">Priorite</label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="urgence_vitale">Urgence vitale</option>
                            <option value="urgent">Urgent</option>
                            <option value="normal" selected>Normal</option>
                            <option value="non_urgent">Non urgent</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="arrival_state">Etat d'arrivee</label>
                        <input class="form-control" id="arrival_state" name="arrival_state" value="<?= e(old('arrival_state')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="immediate_reason">Motif immediat</label>
                        <input class="form-control" id="immediate_reason" name="immediate_reason" value="<?= e(old('immediate_reason')) ?>">
                    </div>
                    <?php foreach ([
                        'temperature' => 'Temperature',
                        'systolic' => 'Tension systolique',
                        'diastolic' => 'Tension diastolique',
                        'pulse' => 'Pouls',
                        'respiratory_rate' => 'Frequence respiratoire',
                        'oxygen_saturation' => 'Saturation O2',
                        'weight_kg' => 'Poids kg',
                        'height_cm' => 'Taille cm',
                    ] as $field => $label): ?>
                        <div class="col-md-3 col-6">
                            <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
                            <input class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" inputmode="decimal" value="<?= e(old($field)) ?>">
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Enregistrer</button>
                    </div>
                </form>
                <?php forget_old(); ?>
            </div>
            <div class="col-lg-5">
                <div class="bg-white border rounded-3 p-3 p-lg-4">
                    <h2 class="h5 fw-bold mb-3">File actuelle</h2>
                    <div id="queueList" data-url="<?= e(url('/reception/queue')) ?>">
                        <?php require BASE_PATH . '/app/Views/reception/partials_queue.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="<?= e(asset('js/reception.js')) ?>"></script>
