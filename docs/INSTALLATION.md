# Installation locale Cliniva - Phase 1

## 1. Prerequis XAMPP

Environnement cible :

- Windows avec XAMPP.
- PHP 8.2.
- MariaDB/MySQL via XAMPP.
- Projet placé dans `C:\xampp 1\htdocs\CLINIV`.

Extensions PHP a activer dans `php.ini` :

```ini
extension=mbstring
extension=intl
extension=gd
extension=zip
extension=fileinfo
extension=openssl
extension=sodium
extension=pdo_mysql
```

Redemarrez Apache apres modification.

## 2. Composer

Depuis le dossier du projet :

```powershell
composer install
composer validate
```

Si Composer n'est pas dans le `PATH`, installez Composer pour Windows ou utilisez `php composer.phar install`.

Dependances prevues :

```powershell
composer require dompdf/dompdf phpoffice/phpword phpmailer/phpmailer endroid/qr-code spomky-labs/otphp
```

## 3. Configuration applicative

Copiez le fichier d'exemple :

```powershell
Copy-Item .env.example .env
```

Puis adaptez :

- `APP_URL=http://127.0.0.1:8000` pour le lancement local simple, ou `http://localhost/CLINIV/public` selon l’accès choisi.
- `DB_DATABASE=cliniva`.
- `DB_USERNAME=root`.
- `DB_PASSWORD=` si XAMPP n'a pas de mot de passe local.
- `APP_ENV=production` uniquement en HTTPS, afin d'activer cookies Secure et HSTS.
- `SODIUM_KEY_BASE64` doit contenir 32 octets aléatoires encodés en base64; l'application refuse une clé absente ou invalide en production.
- `APPOINTMENT_VALIDATION_HOURS=24` et `APPOINTMENT_REMINDER_ENABLED=true` pilotent la validation et les rappels.
- Les délais d’annulation et de validation peuvent être modifiés dans `/admin/manage`; les valeurs de `.env` restent le fallback si aucun réglage n’est enregistré.

Generez des cles aleatoires avant toute donnee reelle :

```powershell
C:\xampp 1\php\php.exe -r "echo 'APP_KEY=base64:', base64_encode(random_bytes(32)), PHP_EOL;"
C:\xampp 1\php\php.exe -r "echo 'SODIUM_KEY_BASE64=', base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), PHP_EOL;"
```

## 4. Base de donnees

Dans phpMyAdmin ou via le client MariaDB, importez dans cet ordre :

```text
database/schema.sql
database/seed.sql
```

Pour une base existante, appliquez les migrations additives dans l'ordre avant d'utiliser la vitrine et l'espace patient :

```text
database/migrations/2026-10-27_create_contact_messages.sql
database/migrations/2026-10-27_patient_portal.sql
database/migrations/2026-10-31_secretary_appointment_validation.sql
database/migrations/2026-10-31_appointment_reminders.sql
database/migrations/2026-10-31_admin_password_change.sql
database/migrations/2026-10-31_doctor_impediment_alerts.sql
database/migrations/2026-11-04_medicines_inventory.sql
database/migrations/2026-11-04_medicine_prescription_returns.sql
```

Les migrations doivent être appliquées dans cet ordre sur une base existante. Les migrations du 31 octobre ajoutent les statuts de validation secrétaire, la table anti-doublon des rappels et l’obligation de changer le mot de passe provisoire. Les migrations du 4 novembre ajoutent le catalogue et le journal de stock des médicaments, la quantité prescrite et le retour du stock lors de l’annulation d’une ligne avant la clôture; les anciennes ordonnances restent consultables et ne déduisent pas rétroactivement du stock. Les nouvelles demandes patient attendent la validation de la secrétaire; les rappels J-1/H-2 sont générés par `cron/send_reminders.php`.

L’administration et la réception partagent la page `/reception/medicines` pour gérer le catalogue et les mouvements de stock. Une prescription médecin exige une quantité explicite en unité de stock et déduit cette quantité de manière transactionnelle; une quantité indisponible est refusée. Les patients voient le détail de leurs ordonnances dans leurs documents et peuvent télécharger le PDF.
Activez également l'extension PHP `gd`; les photos sont réencodées en WebP, redimensionnées et stockées dans `storage/uploads/patient-profiles`, hors du dossier public.

Les coordonnées publiques sont lues dans `settings`. Remplacez les valeurs par les coordonnées réelles du centre avant la mise en ligne :

```sql
INSERT INTO settings (setting_key, setting_value, value_type, updated_by) VALUES
  ('clinic_email', 'VOTRE_EMAIL_PUBLIC', 'string', NULL),
  ('clinic_phone', 'VOTRE_TELEPHONE', 'string', NULL),
  ('clinic_address', 'ADRESSE_DU_CENTRE', 'string', NULL),
  ('clinic_opening_hours', 'HORAIRES_DU_CENTRE', 'string', NULL)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = NULL;
```

Ne demandez pas aux visiteurs d'envoyer des informations médicales dans le formulaire public. L'intégration de Google Maps charge un contenu tiers; tenez-en compte dans l'information de confidentialité du site.

Comptes demo :

| Role | E-mail | Mot de passe |
| --- | --- | --- |
| Admin | admin@cliniva.local | Demo2026! |
| Medecin | medecin@cliniva.local | Demo2026! |
| Secretaire | secretaire@cliniva.local | Demo2026! |
| Patient | patient@cliniva.local | Demo2026! |

## 5. VirtualHost Apache

Ajoutez dans `C:\xampp 1\apache\conf\extra\httpd-vhosts.conf` :

```apacheconf
<VirtualHost *:80>
    ServerName cliniva.local
    DocumentRoot "C:/xampp 1/htdocs/CLINIV/public"

    <Directory "C:/xampp 1/htdocs/CLINIV/public">
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog "logs/cliniva-error.log"
    CustomLog "logs/cliniva-access.log" common
</VirtualHost>
```

Ajoutez dans `C:\Windows\System32\drivers\etc\hosts` avec les droits administrateur :

```text
127.0.0.1 cliniva.local
```

Puis mettez `APP_URL=http://cliniva.local` dans `.env`.

Alternative sans VirtualHost :

```text
http://localhost/CLINIV/public
```

## 6. Verifications Phase 1

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp 1\php\php.exe -l $_.FullName }
composer validate
```

Points de vigilance securite :

- Ne jamais placer `.env`, `storage/uploads` ou `storage/backups` dans `public/`.
- Ne jamais importer de donnees reelles avec les mots de passe demo.
- En production, utiliser HTTPS, `APP_ENV=production` et une cle sodium forte.

## 7. Tests Phase 2

Routes principales :

- `GET /register` : inscription patient avec consentement.
- `GET /verify-email?email=...&token=...` : verification e-mail.
- `GET /login` : connexion.
- `GET /2fa/setup` puis `POST /2fa/setup` : activation TOTP obligatoire pour l'administrateur.
- `GET /password/forgot` puis `GET /password/reset` : reinitialisation par token.

En environnement local, les liens de verification et de reinitialisation sont aussi affiches dans les messages flash apres soumission. Les e-mails sont places dans la table `notifications` avec le statut `en_attente`.

Commandes de verification :

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp 1\php\php.exe -l $_.FullName }
composer validate
```

Comportement attendu :

- `patient@cliniva.local / Demo2026!` se connecte directement.
- `admin@cliniva.local / Demo2026!` est redirige vers la configuration TOTP au premier login.
- Cinq echecs de connexion en moins de 15 minutes bloquent temporairement le compte ou l'IP.
- Apres authentification, le patient arrive sur `/patient`, la secretaire sur `/reception`, le medecin sur `/doctor` et l'administrateur sur `/admin`.
- Le message de bienvenue utilise M. ou Mme lorsque le sexe du patient est enregistre; pour les comptes sans civilite enregistree, le message reste neutre.
- La navigation patient et secretaire ne montre pas les liens publics (A propos, Services, Contact) ni le lien Tableau de bord generique. La deconnexion POST avec CSRF reste visible sur toutes les pages connectees.

## 8. Tests Phase 3

Parcours patient :

- `GET /patient` : espace patient mobile-first.
- `GET /patient/appointments/new` : prise de rendez-vous guidee.
- `GET /patient/appointments/doctors?specialty_id=1` : medecins disponibles en JSON.
- `GET /patient/appointments/slots?doctor_id=1&date=2026-10-13` : creneaux libres en JSON.
- `POST /patient/appointments` : création transactionnelle d’une demande au statut `en_attente_validation`, avec notification à la secrétaire.
- `POST /patient/appointments/respond` : réponse du patient à une proposition de report ou confirmation.
- `GET /appointments/confirm?id=...&token=...` : compatibilité avec les anciens liens de confirmation encore valides.
- `POST /patient/appointments/cancel` : annulation patient selon `APPOINTMENT_CANCEL_MIN_HOURS`.
- `GET /patient/profile` : coordonnées et téléversement sécurisé de la photo de profil.
- `GET /patient/documents` : documents patient; chaque téléchargement vérifie le propriétaire du dossier.
- `GET /patient/history?page=1` : historique paginé des rendez-vous et consultations.
- `GET /patient/documents/download?type=prescription&id=1` : PDF généré pour les documents pris en charge; les fichiers médicaux restent privés dans `storage/uploads`.

Les creneaux deja pris, passes ou couverts par un conge medecin sont exclus cote serveur. La contrainte `UNIQUE (doctor_id, start_datetime)` protege contre la double reservation meme si deux patients valident en meme temps.
L'édition d'un rendez-vous utilise le même parcours de sélection et le même délai minimum que l'annulation; la nouvelle proposition doit être validée par la secrétaire. Les téléchargements PDF générés nécessitent les dépendances installées avec `composer install`.

## 9. Tests Phase 4

Routes reception :

- `GET /reception` : tableau de bord secretaire.
- `GET /reception/patients/new` et `POST /reception/patients` : creation de dossier patient avec detection de doublon.
- `GET /reception/patients/search?q=...` : recherche AJAX par dossier, nom ou telephone.
- `GET /reception/appointments/new` et `POST /reception/appointments` : prise de rendez-vous au guichet.
- `GET /reception/history` : actions récentes du compte réception connecté.
- `POST /reception/doctor-alerts/resolve` : prise en charge d’un signalement médecin.
- `POST /reception/appointments/cancel` : annulation par la reception.
- `POST /reception/appointments/validate` : validation d’une demande patient.
- `POST /reception/appointments/reschedule` : proposition d’un créneau libre recalculé côté serveur.
- `POST /reception/appointments/refuse` et `/reception/appointments/message` : refus motivé ou message au patient.
- `POST /doctor/appointments/impediment` : signalement sécurisé d’un empêchement médecin, visible à la réception.
- `GET /reception/triage` et `POST /reception/triage` : constantes vitales et file d'attente.
- `GET /reception/queue` : file d'attente JSON pour polling AJAX.
- `GET /reception/schedules`, `POST /reception/schedules`, `POST /reception/time-off` : horaires recurrents, exceptions et conges.
- `GET /reception/billing` et `POST /reception/invoices` : facture, paiement et encaissement.

Compte de test :

```text
secretaire@cliniva.local / Demo2026!
```

Le tableau de bord `/dashboard` redirige automatiquement les secretaires vers `/reception`.

## 10. Rappels et nouvelles fonctions

Dans le Planificateur de tâches Windows, créez une tâche qui se répète toutes les 5 minutes :

- Programme : `C:\xampp 1\php\php.exe`
- Arguments : `C:\xampp 1\htdocs\CLINIV\cron\send_reminders.php`
- Démarrer dans : `C:\xampp 1\htdocs\CLINIV`

Le script expire les demandes non traitées, met en file les rappels J-1 et H-2 une seule fois par rendez-vous, puis envoie les e-mails en attente avec reprises temporisées en cas d’échec. Sur cPanel, configurez le même script avec PHP 8.2 dans le cron toutes les 5 minutes.

Créez aussi une tâche distincte qui se répète toutes les minutes pour clôturer les consultations 30 minutes après l’heure prévue du rendez-vous :

- Programme : `C:\xampp 1\php\php.exe`
- Arguments : `C:\xampp 1\htdocs\CLINIV\cron\close_expired_consultations.php`
- Démarrer dans : `C:\xampp 1\htdocs\CLINIV`

Cette clôture marque le rendez-vous comme terminé et verrouille la consultation. Le médecin peut toujours ajouter un addendum, mais ne peut plus modifier les notes ou ajouter des examens et ordonnances. Le tableau de bord et les actions médecin appliquent aussi la clôture dès qu’ils sont utilisés après l’échéance.
Avec le Planificateur de tâches configuré pour le compte Windows courant, ce compte doit rester connecté pour que la tâche s’exécute.

Routes supplémentaires à vérifier après migration :

- `GET /reception/history` : historique des actions réception du compte connecté.
- `GET /doctor/appointments?date=2026-10-31&status=confirme` : rendez-vous médecin filtrés.
- `POST /doctor/appointments/impediment` : alerte d’empêchement, visible sur l’accueil réception.
- `GET /admin/manage` : comptes staff, spécialités, tarifs, messages publics et journal d’audit.
- La gestion admin permet de modifier les rôles staff, d’activer/désactiver comptes/catalogue et de déclencher un reset par lien; tout nouveau compte staff doit changer son mot de passe à la première connexion (après le TOTP admin si applicable).
- Les journaux d’audit et de connexion sont filtrables par utilisateur/action ou e-mail, résultat et période.
- Le dashboard `/admin` expose les tendances, recettes, motifs, taux d’occupation estimé, annulations/absences et attente moyenne.
- `GET /doctor/record/export?patient_id=1` : résumé DOCX, réservé au médecin autorisé sur le dossier.

## 11. Déploiement o2switch

Le guide complet cPanel/PHP 8.2/HTTPS/SMTP/cron/permissions est dans [DEPLOYMENT_O2SWITCH.md](DEPLOYMENT_O2SWITCH.md). Les outils CLI livrés sont `bin/generate_keys.php` (génère et affiche les clés, sans modifier `.env`) et `storage/backups/backup_database.sh` (dump compressé, rotation de 14 jours via `bin/backup_database.php`). Exécutez ces scripts uniquement en SSH/cron, jamais depuis un navigateur.
