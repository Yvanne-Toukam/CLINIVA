# Déploiement Cliniva sur o2switch

Ce guide suppose un compte cPanel avec PHP 8.2, SSH et MariaDB. Remplacez `CPANELUSER` et `votre-domaine.tld` par vos valeurs. Ne copiez jamais les mots de passe ou clés dans un ticket, une capture d’écran ou le dépôt Git.

## 1. Arborescence et document root

Placez le projet hors de `public_html` :

```text
/home/CPANELUSER/cliniva/          # app/, config/, database/, storage/, vendor/, .env
/home/CPANELUSER/cliniva/public/   # seul document root accessible par HTTP
```

Dans cPanel, créez le domaine ou sous-domaine et faites pointer son document root vers `/home/CPANELUSER/cliniva/public`. Ne déplacez pas `storage`, `app`, `config`, `.env` ou `vendor` sous `public_html`. La racine du projet comporte également des règles de refus d’accès pour les installations de repli, mais le document root `public/` reste la configuration recommandée.

## 2. PHP, Composer et base

Dans **MultiPHP Manager**, sélectionnez PHP 8.2. Activez au minimum `mbstring`, `intl`, `gd`, `zip`, `fileinfo`, `openssl`, `sodium` et `pdo_mysql`. En SSH, vérifiez le binaire PHP CLI utilisé par cPanel :

```bash
php -v
which php
```

Si `php` ne pointe pas sur PHP 8.2, utilisez le binaire PHP 8.2 fourni par cPanel, souvent `/opt/cpanel/ea-php82/root/usr/bin/php`, dans les commandes Composer et cron.

Créez une base MariaDB et un utilisateur depuis cPanel, puis accordez à cet utilisateur les droits sur cette base. Dans le répertoire du projet :

```bash
cd /home/CPANELUSER/cliniva
composer install --no-dev --optimize-autoloader
composer validate --no-check-publish
```

Importez `database/schema.sql` dans une base neuve. Pour une base existante, appliquez les migrations non encore exécutées, dans cet ordre :

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

N’importez `database/seed.sql` que dans un environnement de démonstration contrôlé. Ne l’importez pas en production et ne le téléversez pas dans le document root.

Les migrations du 4 novembre ajoutent le catalogue de médicaments et le suivi des mouvements de stock. L’administration et la réception gèrent le catalogue; les médecins prescrivent une quantité explicite, déduite atomiquement du stock disponible, et les patients consultent leurs ordonnances dans leurs documents. Avant la clôture de la consultation, le médecin peut annuler une ligne et réintégrer son stock avec une trace d’audit.

## 3. Configuration de production

Générez les clés sur le serveur, depuis le répertoire du projet :

```bash
php bin/generate_keys.php
```

Copiez les deux lignes émises dans `.env`, puis saisissez les autres valeurs avec un éditeur sécurisé :

```dotenv
APP_NAME="Cliniva"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.tld
APP_TIMEZONE=Africa/Douala
APP_CURRENCY=XAF

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPANELUSER_cliniva
DB_USERNAME=CPANELUSER_dbuser
DB_PASSWORD="REMPLACER_PAR_LE_SECRET_MYSQL"

MAIL_HOST=mail.votre-domaine.tld
MAIL_PORT=465
MAIL_USERNAME=no-reply@votre-domaine.tld
MAIL_PASSWORD="REMPLACER_PAR_LE_SECRET_SMTP"
MAIL_ENCRYPTION=ssl
MAIL_SMTP_AUTH=true
MAIL_FROM_ADDRESS=no-reply@votre-domaine.tld
MAIL_FROM_NAME="Cliniva"

APPOINTMENT_VALIDATION_HOURS=24
APPOINTMENT_CANCEL_MIN_HOURS=6
APPOINTMENT_REMINDER_ENABLED=true
```

Conservez les valeurs `APP_KEY` et `SODIUM_KEY_BASE64` générées. L’application refuse une clé sodium absente ou invalide en production. Ne régénérez pas ces clés après avoir enregistré des données chiffrées : l’ancienne clé est nécessaire pour les déchiffrer. Les paramètres de délai peuvent ensuite être ajustés dans l’administration.

## 4. HTTPS et exposition publique

Activez AutoSSL dans cPanel, puis vérifiez que le domaine sert bien HTTPS. `public/.htaccess` redirige les domaines distants vers HTTPS et laisse les domaines de développement local en HTTP. Si l’hébergement utilise un proxy TLS particulier, vérifiez la détection HTTPS avec le support o2switch avant d’ajouter des règles de proxy.

Le middleware envoie HSTS lorsque `APP_ENV=production`. Le fichier `public/.user.ini` désactive l’affichage des erreurs et active leur journalisation. Configurez également le chemin absolu de journalisation PHP dans cPanel ou `.user.ini`, par exemple :

```ini
error_log=/home/CPANELUSER/cliniva/storage/logs/php-error.log
```

## 5. E-mails

Créez une boîte SMTP dans cPanel et renseignez ses paramètres dans `.env`. Le port 465 utilise généralement `MAIL_ENCRYPTION=ssl`; le port 587 utilise généralement `MAIL_ENCRYPTION=tls`. Confirmez les paramètres exacts dans cPanel avant la mise en ligne.

Dans la zone DNS du domaine, activez les entrées SPF et DKIM proposées par cPanel et ajoutez une politique DMARC adaptée à votre domaine. Testez d’abord la file `notifications` avec une adresse de test. Les e-mails échoués sont retentés avec un délai croissant, jusqu’à la limite configurée.

## 6. Tâches cron et sauvegardes

Dans **cPanel → Cron Jobs**, planifiez l’expiration et l’envoi des rappels toutes les cinq minutes :

```cron
*/5 * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/CPANELUSER/cliniva/cron/send_reminders.php >> /home/CPANELUSER/cliniva/storage/logs/cron-reminders.log 2>&1
```

Planifiez une sauvegarde quotidienne, par exemple à 02:15 :

```cron
15 2 * * * PHP_CLI_BIN=/opt/cpanel/ea-php82/root/usr/bin/php /bin/bash /home/CPANELUSER/cliniva/storage/backups/backup_database.sh >> /home/CPANELUSER/cliniva/storage/logs/cron-backup.log 2>&1
```

Le script utilise les paramètres DB de `.env`, crée un dump compressé `.sql.gz`, garde les fichiers avec des permissions privées et supprime ses propres sauvegardes `cliniva_*.sql.gz` âgées de plus de 14 jours. Si `mysqldump` n’est pas dans le `PATH` du cron, ajoutez `MYSQLDUMP_PATH=/chemin/fourni/par/o2switch` dans `.env`. Vérifiez la première sauvegarde et copiez périodiquement une sauvegarde vers un stockage hors du compte cPanel.

Ajoutez une tâche cron distincte chaque minute pour clôturer les consultations 30 minutes après l’heure prévue du rendez-vous :

```cron
* * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/CPANELUSER/cliniva/cron/close_expired_consultations.php >> /home/CPANELUSER/cliniva/storage/logs/cron-consultations.log 2>&1
```

Le script verrouille la consultation et marque le rendez-vous terminé. Le médecin peut toujours ajouter un addendum, mais ne peut plus modifier les notes ni ajouter des examens ou ordonnances. Les pages et actions du médecin appliquent aussi la clôture dès qu’elles sont utilisées après l’échéance.

Testez la restauration vers une base **de test** avant la mise en ligne :

```bash
gzip -dc /home/CPANELUSER/cliniva/storage/backups/cliniva_YYYYMMDD_HHMMSS.sql.gz \
	| mysql -h localhost -u CPANELUSER_dbuser -p CPANELUSER_cliniva_test
```

La commande `mysql` demandera le mot de passe sans l’inscrire dans l’historique shell. N’effectuez pas ce test en écrasant la base de production.

## 7. Permissions et journaux

Depuis SSH, dans le répertoire du projet, appliquez des permissions restrictives :

```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 600 .env
chmod 750 storage storage/backups storage/logs storage/uploads
chmod 750 storage/backups/backup_database.sh
```

Le propriétaire du compte cPanel doit rester propriétaire des fichiers. `storage/` doit être inscriptible par PHP, mais ne doit pas être publiquement accessible. N’utilisez pas `777`.

## 8. Checklist avant ouverture

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://votre-domaine.tld` et clés de production fortes dans `.env`.
- Document root limité à `public/`; vérifier qu’une requête HTTP vers `.env`, `composer.json`, `database/schema.sql`, `storage/` et `vendor/` ne retourne jamais leur contenu.
- HTTPS, cookies `Secure`, HSTS, CSP et en-têtes de sécurité vérifiés.
- Inscription, vérification e-mail, connexion patient, secrétaire, médecin, TOTP admin et changement obligatoire du mot de passe staff testés.
- Workflow de rendez-vous testé : demande patient, validation/refus/report secrétaire, réponse patient, annulation et expiration.
- Upload privé, téléchargement autorisé, export PDF/DOCX et fichier de log vérifiés.
- SMTP testé, rappels mis en file, cron exécuté, sauvegarde créée et restauration testée sur une base de test.
- Comptes et mots de passe de démonstration supprimés ou changés; `seed.sql` absent du serveur de production.
- `.env`, clés et sauvegardes absents de tout répertoire servi par Apache.