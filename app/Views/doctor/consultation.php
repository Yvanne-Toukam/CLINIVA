<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Consultation</h1>
                <p class="text-secondary mb-0"><?= e($patient['record_number']) ?> - <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
                <?php if (empty($consultation['locked_at']) && !empty($consultation['scheduled_closure_at'])): ?>
                    <p class="small text-warning-emphasis mb-0">Clôture automatique prévue le <?= e(date('d/m/Y à H:i', strtotime((string) $consultation['scheduled_closure_at']))) ?></p>
                <?php endif; ?>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/doctor/record?patient_id=' . $patient['id'])) ?>"><i class="bi bi-folder2-open" aria-hidden="true"></i> Dossier</a>
        </div>

        <?php if ($allergies !== []): ?>
            <div class="alert alert-warning"><strong>Allergies a verifier avant ordonnance :</strong> <?= e(implode(', ', array_map(function (array $a): string { return $a['allergen']; }, $allergies))) ?></div>
        <?php endif; ?>

        <?php if (!empty($consultation['has_other_active'])): ?>
            <div class="alert alert-warning">
                Une consultation est déjà en cours pour un autre patient. Ce dossier est en lecture seule jusqu’à la clôture de cette consultation.
            </div>
            <form method="post" action="<?= e(url('/doctor/consultation/close')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="consultation_id" value="<?= e($consultation['id']) ?>">
                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-lock" aria-hidden="true"></i> Clôturer cette consultation</button>
            </form>
        <?php else: ?>
        <div class="row g-3">
            <div class="col-lg-7">
                <form method="post" action="<?= e(url('/doctor/consultation/save')) ?>" class="bg-white border rounded-3 p-3 p-lg-4 row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="consultation_id" value="<?= e($consultation['id']) ?>">
                    <?php if (!empty($consultation['locked_at'])): ?>
                        <div class="col-12">
                            <div class="alert alert-info">Consultation cloturee le <?= e(date('d/m/Y H:i', strtotime((string) $consultation['locked_at']))) ?>. Toute correction sera ajoutee en addendum.</div>
                            <label class="form-label" for="addendum">Addendum</label>
                            <textarea class="form-control" id="addendum" name="addendum" rows="5"></textarea>
                        </div>
                    <?php else: ?>
                        <div class="col-md-8">
                            <label class="form-label" for="motive">Motif</label>
                            <input class="form-control" id="motive" name="motive" value="<?= e($consultation['motive']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="icd10_code">CIM-10</label>
                            <input class="form-control" id="icd10_code" name="icd10_code" value="<?= e($consultation['icd10_code']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="anamnesis">Anamnese</label>
                            <textarea class="form-control" id="anamnesis" name="anamnesis" rows="4"><?= e($consultation['anamnesis'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="clinical_exam">Examen clinique</label>
                            <textarea class="form-control" id="clinical_exam" name="clinical_exam" rows="4"><?= e($consultation['clinical_exam'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="diagnosis">Diagnostic</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"><?= e($consultation['diagnosis'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="care_plan">Plan de soins</label>
                            <textarea class="form-control" id="care_plan" name="care_plan" rows="3"><?= e($consultation['care_plan'] ?? '') ?></textarea>
                        </div>
                    <?php endif; ?>
                    <div class="col-12 d-flex justify-content-between flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save" aria-hidden="true"></i> Enregistrer</button>
                    </div>
                </form>

                <?php if (empty($consultation['locked_at'])): ?>
                    <form method="post" action="<?= e(url('/doctor/consultation/close')) ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="consultation_id" value="<?= e($consultation['id']) ?>">
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-lock" aria-hidden="true"></i> Cloturer la consultation</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="col-lg-5">
                <div class="bg-white border rounded-3 p-3 p-lg-4 mb-3">
                    <h2 class="h5 fw-bold mb-3">Prescrire un examen</h2>
                    <?php if (empty($consultation['locked_at'])): ?>
                    <form method="post" action="<?= e(url('/doctor/consultation/exams')) ?>" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="consultation_id" value="<?= e($consultation['id']) ?>">
                        <div class="col-5">
                            <select class="form-select" name="type">
                                <option value="laboratoire">Laboratoire</option>
                                <option value="imagerie">Imagerie</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                        <div class="col-7"><input class="form-control" name="label" placeholder="Examen" required></div>
                        <div class="col-12 text-end"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-clipboard-plus" aria-hidden="true"></i> Ajouter</button></div>
                    </form>
                    <?php else: ?>
                        <p class="text-secondary">La consultation est clôturée. Les examens prescrits restent consultables, mais aucun nouvel examen ne peut être ajouté.</p>
                    <?php endif; ?>
                    <ul class="list-group list-group-flush mt-3">
                        <?php foreach ($examRequests as $exam): ?><li class="list-group-item px-0"><?= e($exam['type']) ?> - <?= e($exam['label']) ?> <span class="badge text-bg-light border"><?= e($exam['status']) ?></span></li><?php endforeach; ?>
                    </ul>
                </div>

                <div class="bg-white border rounded-3 p-3 p-lg-4">
                    <h2 class="h5 fw-bold mb-3">Ordonnance</h2>
                    <?php $availableMedicines = array_filter($medicines, static fn(array $medicine): bool => (int) $medicine['stock_quantity'] > 0); ?>
                    <?php if (empty($consultation['locked_at'])): ?>
                        <?php if ($availableMedicines === []): ?>
                            <p class="text-secondary mb-0">Aucun médicament actif avec un stock disponible. La réception ou l’administration doit ajouter un médicament et enregistrer une entrée de stock.</p>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('/doctor/consultation/prescriptions')) ?>" class="row g-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="consultation_id" value="<?= e($consultation['id']) ?>">
                                <div class="col-12">
                                    <label class="form-label" for="prescription-medicine">Médicament disponible</label>
                                    <select class="form-select" id="prescription-medicine" name="medicine_id" required>
                                        <option value="">Choisir un médicament</option>
                                        <?php foreach ($medicines as $medicine): ?>
                                            <?php $available = (int) $medicine['stock_quantity']; ?>
                                            <option value="<?= e($medicine['id']) ?>" <?= $available < 1 ? 'disabled' : '' ?>>
                                                <?= e($medicine['name'] . ($medicine['strength'] ? ' · ' . $medicine['strength'] : '')
                                                    . ' · ' . $medicine['dosage_form'] . ' · stock : ' . $available . ' ' . $medicine['stock_unit']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">La quantité prescrite est déduite du stock dès l’ajout de l’ordonnance.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="prescription-quantity">Quantité à délivrer (dans l’unité de stock)</label>
                                    <input class="form-control" id="prescription-quantity" name="quantity" type="number" min="1" max="1000000" step="1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="prescription-dosage">Dose par prise</label>
                                    <input class="form-control" id="prescription-dosage" name="dosage" maxlength="100" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="prescription-frequency">Fréquence / posologie</label>
                                    <input class="form-control" id="prescription-frequency" name="frequency" maxlength="150" placeholder="Ex. 2 fois par jour" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="prescription-duration">Durée</label>
                                    <input class="form-control" id="prescription-duration" name="duration" maxlength="100" placeholder="Ex. 5 jours" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="prescription-instructions">Instructions complémentaires</label>
                                    <textarea class="form-control" id="prescription-instructions" name="instructions" rows="2" maxlength="2000"></textarea>
                                </div>
                                <div class="col-12 text-end">
                                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-file-medical" aria-hidden="true"></i> Prescrire et déduire le stock</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-secondary">La consultation est clôturée : aucune nouvelle prescription ne peut être ajoutée.</p>
                    <?php endif; ?>
                    <ul class="list-group list-group-flush mt-3">
                        <?php foreach ($prescriptionItems as $item): ?>
                            <li class="list-group-item px-0">
                                <strong><?= e($item['medicine_name']) ?></strong>
                                <?php if (!empty($item['strength'])): ?><span class="text-secondary"><?= e($item['strength']) ?></span><?php endif; ?>
                                · <?= e($item['dosage_form'] ?? '') ?><br>
                                <?php if (($item['status'] ?? 'active') === 'annulee'): ?><span class="badge text-bg-secondary">Annulée</span><br><?php endif; ?>
                                <span><?= e($item['dosage']) ?> · <?= e($item['frequency']) ?> · <?= e($item['duration']) ?></span><br>
                                <span class="small">Quantité : <?= e($item['quantity'] ?? '—') ?> <?= e($item['quantity_unit'] ?? '') ?></span><br>
                                <?php if (!empty($item['instructions'])): ?><span class="text-secondary small"><?= e($item['instructions']) ?><br></span><?php endif; ?>
                                <span class="text-secondary small"><?= e($item['prescription_number']) ?></span>
                                <?php if (empty($consultation['locked_at']) && ($item['status'] ?? 'active') === 'active' && !empty($item['medicine_id'])): ?>
                                    <form method="post" action="<?= e(url('/doctor/consultation/prescriptions/cancel')) ?>" class="mt-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="consultation_id" value="<?= e($consultation['id']) ?>">
                                        <input type="hidden" name="prescription_item_id" value="<?= e($item['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Annuler et réintégrer le stock</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
