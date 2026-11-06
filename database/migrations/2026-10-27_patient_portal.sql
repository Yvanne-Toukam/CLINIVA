-- Patient profile and center messages additions.
ALTER TABLE patients
  ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL AFTER email;

CREATE TABLE IF NOT EXISTS patient_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id BIGINT UNSIGNED NOT NULL,
  appointment_id BIGINT UNSIGNED NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  canal ENUM('espace','email') NOT NULL DEFAULT 'espace',
  sujet VARCHAR(190) NOT NULL,
  corps TEXT NOT NULL,
  lu_le DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_patient_messages_patient_date (patient_id, created_at),
  KEY idx_patient_messages_unread (patient_id, lu_le),
  CONSTRAINT fk_patient_messages_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_patient_messages_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  CONSTRAINT fk_patient_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
