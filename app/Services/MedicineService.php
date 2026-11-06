<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Database;
use PDO;

final class MedicineService
{
    private $audit;

    public function __construct()
    {
        $this->audit = new AuditLogger();
    }

    public function managementData(): array
    {
        $pdo = Database::pdo();
        $medicines = $pdo->query('SELECT id, name, dosage_form, strength, stock_unit, stock_quantity, is_active, updated_at
            FROM medicines ORDER BY is_active DESC, name, dosage_form, strength')->fetchAll();
        $movements = $pdo->query('SELECT sm.id, sm.movement_type, sm.quantity, sm.quantity_unit, sm.stock_before,
                sm.stock_after, sm.reason, sm.created_at, m.name AS medicine_name,
                u.first_name, u.last_name
            FROM medicine_stock_movements sm
            INNER JOIN medicines m ON m.id = sm.medicine_id
            LEFT JOIN users u ON u.id = sm.created_by
            ORDER BY sm.created_at DESC, sm.id DESC LIMIT 100')->fetchAll();

        return ['medicines' => $medicines, 'stockMovements' => $movements];
    }

    public function activeMedicines(): array
    {
        return Database::pdo()->query('SELECT id, name, dosage_form, strength, stock_unit, stock_quantity
            FROM medicines WHERE is_active = 1 ORDER BY name, dosage_form, strength')->fetchAll();
    }

    public function create(int $actorId, array $input): void
    {
        $medicine = $this->validatedMedicine($input);
        Database::transaction(function (PDO $pdo) use ($actorId, $medicine): void {
            $stmt = $pdo->prepare('INSERT INTO medicines
                (name, dosage_form, strength, stock_unit, stock_quantity, is_active, created_by, created_at)
                VALUES (:name, :dosage_form, :strength, :stock_unit, 0, 1, :created_by, NOW())');
            $stmt->execute([
                'name' => $medicine['name'],
                'dosage_form' => $medicine['dosage_form'],
                'strength' => $medicine['strength'],
                'stock_unit' => $medicine['stock_unit'],
                'created_by' => $actorId,
            ]);
            $medicineId = (int) $pdo->lastInsertId();
            $this->audit->log('medicine_created', 'medicine', $medicineId, $actorId, [
                'name' => $medicine['name'],
                'dosage_form' => $medicine['dosage_form'],
                'stock_unit' => $medicine['stock_unit'],
            ]);
        });
    }

    public function update(int $actorId, array $input): void
    {
        $medicineId = filter_var($input['medicine_id'] ?? null, FILTER_VALIDATE_INT);
        if ($medicineId === false || $medicineId < 1) {
            throw new \InvalidArgumentException('Médicament introuvable.');
        }
        $medicine = $this->validatedMedicine($input);
        Database::transaction(function (PDO $pdo) use ($actorId, $medicineId, $medicine): void {
            $stmt = $pdo->prepare('SELECT name, dosage_form, strength, stock_unit
                FROM medicines WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $medicineId]);
            $previous = $stmt->fetch();
            if (!is_array($previous)) {
                throw new \InvalidArgumentException('Médicament introuvable.');
            }
            if ($medicine['stock_unit'] !== $previous['stock_unit']) {
                throw new \InvalidArgumentException('L’unité de stock ne peut pas être modifiée après la création du médicament.');
            }

            $stmt = $pdo->prepare('UPDATE medicines
                SET name = :name, dosage_form = :dosage_form, strength = :strength, updated_at = NOW()
                WHERE id = :id');
            $stmt->execute([
                'name' => $medicine['name'],
                'dosage_form' => $medicine['dosage_form'],
                'strength' => $medicine['strength'],
                'id' => $medicineId,
            ]);
            $this->audit->log('medicine_updated', 'medicine', $medicineId, $actorId, [
                'changed_fields' => array_keys(array_filter(
                    array_intersect_key($medicine, array_flip(['name', 'dosage_form', 'strength'])),
                    static function ($value, string $key) use ($previous): bool {
                        return (string) ($value ?? '') !== (string) ($previous[$key] ?? '');
                    },
                    ARRAY_FILTER_USE_BOTH
                )),
            ]);
        });
    }

    public function toggleStatus(int $medicineId, int $actorId): void
    {
        Database::transaction(function (PDO $pdo) use ($medicineId, $actorId): void {
            $stmt = $pdo->prepare('SELECT name, is_active FROM medicines WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $medicineId]);
            $medicine = $stmt->fetch();
            if (!is_array($medicine)) {
                throw new \InvalidArgumentException('Médicament introuvable.');
            }

            $active = (int) $medicine['is_active'] === 1 ? 0 : 1;
            $update = $pdo->prepare('UPDATE medicines SET is_active = :active, updated_at = NOW() WHERE id = :id');
            $update->execute(['active' => $active, 'id' => $medicineId]);
            $this->audit->log('medicine_status_changed', 'medicine', $medicineId, $actorId, [
                'name' => $medicine['name'],
                'is_active' => $active,
            ]);
        });
    }

    public function recordStockMovement(int $medicineId, int $actorId, array $input): void
    {
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
        $type = (string) ($input['movement_type'] ?? '');
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($medicineId < 1 || $quantity === false || $quantity < 1 || $quantity > 1000000
            || !in_array($type, ['entree', 'sortie'], true)) {
            throw new \InvalidArgumentException('Mouvement de stock invalide.');
        }
        if (mb_strlen($reason) > 255) {
            throw new \InvalidArgumentException('Le motif ne peut pas dépasser 255 caractères.');
        }

        Database::transaction(function (PDO $pdo) use ($medicineId, $actorId, $quantity, $type, $reason): void {
            $stmt = $pdo->prepare('SELECT name, stock_unit, stock_quantity
                FROM medicines WHERE id = :id AND is_active = 1 FOR UPDATE');
            $stmt->execute(['id' => $medicineId]);
            $medicine = $stmt->fetch();
            if (!is_array($medicine)) {
                throw new \InvalidArgumentException('Médicament introuvable ou désactivé.');
            }

            $before = (int) $medicine['stock_quantity'];
            if ($type === 'sortie' && $quantity > $before) {
                throw new \InvalidArgumentException('Le stock disponible est insuffisant.');
            }
            if ($type === 'entree' && $before > 4294967295 - $quantity) {
                throw new \InvalidArgumentException('Le stock dépasserait la capacité maximale autorisée.');
            }
            $after = $type === 'entree' ? $before + $quantity : $before - $quantity;

            $update = $pdo->prepare('UPDATE medicines SET stock_quantity = :stock_quantity, updated_at = NOW() WHERE id = :id');
            $update->execute(['stock_quantity' => $after, 'id' => $medicineId]);
            $movement = $pdo->prepare('INSERT INTO medicine_stock_movements
                (medicine_id, movement_type, quantity, quantity_unit, stock_before, stock_after, reason, created_by, created_at)
                VALUES (:medicine_id, :movement_type, :quantity, :quantity_unit, :stock_before, :stock_after, :reason, :created_by, NOW())');
            $movement->execute([
                'medicine_id' => $medicineId,
                'movement_type' => $type,
                'quantity' => $quantity,
                'quantity_unit' => $medicine['stock_unit'],
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $reason !== '' ? $reason : null,
                'created_by' => $actorId,
            ]);
            $this->audit->log('medicine_stock_moved', 'medicine', $medicineId, $actorId, [
                'name' => $medicine['name'],
                'movement_type' => $type,
                'quantity' => $quantity,
                'unit' => $medicine['stock_unit'],
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $reason,
            ]);
        });
    }

    private function validatedMedicine(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $dosageForm = trim((string) ($input['dosage_form'] ?? ''));
        $strength = trim((string) ($input['strength'] ?? ''));
        $stockUnit = trim((string) ($input['stock_unit'] ?? ''));
        $errors = [];
        if ($name === '' || mb_strlen($name) > 190) {
            $errors[] = 'Le nom du médicament est obligatoire et limité à 190 caractères.';
        }
        if ($dosageForm === '' || mb_strlen($dosageForm) > 100) {
            $errors[] = 'La forme du médicament est obligatoire et limitée à 100 caractères.';
        }
        if (mb_strlen($strength) > 100) {
            $errors[] = 'Le dosage/concentration est limité à 100 caractères.';
        }
        if ($stockUnit === '' || mb_strlen($stockUnit) > 50) {
            $errors[] = 'L’unité de stock est obligatoire et limitée à 50 caractères.';
        }
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        return [
            'name' => $name,
            'dosage_form' => $dosageForm,
            'strength' => $strength !== '' ? $strength : null,
            'stock_unit' => $stockUnit,
        ];
    }
}
