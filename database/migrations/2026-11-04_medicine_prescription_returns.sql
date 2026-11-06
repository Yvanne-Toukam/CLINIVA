ALTER TABLE prescription_items
  ADD COLUMN IF NOT EXISTS status ENUM('active','annulee') NOT NULL DEFAULT 'active' AFTER instructions;

ALTER TABLE medicine_stock_movements
  MODIFY COLUMN movement_type ENUM('entree','sortie','ordonnance','retour') NOT NULL,
  DROP INDEX uq_medicine_movement_prescription_item,
  ADD KEY idx_medicine_movement_prescription_item (prescription_item_id);
