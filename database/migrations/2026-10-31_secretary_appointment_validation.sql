ALTER TABLE appointments
  MODIFY COLUMN status ENUM(
    'en_attente_confirmation',
    'en_attente_validation',
    'confirme',
    'arrive',
    'en_consultation',
    'termine',
    'annule',
    'absent',
    'expire',
    'reporte',
    'refuse'
  ) NOT NULL DEFAULT 'en_attente_confirmation',
  ADD COLUMN IF NOT EXISTS validation_expires_at DATETIME NULL AFTER confirmation_expires_at;