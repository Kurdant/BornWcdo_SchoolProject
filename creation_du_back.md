# WCDO — Guide de création du Backend PHP

> Ce fichier documente **tout** le processus de construction du backend, fichier par fichier,
> avec l'ordre exact, les dépendances, et les points clés pour chaque fichier.

---

## Architecture du Backend

```
Requête HTTP → Nginx → PHP-FPM
    ↓
public/index.php          Front controller unique
    ↓
src/Http/Router.php       Dispatch méthode + URI regex → Controller
    ↓
src/Controllers/          Valide params, orchestre Service/Repository
    ↓
src/Services/             Logique métier (calculs, règles, transactions)
    ↓
src/Repositories/         Accès BDD via PDO (requêtes préparées)
    ↓
src/Entities/             Objets métier (validation données, toArray())
    ↓
src/Http/Response.php     Retourne le JSON au client
```

**Pattern** : MVC + Repository + Service (PHP 8.2 natif, sans framework)
**Namespace** : `WCDO\` (PSR-4 via Composer, `src/` → `WCDO\`)

---

## ÉTAPE 1 — Fondations ✅

Tout le reste dépend de ces fichiers. À faire en premier.

### 1.1 `composer.json`

- Déclare PHP >= 8.2, PHPUnit en dev
- Autoload PSR-4 : `WCDO\` → `src/`
- Script `test` pour lancer PHPUnit
- Après création : `composer install` pour générer `vendor/` et l'autoloader

### 1.2 `src/Config/Database.php` — Singleton PDO

- **Pattern Singleton** : une seule instance PDO partagée par tous les Repositories
- Constructeur privé → impossible de faire `new Database()`
- `getInstance()` retourne toujours la même connexion PDO
- **Options PDO critiques** :
  - `ATTR_ERRMODE => ERRMODE_EXCEPTION` : erreurs SQL → exceptions PHP
  - `ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC` : résultats en tableaux associatifs
  - `ATTR_EMULATE_PREPARES => false` : requêtes préparées réelles côté serveur (sécurité)
- Variables d'environnement Docker : `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`

**Point examinateur** : Pourquoi Singleton ? → Une connexion MySQL = coûteux (TCP + auth). On ne veut pas en ouvrir 10 par requête HTTP.

### 1.3 `src/Http/Response.php` — Réponses JSON

- Classe statique avec méthodes : `json()`, `success()`, `error()`, `notFound()`, `unauthorized()`
- Envoie les headers CORS (`Access-Control-Allow-Origin`)
- `exit` après `json_encode()` pour stopper PHP proprement
- Type de retour `never` (PHP 8.1+) : la méthode ne retourne jamais

### 1.4 `src/Http/Router.php` — Routing regex

- Méthodes `get()`, `post()`, `put()`, `delete()` pour enregistrer les routes
- `dispatch()` lit `$_SERVER['REQUEST_METHOD']` et `REQUEST_URI`
- Transforme `{id}` en `(?P<id>[^/]+)` via `preg_replace()`
- `preg_match()` compare l'URI aux patterns → extrait les paramètres nommés
- Gère CORS preflight (OPTIONS → 204)
- 405 Method Not Allowed si route existe mais mauvaise méthode

**Point examinateur** : Comment le Router sait que `/api/produits/42` correspond à la route `/api/produits/{id}` ? → Regex avec groupes nommés `(?P<id>[^/]+)` + `preg_match()`.

### 1.5 `public/index.php` — Front Controller

- Point d'entrée unique de l'application
- Charge l'autoloader Composer (`vendor/autoload.php`)
- Copie les variables d'environnement Docker dans `$_ENV`
- Instancie tous les Controllers
- Enregistre toutes les routes sur le Router
- Exception handler global : `try/catch \Throwable` → toujours un JSON propre
- Gère le preflight CORS (OPTIONS → 204 + headers)

**Point examinateur** : Pourquoi `\Throwable` et pas `\Exception` ? → `\Throwable` attrape TOUT (exceptions + erreurs fatales PHP comme TypeError).

---

## ÉTAPE 2 — Entités ✅

Objets métier purs. **Aucun accès BDD**. Propriétés `readonly private` + getters + `toArray()`.

### Conventions communes

- `declare(strict_types=1)` en haut de chaque fichier
- Propriétés `readonly` dans le constructeur (PHP 8.1+ promoted properties)
- Getters avec préfixe `get` (ex: `getId()`, `getNom()`)
- `toArray()` pour la sérialisation JSON (camelCase PHP → snake_case JSON)
- Validation dans le constructeur quand nécessaire

### 2.1 `Categorie.php`
- Propriétés : `id` (int), `nom` (string)
- Simple mapping BDD → objet PHP

### 2.2 `Sauce.php`
- Propriétés : `id` (int), `nom` (string)
- Même principe que Categorie

### 2.3 `TailleBoisson.php`
- Propriétés : `id`, `nom`, `volume` (int), `supplementPrix` (float)
- **RG-003** : `supplementPrix` = +0,50€ pour 50cl

### 2.4 `Produit.php`
- Propriétés : `id`, `nom`, `description` (?string), `prix` (float), `stock` (int), `categorieId` (int), `image` (?string), `dateCreation`
- Validation : `prix > 0`, `stock >= 0`
- `estDisponible()` : retourne `$this->stock > 0` → **RG-001**

### 2.5 `Client.php`
- Propriétés : `id`, `prenom`, `nom`, `email`, `motDePasseHash` (string), `pointsFidelite` (int), `dateCreation`
- `verifierMotDePasse(string $mdp)` : utilise `password_verify()` → compare hash bcrypt
- **PAS de getter pour le hash** → le mot de passe ne sort jamais de l'entité
- **RG-009** : lié au panier/commande via `clientId` nullable

### 2.6 `Admin.php`
- Propriétés : `id`, `nom`, `email`, `motDePasseHash`
- `verifierMotDePasse()` : même principe que Client

### 2.7 `Panier.php`
- Propriétés : `id`, `sessionId`, `clientId` (?int), `dateCreation`, `updatedAt`
- `clientId` nullable → **RG-009** : visiteur anonyme

### 2.8 `PanierLigne.php`
- Propriétés : `id`, `idPanier`, `idProduit`, `quantite`, `prixUnitaire` (float), `details` (?array)
- `getSousTotal()` : `prixUnitaire × quantite`
- `details` = JSON flexible (sauces choisies, taille boisson, composition menu)

### 2.9 `Commande.php`
- Propriétés : `id`, `numeroCommande`, `numeroChevalet` (int), `typeCommande`, `modePaiement`, `montantTotal` (float), `dateCreation`, `clientId` (?int)
- `calculerPointsFidelite()` : `floor($montantTotal)` → **RG-005** (1€ = 1 point)
- **RG-004** : chevalet entre 1 et 999
- **RG-009** : `clientId` nullable

---

## ÉTAPE 3 — Repositories ✅

Accès BDD via PDO. Requêtes préparées. Hydratation des Entités.

### Conventions communes

- Constructeur : `$this->pdo = Database::getInstance()`
- Requêtes préparées avec paramètres nommés (`:id`, `:nom`)
- Méthode privée `hydrate*()` pour transformer un `FETCH_ASSOC` en objet Entity
- Retour nullable (`?Entity`) quand `find*` ne trouve rien → pas d'exception

### 3.1 `CategorieRepository.php`
- `findAll()`, `findById(int $id)`, `create(string $nom)`

### 3.2 `SauceRepository.php`
- `findAll()`, `findById(int $id)`, `create(string $nom)`

### 3.3 `TailleBoissonRepository.php`
- `findAll()`, `findById(int $id)`, `create(string $nom, int $volume, float $supplementPrix)`

### 3.4 `ProduitRepository.php`
- `findAll()`, `findById(int $id)`, `findByCategorie(int $categorieId)`
- `create(nom, description, prix, stock, categorieId, image)`
- `updateStock(int $id, int $quantite)` : `UPDATE SET stock = stock - :quantite`
- Utilisé par CommandeService pour décrémenter le stock (RG-008)

### 3.5 `ClientRepository.php`
- `findById(int $id)`, `findByEmail(string $email)`
- `create(prenom, nom, email, motDePasseHash)` : INSERT + retourne Client
- `addFidelityPoints(int $id, int $points)` : `UPDATE SET points_fidelite = points_fidelite + :points`

### 3.6 `AdminRepository.php`
- `findById(int $id)`, `findByEmail(string $email)`
- `create(nom, email, motDePasseHash)`

### 3.7 `PanierRepository.php`
- `findBySessionId(string $sessionId)` : retrouve le panier d'une session
- `findOrCreateBySessionId(sessionId, clientId?)` : crée si inexistant
- `findById(int $id)`, `create(sessionId, clientId?)`
- `updateClientId(panierId, clientId)` : associe un client à un panier existant
- `delete(int $panierId)` : **RG-006** (suppression après commande)

### 3.8 `PanierProduitRepository.php`
- `findByPanierId(int $panierId)` : toutes les lignes d'un panier
- `add(panierId, produitId, quantite, prixUnitaire, details)` : ajoute une ligne
- `deleteById(int $id)` : supprime une ligne
- `deleteByPanierId(int $panierId)` : vide le panier (**RG-006**)

### 3.9 `CommandeRepository.php`
- `create(Commande $commande)` : INSERT + retourne Commande avec ID
- `findByNumero(string $numero)` : recherche par numéro unique
- `findAll()` : toutes les commandes (admin), triées par date DESC
- `findByClientId(int $clientId)` : historique d'un client

### 3.10 `CommandeProduitRepository.php`
- `addFromPanierLigne(int $commandeId, PanierLigne $ligne)` : copie une ligne panier → commande
- `findByCommandeId(int $commandeId)` : lignes d'une commande
- `deleteByCommandeId(int $commandeId)` : supprime les lignes (rare, admin)
- Réutilise l'entité `PanierLigne` (même structure) — pas besoin de dupliquer

**Point examinateur** : Pourquoi Repository pattern ? → Séparation accès BDD / logique métier. Facilite les tests (on peut mocker le repository).

---

## ÉTAPE 4 — Services ✅

Logique métier. Orchestration de plusieurs repositories.

### 4.1 `src/Services/AuthService.php` ✅

- `register(prenom, nom, email, motDePasse)` : vérifie email unique → `password_hash(bcrypt)` → INSERT → session
- `login(email, motDePasse)` : `findByEmail()` → `password_verify()` → session
- `logout()` : `unset($_SESSION['client_id'])`
- `getClientConnecte()` : retourne Client depuis `$_SESSION['client_id']` ou null
- Message d'erreur vague sur login échoué : « Email ou mot de passe incorrect » (anti user-enumeration)

**Sécurité** : bcrypt (PASSWORD_DEFAULT) = hachage lent + sel unique → impossible brute-force.

### 4.2 `src/Services/PanierService.php` ✅

- `getPanier(sessionId)` : retourne `{panier, lignes, total}`
- `ajouter(sessionId, produitId, quantite, details, clientId?)` :
  - **RG-001** : vérifie `estDisponible()` (stock > 0)
  - **RG-002** : vérifie max 2 sauces dans `details['sauces']`
  - **RG-003** : ajoute `supplement_prix` de la taille boisson au prix unitaire
  - **RG-009** : `clientId` nullable pour visiteur anonyme
- `supprimerLigne(ligneId)` : supprime une ligne
- `vider(sessionId)` : supprime toutes les lignes du panier
- `calculerTotal(lignes)` : somme des `getSousTotal()` (prix × quantité)

### 4.3 `src/Services/CommandeService.php` ✅

- **Méthode principale** : `creer(sessionId, modePaiement, typeCommande, clientId?)`
- Orchestre 6 repositories dans une **TRANSACTION SQL** (RG-008 : atomicité)
- Flux :
  1. Charger panier + lignes
  2. Vérifier stock de chaque produit (RG-001)
  3. Calculer montant total
  4. Générer numéro commande (`CMD-YYYYMMDD-XXXXX`) + chevalet (1-999, RG-004)
  5. `beginTransaction()`
  6. INSERT COMMANDE (RG-007)
  7. Copier lignes → COMMANDE_PRODUIT (RG-010)
  8. Décrémenter stocks (RG-008)
  9. Attribuer points fidélité si client connecté (RG-005, RG-009)
  10. Supprimer panier (RG-006)
  11. `commit()` ou `rollBack()` si erreur
- `getByNumero(numero)` : récupère commande + ses lignes
- `getAll()` : toutes les commandes (pour admin)

**Point examinateur** : Pourquoi `beginTransaction()` fonctionne sur tous les repositories ? → Singleton PDO. Tous les repos partagent la MÊME connexion. La transaction s'applique à toutes les requêtes sur cette connexion.

**Faiblesse connue** : `uniqid()` pour numéro commande → risque collision en haute concurrence. Amélioration possible : UUID ou séquence BDD.

---

## ÉTAPE 5 — Controllers ⏳ (à faire)

Les Controllers reçoivent la requête, valident les paramètres, appellent les Services/Repositories, et retournent une Response JSON.

### Conventions communes

- Instanciés dans `public/index.php`
- Méthodes publiques appelées par le Router
- Paramètre `array $params` pour les paramètres d'URL (`{id}`, `{numero}`)
- Lecture du body JSON : `json_decode(file_get_contents('php://input'), true)`
- Gestion de session : `session_start()` au début si nécessaire
- Retournent toujours via `Response::success()`, `Response::error()`, etc.

### 5.1 `src/Controllers/CatalogueController.php`

Routes : GET uniquement (lecture publique)
```
GET /api/categories         → getCategories()
GET /api/produits           → getProduits()
GET /api/produits/{id}      → getProduit($params)
GET /api/boissons           → getBoissons()
GET /api/tailles-boissons   → getTaillesBoissons()
GET /api/sauces             → getSauces()
```
- Dépendances : CategorieRepository, ProduitRepository, SauceRepository, TailleBoissonRepository
- Pas de Service nécessaire (lecture directe, pas de logique métier)
- `getProduit($params)` : vérifie que `$params['id']` est numérique → `findById()` → 404 si null

### 5.2 `src/Controllers/AuthController.php`

Routes : inscription, connexion, déconnexion, profil
```
POST /api/auth/register     → register()
POST /api/auth/login        → login()
POST /api/auth/logout       → logout()
GET  /api/auth/me           → me()
```
- Dépendance : AuthService
- `register()` : lit body JSON → valide champs requis → `AuthService::register()`
- `login()` : lit body → `AuthService::login()` → démarre session
- `logout()` : `AuthService::logout()`
- `me()` : `AuthService::getClientConnecte()` → 401 si non connecté

### 5.3 `src/Controllers/PanierController.php`

Routes : gestion du panier par session
```
GET    /api/panier           → getPanier()
POST   /api/panier/ajouter   → ajouter()
DELETE /api/panier/ligne/{id} → supprimerLigne($params)
DELETE /api/panier            → vider()
```
- Dépendance : PanierService
- `session_start()` nécessaire pour récupérer le `session_id()`
- `ajouter()` : lit body JSON (`id_produit`, `quantite`, `details`) → `PanierService::ajouter()`
- `supprimerLigne($params)` : `$params['id']` → `PanierService::supprimerLigne()`

### 5.4 `src/Controllers/CommandeController.php`

Routes : passer et consulter des commandes
```
POST /api/commande           → passer()
GET  /api/commande/{numero}  → getByNumero($params)
```
- Dépendance : CommandeService
- `passer()` : lit body (`mode_paiement`, `type_commande`, `client_id`) → `CommandeService::creer()`
- `getByNumero($params)` : `CommandeService::getByNumero()` → 404 si null
- Catch `StockInsuffisantException` → Response::error() avec code 409

### 5.5 `src/Controllers/AdminController.php`

Routes : authentification admin + CRUD produits + liste commandes
```
POST   /api/admin/login          → login()
POST   /api/admin/logout         → logout()
GET    /api/admin/produits       → getProduits()
POST   /api/admin/produits       → createProduit()
PUT    /api/admin/produits/{id}  → updateProduit($params)
DELETE /api/admin/produits/{id}  → deleteProduit($params)
GET    /api/admin/commandes      → getCommandes()
```
- Dépendances : AdminRepository, ProduitRepository, CommandeService
- Vérification `$_SESSION['admin_id']` sur chaque route protégée → 401 si non connecté
- `login()` : `findByEmail()` → `password_verify()` → session admin
- CRUD produits : validation des champs, appels au ProduitRepository

---

## ÉTAPE 6 — Exceptions ✅

### 6.1 `src/Exceptions/StockInsuffisantException.php`

- Étend `\Exception`
- Stocke `produitId`, `stockDemande`, `stockDisponible`
- Message auto-généré : "Stock insuffisant pour le produit {id}. Demandé: X, disponible: Y"
- Lancée dans CommandeService quand le stock est insuffisant
- Attrapée dans CommandeController → Response::error() avec code 409

---

## ÉTAPE 7 — Tests PHPUnit ⏳ (à faire)

### Structure prévue

```
tests/
├── Entities/
│   └── ProduitTest.php          Tests unitaires sur Produit
├── Services/
│   ├── PanierServiceTest.php    Tests logique panier
│   └── CommandeServiceTest.php  Tests logique commande
└── Fixtures/
    └── ...                      Données de test
```

### Tests prioritaires

1. **ProduitTest** : validation prix > 0, stock >= 0, `estDisponible()` (RG-001)
2. **PanierServiceTest** : ajout produit, max 2 sauces (RG-002), supplément boisson (RG-003)
3. **CommandeServiceTest** : transaction, stock décrémenté (RG-008), points fidélité (RG-005)

---

## Règles de gestion — Où elles sont implémentées

| RG | Règle | Fichier(s) |
|----|-------|------------|
| RG-001 | Stock = 0 → indisponible | `Produit::estDisponible()`, `PanierService::ajouter()` |
| RG-002 | Max 2 sauces par menu | `PanierService::ajouter()` |
| RG-003 | Boisson 50cl = +0,50€ | `PanierService::ajouter()` (supplement_prix) |
| RG-004 | Chevalet entre 001 et 999 | `CommandeService::creer()` (random_int) |
| RG-005 | 1€ = 1 point fidélité | `Commande::calculerPointsFidelite()`, `CommandeService::creer()` |
| RG-006 | Panier détruit après commande | `CommandeService::creer()` (delete panier) |
| RG-007 | Commande après paiement uniquement | `CommandeService::creer()` (appelé par Controller après validation) |
| RG-008 | Stock dans transaction SQL | `CommandeService::creer()` (beginTransaction/commit/rollBack) |
| RG-009 | Client anonyme = pas de fidélité | `clientId` nullable partout, if ($clientId !== null) dans CommandeService |
| RG-010 | Historique conservé | Tables COMMANDE + COMMANDE_PRODUIT, jamais supprimées |

---

## Route Health Check (CI/CD)

```
GET /api/health → {"status": "ok"} HTTP 200
```

Définie directement dans `public/index.php`. Nécessaire pour le pipeline `deploy.yml` qui vérifie que l'app répond après déploiement.

---

## Points critiques pour l'examinateur

1. **Pourquoi PHP natif ?** → Pédagogie, comprendre les mécanismes (routing, PDO, MVC) sans abstraction
2. **Pourquoi Singleton PDO ?** → Une seule connexion partagée, performance, transaction globale possible
3. **Pourquoi EMULATE_PREPARES = false ?** → Vraies requêtes préparées côté serveur, sécurité injection SQL
4. **Pourquoi Repository pattern ?** → Séparation BDD / logique, testabilité (mocking)
5. **Pourquoi transaction SQL ?** → Atomicité : stock + commande + fidélité = tout ou rien
6. **Comment fonctionne le routing ?** → Regex `(?P<id>[^/]+)` + `preg_match()` → paramètres nommés
7. **Différence Controller / Service / Repository ?** → Orchestration / Logique / Données
8. **Pourquoi `\Throwable` ?** → Attrape exceptions ET erreurs fatales (TypeError, etc.)
9. **Pourquoi `toArray()` ?** → `json_encode()` ne voit pas les propriétés `private` → objet vide `{}`
10. **Risque `uniqid()` ?** → Collision possible en haute concurrence → amélioration : UUID ou séquence
