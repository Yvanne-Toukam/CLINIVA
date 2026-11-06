CREATE TABLE IF NOT EXISTS medicines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  dosage_form VARCHAR(100) NOT NULL,
  strength VARCHAR(100) NULL,
  stock_unit VARCHAR(50) NOT NULL,
  stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_medicines_active_name (is_active, name),
  CONSTRAINT fk_medicines_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE prescription_items
  ADD COLUMN IF NOT EXISTS medicine_id BIGINT UNSIGNED NULL AFTER prescription_id,
  ADD COLUMN IF NOT EXISTS medicine_form VARCHAR(100) NULL AFTER medicine_id,
  ADD COLUMN IF NOT EXISTS medicine_strength VARCHAR(100) NULL AFTER medicine_form,
  ADD COLUMN IF NOT EXISTS quantity INT UNSIGNED NULL AFTER duration,
  ADD COLUMN IF NOT EXISTS quantity_unit VARCHAR(50) NULL AFTER quantity,
  ADD CONSTRAINT fk_prescription_items_medicine
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS medicine_stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medicine_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('entree','sortie','ordonnance') NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  quantity_unit VARCHAR(50) NOT NULL,
  stock_before INT UNSIGNED NOT NULL,
  stock_after INT UNSIGNED NOT NULL,
  prescription_item_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_medicine_movement_prescription_item (prescription_item_id),
  KEY idx_medicine_movements_date (medicine_id, created_at),
  CONSTRAINT fk_medicine_movements_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
  CONSTRAINT fk_medicine_movements_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
