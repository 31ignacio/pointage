# Pointage QR Code + Géolocalisation — 100% Laravel (Blade)

Application de gestion des présences : pointage par QR Code permanent +
vérification de géolocalisation côté serveur. Stack **100% Laravel**
(Blade + Bootstrap 5 et JavaScript compilés avec Vite) — pas de Vue.js.

Ce dossier contient uniquement les **fichiers applicatifs** (le code
métier), pas un projet Laravel complet avec le framework. C'est la
pratique standard : le framework vient de Composer, seuls vos fichiers
sont versionnés/livrés.

## 1. Installation

```bash
# 1. Créer un projet Laravel neuf (Laravel 11+)
cd
cd pointage-app

# 2. Copier le contenu de ce zip PAR-DESSUS le projet fraîchement créé
#    (fusionner app/, database/, resources/, routes/)
#    -> écraser routes/web.php et resources/views/dashboard.blade.php
#       (déjà présents dans un projet neuf) par ceux fournis ici.

# 3. Configurer la base de données dans .env (MySQL recommandé)
#    DB_DATABASE=pointage
#    DB_USERNAME=...
#    DB_PASSWORD=...

# 4. Lancer les migrations + le seeder de démo
php artisan migrate
php artisan db:seed --class="Database\Seeders\PointageDemoSeeder"

# 5. Installer et compiler les assets locaux (QR, Bootstrap, styles)
npm install
npm run build

# 6. Enregistrer le middleware "admin"
#    Ouvrir bootstrap/app.php et ajouter dans ->withMiddleware(...) :
#
#    $middleware->alias([
#        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
#    ]);

# 7. Lancer le serveur
php artisan serve
```

Compte de démonstration créé par le seeder :

- Email : `admin@example.com`
- Mot de passe : `password`
- ⚠️ À changer immédiatement en production.

## 2. Important avant mise en production

- **HTTPS obligatoire** (la géolocalisation navigateur exige un
  contexte sécurisé, sauf `localhost`).
- Modifier les coordonnées GPS du site "Siège" créé par le seeder
  (latitude/longitude/rayon réels de l'entreprise) — ou les créer
  directement via l'interface **Sites** une fois connecté en admin.
- Chaque employé doit avoir un **site assigné** (menu Employés) pour
  pouvoir pointer.
- Le mot de passe temporaire généré à la création d'un employé
  s'affiche **une seule fois** à l'écran (flash message) — à
  communiquer de façon sécurisée. À terme, remplacer par un envoi
  email/SMS automatique.

## 3. Ce qui est inclus (V1, conforme au plan de réalisation du cahier des charges)

- Authentification (connexion / déconnexion, rôles).
- Gestion des services (départements).
- Gestion des sites (latitude, longitude, rayon autorisé).
- Gestion des employés (CRUD, compte utilisateur lié automatiquement,
  rôle, statut actif/inactif).
- Page de pointage (QR → page authentifiée → géolocalisation →
  bouton unique Arrivée/Sortie automatique).
- QR Code permanent généré et imprimable (`/pointage/qrcode`,
  admin uniquement) — pointe simplement vers `/pointage`, sans
  aucune donnée de présence dans le code, comme recommandé.
- Logique automatique arrivée/sortie (section 8 du cahier des charges).
- Contrôle de distance **côté serveur** (formule de Haversine,
  `app/Services/GeoService.php`) — jamais confiance aux données brutes
  du navigateur au-delà de la position elle-même.
- Protection contre le double pointage (relecture du dernier pointage
  du jour dans une transaction verrouillée).
- Journal d'audit (`audit_logs`) : connexions, pointages acceptés et
  refusés.
- Tableau de bord admin (présents / absents / sortis, derniers
  pointages du jour).
- Historique personnel de l'employé.
- Rôles : employé, responsable, RH/admin, super admin
  (`isAdmin()` regroupe les 3 derniers).

## 4. Volontairement laissé pour une V2/V3 (comme prévu dans le plan de réalisation)

- Horaires de travail, calcul des retards/absences automatique.
- Filtres avancés dans le dashboard (par période, service, statut).
- Exports Excel/PDF des rapports (prévu en V3 dans le cahier des
  charges — ajouter `maatwebsite/excel` et `barryvdh/laravel-dompdf`
  le moment venu).
- Notifications de retard/absence.
- Multi-sites avancé / multi-entreprises.
- PWA installable, mode kiosque tablette.
- Gestion des pauses, heures supplémentaires, congés.

## 5. Structure livrée

```
app/
  Http/Controllers/          Auth, Dashboard, Employee, Department, Site, Attendance
  Http/Middleware/           EnsureUserIsAdmin.php
  Models/                    User, Employee, Department, Site, AttendanceRecord, AuditLog
  Services/                  GeoService.php (calcul de distance GPS)
database/
  migrations/                6 migrations (rôle, services, sites, employés, pointages, audit)
  seeders/                   PointageDemoSeeder.php
resources/views/
  layouts/app.blade.php      Layout commun (Bootstrap 5 CDN)
  auth/login.blade.php
  dashboard.blade.php
  attendance/                scan.blade.php, history.blade.php, qrcode.blade.php
  employees/, sites/, departments/   CRUD avec modales Bootstrap
routes/web.php
```

## 6. Passkeys WebAuthn pour le pointage

Les employés (`employe`) doivent vérifier une passkey avant chaque pointage. Les rôles d’administration restent exemptés. L’application utilise le package serveur officiel Laravel `laravel/passkeys` et son client navigateur `@laravel/passkeys`.

### Installation

```bash
composer require laravel/passkeys
npm install @laravel/passkeys
php artisan migrate
npm run build
```

Le dépôt contient la migration publiée du package qui crée `passkeys`, ainsi que les migrations de codes d’activation et du secours appareil. Si vous intégrez ces modifications dans un autre projet, publiez avant la migration les ressources du package :

```bash
php artisan vendor:publish --tag=passkeys-migrations
php artisan vendor:publish --tag=passkeys-config
```

Ne publiez pas une seconde copie si une migration `create_passkeys_table` existe déjà. La configuration utilisée est [config/passkeys.php](config/passkeys.php).

### Configuration WebAuthn et HTTPS

- En production, configurez `APP_URL` avec l’origine HTTPS canonique, par exemple `https://pointage.exemple.fr`, puis exécutez `php artisan config:cache`. Le `relying_party_id` est dérivé de l’hôte `APP_URL` et `allowed_origins` de cette origine exacte.
- En développement, utilisez `http://localhost` (éventuellement avec un port). Les navigateurs autorisent WebAuthn sur `localhost`; l’application refuse les cérémonies WebAuthn sur un autre hôte non HTTPS.
- Gardez `APP_KEY` stable. Pour une clé dédiée, définissez une valeur secrète stable dans `PASSKEYS_USER_HANDLE_SECRET` avant les premières inscriptions et conservez-la lors des déploiements.
- Le package et l’application demandent `userVerification: required`; l’inscription demande aussi un authentificateur de plateforme. Le navigateur peut valider cette vérification avec l’empreinte, Face ID ou le code de déverrouillage du téléphone. WebAuthn ne permet pas au serveur d’imposer ni de distinguer le type biométrique précis utilisé.

### Parcours d’activation

1. Dans **Employés**, l’administrateur sélectionne l’action d’empreinte pour réinitialiser les anciennes passkeys et générer un code à usage unique valable 24 heures.
2. L’administrateur remet le code à l’employé par un canal sûr. Le code n’est affiché qu’une fois et n’est stocké en base que sous forme hachée.
3. L’employé se connecte, ouvre **Activer mon empreinte**, saisit le code et enregistre une passkey depuis son téléphone.
4. En cas de perte ou de changement de téléphone, l’administrateur répète cette opération. Les passkeys antérieures sont révoquées.

Le bouton **Secours par appareil** est désactivé par défaut. Lorsqu’un administrateur l’active pour un employé sans authentificateur compatible, le pointage utilise la liaison d’appareil existante à la place de la passkey.

La page de pointage demande automatiquement la localisation à son ouverture. Si l’autorisation est refusée ou si le GPS est indisponible, l’employé doit réactiver la localisation dans les réglages du navigateur et du téléphone, puis choisir **Réessayer la localisation**.
