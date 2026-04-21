# 📋 PLAN D'ARCHITECTURE — Bloc 3 : Application RH Wacdo (Symfony 7)

> **Auteur du plan :** Agent Architecte BMAD  
> **Projet :** WCDO — Bloc 3 (Sujet 1 : Framework Back)  
> **Développeur :** Hugo  
> **Statut :** Plan validé — prêt pour implémentation  
> **Date :** Juin 2025

---

## Table des matières

1. [Résumé du projet](#1-résumé-du-projet)
2. [Stack technique détaillée](#2-stack-technique-détaillée)
3. [Modèle de données (MCD)](#3-modèle-de-données-mcd)
4. [Structure du projet Symfony](#4-structure-du-projet-symfony)
5. [Fonctionnalités détaillées — Routes et vues](#5-fonctionnalités-détaillées--routes-et-vues)
6. [Sécurité](#6-sécurité)
7. [Infrastructure Docker](#7-infrastructure-docker)
8. [Données de démo (Fixtures)](#8-données-de-démo-fixtures)
9. [Pipeline de mise en place](#9-pipeline-de-mise-en-place)
10. [Points d'examen — Ce que le jury va demander](#10-points-dexamen--ce-que-le-jury-va-demander)

---

## 1. Résumé du projet

### Ce qu'on construit

Une **application web de gestion des Ressources Humaines** pour la chaîne de restaurants Wacdo. Cette application permet à des administrateurs autorisés de :

- **Gérer les restaurants** : créer, modifier, consulter la liste des collaborateurs en poste
- **Gérer les collaborateurs** : créer, modifier, consulter leurs affectations (actuelles et historiques)
- **Gérer les fonctions** (postes) : créer, modifier les intitulés de postes existants (équipier, manager, etc.)
- **Gérer les affectations** : affecter un collaborateur à un restaurant avec un poste donné, avec dates de début et de fin
- **Rechercher les affectations** : filtrer par poste, dates, ville

L'application est un **back-office pur** — il n'y a pas de partie publique. Seuls les collaborateurs ayant le flag `administrateur = true` et un mot de passe peuvent se connecter.

### Pourquoi ce projet

Le Bloc 3 de l'examen RNCP Niveau 5 exige la maîtrise d'un **framework back** pour démontrer la capacité à :
- Installer et configurer un framework et ses dépendances
- Utiliser un ORM pour la gestion des données
- Implémenter un système d'authentification via le framework
- Structurer un projet selon les conventions du framework
- Argumenter les choix techniques devant un jury

### Lien avec les Blocs 1 et 2

| Bloc | Contenu | Localisation |
|------|---------|-------------|
| **Bloc 1** (Front) | Interface borne de commande McDonald's — HTML/CSS/JS natif | `Front/` |
| **Bloc 2** (Back) | API REST PHP natif — MVC + Repository + Service, PDO, sessions manuelles | `Backend/` |
| **Bloc 3** (Framework) | **Application RH séparée** — Symfony 7, Doctrine, Twig, Security Component | `Bloc3/symfony/` |

> ⚠️ **Le Bloc 3 est une application 100% indépendante.** Elle ne partage aucun code avec les Blocs 1 et 2. Elle utilise le même container MariaDB Docker mais une base de données distincte (`wcdo_rh` au lieu de `wcdo`). Elle montre au jury qu'Hugo sait faire **la même chose** (CRUD + auth + BDD) mais avec un framework — et peut comparer les deux approches.

---

## 2. Stack technique détaillée

### Symfony 7.4 — Framework PHP full-stack

| Aspect | Détail |
|--------|--------|
| **Ce que c'est** | Framework PHP MVC complet — le plus utilisé en France. Fournit le routing, les controllers, l'injection de dépendances, les formulaires, la sécurité, la console CLI |
| **Pourquoi on l'utilise** | Imposé par le sujet (« framework back »). Hugo connaît déjà PHP 8.2 OOP/MVC depuis le Bloc 2 — Symfony est la suite logique |
| **Ce que ça remplace (Bloc 2)** | Le `Router.php` custom → remplacé par le composant Routing Symfony. Les controllers custom → remplacés par les AbstractController. Le `public/index.php` custom → remplacé par le kernel Symfony |
| **Version** | 7.4.* (LTS-compatible, PHP 8.2+) |

### Doctrine ORM 3 — Mapping objet-relationnel

| Aspect | Détail |
|--------|--------|
| **Ce que c'est** | ORM (Object-Relational Mapper) — mappe les classes PHP sur les tables SQL. Gère les entités, les relations, les migrations, les requêtes en DQL ou QueryBuilder |
| **Pourquoi on l'utilise** | Imposé par le sujet (« gestion des entités avec un ORM »). Élimine le SQL manuel, gère les migrations de schéma automatiquement |
| **Ce que ça remplace (Bloc 2)** | Les `Repository` avec requêtes PDO manuelles (`$stmt->prepare(...)`) → remplacés par `EntityManager`, `find()`, `findBy()`, `QueryBuilder`. Les `Entities` PHP simples → remplacées par des entités annotées avec attributs PHP 8 (`#[ORM\Entity]`, `#[ORM\Column]`). Le fichier `init.sql` de création de tables → remplacé par les migrations Doctrine (`make:migration`) |

### Twig 3 — Moteur de templates

| Aspect | Détail |
|--------|--------|
| **Ce que c'est** | Moteur de templates natif de Symfony. Syntaxe `{{ variable }}`, `{% block %}`, héritage de templates, filtres, includes |
| **Pourquoi on l'utilise** | Imposé par le sujet (« moteur de template »). Choisi plutôt que EasyAdmin car plus pédagogique — on code les vues soi-même, ce qui permet au jury de vérifier la maîtrise |
| **Ce que ça remplace (Bloc 2)** | Le Bloc 2 est une API pure (JSON) — pas de vues côté serveur. Dans le Bloc 3, Twig génère les pages HTML directement côté serveur (SSR). Plus besoin de fetch JS → les données sont injectées dans les templates |

### Symfony Security — Authentification et autorisation

| Aspect | Détail |
|--------|--------|
| **Ce que c'est** | Composant intégré gérant : firewall, authentification (login form), hachage de mots de passe, rôles et voters, protection CSRF |
| **Pourquoi on l'utilise** | Imposé par le sujet (« authentification et autorisation des utilisateurs »). Solution standard et sécurisée |
| **Ce que ça remplace (Bloc 2)** | Les sessions PHP manuelles (`$_SESSION`). La vérification `password_verify()` manuelle dans `Admin::verifierMotDePasse()`. La protection CSRF faite à la main. Le middleware d'auth custom |

### Symfony Forms — Formulaires

| Aspect | Détail |
|--------|--------|
| **Ce que c'est** | Composant qui crée, valide et traite les formulaires HTML. Les `FormType` définissent les champs, les contraintes de validation, et le rendu |
| **Pourquoi on l'utilise** | Validation automatique côté serveur, protection CSRF intégrée, binding direct vers les entités Doctrine |
| **Ce que ça remplace (Bloc 2)** | Les `$_POST` lus manuellement dans les controllers. La validation manuelle des champs. Le HTML des formulaires écrit à la main |

### MariaDB 10.11 — Base de données

| Aspect | Détail |
|--------|--------|
| **Ce que c'est** | SGBD relationnel MySQL-compatible, déjà utilisé dans les Blocs 1 et 2 |
| **Pourquoi on l'utilise** | Déjà en place dans le container Docker `wcdo-db`. On crée simplement une nouvelle base `wcdo_rh` dans le même serveur |
| **Ce que ça remplace (Bloc 2)** | Rien — c'est la même technologie, même container. Mais l'accès se fait via Doctrine au lieu de PDO |

### Bootstrap 5 — Framework CSS (CDN)

| Aspect | Détail |
|--------|--------|
| **Ce que c'est** | Framework CSS responsive. Grilles, composants (tables, cards, navbars, modals, badges), utilitaires |
| **Pourquoi on l'utilise** | Rendu propre et rapide pour un back-office admin. Pas besoin de perdre du temps sur le CSS custom — le focus est sur le PHP/Symfony |
| **Ce que ça remplace (Bloc 2)** | Le CSS custom du Bloc 1 (Front). Ici on utilise les classes Bootstrap directement dans les templates Twig |

### PHPUnit — Tests

| Aspect | Détail |
|--------|--------|
| **Ce que c'est** | Framework de tests unitaires et fonctionnels PHP. Intégré via `symfony/test-pack` |
| **Pourquoi on l'utilise** | Imposé par le sujet (« tests d'interface utilisateur, tests fonctionnels, tests de sécurité ») |
| **Ce que ça remplace (Bloc 2)** | PHPUnit était déjà utilisé dans le Bloc 2 (`Backend/phpunit.xml`). Dans Symfony, on utilise en plus le `WebTestCase` pour tester les routes et le `KernelTestCase` pour les services |

---

## 3. Modèle de données (MCD)

### 3.1 Diagramme conceptuel

```
┌──────────────────┐          ┌──────────────────┐
│   Collaborateur   │          │    Restaurant     │
│──────────────────│          │──────────────────│
│ id (PK)          │          │ id (PK)          │
│ nom              │          │ nom              │
│ prenom           │          │ adresse          │
│ email (unique)   │          │ code_postal      │
│ date_embauche    │          │ ville            │
│ administrateur   │          └────────┬─────────┘
│ mot_de_passe     │                   │
│ roles (JSON)     │                   │ 1
└────────┬─────────┘                   │
         │                             │
         │ 1                           │
         │          ┌──────────────────┤
         │          │                  │
         ▼          ▼                  │
    ┌────────────────────┐             │
    │    Affectation      │             │
    │────────────────────│             │
    │ id (PK)            │             │
    │ date_debut         │             │
    │ date_fin (nullable)│             │
    │ collaborateur (FK) │─────────────┘
    │ restaurant (FK)    │
    │ fonction (FK)      │──────────┐
    └────────────────────┘          │
                                    │
                           1        │
                    ┌───────────────┘
                    ▼
           ┌──────────────────┐
           │    Fonction       │
           │──────────────────│
           │ id (PK)          │
           │ intitule (unique)│
           └──────────────────┘
```

### 3.2 Entités détaillées

#### Entité `Collaborateur` — implémente `UserInterface` et `PasswordAuthenticatedUserInterface`

| Champ | Type PHP | Type SQL | Contraintes | Notes |
|-------|----------|----------|-------------|-------|
| `id` | `int` | `INT AUTO_INCREMENT` | PK | Généré par Doctrine |
| `nom` | `string` | `VARCHAR(100)` | NOT NULL, Assert\NotBlank, Assert\Length(max:100) | |
| `prenom` | `string` | `VARCHAR(100)` | NOT NULL, Assert\NotBlank, Assert\Length(max:100) | |
| `email` | `string` | `VARCHAR(180)` | NOT NULL, UNIQUE, Assert\NotBlank, Assert\Email | Sert d'identifiant de connexion (`getUserIdentifier()`) |
| `dateEmbauche` | `\DateTimeInterface` | `DATE` | NOT NULL, Assert\NotNull | Date de première embauche chez Wacdo |
| `administrateur` | `bool` | `TINYINT(1)` | NOT NULL, default `false` | Si `true` → peut se connecter à l'app |
| `motDePasse` | `?string` | `VARCHAR(255) NULL` | Nullable | Hash bcrypt. NULL si `administrateur = false` |
| `roles` | `array` | `JSON` | NOT NULL, default `["ROLE_USER"]` | Requis par Symfony Security. Si admin → `["ROLE_ADMIN"]` |
| `affectations` | `Collection<Affectation>` | — | OneToMany(mappedBy="collaborateur") | Relation inverse |

**Implémentation de `UserInterface` :**
- `getUserIdentifier()` → retourne `$this->email`
- `getRoles()` → retourne `$this->roles` (avec toujours `ROLE_USER` minimum)
- `getPassword()` → retourne `$this->motDePasse`
- `eraseCredentials()` → vide (pas de données sensibles en mémoire)

#### Entité `Restaurant`

| Champ | Type PHP | Type SQL | Contraintes | Notes |
|-------|----------|----------|-------------|-------|
| `id` | `int` | `INT AUTO_INCREMENT` | PK | |
| `nom` | `string` | `VARCHAR(150)` | NOT NULL, Assert\NotBlank, Assert\Length(max:150) | |
| `adresse` | `string` | `VARCHAR(255)` | NOT NULL, Assert\NotBlank | |
| `codePostal` | `string` | `VARCHAR(10)` | NOT NULL, Assert\NotBlank, Assert\Regex(`/^\d{5}$/`) | Code postal français (5 chiffres) |
| `ville` | `string` | `VARCHAR(100)` | NOT NULL, Assert\NotBlank, Assert\Length(max:100) | |
| `affectations` | `Collection<Affectation>` | — | OneToMany(mappedBy="restaurant") | Relation inverse |

#### Entité `Fonction`

| Champ | Type PHP | Type SQL | Contraintes | Notes |
|-------|----------|----------|-------------|-------|
| `id` | `int` | `INT AUTO_INCREMENT` | PK | |
| `intitule` | `string` | `VARCHAR(100)` | NOT NULL, UNIQUE, Assert\NotBlank, Assert\Length(max:100) | Ex : « Équipier polyvalent », « Manager » |
| `affectations` | `Collection<Affectation>` | — | OneToMany(mappedBy="fonction") | Relation inverse |

#### Entité `Affectation`

| Champ | Type PHP | Type SQL | Contraintes | Notes |
|-------|----------|----------|-------------|-------|
| `id` | `int` | `INT AUTO_INCREMENT` | PK | |
| `dateDebut` | `\DateTimeInterface` | `DATE` | NOT NULL, Assert\NotNull | Date de début de l'affectation |
| `dateFin` | `?\DateTimeInterface` | `DATE NULL` | Nullable | `NULL` = affectation en cours (active). Renseigné = affectation terminée |
| `collaborateur` | `Collaborateur` | `INT FK` | NOT NULL, ManyToOne(inversedBy="affectations") | Référence vers le collaborateur |
| `restaurant` | `Restaurant` | `INT FK` | NOT NULL, ManyToOne(inversedBy="affectations") | Référence vers le restaurant |
| `fonction` | `Fonction` | `INT FK` | NOT NULL, ManyToOne(inversedBy="affectations") | Référence vers la fonction/poste |

### 3.3 Cardinalités et relations

```
Collaborateur  ──(1)────(N)──  Affectation
Restaurant     ──(1)────(N)──  Affectation
Fonction       ──(1)────(N)──  Affectation
```

- **1 Collaborateur → N Affectations** : un collaborateur peut avoir été affecté à plusieurs postes/restaurants au fil du temps (historique)
- **1 Restaurant → N Affectations** : un restaurant a de nombreux collaborateurs affectés (actuels et passés)
- **1 Fonction → N Affectations** : un même poste (ex : « équipier ») peut être occupé par plusieurs personnes dans plusieurs restaurants
- **Affectation active** : `dateFin IS NULL` — le collaborateur est actuellement en poste
- **Affectation terminée** : `dateFin IS NOT NULL` — fait partie de l'historique

### 3.4 Tables SQL générées par Doctrine

Les noms de tables suivront la convention Doctrine (snake_case par défaut) :

| Entité | Table SQL | Index |
|--------|-----------|-------|
| `Collaborateur` | `collaborateur` | UNIQUE sur `email` |
| `Restaurant` | `restaurant` | — |
| `Fonction` | `fonction` | UNIQUE sur `intitule` |
| `Affectation` | `affectation` | FK sur `collaborateur_id`, `restaurant_id`, `fonction_id`. INDEX sur `date_fin` (pour filtrer les actives) |

---

## 4. Structure du projet Symfony

### 4.1 Arborescence complète

```
Bloc3/symfony/
├── .env                            # Variables d'env par défaut (APP_ENV, DATABASE_URL)
├── .env.dev                        # Overrides dev (APP_SECRET)
├── .env.local                      # ⚠️ Gitignored — DATABASE_URL réelle locale
├── .gitignore
├── composer.json                   # Dépendances Symfony + Doctrine + Security + Twig + Form
├── composer.lock
├── symfony.lock
│
├── bin/
│   └── console                     # CLI Symfony (make:entity, make:migration, etc.)
│
├── config/
│   ├── bundles.php                 # Bundles activés (Doctrine, Twig, Security, Form, etc.)
│   ├── services.yaml               # Injection de dépendances (autowiring)
│   ├── routes.yaml                 # Import des routes depuis les controllers (annotations/attributes)
│   ├── preload.php
│   │
│   └── packages/
│       ├── doctrine.yaml           # Config Doctrine (driver, server_version, mapping)
│       ├── doctrine_migrations.yaml # Config migrations
│       ├── framework.yaml          # Config framework (secret, session, csrf)
│       ├── security.yaml           # ⭐ Firewall, provider, hasher, access_control
│       ├── twig.yaml               # Config Twig (default_path, form_themes)
│       ├── validator.yaml          # Config validation
│       └── test/                   # Config spécifique aux tests
│           └── framework.yaml
│
├── migrations/
│   └── VersionXXXXXXXXXXXXXX.php  # Migrations générées par Doctrine
│
├── public/
│   └── index.php                   # Point d'entrée unique (front controller Symfony)
│
├── src/
│   ├── Kernel.php                  # Kernel Symfony (déjà généré)
│   │
│   ├── Entity/                     # ── ENTITÉS DOCTRINE ──
│   │   ├── Collaborateur.php       # UserInterface + PasswordAuthenticatedUserInterface
│   │   ├── Restaurant.php
│   │   ├── Fonction.php
│   │   └── Affectation.php
│   │
│   ├── Repository/                 # ── REPOSITORIES DOCTRINE ──
│   │   ├── CollaborateurRepository.php  # Méthodes custom : findNonAffectes(), findByFilters()
│   │   ├── RestaurantRepository.php     # Méthodes custom : findByFilters()
│   │   ├── FonctionRepository.php       # Standard (pas de méthodes custom nécessaires)
│   │   └── AffectationRepository.php    # Méthodes custom : findByFilters(), findActivesByRestaurant(), findActivesByCollaborateur()
│   │
│   ├── Controller/                 # ── CONTROLLERS ──
│   │   ├── SecurityController.php       # Login / Logout
│   │   ├── DashboardController.php      # Page d'accueil (redirect vers restaurants ou dashboard)
│   │   ├── RestaurantController.php     # CRUD Restaurant + affichage collabs en poste + affecter
│   │   ├── CollaborateurController.php  # CRUD Collaborateur + affectations + non-affectés
│   │   ├── FonctionController.php       # CRUD Fonction (list, create, edit)
│   │   └── AffectationController.php    # Liste + filtres des affectations
│   │
│   ├── Form/                       # ── FORM TYPES ──
│   │   ├── LoginType.php                # Email + mot de passe (ou géré via security form_login)
│   │   ├── RestaurantType.php           # Champs : nom, adresse, codePostal, ville
│   │   ├── RestaurantFilterType.php     # Filtres : nom, codePostal, ville (tous optionnels)
│   │   ├── CollaborateurType.php        # Champs : nom, prenom, email, dateEmbauche, administrateur, motDePasse
│   │   ├── CollaborateurFilterType.php  # Filtres : nom, prenom, email (tous optionnels)
│   │   ├── FonctionType.php             # Champ : intitule
│   │   ├── AffectationType.php          # Champs : collaborateur (EntityType), restaurant (EntityType), fonction (EntityType), dateDebut, dateFin
│   │   └── AffectationFilterType.php    # Filtres : fonction, dateDebut, dateFin, ville (tous optionnels)
│   │
│   └── DataFixtures/               # ── FIXTURES ──
│       └── AppFixtures.php              # Données de démo (admin, collabs, restaurants, fonctions, affectations)
│
├── templates/                      # ── TEMPLATES TWIG ──
│   ├── base.html.twig              # Layout principal : navbar + Bootstrap CDN + block body + flash messages
│   │
│   ├── security/
│   │   └── login.html.twig         # Page de connexion (formulaire email + mot de passe)
│   │
│   ├── dashboard/
│   │   └── index.html.twig         # Page d'accueil après login (liens vers les 4 sections)
│   │
│   ├── restaurant/
│   │   ├── index.html.twig         # Liste des restaurants + formulaire de filtres + bouton créer
│   │   ├── show.html.twig          # Détail restaurant + liste collabs en poste actuel (filtrable)
│   │   ├── new.html.twig           # Formulaire création restaurant
│   │   └── edit.html.twig          # Formulaire modification + historique affectations + bouton affecter
│   │
│   ├── collaborateur/
│   │   ├── index.html.twig         # Liste des collaborateurs + filtres + boutons créer / non-affectés
│   │   ├── show.html.twig          # Détail collaborateur + affectations en cours + historique (filtrable)
│   │   ├── new.html.twig           # Formulaire création collaborateur
│   │   └── edit.html.twig          # Formulaire modification + affecter à un nouveau poste
│   │
│   ├── fonction/
│   │   ├── index.html.twig         # Liste des fonctions + bouton créer + édition inline ou lien edit
│   │   ├── new.html.twig           # Formulaire création fonction
│   │   └── edit.html.twig          # Formulaire modification intitulé
│   │
│   ├── affectation/
│   │   ├── index.html.twig         # Liste des affectations + formulaire de filtres
│   │   ├── new.html.twig           # Formulaire création affectation (standalone)
│   │   └── edit.html.twig          # Formulaire modification affectation en cours
│   │
│   └── _partials/                  # Fragments réutilisables
│       ├── _navbar.html.twig       # Barre de navigation (Restaurants, Collaborateurs, Fonctions, Affectations, Déconnexion)
│       ├── _flash_messages.html.twig # Messages flash (succès, erreur)
│       └── _pagination.html.twig   # Pagination (si nécessaire)
│
├── tests/                          # ── TESTS ──
│   ├── Entity/
│   │   └── CollaborateurTest.php   # Tests unitaires UserInterface, rôles
│   ├── Controller/
│   │   ├── SecurityControllerTest.php
│   │   ├── RestaurantControllerTest.php
│   │   ├── CollaborateurControllerTest.php
│   │   ├── FonctionControllerTest.php
│   │   └── AffectationControllerTest.php
│   └── Repository/
│       └── AffectationRepositoryTest.php  # Test des méthodes custom (filtres, actives)
│
└── var/                            # Cache et logs (gitignored)
    ├── cache/
    └── log/
```

### 4.2 Dépendances Composer à installer

Les dépendances actuelles du `composer.json` sont le squelette Symfony de base. Il faut ajouter :

**Dépendances de production (`require`) :**
```
symfony/orm-pack            → Doctrine ORM + DBAL + Migrations (1 seul require)
symfony/twig-pack           → Twig + intégration Symfony
symfony/security-bundle     → Composant Security (firewall, auth, hasher)
symfony/form                → Composant Form (FormType, validation)
symfony/validator            → Composant Validator (Assert\NotBlank, Assert\Email, etc.)
symfony/asset               → Helper asset() pour les fichiers statiques dans Twig
symfony/twig-bridge         → Form themes Bootstrap 5 pour Twig
```

**Dépendances de développement (`require-dev`) :**
```
symfony/maker-bundle        → Générateur CLI (make:entity, make:controller, make:form, etc.)
doctrine/doctrine-fixtures-bundle  → Chargement de données de démo
symfony/test-pack           → PHPUnit + WebTestCase + KernelTestCase
symfony/profiler-pack       → Web Debug Toolbar + Profiler (pour le dev)
symfony/debug-bundle        → Meilleur affichage des erreurs en dev
```

### 4.3 Méthodes custom des Repositories

#### `CollaborateurRepository`

```
findByFilters(?string $nom, ?string $prenom, ?string $email): array
    → QueryBuilder avec LIKE conditionnel sur chaque champ non-null

findNonAffectes(): array
    → LEFT JOIN affectation WHERE dateFin IS NOT NULL OR affectation IS NULL
    → Retourne les collaborateurs qui n'ont AUCUNE affectation active

findOneByEmail(string $email): ?Collaborateur
    → Pour le UserProvider de Symfony Security
```

#### `RestaurantRepository`

```
findByFilters(?string $nom, ?string $codePostal, ?string $ville): array
    → QueryBuilder avec LIKE conditionnel sur chaque champ non-null
```

#### `AffectationRepository`

```
findByFilters(?Fonction $fonction, ?\DateTimeInterface $dateDebut, ?\DateTimeInterface $dateFin, ?string $ville): array
    → QueryBuilder avec JOIN restaurant + conditions conditionnelles

findActivesByRestaurant(Restaurant $restaurant, ?Fonction $fonction, ?string $nom, ?\DateTimeInterface $dateDebut): array
    → WHERE restaurant = :r AND dateFin IS NULL + filtres optionnels
    → Pour la page détail restaurant (collabs en poste actuel)

findAllByRestaurant(Restaurant $restaurant, ?Fonction $fonction, ?string $nom, ?\DateTimeInterface $dateDebut): array
    → WHERE restaurant = :r + filtres optionnels (toutes affectations, y compris terminées)
    → Pour la page modifier restaurant (historique complet)

findActivesByCollaborateur(Collaborateur $collaborateur): array
    → WHERE collaborateur = :c AND dateFin IS NULL
    → Affectations en cours du collaborateur

findAllByCollaborateur(Collaborateur $collaborateur, ?Fonction $fonction, ?\DateTimeInterface $dateDebut): array
    → WHERE collaborateur = :c + filtres optionnels
    → Pour la page détail collaborateur (historique complet)
```

#### `FonctionRepository`

```
→ Pas de méthodes custom nécessaires. Les méthodes standard (findAll, find, findOneBy) suffisent.
```

---

## 5. Fonctionnalités détaillées — Routes et vues

### 5.0 Sécurité (Login / Logout)

| Route | URL | Méthode | Controller::method | Description |
|-------|-----|---------|-------------------|-------------|
| `app_login` | `/login` | GET, POST | `SecurityController::login` | Formulaire de connexion |
| `app_logout` | `/logout` | GET | `SecurityController::logout` | Déconnexion (intercepté par le firewall) |

**Template `security/login.html.twig` :**
- Formulaire : email + mot de passe + bouton « Se connecter »
- Affiche les erreurs d'authentification (`last_error`)
- Pré-remplit le champ email (`last_username`)
- Protection CSRF intégrée

### 5.1 Dashboard (Page d'accueil)

| Route | URL | Méthode | Controller::method | Description |
|-------|-----|---------|-------------------|-------------|
| `app_dashboard` | `/` | GET | `DashboardController::index` | Page d'accueil après login |

**Template `dashboard/index.html.twig` :**
- Message de bienvenue avec le nom de l'admin connecté
- 4 cartes/liens vers les 4 sections : Restaurants, Collaborateurs, Fonctions, Affectations
- Optionnel : quelques statistiques (nombre de restaurants, nombre de collaborateurs, nombre d'affectations actives)

---

### 5.2 Gestion des Restaurants

| Route | URL | Méthode | Controller::method | Description |
|-------|-----|---------|-------------------|-------------|
| `app_restaurant_index` | `/restaurant` | GET | `RestaurantController::index` | Liste + filtres |
| `app_restaurant_new` | `/restaurant/new` | GET, POST | `RestaurantController::new` | Créer un restaurant |
| `app_restaurant_show` | `/restaurant/{id}` | GET | `RestaurantController::show` | Détail + collabs en poste actuel |
| `app_restaurant_edit` | `/restaurant/{id}/edit` | GET, POST | `RestaurantController::edit` | Modifier + historique + affecter |
| `app_restaurant_affecter` | `/restaurant/{id}/affecter` | GET, POST | `RestaurantController::affecter` | Affecter un nouveau collaborateur |

#### Vue `restaurant/index.html.twig` — Liste des restaurants
- **Formulaire de filtres** (RestaurantFilterType) : champs nom, code postal, ville — tous optionnels, soumis en GET
- **Tableau** des restaurants avec colonnes : Nom, Adresse, Code Postal, Ville, Actions (Voir)
- **Bouton « Créer un restaurant »** en haut de page
- Les lignes du tableau sont cliquables → redirigent vers `app_restaurant_show`

#### Vue `restaurant/show.html.twig` — Détail du restaurant
- **Fiche du restaurant** : nom, adresse, code postal, ville
- **Bouton « Modifier »** → redirige vers `app_restaurant_edit`
- **Tableau « Collaborateurs en poste actuellement »** :
  - Colonnes : Nom, Prénom, Fonction, Date début
  - **Filtrable** par : poste (select Fonction), nom (texte), date début (date)
  - Source : `AffectationRepository::findActivesByRestaurant()` (WHERE `dateFin IS NULL`)
  - Les noms sont cliquables → redirigent vers `app_collaborateur_show`

#### Vue `restaurant/new.html.twig` — Créer un restaurant
- **Formulaire** (RestaurantType) : nom, adresse, code postal, ville
- Boutons : Enregistrer / Annuler
- Après succès → flash message + redirect vers `app_restaurant_show`

#### Vue `restaurant/edit.html.twig` — Modifier un restaurant
- **Formulaire de modification** (RestaurantType) pré-rempli avec les données actuelles
- **Tableau « Historique des affectations »** (toutes, y compris terminées) :
  - Colonnes : Collaborateur (nom prénom), Fonction, Date début, Date fin, Statut (actif/terminé)
  - **Filtrable** par : fonction, nom, date début
  - Source : `AffectationRepository::findAllByRestaurant()`
- **Bouton « Affecter un nouveau collaborateur »** → redirige vers `app_restaurant_affecter`

#### Vue implicite via `restaurant/edit.html.twig` ou route dédiée — Affecter
- **Formulaire d'affectation** (AffectationType partiel) :
  - Collaborateur (EntityType select/autocomplete)
  - Fonction (EntityType select)
  - Date début (DateType)
  - Le restaurant est pré-rempli (caché)
- Après succès → flash message + redirect vers `app_restaurant_edit`

---

### 5.3 Gestion des Collaborateurs

| Route | URL | Méthode | Controller::method | Description |
|-------|-----|---------|-------------------|-------------|
| `app_collaborateur_index` | `/collaborateur` | GET | `CollaborateurController::index` | Liste + filtres |
| `app_collaborateur_non_affectes` | `/collaborateur/non-affectes` | GET | `CollaborateurController::nonAffectes` | Liste des non-affectés |
| `app_collaborateur_new` | `/collaborateur/new` | GET, POST | `CollaborateurController::new` | Créer un collaborateur |
| `app_collaborateur_show` | `/collaborateur/{id}` | GET | `CollaborateurController::show` | Détail + affectations |
| `app_collaborateur_edit` | `/collaborateur/{id}/edit` | GET, POST | `CollaborateurController::edit` | Modifier + affecter |
| `app_collaborateur_affecter` | `/collaborateur/{id}/affecter` | GET, POST | `CollaborateurController::affecter` | Affecter à un nouveau poste |
| `app_affectation_edit` | `/affectation/{id}/edit` | GET, POST | `AffectationController::edit` | Modifier une affectation en cours |

#### Vue `collaborateur/index.html.twig` — Liste des collaborateurs
- **Formulaire de filtres** (CollaborateurFilterType) : champs nom, prénom, email — tous optionnels, soumis en GET
- **Tableau** des collaborateurs avec colonnes : Nom, Prénom, Email, Date embauche, Admin (oui/non), Actions (Voir)
- **Bouton « Créer un collaborateur »** en haut de page
- **Bouton « Voir les non-affectés »** → redirige vers `app_collaborateur_non_affectes`
- Les lignes sont cliquables → `app_collaborateur_show`

#### Vue réutilisant `collaborateur/index.html.twig` — Non-affectés
- Même template que `index`, mais avec `collaborateurs` = résultat de `CollaborateurRepository::findNonAffectes()`
- Un indicateur visuel (titre, badge) précise qu'on est en mode « non-affectés »

#### Vue `collaborateur/show.html.twig` — Détail du collaborateur
- **Fiche du collaborateur** : nom, prénom, email, date embauche, administrateur (badge oui/non)
- **Bouton « Modifier »** → `app_collaborateur_edit`
- **Section « Affectations en cours »** :
  - Tableau : Restaurant, Fonction, Date début, Actions (Modifier)
  - Source : `AffectationRepository::findActivesByCollaborateur()`
  - Les affectations en cours sont **modifiables** (lien vers `app_affectation_edit`)
- **Section « Historique des affectations »** :
  - Tableau : Restaurant, Fonction, Date début, Date fin
  - **Filtrable** par : poste (select Fonction), date début (date)
  - Source : `AffectationRepository::findAllByCollaborateur()`

#### Vue `collaborateur/new.html.twig` — Créer un collaborateur
- **Formulaire** (CollaborateurType) : nom, prénom, email, date embauche, administrateur (checkbox), mot de passe (si admin)
- Note : le champ mot de passe n'apparaît que si administrateur = true (logique JS simple ou rendu conditionnel)
- Après succès → flash message + redirect vers `app_collaborateur_show`

#### Vue `collaborateur/edit.html.twig` — Modifier un collaborateur
- **Formulaire de modification** (CollaborateurType) pré-rempli
- **Bouton « Affecter à un nouveau poste »** → `app_collaborateur_affecter`
- Affiche aussi les affectations en cours (modifiables) sous le formulaire

#### Affectation depuis le collaborateur
- **Formulaire** (AffectationType partiel) :
  - Restaurant (EntityType select)
  - Fonction (EntityType select)
  - Date début (DateType)
  - Le collaborateur est pré-rempli (caché)

---

### 5.4 Gestion des Fonctions

| Route | URL | Méthode | Controller::method | Description |
|-------|-----|---------|-------------------|-------------|
| `app_fonction_index` | `/fonction` | GET | `FonctionController::index` | Liste des fonctions |
| `app_fonction_new` | `/fonction/new` | GET, POST | `FonctionController::new` | Créer une fonction |
| `app_fonction_edit` | `/fonction/{id}/edit` | GET, POST | `FonctionController::edit` | Modifier l'intitulé |

#### Vue `fonction/index.html.twig` — Liste des fonctions
- **Tableau** simple avec colonnes : Intitulé, Actions (Modifier)
- **Bouton « Créer une fonction »** en haut de page
- Chaque fonction est **éditable** (lien vers `app_fonction_edit`)

#### Vue `fonction/new.html.twig` — Créer une fonction
- **Formulaire** (FonctionType) : champ intitulé
- Contrainte UNIQUE sur l'intitulé → message d'erreur si doublon

#### Vue `fonction/edit.html.twig` — Modifier une fonction
- **Formulaire** (FonctionType) pré-rempli avec l'intitulé actuel

---

### 5.5 Recherche des Affectations

| Route | URL | Méthode | Controller::method | Description |
|-------|-----|---------|-------------------|-------------|
| `app_affectation_index` | `/affectation` | GET | `AffectationController::index` | Liste + filtres |
| `app_affectation_new` | `/affectation/new` | GET, POST | `AffectationController::new` | Créer une affectation (standalone) |
| `app_affectation_edit` | `/affectation/{id}/edit` | GET, POST | `AffectationController::edit` | Modifier une affectation en cours |

#### Vue `affectation/index.html.twig` — Liste des affectations
- **Formulaire de filtres** (AffectationFilterType) : 
  - Poste (EntityType select → Fonction)
  - Date début (DateType)
  - Date fin (DateType)
  - Ville (texte)
- **Tableau** avec colonnes : Collaborateur (nom prénom), Restaurant, Ville, Fonction, Date début, Date fin, Statut (badge « Actif » vert / « Terminé » gris)
- Source : `AffectationRepository::findByFilters()`

#### Vue `affectation/edit.html.twig` — Modifier une affectation
- Permet de modifier la date de fin (pour clôturer une affectation)
- Permet de changer la fonction ou le restaurant si besoin
- **Accessible depuis** la page détail collaborateur (affectations en cours modifiables)

---

### 5.6 Récapitulatif des routes

| # | Nom de route | URL | Méthodes | Controller |
|---|-------------|-----|----------|------------|
| 1 | `app_login` | `/login` | GET, POST | SecurityController::login |
| 2 | `app_logout` | `/logout` | GET | SecurityController::logout |
| 3 | `app_dashboard` | `/` | GET | DashboardController::index |
| 4 | `app_restaurant_index` | `/restaurant` | GET | RestaurantController::index |
| 5 | `app_restaurant_new` | `/restaurant/new` | GET, POST | RestaurantController::new |
| 6 | `app_restaurant_show` | `/restaurant/{id}` | GET | RestaurantController::show |
| 7 | `app_restaurant_edit` | `/restaurant/{id}/edit` | GET, POST | RestaurantController::edit |
| 8 | `app_restaurant_affecter` | `/restaurant/{id}/affecter` | GET, POST | RestaurantController::affecter |
| 9 | `app_collaborateur_index` | `/collaborateur` | GET | CollaborateurController::index |
| 10 | `app_collaborateur_non_affectes` | `/collaborateur/non-affectes` | GET | CollaborateurController::nonAffectes |
| 11 | `app_collaborateur_new` | `/collaborateur/new` | GET, POST | CollaborateurController::new |
| 12 | `app_collaborateur_show` | `/collaborateur/{id}` | GET | CollaborateurController::show |
| 13 | `app_collaborateur_edit` | `/collaborateur/{id}/edit` | GET, POST | CollaborateurController::edit |
| 14 | `app_collaborateur_affecter` | `/collaborateur/{id}/affecter` | GET, POST | CollaborateurController::affecter |
| 15 | `app_fonction_index` | `/fonction` | GET | FonctionController::index |
| 16 | `app_fonction_new` | `/fonction/new` | GET, POST | FonctionController::new |
| 17 | `app_fonction_edit` | `/fonction/{id}/edit` | GET, POST | FonctionController::edit |
| 18 | `app_affectation_index` | `/affectation` | GET | AffectationController::index |
| 19 | `app_affectation_new` | `/affectation/new` | GET, POST | AffectationController::new |
| 20 | `app_affectation_edit` | `/affectation/{id}/edit` | GET, POST | AffectationController::edit |

**Total : 20 routes**

---

## 6. Sécurité

### 6.1 Architecture d'authentification

```
Navigateur
    │
    ▼
[GET /login]  ← SecurityController::login
    │                affiche login.html.twig
    ▼
[POST /login] ← Intercepté par le firewall Symfony (form_login)
    │
    ├── UserProvider charge le Collaborateur par email
    │   (via CollaborateurRepository implémentant UserProviderInterface 
    │    OU via entity provider configuré dans security.yaml)
    │
    ├── PasswordHasher vérifie le mot de passe (bcrypt)
    │
    ├── Vérifie que le Collaborateur a le rôle ROLE_ADMIN
    │
    ├── ✅ Succès → Crée un token de session → Redirect vers /
    │
    └── ❌ Échec → Redirect vers /login avec message d'erreur
```

### 6.2 Configuration `security.yaml`

```yaml
security:
    # Algorithme de hachage des mots de passe
    password_hashers:
        App\Entity\Collaborateur:
            algorithm: bcrypt
            cost: 13           # Facteur de coût bcrypt (défaut Symfony)

    # Fournisseur d'utilisateurs
    providers:
        app_user_provider:
            entity:
                class: App\Entity\Collaborateur
                property: email    # Champ utilisé comme identifiant de connexion

    # Firewall
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt))/
            security: false        # Pas de sécurité sur le profiler

        main:
            lazy: true
            provider: app_user_provider

            # Formulaire de login
            form_login:
                login_path: app_login
                check_path: app_login
                default_target_path: app_dashboard
                enable_csrf: true

            # Logout
            logout:
                path: app_logout
                target: app_login

            # Remember me (optionnel)
            # remember_me:
            #     secret: '%kernel.secret%'

    # Contrôle d'accès
    access_control:
        - { path: ^/login$, roles: PUBLIC_ACCESS }     # Login accessible sans auth
        - { path: ^/, roles: ROLE_ADMIN }               # Tout le reste = admin requis
```

### 6.3 Logique d'autorisation

- **Seuls les `Collaborateur` avec `administrateur = true` peuvent se connecter.**
- Dans le setter `setAdministrateur(bool)` de l'entité :
  - Si `true` → `$this->roles = ['ROLE_ADMIN']`
  - Si `false` → `$this->roles = ['ROLE_USER']`
- Le `access_control` bloque tout accès non-ROLE_ADMIN après `/login`
- Les collaborateurs non-admin existent dans la BDD (ils sont gérés) mais ne peuvent pas se connecter

### 6.4 Hachage des mots de passe

- **Algorithme : bcrypt** (standard Symfony, recommandé)
- Le hachage se fait :
  - Dans les **Fixtures** : via `UserPasswordHasherInterface::hashPassword()`
  - Dans le **CollaborateurController::new** et **edit** : via le même service
- **Jamais de mot de passe en clair** dans la BDD
- Comparaison avec Bloc 2 : dans le Bloc 2, on utilisait `password_hash()` et `password_verify()` manuellement

### 6.5 Protection CSRF

- **Activée par défaut** sur tous les formulaires Symfony (token `_token` dans les FormType)
- **Activée sur le formulaire de login** (`enable_csrf: true` dans le firewall)
- Comparaison avec Bloc 2 : dans le Bloc 2, le CSRF devait être géré manuellement

### 6.6 Validation des données

- **Côté entité** : contraintes Assert (NotBlank, Email, Length, Regex pour le code postal, etc.)
- **Côté formulaire** : validation automatique au `handleRequest()` — le formulaire n'est `isValid()` que si toutes les contraintes passent
- **Côté Twig** : les erreurs de validation s'affichent automatiquement sous chaque champ grâce au form theme Bootstrap 5
- Le sujet exige : « Les champs de formulaires doivent être vérifiés (téléphone, adresse, nom, prénom) »

---

## 7. Infrastructure Docker

### 7.1 Situation actuelle

Le `docker-compose.dev.yml` existant a 4 services :

| Service | Container | Port hôte | Rôle |
|---------|-----------|-----------|------|
| `db` | `wcdo-db` | — | MariaDB 10.11 (base `wcdo`) |
| `php` | `wcdo-php` | — | PHP-FPM 8.2 pour le Backend natif |
| `nginx` | `wcdo-nginx` | 8080, 8081 | Front (8080) + API (8081) |
| `phpmyadmin` | `wcdo-phpmyadmin` | 8082 | Interface BDD |

### 7.2 Services à ajouter pour le Bloc 3

On ajoute **2 nouveaux services** au `docker-compose.dev.yml` :

#### Service `symfony-php` — PHP-FPM pour Symfony

| Aspect | Valeur |
|--------|--------|
| **Container name** | `wcdo-symfony-php` |
| **Image** | Build custom depuis `dossier_pr_docker_etc/docker/symfony/Dockerfile` |
| **Base image** | `php:8.2-fpm-alpine` |
| **Extensions PHP** | `pdo`, `pdo_mysql`, `intl`, `zip`, `opcache` |
| **Outils** | Composer (copié depuis image officielle), `git`, `curl`, `unzip` |
| **Volume** | `../Bloc3/symfony:/app` (montage du code source) |
| **Working dir** | `/app` |
| **Dépend de** | `db` (condition: service_healthy) |
| **Réseau** | `wcdo-dev` (même réseau que les autres services) |
| **Env vars** | `DATABASE_URL=mysql://wcdo_user:wcdo_pass@db:3306/wcdo_rh?serverVersion=mariadb-10.11.0` |

#### Service `symfony-nginx` — Nginx pour Symfony

| Aspect | Valeur |
|--------|--------|
| **Container name** | `wcdo-symfony-nginx` |
| **Image** | `nginx:alpine` |
| **Volume config** | `./docker/symfony/nginx.conf:/etc/nginx/conf.d/default.conf` |
| **Volume code** | `../Bloc3/symfony:/app` |
| **Port** | `8090:80` → **http://localhost:8090** |
| **Dépend de** | `symfony-php` |
| **Réseau** | `wcdo-dev` |

### 7.3 Dockerfile Symfony — `dossier_pr_docker_etc/docker/symfony/Dockerfile`

```dockerfile
FROM php:8.2-fpm-alpine

WORKDIR /app

# Extensions PHP nécessaires pour Symfony + Doctrine
RUN apk add --no-cache \
    git curl unzip icu-dev libzip-dev \
    && docker-php-ext-install \
        pdo pdo_mysql intl zip opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN chmod +x /usr/bin/composer

# Symfony CLI (optionnel mais pratique pour le dev)
# RUN curl -sS https://get.symfony.com/cli/installer | bash

EXPOSE 9000

CMD ["php-fpm"]
```

**Différences avec le Dockerfile existant (Bloc 2) :**
- Ajout de `intl` (internationalisation, requis par Symfony Validator)
- Ajout de `zip` et `unzip` (requis par Composer pour installer les dépendances)
- Ajout de `opcache` (performances PHP)
- Le reste est identique

### 7.4 Config Nginx Symfony — `dossier_pr_docker_etc/docker/symfony/nginx.conf`

```nginx
server {
    listen 80;
    server_name _;

    root /app/public;
    index index.php;

    # Symfony front controller
    location / {
        try_files $uri /index.php$is_args$args;
    }

    # PHP-FPM
    location ~ ^/index\.php(/|$) {
        fastcgi_pass symfony-php:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    # Bloquer l'accès aux autres fichiers PHP
    location ~ \.php$ {
        return 404;
    }

    # Assets statiques
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    error_log  /var/log/nginx/symfony_error.log;
    access_log /var/log/nginx/symfony_access.log;
}
```

### 7.5 Base de données `wcdo_rh`

La base `wcdo_rh` est créée dans le **même container MariaDB** (`wcdo-db`) déjà en place.

**Modification du `init.sql`** existant — ajouter à la fin :

```sql
-- ========================================
-- Base de données Bloc 3 — App RH Symfony
-- ========================================
CREATE DATABASE IF NOT EXISTS `wcdo_rh`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Donner les droits à l'utilisateur existant
GRANT ALL PRIVILEGES ON `wcdo_rh`.* TO 'wcdo_user'@'%';
FLUSH PRIVILEGES;
```

> ⚠️ Les tables seront créées par les **migrations Doctrine**, pas par du SQL manuel.

**DATABASE_URL pour Symfony** (dans `.env.local` ou en variable d'environnement Docker) :

```
DATABASE_URL="mysql://wcdo_user:wcdo_pass@db:3306/wcdo_rh?serverVersion=mariadb-10.11.0&charset=utf8mb4"
```

### 7.6 Récapitulatif des ports

| Port hôte | Service | Application |
|-----------|---------|-------------|
| **8080** | wcdo-nginx | Frontend Bloc 1 (borne de commande) |
| **8081** | wcdo-nginx | API Backend Bloc 2 (PHP natif) |
| **8082** | wcdo-phpmyadmin | phpMyAdmin |
| **8090** | wcdo-symfony-nginx | **App RH Bloc 3 (Symfony)** |

### 7.7 Mise à jour du `docker-compose.dev.yml`

Ajouter les 2 services suivants après le service `phpmyadmin` :

```yaml
  # ── Symfony PHP-FPM (Bloc 3 — App RH) ─────────────────────
  symfony-php:
    build:
      context: .
      dockerfile: docker/symfony/Dockerfile
    image: wcdo-symfony:dev
    container_name: wcdo-symfony-php
    restart: unless-stopped
    volumes:
      - ../Bloc3/symfony:/app
    working_dir: /app
    depends_on:
      db:
        condition: service_healthy
    environment:
      DATABASE_URL: "mysql://wcdo_user:wcdo_pass@db:3306/wcdo_rh?serverVersion=mariadb-10.11.0&charset=utf8mb4"
      APP_ENV: dev
      APP_SECRET: "change-me-in-production"
    networks:
      - wcdo-dev

  # ── Symfony Nginx (Bloc 3 — App RH) ────────────────────────
  symfony-nginx:
    image: nginx:alpine
    container_name: wcdo-symfony-nginx
    restart: unless-stopped
    volumes:
      - ../Bloc3/symfony:/app
      - ./docker/symfony/nginx.conf:/etc/nginx/conf.d/default.conf
    ports:
      - "8090:80"   # App RH Symfony → http://localhost:8090
    depends_on:
      - symfony-php
    networks:
      - wcdo-dev
```

---

## 8. Données de démo (Fixtures)

### 8.1 Structure des fixtures — `src/DataFixtures/AppFixtures.php`

Les fixtures sont chargées via `doctrine:fixtures:load`. Elles créent les données dans cet ordre (respect des dépendances FK) :

1. **Fonctions** (pas de FK)
2. **Restaurants** (pas de FK)
3. **Collaborateurs** (pas de FK, mais les admins ont un mot de passe hashé)
4. **Affectations** (FK vers Collaborateur, Restaurant, Fonction)

### 8.2 Données détaillées

#### Fonctions (5)

| # | Intitulé |
|---|----------|
| 1 | Équipier polyvalent |
| 2 | Caissier |
| 3 | Manager |
| 4 | Préparateur |
| 5 | Directeur de restaurant |

#### Restaurants (3)

| # | Nom | Adresse | Code Postal | Ville |
|---|-----|---------|-------------|-------|
| 1 | Wacdo Nice Centre | 12 avenue Jean Médecin | 06000 | Nice |
| 2 | Wacdo Marseille Vieux-Port | 45 quai du Port | 13002 | Marseille |
| 3 | Wacdo Lyon Part-Dieu | 8 rue de la Part-Dieu | 69003 | Lyon |

#### Collaborateurs (8)

| # | Nom | Prénom | Email | Date embauche | Admin | Mot de passe |
|---|-----|--------|-------|---------------|-------|-------------|
| 1 | **Admin** | **Système** | **admin@wcdo.fr** | 2020-01-15 | **true** | **admin123** (hashé bcrypt) |
| 2 | Dupont | Marie | marie.dupont@wcdo.fr | 2021-03-10 | true | manager1 (hashé) |
| 3 | Martin | Lucas | lucas.martin@wcdo.fr | 2022-06-01 | false | — (null) |
| 4 | Bernard | Sophie | sophie.bernard@wcdo.fr | 2021-09-15 | false | — |
| 5 | Petit | Thomas | thomas.petit@wcdo.fr | 2023-01-20 | false | — |
| 6 | Leroy | Emma | emma.leroy@wcdo.fr | 2022-11-05 | false | — |
| 7 | Moreau | Antoine | antoine.moreau@wcdo.fr | 2020-07-12 | false | — |
| 8 | Garcia | Camille | camille.garcia@wcdo.fr | 2023-05-01 | false | — |

> **Compte de connexion principal :** `admin@wcdo.fr` / `admin123`  
> **Compte de connexion secondaire :** `marie.dupont@wcdo.fr` / `manager1`

#### Affectations (10)

| # | Collaborateur | Restaurant | Fonction | Date début | Date fin | Statut |
|---|--------------|-----------|----------|-----------|---------|--------|
| 1 | Lucas Martin | Wacdo Nice Centre | Équipier polyvalent | 2022-06-15 | — | ✅ **Active** |
| 2 | Sophie Bernard | Wacdo Nice Centre | Caissier | 2021-10-01 | — | ✅ **Active** |
| 3 | Thomas Petit | Wacdo Marseille VP | Équipier polyvalent | 2023-02-01 | — | ✅ **Active** |
| 4 | Emma Leroy | Wacdo Marseille VP | Manager | 2023-01-10 | — | ✅ **Active** |
| 5 | Antoine Moreau | Wacdo Lyon PD | Directeur de restaurant | 2020-08-01 | — | ✅ **Active** |
| 6 | Antoine Moreau | Wacdo Nice Centre | Manager | 2020-08-01 | 2022-12-31 | ❌ **Terminée** |
| 7 | Lucas Martin | Wacdo Marseille VP | Préparateur | 2022-06-15 | 2023-01-31 | ❌ **Terminée** |
| 8 | Sophie Bernard | Wacdo Lyon PD | Équipier polyvalent | 2021-10-01 | 2022-03-15 | ❌ **Terminée** |
| 9 | Marie Dupont | Wacdo Nice Centre | Manager | 2021-03-15 | — | ✅ **Active** |
| 10 | Camille Garcia | Wacdo Lyon PD | Caissier | 2023-05-15 | — | ✅ **Active** |

> **Note :** Camille Garcia n'a jamais été non-affecté dans cet exemple. Pour tester le filtre « non-affectés », on pourrait ajouter un 9ème collaborateur sans affectation, ou temporairement retirer l'affectation #10.
>
> **Alternative :** Ajouter un collaborateur #9 « Durand Paul » sans aucune affectation → il apparaîtra dans la liste des non-affectés.

**Scénarios testables avec ces données :**
- ✅ Antoine Moreau a un **historique** (ancienne affectation à Nice terminée, nouvelle à Lyon active)
- ✅ Lucas Martin a un **historique** (ancienne à Marseille terminée, nouvelle à Nice active)
- ✅ Sophie Bernard a un **historique** (ancienne à Lyon terminée, nouvelle à Nice active)
- ✅ Wacdo Nice Centre a **3 collaborateurs en poste** (Lucas, Sophie, Marie)
- ✅ Filtrage par **ville** possible (Nice, Marseille, Lyon)
- ✅ Filtrage par **fonction** possible (5 fonctions différentes utilisées)
- ✅ 2 comptes admin pour tester la connexion
- ✅ 6 comptes non-admin qui ne peuvent PAS se connecter

---

## 9. Pipeline de mise en place (ordre des étapes)

### Phase 1 — Infrastructure (Étapes 1-3)

#### Étape 1 : Vérifier et compléter le projet Symfony existant

Le squelette Symfony existe déjà dans `Bloc3/symfony/` (créé avec `symfony new`). Il faut :

1. Vérifier que `composer.json` a Symfony 7.4.*
2. Installer les dépendances manquantes (voir étape 2)
3. Vérifier que le `Kernel.php` et `public/index.php` sont corrects

#### Étape 2 : Installer toutes les dépendances

Exécuter **dans le container `symfony-php`** (ou localement si PHP 8.2 + Composer sont installés) :

```bash
# Dépendances de production
composer require symfony/orm-pack
composer require symfony/twig-pack
composer require symfony/security-bundle
composer require symfony/form
composer require symfony/validator
composer require symfony/asset

# Dépendances de dev
composer require --dev symfony/maker-bundle
composer require --dev doctrine/doctrine-fixtures-bundle
composer require --dev symfony/test-pack
composer require --dev symfony/profiler-pack
composer require --dev symfony/debug-bundle
```

#### Étape 3 : Configurer Docker

1. Créer le fichier `dossier_pr_docker_etc/docker/symfony/Dockerfile`
2. Créer le fichier `dossier_pr_docker_etc/docker/symfony/nginx.conf`
3. Ajouter les 2 services dans `docker-compose.dev.yml`
4. Modifier `docker/mariadb/init.sql` pour créer la base `wcdo_rh`
5. Configurer `Bloc3/symfony/.env.local` avec le `DATABASE_URL`
6. Lancer `docker compose up -d --build` et vérifier que http://localhost:8090 affiche la page Symfony

### Phase 2 — Modèle de données (Étapes 4-5)

#### Étape 4 : Créer les entités Doctrine

Utiliser `php bin/console make:entity` pour chaque entité, ou les créer manuellement avec les attributs PHP 8 :

**Ordre de création :**
1. `Fonction` (aucune dépendance)
2. `Restaurant` (aucune dépendance)
3. `Collaborateur` (aucune dépendance FK, mais implémente UserInterface)
4. `Affectation` (dépend des 3 autres)

Pour chaque entité :
- Définir les propriétés avec attributs `#[ORM\Column]`
- Définir les relations avec `#[ORM\ManyToOne]` et `#[ORM\OneToMany]`
- Ajouter les contraintes de validation `#[Assert\...]`
- Pour `Collaborateur` : implémenter `UserInterface` et `PasswordAuthenticatedUserInterface`

#### Étape 5 : Générer et exécuter les migrations

```bash
php bin/console make:migration          # Génère le fichier de migration
php bin/console doctrine:migrations:migrate  # Crée les tables dans wcdo_rh
```

Vérifier dans phpMyAdmin (http://localhost:8082) que les 4 tables existent avec les bonnes colonnes et FK.

### Phase 3 — Sécurité (Étape 6)

#### Étape 6 : Configurer l'authentification

1. Configurer `config/packages/security.yaml` :
   - Password hasher (bcrypt)
   - Entity provider (Collaborateur, property: email)
   - Firewall main (form_login, logout)
   - Access control (PUBLIC_ACCESS sur /login, ROLE_ADMIN partout ailleurs)

2. Créer `SecurityController` avec les routes `/login` et `/logout`

3. Créer le template `templates/security/login.html.twig`

4. Tester :
   - Accéder à http://localhost:8090 → doit rediriger vers /login
   - Se connecter avec admin@wcdo.fr / admin123 → doit accéder au dashboard
   - Se connecter avec un compte non-admin → doit être refusé

### Phase 4 — CRUD Controllers + Templates (Étapes 7-9)

#### Étape 7 : Créer les controllers

**Ordre recommandé :**
1. `DashboardController` — simple, juste une page d'accueil
2. `FonctionController` — CRUD le plus simple (1 seul champ)
3. `RestaurantController` — CRUD + affichage des affectations
4. `CollaborateurController` — CRUD + affectations + non-affectés
5. `AffectationController` — liste avec filtres + edit

Pour chaque controller :
- Utiliser `#[Route('/...', name: 'app_...')]` comme attributs PHP 8
- Injecter `EntityManagerInterface` et le Repository correspondant
- Chaque méthode retourne un `$this->render(...)` avec les données nécessaires

#### Étape 8 : Créer les templates Twig

**Ordre recommandé :**
1. `base.html.twig` — layout avec Bootstrap 5 CDN, navbar, flash messages
2. `_partials/_navbar.html.twig` — barre de navigation
3. `_partials/_flash_messages.html.twig`
4. `dashboard/index.html.twig`
5. `security/login.html.twig`
6. Templates par section (fonction → restaurant → collaborateur → affectation)

**Éléments du `base.html.twig` :**
- `<link>` Bootstrap 5 CDN (CSS)
- `<script>` Bootstrap 5 CDN (JS bundle)
- `{% block title %}` — titre de la page
- `{% block body %}` — contenu principal
- Include `_navbar.html.twig`
- Include `_flash_messages.html.twig`

**Configuration du form theme Bootstrap 5** dans `config/packages/twig.yaml` :
```yaml
twig:
    form_themes: ['bootstrap_5_layout.html.twig']
```

#### Étape 9 : Créer les FormTypes

Pour chaque formulaire :
1. Créer la classe dans `src/Form/`
2. Définir les champs avec `$builder->add(...)`
3. Configurer `data_class` vers l'entité (pour les formulaires d'entité)
4. Pour les formulaires de filtre : pas de `data_class`, méthode GET, `required: false` sur tous les champs

### Phase 5 — Données et tests (Étapes 10-11)

#### Étape 10 : Charger les fixtures

1. Créer `src/DataFixtures/AppFixtures.php` avec toutes les données de la section 8
2. Utiliser `UserPasswordHasherInterface` pour hasher les mots de passe
3. Charger :
   ```bash
   php bin/console doctrine:fixtures:load
   ```
4. Vérifier dans phpMyAdmin que les données sont présentes

#### Étape 11 : Tests

**Tests unitaires :**
- `CollaborateurTest` : vérifier getUserIdentifier(), getRoles(), que admin a ROLE_ADMIN
- Validation des entités (contraintes Assert)

**Tests fonctionnels (WebTestCase) :**
- **SecurityControllerTest** :
  - Login avec bon mot de passe → 302 redirect vers /
  - Login avec mauvais mot de passe → reste sur /login avec erreur
  - Login avec compte non-admin → refusé
  - Accès à / sans être connecté → redirect vers /login
  - Logout → redirect vers /login

- **RestaurantControllerTest** :
  - GET /restaurant → 200 + liste affichée
  - GET /restaurant/new → 200 + formulaire
  - POST /restaurant/new (valide) → 302 redirect
  - POST /restaurant/new (invalide) → 200 + erreurs
  - GET /restaurant/{id} → 200 + détail

- **CollaborateurControllerTest** :
  - Idem que Restaurant
  - GET /collaborateur/non-affectes → 200

- **FonctionControllerTest** :
  - GET /fonction → 200
  - POST /fonction/new → 302

- **AffectationControllerTest** :
  - GET /affectation → 200
  - Filtres fonctionnels (par ville, par poste, etc.)

**Tests de repository :**
- `AffectationRepositoryTest` : vérifier que `findActivesByRestaurant` ne retourne que les affectations sans date_fin

**Exécution :**
```bash
php bin/phpunit                        # Tous les tests
php bin/phpunit tests/Controller/      # Seulement les tests de controller
php bin/phpunit --filter=Security      # Seulement les tests de sécurité
```

---

## 10. Points d'examen — Ce que le jury va demander

### 10.1 Questions probables et réponses préparées

#### Q1 : « Pourquoi avoir choisi Symfony plutôt qu'un autre framework ? »

> **Réponse :** Symfony est le framework PHP le plus utilisé en France et le plus structurant. Il impose une architecture MVC propre, utilise Doctrine comme ORM standard, et a un écosystème de composants réutilisables (Security, Form, Validator, Twig). Je connaissais déjà PHP 8.2 en OOP depuis le Bloc 2, donc Symfony était la suite logique — il fait la même chose que mon code natif mais de manière plus robuste et standardisée. De plus, Symfony a la communauté et la documentation les plus complètes en français.

#### Q2 : « Expliquez la différence entre votre code PHP natif (Bloc 2) et le code Symfony (Bloc 3). »

> **Réponse :** 
> 
> | Aspect | Bloc 2 (PHP natif) | Bloc 3 (Symfony) |
> |--------|-------------------|------------------|
> | **Routing** | `Router.php` custom avec regex | Attributs `#[Route]` sur les méthodes de controller |
> | **Controllers** | Classes PHP custom avec `Response::json()` | Héritent de `AbstractController`, utilisent `$this->render()` |
> | **Accès BDD** | PDO avec requêtes SQL préparées (`$stmt->prepare()`) | Doctrine ORM : `EntityManager`, `Repository->find()`, QueryBuilder |
> | **Entités** | Classes PHP simples avec constructeur et getters | Classes annotées `#[ORM\Entity]` avec mapping automatique vers les tables |
> | **Création tables** | Fichier `init.sql` écrit à la main | Migrations Doctrine générées par `make:migration` |
> | **Auth** | Sessions PHP manuelles (`$_SESSION`), `password_verify()` | Security Component : firewall, form_login, UserInterface, bcrypt hasher |
> | **Vues** | Pas de vues serveur (API JSON + JS côté client) | Templates Twig avec héritage (`{% extends %}`, `{% block %}`) |
> | **Formulaires** | `$_POST` lus manuellement | FormType avec validation automatique et protection CSRF |
> | **Tests** | PHPUnit basique | PHPUnit + WebTestCase + KernelTestCase |

#### Q3 : « Qu'est-ce que Doctrine fait exactement ? Pourquoi ne pas écrire le SQL directement ? »

> **Réponse :** Doctrine est un ORM — il mappe les classes PHP (entités) sur les tables SQL. Au lieu d'écrire `INSERT INTO collaborateur (nom, ...) VALUES (?, ...)` en PDO, j'écris `$em->persist($collaborateur); $em->flush();`. Les avantages :
> 1. **Pas de SQL à écrire** pour le CRUD basique → moins d'erreurs, code plus lisible
> 2. **Migrations automatiques** → quand je modifie une entité, `make:migration` génère le SQL de modification de table
> 3. **Relations en objet** → `$affectation->getCollaborateur()->getNom()` au lieu de JOIN manuels
> 4. **Protection injection SQL** → les paramètres sont automatiquement échappés
> 5. **DQL / QueryBuilder** → pour les requêtes complexes, j'utilise un QueryBuilder orienté objet
>
> Ce que PDO faisait dans le Bloc 2, c'est Doctrine qui le fait maintenant — mais de manière beaucoup plus abstraite.

#### Q4 : « Qu'est-ce que Twig fait que du PHP natif ne fait pas ? »

> **Réponse :** Twig est un moteur de templates qui sépare la logique PHP de la présentation HTML. 
>
> - **Héritage de templates** : `{% extends 'base.html.twig' %}` → j'écris une seule fois le layout (navbar, Bootstrap, etc.) et chaque page ne définit que son contenu via `{% block body %}`
> - **Échappement automatique** : `{{ variable }}` échappe automatiquement le HTML → protection XSS native, contrairement à `echo $variable` en PHP
> - **Filtres** : `{{ date|date('d/m/Y') }}`, `{{ nom|upper }}` → transformations inline propres
> - **Includes et macros** : réutilisation de fragments de templates
> - **Form rendering** : `{{ form_widget(form) }}` génère automatiquement le HTML des formulaires avec les erreurs de validation
>
> Dans le Bloc 2, je n'avais pas de vues serveur (c'était une API JSON). Ici, Twig me permet de générer le HTML côté serveur, ce qui est le pattern classique d'un back-office.

#### Q5 : « Comment fonctionne la sécurité dans votre application ? »

> **Réponse :** J'utilise le Security Component de Symfony :
> 1. **Firewall** : configuré dans `security.yaml`, il intercepte toutes les requêtes. La route `/login` est publique, tout le reste exige le rôle `ROLE_ADMIN`
> 2. **Entity Provider** : Symfony charge l'utilisateur (Collaborateur) depuis la BDD par son email
> 3. **Password Hasher** : les mots de passe sont hachés en bcrypt. À la connexion, Symfony compare le hash automatiquement
> 4. **Rôles** : mon entité `Collaborateur` implémente `UserInterface`. Si `administrateur = true`, le rôle est `ROLE_ADMIN`. Sinon, c'est `ROLE_USER` (qui ne donne pas accès)
> 5. **CSRF** : un token caché est ajouté automatiquement dans chaque formulaire et vérifié à la soumission
>
> Dans le Bloc 2, je faisais tout ça à la main : `$_SESSION` pour stocker l'utilisateur, `password_verify()` pour le mot de passe, un middleware custom pour vérifier l'accès. Symfony le fait de manière standard et sécurisée.

#### Q6 : « Pourquoi Twig plutôt que EasyAdmin ? »

> **Réponse :** EasyAdmin génère automatiquement les pages CRUD — c'est rapide mais on ne code presque rien soi-même. Avec Twig, je code chaque template manuellement : les listes, les détails, les formulaires, les filtres. C'est plus de travail mais ça me permet de :
> 1. **Maîtriser le rendu** : chaque page est exactement comme je le veux
> 2. **Montrer ma compétence** : le jury voit que je sais écrire du Twig, pas juste configurer un outil
> 3. **Comprendre le fonctionnement** : je sais comment Symfony rend les vues, comment les données passent du controller au template

#### Q7 : « Le jury vous demande d'ajouter une fonctionnalité en live. » (Exemples probables)

**Scénario A : « Ajoutez un champ téléphone au collaborateur. »**
1. Modifier l'entité `Collaborateur` → ajouter `$telephone` avec `#[ORM\Column(length: 20, nullable: true)]`
2. `php bin/console make:migration` → `php bin/console d:m:m`
3. Ajouter le champ dans `CollaborateurType` → `$builder->add('telephone', TelType::class)`
4. Afficher dans les templates (`show.html.twig`, `index.html.twig`)
5. Temps estimé : 5-10 minutes

**Scénario B : « Ajoutez un bouton de suppression sur un restaurant. »**
1. Ajouter une route `app_restaurant_delete` dans `RestaurantController`
2. Vérifier qu'il n'y a pas d'affectations actives avant de supprimer
3. Ajouter un bouton dans le template `edit.html.twig` avec un formulaire de confirmation
4. Temps estimé : 5-10 minutes

**Scénario C : « Ajoutez la pagination sur la liste des collaborateurs. »**
1. Installer `knplabs/knp-paginator-bundle`
2. Dans le controller, remplacer `findAll()` par une requête paginée
3. Dans le template, ajouter les liens de pagination
4. Temps estimé : 10-15 minutes

#### Q8 : « Comment fonctionnent les migrations Doctrine ? »

> **Réponse :** Quand je modifie une entité (ajouter un champ, changer un type, ajouter une relation), Doctrine compare l'état des entités PHP avec l'état actuel de la base de données. La commande `make:migration` génère un fichier PHP dans `migrations/` contenant le SQL nécessaire (ALTER TABLE, CREATE TABLE, etc.). Ensuite `doctrine:migrations:migrate` exécute ce SQL. C'est versionné — chaque migration a un timestamp et Doctrine sait lesquelles ont déjà été appliquées. Dans le Bloc 2, je devais modifier le `init.sql` manuellement et recréer la base — avec Doctrine, je fais évoluer le schéma de manière incrémentale.

#### Q9 : « Qu'est-ce que l'injection de dépendances dans Symfony ? »

> **Réponse :** Dans Symfony, les services (repositories, mailer, hasher, etc.) sont déclarés dans un conteneur de services. Au lieu de faire `new MonRepository(new PDO(...))` moi-même, je déclare le type dans le constructeur de mon controller ou service (`public function __construct(private CollaborateurRepository $repo)`) et Symfony l'injecte automatiquement grâce à l'autowiring. C'est configuré dans `services.yaml`. Ça rend le code plus modulaire, testable, et évite le couplage fort entre les classes.

#### Q10 : « Quelle est la différence entre ManyToOne et ManyToMany ? Pourquoi avez-vous une table Affectation ? »

> **Réponse :** Un ManyToMany créerait une simple table pivot sans données supplémentaires (juste collaborateur_id et restaurant_id). Mais j'ai besoin de stocker des informations sur la relation elle-même : la date de début, la date de fin, et le poste (fonction). Donc j'ai créé une entité `Affectation` avec ses propres champs et 3 relations ManyToOne (vers Collaborateur, Restaurant, et Fonction). C'est le pattern classique quand la relation a ses propres attributs — on « casse » le ManyToMany en une entité intermédiaire.

### 10.2 Points clés à mémoriser pour l'oral

1. **Toujours comparer avec le Bloc 2** — le jury veut voir que tu comprends CE QUE fait le framework, pas juste que tu l'utilises
2. **Savoir expliquer chaque fichier de config** — `security.yaml`, `doctrine.yaml`, `services.yaml`
3. **Savoir créer une entité en live** — `make:entity` ou à la main
4. **Savoir expliquer le cycle de vie d'une requête** :
   ```
   HTTP Request → Kernel → Router → Controller → Doctrine → Twig → HTTP Response
   ```
5. **Savoir expliquer pourquoi `dateFin IS NULL` = affectation active** — c'est le cœur de la logique métier
6. **Savoir modifier le code en live** — le jury demandera un ajout/modification sur le champ

---

## Annexe A — Commandes de référence

```bash
# ── Docker ──
docker compose -f dossier_pr_docker_etc/docker-compose.dev.yml up -d --build
docker compose -f dossier_pr_docker_etc/docker-compose.dev.yml exec symfony-php bash

# ── Symfony CLI (dans le container symfony-php) ──
php bin/console                                  # Liste toutes les commandes
php bin/console make:entity                      # Créer/modifier une entité
php bin/console make:controller                  # Créer un controller
php bin/console make:form                        # Créer un FormType
php bin/console make:migration                   # Générer une migration
php bin/console doctrine:migrations:migrate      # Exécuter les migrations
php bin/console doctrine:fixtures:load           # Charger les fixtures
php bin/console debug:router                     # Voir toutes les routes
php bin/console cache:clear                      # Vider le cache

# ── Tests ──
php bin/phpunit                                  # Lancer tous les tests
php bin/phpunit --filter=Security                # Tests de sécurité uniquement
php bin/phpunit --coverage-html var/coverage     # Couverture de code

# ── Composer ──
composer require symfony/orm-pack               # Installer Doctrine
composer require --dev symfony/maker-bundle      # Installer Maker
```

---

## Annexe B — Schéma de flux de navigation

```
                    ┌─────────┐
                    │  LOGIN  │
                    └────┬────┘
                         │ (auth OK)
                         ▼
                   ┌───────────┐
                   │ DASHBOARD │
                   └─────┬─────┘
            ┌────────┬───┴───┬────────┐
            ▼        ▼       ▼        ▼
      ┌──────────┐ ┌─────┐ ┌──────┐ ┌──────────────┐
      │RESTAURANTS│ │FONC.│ │COLLAB│ │AFFECTATIONS  │
      │  index   │ │index│ │index │ │   index      │
      └────┬─────┘ └──┬──┘ └──┬───┘ └──────────────┘
           │          │       │
      ┌────┴────┐   edit   ┌──┴──────┐
      ▼         ▼          ▼         ▼
   ┌──────┐ ┌──────┐  ┌──────┐  ┌──────┐
   │ show │ │ new  │  │ show │  │ new  │
   └──┬───┘ └──────┘  └──┬───┘  └──────┘
      │                   │
      ▼                   ▼
   ┌──────┐           ┌──────┐
   │ edit │           │ edit │
   │+hist.│           │+aff. │
   │+aff. │           │+hist.│
   └──────┘           └──────┘
```

---

> **Ce plan est maintenant complet.** Avec ce document, Hugo peut construire l'intégralité de l'application Bloc 3 étape par étape, sans se poser de questions. Chaque entité, chaque route, chaque template, chaque config est détaillé. Les réponses aux questions du jury sont prêtes.
