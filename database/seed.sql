USE cliniva;

INSERT INTO specialties (id, name, description) VALUES
  (1, 'Medecine generale', 'Consultations de premiere ligne'),
  (2, 'Pediatrie', 'Suivi medical des enfants'),
  (3, 'Gynecologie', 'Sante de la femme')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO users (id, role, first_name, last_name, email, phone, password_hash, email_verified_at, consent_at, is_active) VALUES
  (1, 'admin', 'Amina', 'Ngono', 'admin@cliniva.local', '+237690000001', '$argon2id$v=19$m=65536,t=4,p=1$YUdxOTRMeVJ5WHk1bjRYYQ$BdL1WymzsyhOmQ8YuMWWJEFKw39faJ7c0dlO4y4+MQ4', NOW(), NOW(), 1),
  (2, 'medecin', 'Patrick', 'Mbarga', 'medecin@cliniva.local', '+237690000002', '$argon2id$v=19$m=65536,t=4,p=1$aUFIc2YwSHdnaG9NSEJXUQ$29HSgxU8HEkIQws4mgLNX6dCmTSVYnjeEz2N+sstu9o', NOW(), NOW(), 1),
  (3, 'secretaire', 'Carine', 'Mballa', 'secretaire@cliniva.local', '+237690000003', '$argon2id$v=19$m=65536,t=4,p=1$dlhvTFBIMDI2YmpPZ0ZXdg$sW2conLYIfNe9TZFG8yHoQN4YCO6rlFRqMAK8kkMdRs', NOW(), NOW(), 1),
  (4, 'patient', 'Joel', 'Talla', 'patient@cliniva.local', '+237690000004', '$argon2id$v=19$m=65536,t=4,p=1$Z2RHTFdBampWOFZxVHh5WA$K4ib4cKwY27/O9kycYoupmZkLjjzk7FCR89+kbABUNY', NOW(), NOW(), 1)
ON DUPLICATE KEY UPDATE role = VALUES(role), is_active = VALUES(is_active);

INSERT INTO doctors (id, user_id, specialty_id, license_number, slot_duration_minutes) VALUES
  (1, 2, 1, 'ONMC-CLV-0001', 30)
ON DUPLICATE KEY UPDATE specialty_id = VALUES(specialty_id), slot_duration_minutes = VALUES(slot_duration_minutes);

INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time) VALUES
  (1, 1, '08:00:00', '12:00:00'),
  (1, 2, '08:00:00', '12:00:00'),
  (1, 3, '14:00:00', '17:00:00'),
  (1, 4, '08:00:00', '12:00:00'),
  (1, 5, '08:00:00', '12:00:00')
ON DUPLICATE KEY UPDATE end_time = VALUES(end_time);

INSERT INTO patients (id, user_id, record_number, first_name, last_name, birth_date, sex, phone, email, address, emergency_contact, consent_at) VALUES
  (1, 4, 'CLV-2026-000001', 'Joel', 'Talla', '1992-05-14', 'M', '+237690000004', 'patient@cliniva.local', 'Douala - Bonamoussadi', 'Marie Talla +237690000005', NOW()),
  (2, NULL, 'CLV-2026-000002', 'Grace', 'Essomba', '1988-09-23', 'F', '+237690000006', NULL, 'Douala - Akwa', 'Paul Essomba +237690000007', NOW())
ON DUPLICATE KEY UPDATE phone = VALUES(phone), address = VALUES(address);

INSERT INTO patient_allergies (patient_id, allergen, severity, notes) VALUES
  (1, 'Penicilline', 'severe', 'Reaction cutanee importante signalee par le patient')
ON DUPLICATE KEY UPDATE notes = VALUES(notes);

INSERT INTO patient_history (patient_id, type, label, notes_encrypted, recorded_by) VALUES
  (1, 'medical', 'Asthme intermittent', 'DEMO_CHIFFREMENT_A_REMPLACER_PAR_LIBSODIUM', 2)
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO appointments (id, patient_id, doctor_id, specialty_id, start_datetime, end_datetime, status, reason, confirmed_at, created_by) VALUES
  (1, 1, 1, 1, '2026-10-12 08:30:00', '2026-10-12 09:00:00', 'confirme', 'Controle general', NOW(), 3),
  (2, 2, 1, 1, '2026-10-12 09:00:00', '2026-10-12 09:30:00', 'en_attente_confirmation', 'Fievre et fatigue', NULL, 3)
ON DUPLICATE KEY UPDATE status = VALUES(status), reason = VALUES(reason);

INSERT INTO services (code, name, price_xaf) VALUES
  ('CONS-GEN', 'Consultation medecine generale', 5000),
  ('TRIAGE', 'Triage et constantes', 1000),
  ('CERT-MED', 'Certificat medical', 3000)
ON DUPLICATE KEY UPDATE price_xaf = VALUES(price_xaf);

INSERT INTO settings (setting_key, setting_value, value_type, updated_by) VALUES
  ('appointment_confirmation_hours', '24', 'integer', 1),
  ('password_reset_minutes', '60', 'integer', 1),
  ('appointment_cancel_min_hours', '6', 'integer', 1),
  ('clinic_timezone', 'Africa/Douala', 'string', 1),
  ('clinic_currency', 'XAF', 'string', 1)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by);

INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, metadata) VALUES
  (1, 'seed_import', 'system', NULL, '127.0.0.1', 'Cliniva seed.sql', '{"phase":1}');
