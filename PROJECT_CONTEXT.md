# WCDO — Contexte Complet du Projet
## Borne de Commande McDonald's (Self-Order Kiosk)

---

## Identité du projet

| Champ | Valeur |
|-------|--------|
| **Nom projet** | WCDO (WakDo / Borne de commande) |
| **Titre complet** | Borne de Commande McDonald's — Self-Order Kiosk |
| **Repository GitHub** | `Kurdant/BornMcdoFromScratch` |
| **Auteur** | Hugo |
| **Contexte** | Projet scolaire — AcadeNice / École |
| **Type** | Application web fullstack |
| **Statut** | Repartir de zéro (backend + logique à reconstruire) |

---

## Objectif fonctionnel

Simuler une **borne de commande type McDonald's** permettant à un client de :

1. Naviguer le catalogue produits par catégories
2. Ajouter des produits au panier (avec choix de sauce, taille boisson)
3. Valider sa commande et choisir le mode de paiement
4. Recevoir un numéro de commande + numéro de chevalet
5. Optionnellement se connecter pour accumuler des points de fidélité

Et à un **admin** de :

1. Se connecter à un espace d'administration
2. Gérer les produits (CRUD : créer, modifier, supprimer)
3. Consulter les commandes passées

---

## Stack technique

| Couche | Technologie | Version |
|--------|------------|---------|
| Frontend | HTML / CSS / JavaScript natif | - |
| Backend | PHP natif (sans framework) | 8.2 |
| Base de données | MariaDB | 10.11 |
| Serveur HTTP | Nginx | Alpine |
| Conteneurisation | Docker + Docker Compose | - |
| Reverse proxy | Traefik | v2 |
| Registry Docker | Docker Registry v2 (privé) | - |
| Tests | PHPUnit | ^10 |
| CI/CD | GitHub Actions | - |
| Runner CI/CD | Self-hosted (VPS stark) | - |

---

## Architecture backend

Pattern : **MVC + Repository + Service** (PHP natif, sans framework)

```
Requête HTTP
    ↓
public/index.php        Point d'entrée — charge autoloader, définit routes
    ↓
src/Http/Router.php     Dispatch vers le bon Controller selon méthode + URL
    ↓
src/Controllers/        Reçoit requête, valide params, appelle Service ou Repo
    ↓
src/Services/           Logique métier (calculs, règles, orchestration)
    ↓
src/Repositories/       Accès BDD via PDO (requêtes SQL préparées)
    ↓
src/Entities/           Objets métier (validation des données)
    ↓
src/Http/Response.php   Retourne le JSON au client
```

Namespace PHP : `WCDO\`
Autoload : PSR-4 via Composer (`src/` → `WCDO\`)

---

## Structure des fichiers PHP

```
src/
├── Config/
│   └── Database.php                  Singleton PDO — connexion BDD
├── Controllers/
│   ├── CatalogueController.php       Produits, catégories, sauces, boissons
│   ├── PanierController.php          Gestion du panier (session)
│   ├── CommandeController.php        Validation et récupération commandes
│   ├── AuthController.php            Inscription/connexion client
│   └── AdminController.php           CRUD produits + liste commandes
├── Entities/
│   ├── Produit.php                   Prix > 0, stock >= 0, estDisponible()
│   ├── Categorie.php
│   ├── Panier.php
│   ├── PanierLigne.php
│   ├── Commande.php
│   ├── Client.php
│   ├── Sauce.php
│   └── TailleBoisson.php
├── Repositories/
│   ├── ProduitRepository.php
│   ├── CategorieRepository.php
│   ├── PanierRepository.php
│   ├── PanierProduitRepository.php
│   ├── CommandeRepository.php
│   ├── CommandeProduitRepository.php
│   ├── ClientRepository.php
│   ├── AdminRepository.php
│   ├── SauceRepository.php
│   └── TailleBoissonRepository.php
├── Services/
│   ├── PanierService.php
│   ├── CommandeService.php
│   └── AuthService.php
├── Http/
│   ├── Router.php                    Regex routing, params {id}
│   └── Response.php                  JSON responses (json(), error(), notFound())
└── Exceptions/
    └── StockInsuffisantException.php
```

---

## Routes API complètes

### Catalogue (lecture publique — GET)

| Méthode | Route | Controller | Description |
|---------|-------|-----------|-------------|
| GET | `/api/categories` | CatalogueController::getCategories | Toutes les catégories |
| GET | `/api/produits` | CatalogueController::getProduits | Tous les produits (filtrables) |
| GET | `/api/produits/{id}` | CatalogueController::getProduit | Un produit par ID |
| GET | `/api/boissons` | CatalogueController::getBoissons | Produits catégorie boissons |
| GET | `/api/tailles-boissons` | CatalogueController::getTaillesBoissons | Tailles + suppléments prix |
| GET | `/api/sauces` | CatalogueController::getSauces | Liste des sauces |

### Panier (session PHP)

| Méthode | Route | Controller | Description |
|---------|-------|-----------|-------------|
| GET | `/api/panier` | PanierController::getPanier | Panier courant |
| POST | `/api/panier/ajouter` | PanierController::ajouter | Ajouter un produit |
| DELETE | `/api/panier/ligne/{id}` | PanierController::supprimerLigne | Retirer une ligne |
| DELETE | `/api/panier` | PanierController::vider | Vider le panier |

### Commande

| Méthode | Route | Controller | Description |
|---------|-------|-----------|-------------|
| POST | `/api/commande` | CommandeController::passer | Valider la commande |
| GET | `/api/commande/{numero}` | CommandeController::getByNumero | Récupérer par numéro |

### Auth Client

| Méthode | Route | Controller | Description |
|---------|-------|-----------|-------------|
| POST | `/api/auth/register` | AuthController::register | Créer un compte |
| POST | `/api/auth/login` | AuthController::login | Connexion |
| POST | `/api/auth/logout` | AuthController::logout | Déconnexion |
| GET | `/api/auth/me` | AuthController::me | Profil connecté |

### Admin

| Méthode | Route | Controller | Description |
|---------|-------|-----------|-------------|
| POST | `/api/admin/login` | AdminController::login | Connexion admin |
| POST | `/api/admin/logout` | AdminController::logout | Déconnexion admin |
| GET | `/api/admin/produits` | AdminController::getProduits | Liste produits |
| POST | `/api/admin/produits` | AdminController::createProduit | Créer produit |
| PUT | `/api/admin/produits/{id}` | AdminController::updateProduit | Modifier produit |
| DELETE | `/api/admin/produits/{id}` | AdminController::deleteProduit | Supprimer produit |
| GET | `/api/admin/commandes` | AdminController::getCommandes | Liste commandes |

### Système

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/health` | Health check pour CI/CD — doit retourner `{"status":"ok"}` HTTP 200 |

---

## Base de données

### Informations générales

| Champ | Valeur |
|-------|--------|
| SGBD | MariaDB 10.11 |
| Normalisation | 3NF (Troisième Forme Normale) |
| Charset | utf8mb4 / utf8mb4_unicode_ci |
| Engine | InnoDB |
| Nombre de tables | 10 |

### Schéma des tables

```
CATEGORIE (id, nom)
    │ 1,n
    ▼
PRODUIT (id, nom, description, prix, stock, id_categorie, image, date_creation)
    │ n,n                              │ n,n
    ▼                                  ▼
PANIER_PRODUIT                   COMMANDE_PRODUIT
(id, id_panier, id_produit,      (id, id_commande, id_produit,
 quantite, prix_unitaire, details) quantite, prix_unitaire, details)
    │                                  │
    ▼                                  ▼
PANIER                           COMMANDE
(id, session_id, client_id,      (id, numero_commande, numero_chevalet,
 date_creation, updated_at)       type_commande, mode_paiement,
    │ 0,n                          montant_total, date_creation, client_id)
    ▼                                  │ 0,n
CLIENT                                 ▼
(id, prenom, nom, email,          CLIENT (même entité)
 mot_de_passe, points_fidelite,
 date_creation)

SAUCE (id, nom)                    ← Référencée dans JSON details
TAILLE_BOISSON (id, nom, volume, supplement_prix)  ← Référencée dans JSON details
ADMIN (id, nom, email, mot_de_passe)  ← Table indépendante
```

### Détail des tables

#### CATEGORIE
| Colonne | Type | Contraintes |
|---------|------|-------------|
| id | BIGINT | PK, AUTO_INCREMENT |
| nom | VARCHAR(100) | NOT NULL, UNIQUE |

Valeurs initiales : Menu, Sandwiches, Wraps, Frites, Boissons Froides, Encas, Desserts

#### SAUCE
| Colonne | Type | Contraintes |
|---------|------|-------------|
| id | BIGINT | PK, AUTO_INCREMENT |
| nom | VARCHAR(100) | NOT NULL, UNIQUE |

Valeurs initiales : Barbecue, Moutarde, Cremy-Deluxe, Ketchup, Chinoise, Curry, Pomme-Frite

#### TAILLE_BOISSON
| Colonne | Type | Contraintes |
|---------|------|-------------|
| id | BIGINT | PK, AUTO_INCREMENT |
| nom | VARCHAR(50) | NOT NULL, UNIQUE |
| volume | INT | NOT NULL, > 0 |
| supplement_prix | DECIMAL(10,2) | NOT NULL, DEFAULT 0.00, >= 0 |

Valeurs initiales : 30cl (0.00€), 50cl (0.50€)

#### ADMIN
| Colonne | Type | Contraintes |
|---------|------|-------------|
| id | BIGINT | PK, AUTO_INCREMENT |
| nom | VARCHAR(100) | NOT NULL |
| email | VARCHAR(255) | NOT NULL, UNIQUE |
| mot_de_passe | VARCHAR(255) | NOT NULL (bcrypt) |

Comptes initiaux : `admin@wcdo.fr` / `hugo@wcdo.fr` (mot de passe : `admin123`)

#### CLIENT
| Colonne | Type | Contraintes |
|---------|------|-------------|
| id | BIGINT | PK, AUTO_INCREMENT |
| prenom | VARCHAR(100) | NOT NULL |
| nom | VARCHAR(100) | NOT NULL |
| email | VARCHAR(255) | NOT NULL, UNIQUE |
| mot_de_passe | VARCHAR(255) | NOT NULL (bcrypt) |
| points_fidelite | INT | NOT NULL, DEFAULT 0, >= 0 |
| date_creation | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |

Comptes initiaux : `jean.dupont@mail.fr`, `sophie.martin@mail.fr` (mot de passe : `client123`)

#### PRODUIT
| Colonne | Type | Contraintes |
|---------|------|-------------|
| id | BIGINT | PK, AUTO_INCREMENT |
| nom | VARCHAR(200) | NOT NULL |
| description | TEXT | NULL |
| prix | DECIMAL(10,2) | NOT NULL, > 0 |
| stock | INT | NOT NULL, DEFAULT 0, >= 0 |
| id_categorie | BIGINT | FK CATEGORIE(id), NOT NULL |
| image | VARCHAR(255) | NULL |
| date_creation | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |

#### PANIER
| Colonne | Type | Contraintes |
|---------|------|-------------|
| id | BIGINT | PK, AUTO_INCREMENT |
| session_id | VARCHAR(255) | NOT NULL |
| date_creation | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |
| updated_at | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP |
| client_id | BIGINT | FK CLIENT(id), NULL (anonyme autorisé) |

#### COMMANDE
| Colonne | Type | Contraintes |
|---------|------|-------------|
| id | BIGINT | PK, AUTO_INCREMENT |
| numero_commande | VARCHAR(20) | NOT NULL, UNIQUE |
| numero_chevalet | INT | NOT NULL, BETWEEN 1 AND 999 |
| type_commande | ENUM | 'sur_place', 'a_emporter' |
| mode_paiement | ENUM | 'carte', 'especes' |
| montant_total | DECIMAL(10,2) | NOT NULL, > 0 |
| date_creation | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |
| client_id | BIGINT | FK CLIENT(id), NULL (anonyme autorisé) |

#### PANIER_PRODUIT et COMMANDE_PRODUIT
| Colonne | Type | Description |
|---------|------|-------------|
| id | BIGINT | PK |
| id_panier / id_commande | BIGINT | FK vers PANIER ou COMMANDE, CASCADE DELETE |
| id_produit | BIGINT | FK PRODUIT(id), RESTRICT DELETE |
| quantite | INT | >= 1 |
| prix_unitaire | DECIMAL(10,2) | Figé au moment de l'ajout |
| details | JSON | Sauces, taille boisson, composition menu |

Exemple `details` JSON :
```json
{
  "sauces": ["Barbecue", "Ketchup"],
  "taille_boisson": "50cl",
  "composition_menu": {
    "sandwich": "Big Mac",
    "frites": "Moyennes Frites",
    "boisson": "Coca-Cola"
  }
}
```

---

## Règles de gestion (10 RG)

| ID | Règle | Implémentation |
|----|-------|----------------|
| RG-001 | Stock = 0 → Produit indisponible sur la borne | `Produit::estDisponible()` |
| RG-002 | Maximum 2 sauces par menu | Validation dans PanierService |
| RG-003 | Boisson 50cl = +0,50€ par rapport au prix de base | `TAILLE_BOISSON.supplement_prix` |
| RG-004 | Numéro de chevalet entre 001 et 999 | Contrainte CHECK + validation |
| RG-005 | 1€ dépensé = 1 point de fidélité (arrondi inférieur) | CommandeService après paiement |
| RG-006 | Panier détruit après transformation en commande | PanierService::convertirEnCommande() |
| RG-007 | Commande créée UNIQUEMENT après paiement validé | CommandeService::passer() |
| RG-008 | Stock décrémenté pour chaque produit commandé | CommandeService (transaction) |
| RG-009 | Client anonyme = client_id NULL, pas de points fidélité | Structure BDD + logique |
| RG-010 | Historique des commandes stocké pour raisons légales | Tables COMMANDE + COMMANDE_PRODUIT |

---

## Glossaire métier (17 concepts)

| Concept | Définition |
|---------|-----------|
| **Borne** | Interface tactile de commande autonome |
| **Produit** | Article vendable (sandwich, boisson, dessert, etc.) |
| **Catégorie** | Classification des produits (Menu, Sandwiches, Wraps, etc.) |
| **Menu** | Combinaison sandwich + frites + boisson à prix fixe |
| **Panier** | Sélection temporaire avant validation |
| **Commande** | Transaction finalisée après paiement |
| **Numéro chevalet** | Identifiant visuel 001-999 pour récupérer la commande |
| **Sauce** | Option incluse dans les menus (max 2) |
| **Taille boisson** | Format de boisson avec supplément éventuel (30cl / 50cl) |
| **Sur place** | Type de commande (consommé sur place) |
| **A emporter** | Type de commande (emporté) |
| **Mode paiement** | Carte ou espèces |
| **Stock** | Quantité disponible d'un produit |
| **Points fidélité** | Récompense client (1€ = 1 point) |
| **Client anonyme** | Commande sans compte, pas de fidélité |
| **Client connecté** | Commande avec compte, accumule des points |
| **Admin** | Gestionnaire des stocks et commandes |

---

## Acteurs du système

| Acteur | Description | Accès |
|--------|-------------|-------|
| **Client anonyme** | Commande sans se connecter | Catalogue, panier, commande |
| **Client connecté** | A un compte, accumule des points | + Historique, profil |
| **Admin** | Gère les stocks et voit les commandes | Back-office admin |

---

## Catalogue produits (seed initial)

### Sandwiches (catégorie 2)
- Big Mac — 5,40€
- McChicken — 4,20€
- Royal Cheese — 4,90€
- Double Cheeseburger — 3,80€
- Filet-O-Fish — 4,50€
- Quarter Pounder — 5,90€

### Wraps (catégorie 3)
- Wrap Crispy Chicken — 4,70€
- Wrap McChicken — 4,20€

### Frites (catégorie 4)
- Petites Frites — 1,90€
- Moyennes Frites — 2,40€
- Grandes Frites — 3,10€

### Boissons Froides (catégorie 5)
- Coca-Cola — 2,20€
- Sprite — 2,20€
- Fanta Orange — 2,20€
- Eau Minérale — 1,50€
- Milkshake Vanille — 3,50€

### Encas (catégorie 6)
- McNuggets x6 — 4,20€
- McNuggets x9 — 5,80€
- McNuggets x20 — 9,90€
- McBaguette — 3,90€

### Desserts (catégorie 7)
- McFlurry Oreo — 3,20€
- McFlurry Caramel — 3,20€
- Apple Pie — 1,80€
- Sundae Caramel — 2,50€

### Menus (catégorie 1)
- Menu Big Mac — 9,20€
- Menu McChicken — 8,50€
- Menu Royal Cheese — 8,90€
- Menu McNuggets 9 — 9,50€

---

## Infrastructure

### Environnements

| Environnement | Serveur | URL front | URL back |
|--------------|---------|-----------|----------|
| Dev | stark.a3n.fr | `wakdo-front.acadenice.fr` | `wakdo-back.acadenice.fr` |
| Prod | vision.a3n.fr | `wakdo-front.acadenice.fr` | `wakdo-back.acadenice.fr` |

### Services Docker (docker-compose.yml)

| Service | Image | Description |
|---------|-------|-------------|
| `db` | mariadb:10.11 | Base de données |
| `php` | hugo-registry.a3n.fr/wcdo:dev | Backend PHP-FPM |
| `nginx` | nginx:alpine | Reverse proxy HTTP + static files |
| `phpmyadmin` | phpmyadmin:latest | Interface BDD (dev) |
| `runner` | myoung34/github-runner:latest | GitHub Actions runner self-hosted |

### Réseau Docker
- `internal` — réseau interne entre les services
- `admin_proxy` — réseau externe partagé avec Traefik (doit être créé manuellement)

### Traefik (reverse proxy HTTPS)
- Gère automatiquement les certificats TLS via Let's Encrypt
- Dashboard protégé par BasicAuth (htpasswd)
- Écoute sur ports 80 (redirect vers 443) et 443
- Cert resolver : `letsencrypt`

### Registry Docker privé
- URL : `hugo-registry.a3n.fr`
- UI : `hugo-registry-ui.a3n.fr`
- Images : `hugo-registry.a3n.fr/wcdo:dev` et `hugo-registry.a3n.fr/wcdo:prod`
- Auth : htpasswd (fichier `auth/htpasswd`)

---

## Nginx — Configuration virtual hosts

Deux virtual hosts dans `docker/nginx/nginx.conf` :

1. **Front** — `wakdo-front.acadenice.fr` → sert `Front/` (HTML/CSS/JS statique)
   - `root /app/Front`
   - `index accueil.html`

2. **Back** — `wakdo-back.acadenice.fr` → passe à PHP-FPM
   - `root /app/public`
   - FastCGI vers `php:9000`
   - Toutes les routes → `index.php`

---

## CI/CD GitHub Actions

### Schéma de branches

```
main   ← merge automatique après deploy prod réussi
prod   ← merge manuel depuis dev (déclenche deploy vision)
dev    ← branche de travail quotidienne (déclenche build + deploy stark)
```

### Workflows

| Fichier | Déclencheur | Runner | Actions |
|---------|------------|--------|---------|
| `tests.yml` | PR vers dev/prod/main | ubuntu-latest | PHPUnit PHP 8.2 |
| `dev-cicd.yml` | Push sur `dev` | self-hosted (stark) | Install deps → Tests → Build image → Push registry → docker compose up |
| `deploy.yml` | Push sur `prod` | stark + ubuntu-latest | Retag :dev→:prod → SSH deploy vision → Health check → Merge prod→main |

### Secrets GitHub requis

| Secret | Description |
|--------|-------------|
| `REGISTRY_USER` | Login du registry Hugo |
| `REGISTRY_PASSWORD` | Mot de passe du registry |
| `VISION_SSH_KEY` | Clé SSH privée pour vision.a3n.fr |
| `VISION_SSH_PASSPHRASE` | Passphrase SSH (vide si sans passphrase) |
| `GH_TOKEN` | Personal Access Token (scope: repo) pour le merge auto |

### Health check CI/CD
Le pipeline `deploy.yml` appelle :
```
GET https://wakdo-back.acadenice.fr/api/health
```
Doit retourner HTTP 200 (ou 302) pour valider le déploiement.

---

## Plan de tests TDD (45 tests, 12 suites)

### Règles de gestion couvertes

| RG | Suites de tests |
|----|----------------|
| RG-001 (Stock = 0) | Suite 4, 8, 10 |
| RG-002 (Max 2 sauces) | Suite 8 |
| RG-003 (Supplément 50cl) | Suite 3, 8 |
| RG-004 (Chevalet 001-999) | Suite 9 |
| RG-005 (Points fidélité) | Suite 5, 11 |
| RG-006 (Panier temporaire) | Suite 12 |
| RG-007 (Commande après paiement) | Suite 9, 12 |
| RG-008 (Stock décrémenté) | Suite 10 |
| RG-009 (Anonyme sans historique) | Suite 7, 11 |
| RG-010 (Historique légal) | Suite 9 |

### Structure des tests

```
tests/
├── Entities/
│   ├── CategorieTest.php     (3 tests)
│   ├── SauceTest.php         (3 tests)
│   ├── TailleBoissonTest.php (3 tests)
│   ├── ProduitTest.php       (6 tests) ← CRITIQUE
│   ├── ClientTest.php        (6 tests)
│   ├── AdminTest.php         (3 tests)
│   ├── PanierTest.php        (3 tests)
│   └── CommandeTest.php      (9 tests)
├── Business/
│   ├── PanierProduitTest.php       (9 tests) ← CRITIQUE
│   ├── CommandeStockTest.php       (4 tests) ← CRITIQUE
│   ├── PointsFideliteTest.php      (4 tests)
│   └── ConversionPanierCommandeTest.php (4 tests)
└── Fixtures/
    ├── CategorieFixtures.php
    ├── ProduitFixtures.php
    ├── ClientFixtures.php
    └── SauceFixtures.php
```

Lancer les tests :
```bash
./vendor/bin/phpunit tests/ -v
./vendor/bin/phpunit tests/Entities/ProduitTest.php
./vendor/bin/phpunit --filter testProduitIndisponibleSiStockZero
```

---

## Variables d'environnement

```env
# Base de données MariaDB
MYSQL_ROOT_PASSWORD=...
MYSQL_DATABASE=wcdo
MYSQL_USER=wcdo_user
MYSQL_PASSWORD=...

# Connexion PHP → DB (dans les conteneurs, DB_HOST=db)
DB_HOST=db
DB_NAME=wcdo
DB_USER=wcdo_user
DB_PASS=...

# Docker Registry
REGISTRY_HOST=hugo-registry.a3n.fr
REGISTRY_USER=...
REGISTRY_PASSWORD=...

# GitHub Actions Runner
GITHUB_TOKEN=ghp_...
```

---

## Points d'attention pour la reconstruction

1. **`DB_HOST=db`** dans Docker (pas `localhost`) — le nom du service Docker Compose

2. **`init.sql` exécuté une seule fois** au premier démarrage du volume MariaDB.
   Pour réinitialiser : `docker compose down -v && docker compose up -d`

3. **Réseau `admin_proxy` doit exister** avant `docker compose up` sur le VPS :
   ```bash
   docker network create admin_proxy
   ```

4. **`acme.json` en permissions 600** sinon Traefik refuse de démarrer :
   ```bash
   chmod 600 traefik/acme.json
   ```

5. **CORS déjà géré** par le Router PHP (preflight OPTIONS → headers CORS)

6. **Sessions PHP** pour le panier — le `session_id` identifie le panier anonyme

7. **Mots de passe en bcrypt** — utiliser `password_hash($mdp, PASSWORD_BCRYPT, ['cost' => 12])`
   et `password_verify($mdp, $hash)` pour la vérification

8. **Prix figés** dans PANIER_PRODUIT et COMMANDE_PRODUIT — stocker le prix au moment
   de l'ajout, pas récupérer le prix actuel du produit

9. **Numéro de commande** généré automatiquement — format suggéré : `CMD-YYYYMMDD-NNN`

10. **Route `/api/health`** obligatoire pour le health check CI/CD — sans elle, le
    pipeline de déploiement prod échoue toujours

---

## Fichiers à NE PAS commiter

- `.env`, `.env.prod`, `.env.dev`
- `vendor/`
- `traefik/acme.json`
- `traefik/htpasswd`
- `auth/htpasswd`
- `data/` (données du registry)

---

## Statut au moment de la reconstruction

- **Frontend** : complet, fonctionnel, conservé tel quel dans `Front/`
- **Base de données** : schéma complet + seed dans `docker/mariadb/init.sql`
- **Infrastructure** : Docker, Traefik, Registry, CI/CD — tous les fichiers conservés
- **Backend PHP** : à reconstruire depuis zéro (classes, repositories, services, controllers)
- **Tests** : plan de 45 tests documenté, à implémenter avec le backend
