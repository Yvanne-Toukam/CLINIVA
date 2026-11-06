<?php $errors = form_errors(); ?>
<section class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary mb-1">Facturation</h1>
                <p class="text-secondary mb-0">Facture, paiement et recu imprimable.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/reception')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour</a>
        </div>

        <?php if ($errors !== []): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-7">
                <form method="post" action="<?= e(url('/reception/invoices')) ?>" class="bg-white border rounded-3 p-3 p-lg-4 row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="patient_id" id="billing_patient_id" value="<?= e(old('patient_id')) ?>">
                    <div class="col-12 position-relative">
                        <label class="form-label" for="billingPatientSearch">Patient</label>
                        <input class="form-control patient-search" id="billingPatientSearch" data-target="billing_patient_id" data-url="<?= e(url('/reception/patients/search')) ?>" autocomplete="off" placeholder="Nom, dossier ou telephone">
                        <div class="search-results list-group shadow-sm"></div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Actes</label>
                        <div class="row g-2">
                            <?php foreach ($services as $service): ?>
                                <div class="col-md-6">
                                    <label class="border rounded-3 p-3 d-flex gap-2 h-100">
                                        <input class="form-check-input mt-1" type="checkbox" name="service_ids[]" value="<?= e($service['id']) ?>">
                                        <span>
                                            <strong><?= e($service['name']) ?></strong><br>
                                            <span class="text-secondary"><?= e(number_format((int) $service['price_xaf'], 0, ',', ' ')) ?> FCFA</span>
                                            <input class="form-control form-control-sm mt-2" type="number" name="quantities[<?= e($service['id']) ?>]" min="1" value="1" aria-label="Quantite">
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="mark_paid" name="mark_paid">
                            <label class="form-check-label" for="mark_paid">Payee maintenant</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="payment_method">Mode</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value="especes">Especes</option>
                            <option value="mobile_money">Mobile money</option>
                            <option value="carte">Carte</option>
                            <option value="virement">Virement</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="payment_reference">Reference</label>
                        <input class="form-control" id="payment_reference" name="payment_reference">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-receipt" aria-hidden="true"></i> Creer la facture</button>
                    </div>
                </form>
                <?php forget_old(); ?>
            </div>
            <div class="col-lg-5">
                <div class="bg-white border rounded-3 p-3 p-lg-4">
                    <h2 class="h5 fw-bold mb-3">Dernieres factures</h2>
                    <?php if ($recentInvoices === []): ?>
                        <p class="text-secondary mb-0">Aucune facture.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentInvoices as $invoice): ?>
                                <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                    <span><?= e($invoice['invoice_number']) ?><br><span class="text-secondary small"><?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?></span></span>
                                    <strong><?= e(number_format((int) $invoice['total_xaf'], 0, ',', ' ')) ?> FCFA</strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="<?= e(asset('js/reception.js')) ?>"></script>
