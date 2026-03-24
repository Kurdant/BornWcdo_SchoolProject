# 1. Introduction et vue d'ensemble

Ce document présente l'architecture et les choix techniques du projet WCDO (borne de commande McDonald's) afin de préparer un examen RNCP 37805 Niveau 5. Il est rédigé en français, avec un ton pédagogique et pratique.

- Description du projet WCDO (Borne de commande McDonald's)
  - WCDO est une application back-end PHP natif qui expose une API JSON destinée à une borne de commande. Elle gère le catalogue (catégories, produits, boissons, sauces), le panier, la validation et le passage de commande, ainsi que l'authentification client et admin.
  - Objectif pédagogique : fournir un backend simple, testable et compréhensible sans framework pour enseigner les bonnes pratiques (TDD, séparation des couches, PDO préparé).

- Stack technique complète
  - Langage : PHP 8.x (declare(strict_types=1) utilisé dans public/index.php)
  - Base de données : MariaDB 10.11 (script docker/mariadb/init.sql)
  - Conteneurisation : Docker (Dockerfile, docker-compose.yml)
  - Serveur web : Nginx (docker/nginx/nginx.conf) + PHP-FPM
  - Reverse proxy / TLS : Traefik (configuration via labels dans docker-compose.yml)
  - Autoloading : Composer (PSR-4, namespace WCDO\ mapped to src/)
  - Tests : PHPUnit (dev dependency)

- Philosophie : PHP natif sans framework, pourquoi ce choix
  - Pédagogie : comprendre les mécanismes internes (routing, controllers, services, PDO) sans abstraction d'un framework.
  - Contrôle : gestion fine des requêtes SQL, transactions, performances et sécurité.
  - Simplicité : KISS — réduire la surface d'apprentissage pour un rendu clair en contexte d'examen.
  - Testabilité : architecture en couches facilite les tests unitaires et l'injection de dépendances.

# 2. Architecture en couches

Schéma ASCII des couches :

HTTP (Front Controller public/index.php)
  ↓
Controller (src/Controllers/*Controller.php)
  ↓
Service (src/Services/*Service.php)
  ↓
Repository (src/Repositories/*Repository.php)
  ↓
Base de données (MariaDB - docker/mariadb/init.sql)

Flux complet d'une requête HTTP de bout en bout (exemple : GET /api/produits/12)
1. Le navigateur / client envoie une requête HTTP vers Nginx.
2. Nginx redirige vers PHP-FPM, point d'entrée public/index.php.
3. index.php charge l'autoloader Composer et initialise les controllers et le Router.
4. Le Router lit $_SERVER['REQUEST_METHOD'] et REQUEST_URI et identifie la route correspondante.
5. Le Router appelle le Controller concerné (CatalogueController::getProduit).
6. Le Controller délègue à un Service si nécessaire (ex : CatalogueService) ou appelle directement un Repository.
7. Le Repository utilise Config\Database::getConnection() (singleton PDO) pour exécuter des requêtes préparées et retourne des tableaux/entités.
8. Le Service applique la logique métier (vérification stock, calculs prix, points fidélité) et retourne un résultat.
9. Le Controller utilise Http\Response::json()/success() pour renvoyer la réponse JSON au client.
10. Nginx / Traefik renvoie la réponse HTTP au client.

# 3. Point d'entrée : public/index.php

- Rôle du front controller unique
  - public/index.php est le point d'entrée unique (Front Controller). Il orchestre le routing, l'instanciation des controllers et gère les exceptions globales.

- Chargement autoloader Composer
  - require_once __DIR__ . '/../vendor/autoload.php' : active le PSR-4 autoload pour les classes sous le namespace WCDO\\.

- Chargement variables d'environnement Docker
  - Le fichier index.php copie certaines variables d'environnement (DB_HOST, DB_NAME, DB_USER, DB_PASS) dans $_ENV si elles existent via getenv(). Cela permet à Config\Database d'utiliser $_ENV de façon fiable.

- Déclaration de toutes les routes
  - Les routes sont déclarées de façon explicite dans index.php via $router->get/post/put/delete(...). Exemple : $router->get('/api/produits/{id}', [$catalogue, 'getProduit']).

- Exception handler global
  - set_exception_handler(...) capture toute exception non interceptée et renvoie Response::error($e->getMessage(), 500) pour garantir une réponse JSON structurée.

# 4. Couche Http/

## Router.php

- Enregistrement des routes (get/post/put/delete)
  - La classe Router expose 4 méthodes publiques get(), post(), put(), delete() qui ajoutent la route au tableau interne $routes.

- Dispatch : lecture $_SERVER['REQUEST_METHOD'] et REQUEST_URI
  - dispatch() lit la méthode et l'URI (parse_url + rtrim) et cherche une route correspondante. Si aucune route, Response::notFound() est appelée.

- Gestion CORS préflight OPTIONS → 204
  - Si la méthode est OPTIONS, le Router renvoie immédiatement un 204 et les headers CORS (Access-Control-Allow-*).

- Méthode match() : explication de la regex nommée
  - La méthode match transforme une route contenant des placeholders de la forme {id} en expression régulière via :
    preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $routePath)
  - Cela produit des groupes nommés (?P<id>[^/]+) pour récupérer les paramètres directement dans $matches retourné par preg_match.
  - Ensuite le pattern est encadré (#^ ... $#) pour correspondre à l'URI complète. Si match, on conserve uniquement les clés de type chaîne (les noms des paramètres) via array_filter(..., 'is_string', ARRAY_FILTER_USE_KEY).

## Response.php

- json(), error(), notFound() — codes HTTP retournés
  - Response::json($data, $status) : envoie les headers (Content-Type, CORS, status) puis echo json_encode($data).
  - Response::success($data, $status) : enveloppe la réponse avec { success:true, data: ... }.
  - Response::error($message, $status) : enveloppe la réponse { success:false, error: ... } et utilise le code HTTP fourni (400 par défaut, 500 pour exceptions non prévues).
  - Response::notFound() : alias pour error() avec code 404.

# 5. Couche Config/ : Database Singleton PDO

- Pattern Singleton expliqué (pourquoi une seule connexion)
  - Database::getConnection() conserve une instance PDO statique privée. Cela évite d'ouvrir plusieurs connexions coûteuses et facilite le partage de la connexion entre repositories.
  - Avantage : réduction de la surcharge de connexion, gestion centralisée des options PDO.

- Options PDO : ERRMODE_EXCEPTION, FETCH_ASSOC, EMULATE_PREPARES=false — explication
  - PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION : permet d'obtenir des exceptions PDO au lieu d'erreurs silencieuses ou warnings. Facilite la gestion d'erreurs et le rollback.
  - PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC : les fetch() renvoient des tableaux associatifs (clés colonnes) — pratique pour mapping manuel vers les Entities.
  - PDO::ATTR_EMULATE_PREPARES => false : désactive l'émulation des requêtes préparées pour utiliser les vraies préparations côté serveur (meilleure sécurité contre injection et gestion correcte des types).

- Variables d'environnement Docker
  - Database::getConnection() lit DB_HOST, DB_NAME, DB_USER, DB_PASS depuis $_ENV ou getenv(). Dans le docker-compose.yml, ces variables sont fournies via .env et env_file.

- reset() pour les tests
  - Database::reset() remet l'instance PDO à null afin d'isoler les tests et forcer une nouvelle connexion contrôlée (utile pour les fixtures ou sandboxing).

# 6. Couche Controllers/

NOTE : les fichiers Controllers existent sous namespace WCDO\\Controllers et sont instanciés dans public/index.php.

Pour chaque controller (Admin, Auth, Catalogue, Commande, Panier) :

- CatalogueController
  - Rôle : exposer les endpoints publics du catalogue (catégories, produits, boissons, sauces, tailles de boissons).
  - Routes associées (déclarées dans index.php) :
    - GET /api/categories → getCategories()
    - GET /api/produits → getProduits()
    - GET /api/produits/{id} → getProduit($params)
    - GET /api/boissons → getBoissons()
    - GET /api/tailles-boissons → getTaillesBoissons()
    - GET /api/sauces → getSauces()
  - Méthodes publiques : elles appellent les repositories (ex : ProduitRepository::findAll, findById) et renvoient Response::success(data) ou Response::notFound().
  - Gestion des erreurs : 404 si ressource introuvable, 500 pour exception inattendue.
  - Exemple de réponse succès : { success: true, data: { id, nom, prix, stock, ... } }

- PanierController
  - Rôle : gestion du panier par session (lecture, ajout, suppression ligne, vider panier).
  - Routes :
    - GET /api/panier → getPanier()
    - POST /api/panier/ajouter → ajouter() (body JSON attendu)
    - DELETE /api/panier/ligne/{id} → supprimerLigne($params)
    - DELETE /api/panier → vider()
  - Méthodes publiques : valident le body (quantité > 0, id produit numeric), utilisent PanierService pour la logique.
  - Gestion des erreurs : 400 pour validation invalide, 404 si ligne non trouvée, 500 pour erreur serveur.
  - Exemple de body JSON pour POST /api/panier/ajouter :
    {
      "id_produit": 12,
      "quantite": 2,
      "details": { "taille": "50cl", "sauces": [1,3] }
    }

- CommandeController
  - Rôle : finaliser une commande et la récupérer par numéro.
  - Routes :
    - POST /api/commande → passer()
    - GET /api/commande/{numero} → getByNumero($params)
  - Méthodes publiques : passer() valide le panier, crée la COMMANDE et COMMANDE_PRODUIT, décrémente le stock via ProduitRepository ou CommandeService.
  - Gestion des erreurs : 400 pour panier vide ou validation, 409 en cas de stock insuffisant (idée à implémenter), 500 sinon.
  - Exemple de body JSON pour POST /api/commande :
    {
      "mode_paiement": "carte",
      "type_commande": "a_emporter",
      "client_id": 5
    }

- AuthController
  - Rôle : inscription, login, logout, récupération de l'utilisateur courant.
  - Routes :
    - POST /api/auth/register → register()
    - POST /api/auth/login → login()
    - POST /api/auth/logout → logout()
    - GET /api/auth/me → me()
  - Méthodes publiques : register() valide email, hash le mot de passe (password_hash), et sauvegarde via ClientRepository. login() vérifie via password_verify et démarre une session (session_start(), $_SESSION['client_id']).
  - Gestion des erreurs : 400 pour validation, 401 pour identifiants invalides, 500 pour erreur serveur.
  - Exemple de body JSON pour POST /api/auth/register :
    {
      "prenom": "Alice",
      "nom": "Durand",
      "email": "alice@mail.fr",
      "mot_de_passe": "secret123"
    }

- AdminController
  - Rôle : endpoints protégés pour la gestion des produits et consultation des commandes.
  - Routes :
    - POST /api/admin/login → login()
    - POST /api/admin/logout → logout()
    - GET /api/admin/produits → getProduits()
    - POST /api/admin/produits → createProduit()
    - PUT /api/admin/produits/{id} → updateProduit($params)
    - DELETE /api/admin/produits/{id} → deleteProduit($params)
    - GET /api/admin/commandes → getCommandes()
  - Méthodes publiques : gestion d'authentification admin (password_verify), contrôle d'accès (vérifier $_SESSION['admin_id']), retours 403 si non autorisé.
  - Exemples de body JSON pour créations/updates :
    {
      "nom": "Nouveau Produit",
      "description": "...",
      "prix": 2.50,
      "stock": 100,
      "id_categorie": 3
    }

# 7. Couche Services/

Les Services implémentent la logique métier et orchestrent plusieurs repositories si nécessaire.

- AuthService
  - Responsabilités : créer un client (hash du mot de passe), vérifier identifiants, gérer la session client ET admin.
  - Architecture : sépare Repository (accès BDD) de la logique métier (validation, hachage, session).
  - Méthodes principales :
    1. `register(prenom, nom, email, motDePasse)` : valide email unique → hash bcrypt → insère client → crée session.
    2. `login(email, motDePasse)` : cherche client → vérifie password_verify → crée session.
    3. `logout()` : détruit $_SESSION['client_id'].
    4. `getClientConnecte()` : retrouve le client depuis $_SESSION à chaque requête (fraîcheur données).
    5. `loginAdmin(email, motDePasse)` : idem pour admin (stocke $_SESSION['admin_id']).
  - Sécurité :
    - Bcrypt (PASSWORD_DEFAULT) : hachage lent volontairement (coût 10 = ~100ms) + sel unique = protection brute-force.
    - Message d'erreur vague ("Email ou mot de passe incorrect") : prévient user enumeration.
    - password_verify() : compare mot de passe saisi contre hash, impossible d'inverser le hash.
  - Session PHP :
    - `session_start()` : active les cookies HTTP PHPSESSID.
    - `$_SESSION['client_id']` : stocke l'ID du client connecté, persisté via cookie.
    - À chaque requête, le navigateur renvoie le cookie automatiquement → backend retrouve $_SESSION.
  - RG appliquées :
    - RG-009 : client anonyme = $_SESSION['client_id'] NULL ou inexistant = pas de fidélité.
    - RG-005 : points fidélité gérés dans CommandeService (pas ici).
  - Points critiques examinateur :
    - Pourquoi AuthService et pas garder logique dans Controller ? Séparation des responsabilités, testabilité, réutilisabilité.
    - Différence Bcrypt vs MD5 ? Bcrypt irréversible + lent + sel unique = impossible brute-force pratique.
    - Comment le backend sait quel client fait la requête suivante ? Via Cookie HTTP PHPSESSID → $_SESSION retrouvée → $_SESSION['client_id'] lue.

- CommandeService
  - Responsabilités : transformer un panier en commande, générer numero_commande, gérer stock, appliquer règles fidélité.
  - Flux détaillé :
    1. Charger le panier et ses lignes via PanierRepository/PanierProduitRepository.
    2. Valider que le panier n'est pas vide.
    3. Pour chaque ligne, vérifier stock → si insuffisant, error/409.
    4. Calculer montant_total (somme quantite * prix_unitaire + suppléments tailles).
    5. Générer numero_commande unique (actuellement uniqid() est possible, risque collision en haute concurrence).
    6. Insérer la ligne dans COMMANDE et COMMANDE_PRODUIT (préférer transaction SQL pour atomicité).
    7. Décrémenter les stocks des produits.
    8. Mettre à jour points fidélité client (ex : 1 point / 1€ dépensé).
  - Règles business :
    - Points fidélité : 1 point par euro TTC (arrondi à l'entier le plus proche).
    - Stock : décrémentation immédiate à la commande (pas de réservation différée).
    - Numéro commande : doit être unique (unique index idx_commande_numero dans init.sql).

- PanierService
  - Responsabilités : ajout/suppression de lignes, calcul total, mapping session->panier.
  - Flux métier détaillé :
    1. Récupérer/ouvrir une session (session_id) et charger ou créer un PANIER.
    2. Ajouter ligne : valider produit existant et stock suffisant, créer ou mettre à jour PANIER_PRODUIT.
    3. Calculer totaux et retourner représentation sérialisée.
  - Règles de validation : quantite > 0, id_produit numérique, details JSON bien formé.

# 8. Couche Repositories/

- Explication du pattern Repository
  - Le Repository encapsule l'accès à la base de données (CRUD). Il expose des méthodes claires (find, findAll, save, delete, findByX) et retourne des entités ou tableaux.
  - Avantage : séparation nette entre logique métier et accès BDD ; facilite le mocking pendant les tests.

- Liste type des repositories (exemples tirés du schéma et de la structure attendue)
  1. CategorieRepository : findAll(), find(int $id)
  2. ProduitRepository : findAll(), find(int $id), findByCategory(int $catId), updateStock(int $id, int $delta)
  3. SauceRepository : findAll()
  4. TailleBoissonRepository : findAll()
  5. PanierRepository : findBySession(string $sessionId), create(array $data), update(array $data)
  6. PanierProduitRepository : findByPanier(int $panierId), addLine(array $data), removeLine(int $id)
  7. CommandeRepository : create(array $data), findByNumero(string $numero), findAll()
  8. CommandeProduitRepository : createLines(int $commandeId, array $lines)
  9. ClientRepository : findByEmail(string $email), save(Client $client)
  10. AdminRepository : findByEmail(string $email)

- Exemple de requête PDO préparée
  - Exemple : récupération d'un produit par id

```php
$pdo = Database::getConnection();
$stmt = $pdo->prepare('SELECT * FROM PRODUIT WHERE id = :id');
$stmt->execute([':id' => $id]);
$data = $stmt->fetch();

// mapping manuel vers l'entité Produit
```

# 9. Couche Entities/

- Rôle des entités (objets métier, pas d'ORM)
  - Les entités représentent les objets métiers (Client, Produit, Commande, Panier...). Elles contiennent des propriétés typées et éventuellement quelques méthodes métiers simples (ex : vérification de mot de passe dans l'entité Client), mais n'accèdent jamais à la base de données.

- Liste type des entités (8 principales)
  1. Categorie { id: int, nom: string }
  2. Sauce { id: int, nom: string }
  3. TailleBoisson { id: int, nom: string, volume: int, supplement_prix: float }
  4. Admin { id: int, nom: string, email: string, mot_de_passe: string }
  5. Client { id: int, prenom: string, nom: string, email: string, mot_de_passe: string, points_fidelite: int, date_creation: DateTime }
  6. Produit { id: int, nom: string, description: string|null, prix: float, stock: int, id_categorie: int, image: string|null }
  7. Panier { id: int, session_id: string, date_creation: DateTime, updated_at: DateTime, client_id: ?int }
  8. Commande { id: int, numero_commande: string, numero_chevalet: int, type_commande: string, mode_paiement: string, montant_total: float, date_creation: DateTime, client_id: ?int }

- Exemple de méthode métier (ex: Client::verifierMotDePasse)

```php
public function verifierMotDePasse(string $motDePasse): bool
{
    return password_verify($motDePasse, $this->mot_de_passe);
}
```

Cette méthode encapsule la vérification sans exposer la logique de hachage partout.

# 10. Base de données

- 10 tables (colonnes, types, contraintes) — résumé issu de docker/mariadb/init.sql

1) CATEGORIE
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - nom VARCHAR(100) NOT NULL UNIQUE

2) SAUCE
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - nom VARCHAR(100) NOT NULL UNIQUE

3) TAILLE_BOISSON
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - nom VARCHAR(50) NOT NULL UNIQUE
 - volume INT NOT NULL CHECK(volume > 0)
 - supplement_prix DECIMAL(10,2) NOT NULL DEFAULT 0.00 CHECK(supplement_prix >= 0)

4) ADMIN
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - nom VARCHAR(100) NOT NULL
 - email VARCHAR(255) NOT NULL UNIQUE
 - mot_de_passe VARCHAR(255) NOT NULL

5) CLIENT
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - prenom, nom VARCHAR(100) NOT NULL
 - email VARCHAR(255) NOT NULL UNIQUE
 - mot_de_passe VARCHAR(255) NOT NULL
 - points_fidelite INT NOT NULL DEFAULT 0 CHECK(points_fidelite >= 0)
 - date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP

6) PRODUIT
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - nom VARCHAR(200) NOT NULL
 - description TEXT NULL
 - prix DECIMAL(10,2) NOT NULL CHECK(prix > 0)
 - stock INT NOT NULL DEFAULT 0 CHECK(stock >= 0)
 - id_categorie BIGINT NOT NULL FK -> CATEGORIE(id)
 - image VARCHAR(255) NULL
 - date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP

7) PANIER
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - session_id VARCHAR(255) NOT NULL
 - date_creation TIMESTAMP
 - updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 - client_id BIGINT NULL FK -> CLIENT(id) ON DELETE SET NULL

8) COMMANDE
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - numero_commande VARCHAR(20) NOT NULL UNIQUE
 - numero_chevalet INT NOT NULL CHECK(numero_chevalet BETWEEN 1 AND 999)
 - type_commande ENUM('sur_place','a_emporter') NOT NULL
 - mode_paiement ENUM('carte','especes') NOT NULL
 - montant_total DECIMAL(10,2) NOT NULL CHECK(montant_total > 0)
 - date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 - client_id BIGINT NULL FK -> CLIENT(id) ON DELETE SET NULL

9) PANIER_PRODUIT
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - id_panier BIGINT FK -> PANIER(id) ON DELETE CASCADE
 - id_produit BIGINT FK -> PRODUIT(id) ON DELETE RESTRICT
 - quantite INT NOT NULL CHECK(quantite > 0)
 - prix_unitaire DECIMAL(10,2) NOT NULL CHECK(prix_unitaire > 0)
 - details JSON NULL

10) COMMANDE_PRODUIT
 - id BIGINT AUTO_INCREMENT PRIMARY KEY
 - id_commande BIGINT FK -> COMMANDE(id) ON DELETE CASCADE
 - id_produit BIGINT FK -> PRODUIT(id) ON DELETE RESTRICT
 - quantite INT NOT NULL CHECK(quantite > 0)
 - prix_unitaire DECIMAL(10,2) NOT NULL CHECK(prix_unitaire > 0)
 - details JSON NULL

- Relations entre tables avec cardinalités
  - CATEGORIE 1 --- N PRODUIT
  - PRODUIT 1 --- N PANIER_PRODUIT (via id_produit)
  - PANIER 1 --- N PANIER_PRODUIT
  - COMMANDE 1 --- N COMMANDE_PRODUIT
  - CLIENT 1 --- N COMMANDE
  - CLIENT 1 --- N PANIER (optionnel)

- Explication de la 3NF (Troisième Forme Normale)
  - 1NF : chaque colonne contient des valeurs atomiques (pas de liste dans une colonne), ce qui est respecté (détails JSON est une exception maîtrisée pour extra options).
  - 2NF : toutes les colonnes non-clé dépendent entièrement de la clé primaire.
  - 3NF : pas de dépendance transitives entre colonnes non-clés — par exemple, les catégories sont dans une table séparée (CATEGORIE) et référencées par id_categorie.
  - Bénéfices : cohérence, suppression des anomalies d'insertion/mise à jour, facilité des contraintes FK.

- Seed data disponible
  - Le script init.sql contient des INSERT IGNORE pour catégories, sauces, tailles de boissons, admins, clients et de nombreux produits. Utile pour tests manuels et intégration.

# 11. Table complète des routes API

| Méthode | Route | Controller::méthode | Description | Body JSON (si POST/PUT) | Réponse succès |
|---|---:|---|---|---|---|
| GET | /api/categories | CatalogueController::getCategories | Liste des catégories | - | { success:true, data: [...] } |
| GET | /api/produits | CatalogueController::getProduits | Liste des produits | - | { success:true, data: [...] } |
| GET | /api/produits/{id} | CatalogueController::getProduit | Détail produit par id | - | { success:true, data: { ... } } |
| GET | /api/boissons | CatalogueController::getBoissons | Liste des boissons (filtrées) | - | { success:true, data: [...] } |
| GET | /api/tailles-boissons | CatalogueController::getTaillesBoissons | Tailles et suppléments | - | { success:true, data: [...] } |
| GET | /api/sauces | CatalogueController::getSauces | Liste des sauces | - | { success:true, data: [...] } |
| GET | /api/panier | PanierController::getPanier | Récupère le panier de la session | - | { success:true, data: { panier, total } } |
| POST | /api/panier/ajouter | PanierController::ajouter | Ajoute une ligne au panier | { "id_produit":int, "quantite":int, "details":object } | { success:true, data: { panier } } |
| DELETE | /api/panier/ligne/{id} | PanierController::supprimerLigne | Supprime une ligne de panier | - | { success:true } |
| DELETE | /api/panier | PanierController::vider | Vide le panier | - | { success:true } |
| POST | /api/commande | CommandeController::passer | Passe la commande à partir du panier | { "mode_paiement":"carte|especes", "type_commande":"sur_place|a_emporter", "client_id":int } | { success:true, data: { numero_commande, montant_total } } |
| GET | /api/commande/{numero} | CommandeController::getByNumero | Récupère une commande via son numéro | - | { success:true, data: { commande, lignes } } |
| POST | /api/auth/register | AuthController::register | Inscription client | { "prenom":"", "nom":"", "email":"", "mot_de_passe":"" } | { success:true, data: { client } } |
| POST | /api/auth/login | AuthController::login | Connexion client (session) | { "email":"", "mot_de_passe":"" } | { success:true, data: { client } } |
| POST | /api/auth/logout | AuthController::logout | Déconnexion client (session_destroy) | - | { success:true } |
| GET | /api/auth/me | AuthController::me | Récupère le client connecté via session | - | { success:true, data: { client } } |
| POST | /api/admin/login | AdminController::login | Connexion admin | { "email":"", "mot_de_passe":"" } | { success:true, data: { admin } } |
| POST | /api/admin/logout | AdminController::logout | Déconnexion admin | - | { success:true } |
| GET | /api/admin/produits | AdminController::getProduits | Liste produits (admin) | - | { success:true, data: [...] } |
| POST | /api/admin/produits | AdminController::createProduit | Création produit | { "nom":"","prix":float,"stock":int,"id_categorie":int } | { success:true, data: { produit } } |
| PUT | /api/admin/produits/{id} | AdminController::updateProduit | Mise à jour produit | { champs à mettre à jour } | { success:true, data: { produit } } |
| DELETE | /api/admin/produits/{id} | AdminController::deleteProduit | Suppression produit | - | { success:true } |
| GET | /api/admin/commandes | AdminController::getCommandes | Liste des commandes (admin) | - | { success:true, data: [...] } |

# 12. Sécurité

- Hachage mots de passe : password_hash() / password_verify() (bcrypt)
  - Les mots de passe sont hachés en base (init.sql contient des hashes bcrypt). En PHP utiliser password_hash($pwd, PASSWORD_BCRYPT) et password_verify() pour la vérification.

- Sessions PHP : session_start(), $_SESSION, pas de JWT (justification)
  - Le projet utilise des sessions PHP classiques (stockage côté serveur, identifiant de session côté client) pour simplifier l'authentification et la gestion de l'état.
  - Justification de ne pas utiliser JWT : simplicité pédagogique, éviter l'exposition d'informations encodées côté client, pas besoin d'auth stateless pour une borne locale.

- Protection injection SQL : PDO préparé (EMULATE_PREPARES=false)
  - Toutes les requêtes doivent utiliser prepare() et execute([]) avec liaison de paramètres pour éviter l'injection SQL.
  - EMULATE_PREPARES=false force l'utilisation des véritables préparations côté serveur.

- CORS headers : Access-Control-Allow-Origin (et sa limite actuelle *)
  - Response::headers() utilise l'origine du client si présente sinon '*' : header("Access-Control-Allow-Origin: {$origin}") → actuellement permissif. En production, limiter aux domaines autorisés.

- declare(strict_types=1) : protection contre conversions implicites
  - présent dans public/index.php ; force le typage strict pour réduire les erreurs silencieuses.

- Lecture body JSON : file_get_contents('php://input') et pourquoi pas $_POST
  - Pour des requêtes JSON, il faut lire php://input et json_decode plutôt que $_POST, qui contient uniquement les données encodées en application/x-www-form-urlencoded ou multipart/form-data.

# 13. Infrastructure Docker

- Architecture des services (dans docker-compose.yml)
  - wcdo-db (mariadb:10.11) : base de données, monté avec init.sql et healthcheck.
  - wcdo-php (PHP-FPM) : exécute PHP, construit depuis Dockerfile.
  - wcdo-nginx : sert le front statique et route vers PHP-FPM pour l'API.
  - wcdo-phpmyadmin : interface d'administration MySQL/MariaDB.

- Dockerfile : base php:8.2-fpm-alpine, extensions PDO, Composer --no-dev --optimize-autoloader
  - Installe pdo_mysql, copie Composer depuis l'image officielle, installe les dépendances composer en production.

- nginx.conf : dual vhost
  - Un vhost sert le Front statique (wakdo-front.acadenice.fr) depuis /app/Front.
  - L'autre vhost sert le backend (wakdo-back.acadenice.fr) depuis /app/public et redirige les requêtes PHP vers php:9000 (PHP-FPM).

- docker-compose.yml : depends_on avec condition service_healthy, réseaux internal + admin_proxy
  - Le service php dépend de db et attend qu'il soit healthy (healthcheck configuré).
  - Deux réseaux : internal (communication interne) et admin_proxy (externe pour Traefik).

- Traefik : rôle reverse proxy externe, SSL/TLS letsencrypt, routing par hostname
  - Les labels nginx dans docker-compose mappent des routers Traefik pour front et back et activent le certresolver letsencrypt.

- PHP-FPM : qu'est-ce que c'est, pourquoi séparé de Nginx
  - PHP-FPM est un gestionnaire de processus FastCGI qui exécute du code PHP en arrière-plan.
  - Nginx agit comme serveur HTTP et proxy vers PHP-FPM pour exécuter les scripts PHP. Séparer les responsabilités améliore la performance et la scalabilité.

# 14. Points forts et points faibles

Points forts (à valoriser à l'oral) :
- Architecture MVC propre sans framework — facilement compréhensible et testable.
- Pattern Repository testable : séparation logique accès BDD / logique métier.
- Docker production-ready avec Traefik pour TLS et routage par hostname.
- Schéma BDD normalisé (3NF) avec contraintes CHECK/UNIQUE/FK fortes.

Points faibles connus (à assumer honnêtement) :
- Front non connecté à l'API (bd.json local) — le front fourni peut ne pas consommer l'API dynamique.
- Pas de transactions SQL explicites dans CommandeService (risque d'incohérences si une étape échoue).
- Utilisation éventuelle de uniqid() pour numéro_commande présente un faible risque collision sous forte concurrence.
- CORS trop permissif (Access-Control-Allow-Origin: * ou echo de Origin) — à verrouiller en production.
- Pas de gestion de stock insuffisant bloquant atomiquement (il faudrait des verrous ou transactions avec vérification FOR UPDATE).
- Pas de rate limiting ni de protections contre brute-force pour login.

# 15. Glossaire technique

- PDO : PHP Data Objects — abstraction de la couche d'accès à la BDD.
- Singleton : pattern garantissant une seule instance (ex : Database::getConnection()).
- MVC : Modèle-Vue-Contrôleur (ici : Entities → Services/Repositories → Controllers → Router).
- Repository Pattern : encapsulation des opérations CRUD et mapping BD→Entity.
- PHP-FPM : FastCGI Process Manager pour exécuter PHP en arrière-plan.
- Nginx : serveur HTTP et reverse proxy qui distribue les requêtes vers PHP-FPM.
- Traefik : reverse proxy dynamique et gestionnaire TLS/Let’s Encrypt.
- Docker Compose : orchestration locale des services (db, php, nginx, phpmyadmin).
- Healthcheck : vérification de l'état d'un service (utilisé pour DB dans docker-compose.yml).
- bcrypt : algorithme de hachage par défaut via password_hash() en PHP.
- Session PHP : mécanisme serveur pour garder l'état utilisateur entre requêtes (session_start, $_SESSION).
- JWT : JSON Web Token — méthode d'auth stateless (expliqué pourquoi non utilisée ici).
- Autoloader PSR-4 : autoloading standard configuré via composer.json (namespace WCDO\\ -> src/).
- strict_types : directive declare(strict_types=1) pour typage strict.
- CORS : Cross-Origin Resource Sharing — header contrôlant l'accès cross-origin.
- 3NF : Troisième Forme Normale — règle de normalisation des bases de données.
- Front Controller : point d'entrée unique (public/index.php) qui centralise le routing.

---

Annexe rapide : commandes utiles
- Lancer les tests PHPUnit (dans le container PHP) : docker exec wcdo-php ./vendor/bin/phpunit tests/
- Forcer la réinitialisation de la connexion PDO (tests) : WCDO\Config\Database::reset();

Fin du document.
