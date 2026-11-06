Tu travailles directement dans le dossier courant (C:\xampp 1\htdocs\CLINIV). Crée et modifie les fichiers réellement sur le disque, ne te contente pas de les afficher. Mon environnement : Windows, XAMPP, PHP 8.2, MariaDB. Après chaque phase, exécute les vérifications possibles (php -l sur chaque fichier PHP, composer validate) et corrige les erreurs avant de me répondre. Ne lance jamais de commande destructrice (suppression de dossiers, DROP DATABASE) sans me demander.
Agis en tant qu'Architecte Logiciel Senior et Développeur Full-Stack expert (PHP 8.2, MariaDB/MySQL, HTML5, CSS3, Bootstrap 5.3, JavaScript ES6+, AJAX). Conçois la plateforme web "Cliniva" : un système complet de gestion de centre de santé (patients, rendez-vous, consultations, dossiers médicaux, facturation). Le code doit être de qualité production : propre, commenté en français, sécurisé, sans pseudo-code ni "à compléter".

══════════════════════════════════════
1. ENVIRONNEMENT & CONTRAINTES GÉNÉRALES
══════════════════════════════════════
- Environnement de développement : Windows + XAMPP (Apache, PHP 8.2, MariaDB, phpMyAdmin). Le projet est installé dans C:\xampp 1\htdocs\CLINIV. Le SQL doit être 100 % compatible MariaDB (aucune fonctionnalité exclusive à MySQL 8).
- Stack imposée : PHP orienté objet, HTML5 sémantique, CSS3, Bootstrap 5.3 (grille, composants, modales, formulaires, toasts) + Bootstrap Icons, JavaScript ES6+ natif (fetch/AJAX). Pas de framework PHP lourd.
- Architecture MVC simple : autoloading PSR-4 via Composer, point d'entrée unique (public/index.php), routeur maison, .htaccess pour la réécriture d'URL (mod_rewrite d'Apache).
- Accès : fournis la configuration d'un VirtualHost Apache (cliniva.local pointant vers le dossier public/) ET une alternative simple en http://localhost/cliniva/public. L'URL de base doit être configurable.
- Configuration via fichier .env (hors du dossier public), jamais d'identifiants en dur. Paramètre APP_ENV (local/production) : en local, HTTP accepté ; en production, cookies Secure et HSTS activés automatiquement.
- Connexion base de données via PDO uniquement : requêtes préparées obligatoires, ERRMODE_EXCEPTION, charset utf8mb4, transactions pour les opérations multi-tables.
- Bibliothèques autorisées (via Composer) : Dompdf (PDF), PHPWord (DOCX), PHPMailer (e-mails SMTP), endroid/qr-code ou équivalent (QR), spomky-labs/otphp ou équivalent (TOTP). Côté front : Bootstrap 5.3, Chart.js, FullCalendar (chargés en local dans public/assets/vendor ou via CDN, au choix documenté).
- Fournis la liste des extensions PHP à activer dans php.ini de XAMPP (mbstring, intl, gd, zip, fileinfo, openssl, sodium, pdo_mysql…) et les commandes Composer exactes.
- Contexte : centre de santé au Cameroun. Langue : français. Fuseau horaire : Africa/Douala. Devise : FCFA (XAF), sans décimales.

══════════════════════════════════════
2. SÉCURITÉ & CONFORMITÉ (NON NÉGOCIABLE)
══════════════════════════════════════
Les données de santé sont sensibles : applique le principe du moindre privilège.
- Mots de passe : password_hash (Argon2id ou bcrypt) / password_verify, politique de complexité, rehash automatique si l'algorithme évolue.
- Sessions : cookies HttpOnly, SameSite=Strict (et Secure en production) ; session_regenerate_id(true) à la connexion et à chaque changement de privilège ; expiration par inactivité (20 min) ; destruction complète à la déconnexion.
- CSRF : token par session vérifié sur TOUTES les requêtes POST/PUT/DELETE, y compris AJAX (en-tête X-CSRF-Token), comparaison via hash_equals.
- XSS : échappement systématique des sorties (htmlspecialchars ENT_QUOTES|ENT_SUBSTITUTE) ; en-tête Content-Security-Policy compatible avec Bootstrap/Chart.js/FullCalendar.
- Injection SQL : requêtes préparées partout, aucune concaténation de variable dans une requête.
- RBAC : 4 rôles (admin, medecin, secretaire, patient). Middleware d'autorisation centralisé vérifié côté serveur sur chaque route et chaque appel AJAX. Un patient ne peut JAMAIS accéder au dossier d'un autre patient (vérification de propriété sur chaque ressource). Un médecin n'accède qu'aux dossiers de ses patients.
- Anti-brute-force : limitation des tentatives de connexion (par IP et par compte), verrouillage temporaire, message d'erreur générique.
- Double authentification (TOTP) obligatoire pour l'administrateur.
- Réinitialisation de mot de passe : token à usage unique, haché en base, expirant en 1 heure.
- Vérification de l'e-mail à l'inscription du patient.
- Uploads : validation du type MIME réel, taille maximale, renommage aléatoire, stockage hors du dossier public, téléchargement via script contrôlé par autorisation.
- En-têtes HTTP : X-Content-Type-Options, X-Frame-Options, Referrer-Policy.
- Journal d'audit : qui a consulté/modifié/exporté quel dossier, quand, depuis quelle IP (table audit_logs). Toute lecture d'un dossier médical est tracée.
- Suppression logique (soft delete) : jamais de suppression physique des données de soins.
- Chiffrement au repos des champs les plus sensibles (notes cliniques, diagnostics) via libsodium, clé stockée dans le .env hors base.
- Conformité : respect du secret médical et de la législation camerounaise sur la protection des données personnelles ; case de consentement à l'inscription ; droit d'accès et d'export des données du patient.
- Sauvegardes : script .bat utilisant mysqldump (chemin XAMPP : C:\xampp\mysql\bin\mysqldump.exe) avec rotation, planifiable via le Planificateur de tâches Windows.

══════════════════════════════════════
3. RÔLES, PERMISSIONS & WORKFLOWS
══════════════════════════════════════

A. PATIENT (interface mobile-first, optimisée smartphone)
- Inscription (nom, prénom, date de naissance, sexe, téléphone, e-mail, mot de passe, consentement), vérification e-mail, connexion sécurisée, mot de passe oublié.
- Prise de rendez-vous guidée :
  1. Choix d'une spécialité.
  2. Affichage des médecins de cette spécialité avec leurs créneaux libres réels, calculés dynamiquement (ex. : "Aujourd'hui [jour], Dr X est disponible de 08h00 à 12h00"). Les créneaux déjà pris, passés ou en congé n'apparaissent pas.
  3. Choix du créneau, motif de consultation, validation.
- Confirmation : e-mail automatique avec lien unique (token aléatoire, expiration configurable, ex. 24 h). Un rendez-vous non confirmé à temps passe automatiquement au statut "expiré" et libère le créneau.
- Annulation/report par le patient jusqu'à X heures avant le rendez-vous (règle configurable), avec notification au médecin.
- Espace patient : prochains rendez-vous, historique, résumé de ses consultations, résultats d'examens, téléchargement de ses ordonnances et certificats en PDF, profil, factures.

B. RÉCEPTIONNISTE / SECRÉTAIRE
- Enregistrement des nouveaux patients (numéro de dossier unique, ex. CLV-2026-000123), y compris sans e-mail ; détection de doublons (nom + date de naissance + téléphone).
- Recherche instantanée AJAX (debounce, résultats paginés) par nom, numéro de dossier ou téléphone.
- Prise, modification et annulation de rendez-vous pour le compte d'un patient.
- Gestion des plages de disponibilité des médecins (horaires hebdomadaires récurrents, exceptions, congés, durée de créneau par médecin).
- Accueil & triage : à l'arrivée, saisie des paramètres d'entrée — température, tension artérielle, pouls, fréquence respiratoire, saturation en O2, poids, taille, motif immédiat, état d'arrivée, niveau de priorité (urgence vitale / urgent / normal / non urgent). File d'attente triée par priorité puis heure d'arrivée, mise à jour en temps réel (polling AJAX toutes les 10 à 15 s).
- Encaissement : facture (consultation, examens), paiement (espèces, mobile money, carte), reçu imprimable.

C. MÉDECIN (interface optimisée tablette et PC)
- Agenda du jour : liste et vue calendrier, statut de chaque patient (en attente, arrivé, en consultation, terminé), accès à la file d'attente.
- Gestion de ses propres disponibilités.
- Dossier médical en un clic : identité, allergies (alerte visuelle bien visible), antécédents médicaux/chirurgicaux/familiaux, traitements en cours, constantes vitales avec courbes d'évolution, consultations passées, examens et résultats, documents joints.
- Consultation en direct : motif, anamnèse, examen clinique, diagnostic (code CIM-10 optionnel), observations, plan de soins.
- Prescription d'examens (laboratoire, imagerie) et saisie/consultation des résultats.
- Ordonnance : lignes de médicaments (nom, dosage, posologie, durée, instructions), vérification des allergies connues avant validation, numérotation unique, génération PDF.
- Certificats médicaux (repos, aptitude, etc.) à partir de modèles.
- Verrouillage d'une consultation clôturée : toute correction se fait par addendum tracé, jamais par écrasement.

D. ADMINISTRATEUR
- Gestion des utilisateurs (création, activation/désactivation, réinitialisation, attribution de rôle), des spécialités, des médecins, des tarifs/actes.
- Journal d'audit et logs de connexion avec filtres (utilisateur, date, action).
- Paramétrage : durée des tokens, règles d'annulation, horaires d'ouverture, modèles d'e-mails et de documents.
- Dashboard analytique (Chart.js) : consultations par jour/semaine/mois, motifs les plus fréquents, taux d'occupation par médecin, temps d'attente moyen, taux d'annulation/absence, répartition par spécialité, recettes.

══════════════════════════════════════
4. LES 12 FONCTIONNALITÉS CLÉS (À IMPLÉMENTER INTÉGRALEMENT)
══════════════════════════════════════
1. Authentification & Sécurité : hachage, sessions sécurisées, CSRF, RBAC strict, anti-brute-force, 2FA admin, journal d'audit.
2. Gestion des Patients & Dossier Médical : fiche signalétique complète, allergies, antécédents, constantes, historique et traçabilité des soins.
3. Gestion des Médecins, Spécialités & Horaires : paramétrage des spécialités, grilles horaires hebdomadaires, exceptions et congés.
4. Prise de Rendez-vous Intelligente : disponibilités vérifiées en temps réel côté serveur, protection contre la double réservation (contrainte UNIQUE + transaction), validation par le patient.
5. Espace Consultation Médicale : diagnostics, observations, prescriptions d'examens et ordonnances.
6. Recherche de Patients en Direct : AJAX instantané par nom, numéro de dossier ou téléphone, avec debounce et respect des droits d'accès.
7. Calendrier Interactif & Drag-and-Drop : FullCalendar avec glisser-déposer pour planifier/replanifier, détection automatique des conflits (côté client ET revalidation côté serveur), code couleur par statut.
8. Interface Smartphone Patient : mobile-first avec Bootstrap, rapide, utilisable en connexion lente.
9. Tableau de Bord & Analytics : graphiques dynamiques (occupation, flux journalier, motifs fréquents).
10. Notifications & Rappels : e-mail de confirmation avec lien, rappels automatiques (J-1 et H-2, configurables) via script PHP en ligne de commande (cron/send_reminders.php) exécuté par le Planificateur de tâches Windows en local ou par CRON en production ; file d'envoi avec reprise en cas d'échec ; architecture prête pour SMS/WhatsApp. Pour les tests locaux, configure PHPMailer avec un SMTP de test (Mailtrap ou Gmail avec mot de passe d'application) et explique la procédure.
11. Exports PDF : ordonnances, certificats, reçus et factures via Dompdf, en-tête du centre, numéro unique et QR code de vérification.
12. Exports Word (DOCX) : résumés de dossiers médicaux et rapports d'activité mensuels/annuels via PHPWord.

══════════════════════════════════════
5. MODÈLE DE DONNÉES (schema.sql)
══════════════════════════════════════
Moteur InnoDB, utf8mb4, clés étrangères avec ON DELETE appropriés, index sur les colonnes de recherche/jointure, contraintes UNIQUE là où nécessaire, colonnes created_at / updated_at / deleted_at. Le script doit s'importer tel quel dans phpMyAdmin. Tables minimales :
users, password_resets, email_verifications, login_attempts, specialties, doctors, doctor_schedules, doctor_time_off, patients, patient_allergies, patient_history, appointments (statuts : en_attente_confirmation historique, en_attente_validation, confirmé, reporté, refusé, arrivé, en_consultation, terminé, annulé, absent, expiré ; expiration de validation ; token de confirmation haché conservé pour compatibilité ; UNIQUE (doctor_id, start_datetime)), appointment_reminders, doctor_impediment_alerts, queue_triage, vitals, consultations, medical_records, exam_requests, exam_results, prescriptions, prescription_items, certificates, documents, services, invoices, invoice_items, payments, notifications, patient_messages, contact_messages, audit_logs, settings.
Fournis aussi seed.sql : spécialités, un compte par rôle (mots de passe de démo hachés), médecins avec horaires, quelques patients et rendez-vous.

══════════════════════════════════════
6. ARCHITECTURE & DESIGN
══════════════════════════════════════
- Arborescence : public/ (index.php, .htaccess, assets/css, assets/js, assets/vendor), app/ (Controllers, Models, Services, Middleware, Views), config/, database/, storage/ (logs, uploads, backups — non accessible par le web), cron/, vendor/.
- Design "chic" et épuré basé sur Bootstrap 5.3 : surcharge des variables Bootstrap (--bs-primary, etc.) dans un fichier cliniva.css pour un thème bleu nuit (#0B1F3A), blanc et or minimaliste (#C9A227). Typographie soignée (Google Fonts ou police locale), cartes, tableaux, badges de statut, modales et toasts Bootstrap cohérents. Responsive mobile/tablette/desktop, accessibilité (contrastes, labels, navigation clavier, aria), états de chargement et messages d'erreur clairs.
- Réponses AJAX en JSON avec codes HTTP corrects ; validation côté client ET serveur ; gestion centralisée des erreurs (aucune trace technique exposée, journalisation dans storage/logs).

══════════════════════════════════════
7. LIVRAISON PAR PHASES
══════════════════════════════════════
Ne génère pas tout d'un bloc. Livre phase par phase, en attendant ma validation ("suite") entre chaque phase, avec du code complet et fonctionnel :
- Phase 1 : guide d'installation XAMPP (extensions php.ini, Composer, VirtualHost), arborescence, schema.sql, seed.sql, .env.example, composer.json, config, classe PDO (db.php), routeur, helpers de sécurité (CSRF, session, échappement), middleware RBAC.
- Phase 2 : authentification (inscription, vérification e-mail, connexion, 2FA admin, reset mot de passe), page d'accueil index.php (ambiance chic) et layouts Bootstrap.
- Phase 3 : module patient — prise de rendez-vous (spécialité → médecins disponibles → créneau → confirmation e-mail), espace patient.
- Phase 4 : module réceptionniste — création et recherche AJAX de patients, constantes d'entrée (température, etc.), file de triage, gestion des horaires, facturation.
- Phase 5 : module médecin — agenda, dossier médical, consultation, prescription, calendrier drag-and-drop.
- Phase 6 : exports PDF/DOCX, notifications et rappels (script CLI + Planificateur de tâches).
- Phase 7 : dashboard administrateur, journal d'audit, sauvegardes, guide de passage en production (HTTPS, hébergement) et checklist de sécurité.
À la fin de chaque phase : liste des fichiers créés (avec leur chemin exact dans htdocs\cliniva), instructions de test, et points de vigilance sécurité.

CONTEXTE
Le projet Cliniva (PHP 8.2 MVC, MariaDB, Bootstrap 5.3) possède un socle applicatif existant. Ne présume pas que toutes les phases ou fonctionnalités décrites ci-dessous sont terminées : vérifie leur présence dans le code avant de les annoncer comme livrées. Tu ne le recrées pas : tu le METS À JOUR. Travaille directement dans le dossier courant, modifie les fichiers existants sur le disque, ne change PAS le fonctionnement ni l'affichage des fonctionnalités existantes sauf si indiqué. Avant de commencer, analyse l'arborescence existante. Après chaque phase : php -l sur chaque fichier PHP, composer validate, corrige les erreurs. Aucune commande destructrice (DROP, suppression de dossiers) sans me demander. Toute modification de base de données passe par un fichier database/migrations/AAAA-MM-JJ_nom.sql idempotent, compatible MariaDB, sans toucher aux données existantes.
Cible d'hébergement : o2switch (cPanel, PHP 8.2 via le sélecteur de version, MariaDB, SSH, Composer, cron cPanel, AutoSSL). Langue : français. Fuseau : Africa/Douala. Devise : FCFA.

═══ PHASE A : PAGE D'ACCUEIL PUBLIQUE (vitrine universelle) ═══
Refonte visuelle professionnelle de public/index (page d'accueil visible sans connexion), thème existant (bleu nuit #0B1F3A, blanc, or #C9A227), mobile-first. Sections :
1. Hero plein écran : image ou vidéo d'ambiance (vidéo muette en boucle, poster image, chargement différé, fichier MP4 < 5 Mo, repli sur image si connexion lente ou prefers-reduced-motion), slogan, boutons « Se connecter » et « S'inscrire » bien visibles.
2. Section « À propos de Cliniva » : description du centre, mission, valeurs, chiffres clés (animés), équipe/spécialités, galerie photos.
3. Section « Nos services / spécialités » (lue depuis la table specialties).
4. Section « Comment ça marche » en 4 étapes (inscription → demande de rendez-vous → validation par la secrétaire → consultation).
5. Section « Nous contacter » : coordonnées, horaires d'ouverture (depuis settings), carte, formulaire de contact (nom, e-mail, téléphone, message) avec CSRF, honeypot, limitation de débit par IP, validation client+serveur, stockage dans une nouvelle table contact_messages et e-mail de notification à l'admin.
6. Pied de page : liens utiles, mentions légales, politique de confidentialité, boutons Connexion / Inscription.
7. Navbar publique (À propos, Services, Contact, Connexion, Inscription) avec défilement fluide.
Performances : images en WebP avec loading="lazy", CSS/JS minimisés, polices locales ou preload, balises SEO (title, description, Open Graph, favicon), accessibilité (alt, contrastes, aria). Les médias vont dans public/assets/img et public/assets/video.
La page d'inscription est propre, centrée, avec consentement, indicateur de force du mot de passe, et redirige vers l'espace patient après vérification de l'e-mail.

═══ PHASE B : NAVIGATION DES ESPACES CONNECTÉS ═══
- RETIRER « À propos », « Tableau de bord » (sauf médecin et admin où il est demandé ci-dessous) et « Nous contacter » de toutes les barres de navigation des espaces patient et secrétaire.
- Un bouton « Déconnexion » visible sur TOUTES les pages de TOUS les rôles (POST + CSRF, destruction complète de la session).
- Chaque connexion redirige vers l'accueil de l'espace du rôle avec un message « Bienvenue M./Mme [Nom] » (civilité selon le sexe enregistré).

═══ PHASE C : ESPACE PATIENT ═══
Navbar : Accueil, Profil, Nouveau rendez-vous, Documents, Historique, Déconnexion.
- Accueil : « Bienvenue M./Mme [Nom] », compte à rebours (chrono jours/heures/minutes/secondes, JS) jusqu'au prochain rendez-vous, puis un tableau/calendrier « Mes rendez-vous » avec colonnes : date et heure, spécialité, médecin, statut (badge coloré), actions Annuler / Modifier (selon la règle d'annulation configurable, et notification à la secrétaire).
- Profil : photo (avec bouton d'ajout/changement ; upload sécurisé : MIME réel, taille max, renommage aléatoire, stockage hors public, redimensionnement via GD), téléphone, e-mail, et modification de ces champs.
- Nouveau rendez-vous : conserver EXACTEMENT le parcours existant (spécialité → médecin → créneau → motif). Seule la présentation de la page est améliorée. Le tableau « Mes rendez-vous » est affiché sous le formulaire.
- Documents : ordonnances, certificats, résultats d'examens, factures (téléchargement PDF contrôlé par autorisation), avec une présentation en cartes par type.
- Historique : liste des derniers rendez-vous et consultations passés, paginée.
- Le patient voit une zone « Messages du centre » (messages envoyés par la secrétaire, non lus signalés).

═══ PHASE D : NOUVEAU WORKFLOW DE RENDEZ-VOUS (VALIDATION PAR LA SECRÉTAIRE) ═══
Le médecin n'est jamais en contact direct avec le patient pour les changements de rendez-vous : tout passe par la secrétaire.
- Un rendez-vous demandé par le patient prend le statut « en_attente_validation » (ajouter ce statut et « reporté » via migration ; le créneau est réservé provisoirement, contrainte UNIQUE conservée).
- La secrétaire voit la liste « Rendez-vous à valider » : elle peut Valider (→ confirmé), Reporter (proposer un autre créneau), Refuser (avec motif) ou Annuler. Revalidation serveur des conflits et des disponibilités.
- Messagerie secrétaire → patient (table patient_messages : appointment_id, sender_id, canal, sujet, corps, lu_le) avec modèles prêts à l'emploi : « Merci de confirmer votre rendez-vous de demain », « Le médecin est retenu par une urgence, vous serez reçu dans quelques minutes », « Votre rendez-vous est reporté au… ». Envoi dans l'espace patient ET par e-mail via la file existante (notifications).
- Le patient peut répondre « Je confirme » / « Je ne viendrai pas » depuis son espace ; la secrétaire voit le retour.
- Rappels J-1 et H-2 conservés. Le statut « expiré » s'applique aux demandes non traitées après un délai configurable.
- Toute action est tracée dans audit_logs.

═══ PHASE E : ESPACE SECRÉTAIRE / RÉCEPTIONNISTE ═══
Navbar : Accueil, Ajouter un patient, Rendez-vous, Disponibilités, File d'attente, Historique, Déconnexion (rien d'autre).
- Accueil : « Bienvenue [Prénom Nom] », cartes de synthèse (RDV à valider, patients du jour, file d'attente), patients récents, accès rapides.
- Patients : recherche AJAX existante + liste des patients ayant des rendez-vous, avec statut.
- Rendez-vous : validation, report, annulation, envoi de messages (Phase D).
- Disponibilités : la secrétaire ajoute/modifie les horaires et congés de n'importe quel médecin (récurrent, exceptions, durée de créneau).
- File d'attente et triage : conservés. Historique des actions du compte réception disponible dans `/reception/history`.

═══ PHASE F : ESPACE MÉDECIN ═══
Navbar : Accueil, Agenda, Disponibilités, Rendez-vous, Déconnexion.
- Accueil : « Bonjour Dr [Nom] », indicateurs (patients déjà traités, consultations du jour, à venir), mini-graphiques Chart.js, puis le calendrier FullCalendar EXISTANT conservé tel quel (drag-and-drop, couleurs par statut).
- Disponibilités : le médecin gère lui-même ses horaires et congés (la secrétaire peut aussi le faire).
- Rendez-vous : `GET /doctor/appointments` liste ses rendez-vous filtrables par date et statut, avec accès au dossier médical. Le médecin ne contacte pas le patient pour un report : le bouton « Signaler un empêchement » crée une alerte visible et traitable côté secrétaire.

═══ PHASE G : ESPACE ADMINISTRATEUR ═══
Accès total. Navbar : Tableau de bord, Utilisateurs, Médecins, Secrétaires, Spécialités, Tarifs, Journal d'audit, Paramètres, Messages de contact, Déconnexion.
- Tableau de bord avec graphiques Chart.js : médecins les plus consultés (top), médecins les moins consultés, consultations par période, occupation, motifs fréquents, annulations/absences, recettes, temps d'attente moyen.
- Création/édition/désactivation de médecins ET de secrétaires (avec mot de passe provisoire à changer à la première connexion), attribution de rôles, réinitialisation.
- Journal d'audit et logs de connexion filtrables, consultation des messages reçus via le formulaire de contact.
- 2FA TOTP obligatoire conservée.

═══ PHASE H : PRÉPARATION À L'HÉBERGEMENT O2SWITCH ═══
Livrables ajoutés : guide pas à pas [docs/DEPLOYMENT_O2SWITCH.md](docs/DEPLOYMENT_O2SWITCH.md), générateur `bin/generate_keys.php`, règles Apache HTTPS et sauvegarde quotidienne `storage/backups/backup_database.sh` avec rotation à 14 jours. Le guide couvre :
1. Structure serveur : le projet est placé hors de public_html (ex. /home/CPANELUSER/cliniva/), le dossier public/ est exposé via un sous-domaine ou domaine dont la racine pointe sur cliniva/public (ou, à défaut, un .htaccess de redirection sûr), storage/, app/, config/, .env et vendor/ inaccessibles depuis le web.
2. cPanel : sélection de PHP 8.2 et activation des extensions (mbstring, intl, gd, zip, fileinfo, openssl, sodium, pdo_mysql) ; création de la base MariaDB et de l'utilisateur ; import de schema.sql et des migrations via phpMyAdmin ; Composer en SSH (composer install --no-dev --optimize-autoloader).
3. .env de production : APP_ENV=production, APP_URL=https://votre-domaine, identifiants BD, clés de chiffrement, SMTP. Les clés sont générées avec le script CLI `php bin/generate_keys.php`.
4. HTTPS : AutoSSL, redirection forcée HTTP→HTTPS dans .htaccess, HSTS, cookies Secure (déjà gérés par APP_ENV).
5. E-mails : utiliser le SMTP du compte cPanel, configurer SPF, DKIM et DMARC pour éviter le spam.
6. Cron cPanel : rappels (php cron/send_reminders.php toutes les 5 min), expiration des demandes, sauvegarde quotidienne de la base avec mysqldump (script shell storage/backups avec rotation à 14 jours), à la place du .bat Windows.
7. Droits fichiers (dossiers 755, fichiers 644, storage 750), désactivation de l'affichage des erreurs (display_errors=0), journalisation dans storage/logs.
8. Checklist de mise en ligne : tester inscription, e-mail, connexion de chaque rôle, upload, PDF, cron, CSP avec les CDN utilisés ; changer tous les mots de passe de démo ; supprimer seed.sql du serveur ; vérifier qu'aucun fichier sensible n'est accessible par URL.

