<?php if ($queue === []): ?>
    <p class="text-secondary mb-0">La file d'attente est vide.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Priorite</th>
                <th>Patient</th>
                <th>Arrivee</th>
                <th>Motif</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($queue as $entry): ?>
                <tr>
                    <td data-label="Priorité"><span class="status-badge bg-light text-primary border"><?= e($entry['priority']) ?></span></td>
                    <td data-label="Patient"><strong><?= e($entry['record_number']) ?></strong><br><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?></td>
                    <td data-label="Arrivée"><?= e(date('H:i', strtotime((string) $entry['arrived_at']))) ?></td>
                    <td data-label="Motif"><?= e($entry['immediate_reason'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
