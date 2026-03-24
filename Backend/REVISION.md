# 📚 WCDO Backend — Fiche de révision

> Fichier mis à jour à chaque étape de construction.
> Pour chaque fichier : les questions posées + les bonnes réponses.

---

## ÉTAPE 1 — Fondations

---

### 📄 `composer.json`

**Q1. Si tu crées une classe `WCDO\Services\PanierService`, dans quel fichier PHP Composer va-t-il la chercher ?**

> `src/Services/PanierService.php`
> Parce que `composer.json` mappe `"WCDO\\" → "src/"`. Composer remplace le namespace par le chemin et ajoute `.php`.

---

**Q2. Quelle différence entre `require` et `require-dev` dans `composer.json` ?**

> - `require` : dépendances installées **partout** (prod + dev) — ex: `php >=8.2`
> - `require-dev` : dépendances installées **uniquement en développement** — ex: PHPUnit
> ⚠️ Ne pas confondre avec le `require` de PHP (inclusion de fichiers) — ce sont deux choses différentes.

---

**Q3. Après avoir modifié `composer.json`, quelle commande lancer ?**

> - `composer install` → installe toutes les dépendances (première fois ou après un clone)
> - `composer dump-autoload` → si on a seulement modifié la section autoloading
> - `composer update` → met à jour les versions (à ne pas confondre avec install)

---

### 📄 `src/Config/Database.php`

**Q1. Qu'est-ce qui empêche concrètement d'écrire `new Database()` depuis un Repository ?**

> Le constructeur est déclaré `private`. PHP interdit l'instanciation depuis l'extérieur de la classe.
> On passe obligatoirement par `Database::getInstance()`.

---

**Q2. Que se passerait-il si on ne mettait pas `ATTR_EMULATE_PREPARES => false` ?**

> PHP émulerait les requêtes préparées côté client : il construit lui-même la chaîne SQL finale avant de l'envoyer à MariaDB.
> Avec `false`, PHP envoie la requête ET les paramètres **séparément** à MariaDB, qui les traite côté serveur.
> **Différence de sécurité** : l'émulation peut laisser passer des injections SQL dans certains cas limites. Le "vrai" prepare côté serveur est réellement sûr car la structure SQL est figée avant l'injection des données.
> ⚠️ Mot clé à retenir : **"prepare réel côté serveur, pas simulé côté PHP"**

---

**Q3. Pourquoi `ATTR_ERRMODE => ERRMODE_EXCEPTION` est important avec notre architecture ?**

> Sans ça, les erreurs SQL retournent `false` silencieusement — difficile à détecter.
> Avec `ERRMODE_EXCEPTION`, PDO lève une `PDOException` que notre `index.php` (exception handler global) attrape automatiquement et retourne une réponse JSON d'erreur propre au client.
> Bonus : utile pour le debug ET pour la robustesse en production.

---

### 📄 `src/Http/Response.php`

**Q1. Pourquoi `Response::json()` appelle `exit` à la fin ?**

> `exit` arrête **totalement l'exécution de PHP** après avoir envoyé la réponse.
> Sans lui, le code continuerait à s'exécuter, d'autres `echo` ou erreurs pourraient s'afficher et **casser le JSON**.
> ⚠️ Mot clé : **"exit coupe net l'exécution — rien ne peut polluer la réponse après."**

---

**Q2. À quoi sert le header `Access-Control-Allow-Origin: *` ?**

> C'est le **CORS** (Cross-Origin Resource Sharing).
> Le navigateur bloque par défaut les requêtes vers un domaine différent.
> Notre frontend sur `localhost:3000` appelle le backend sur `localhost:8080` → domaines différents.
> Sans ce header, le navigateur refuse la requête. Avec `*`, n'importe quel domaine peut appeler l'API.

---

**Q3. Quel code HTTP retourne chaque méthode par défaut ?**

> - `Response::json($data)` → **200** (OK)
> - `Response::error("message")` → **400** (Bad Request)
> - `Response::notFound()` → **404** (Not Found)
> - `Response::unauthorized()` → **401** (Unauthorized)

---

### 📄 `src/Http/Router.php`

**Q1. `GET /api/produits/{id}` est enregistrée, on reçoit `DELETE /api/produits/42` — réponse du Router ?**

> **405 Method Not Allowed** — l'URI matche le pattern donc `$methodMatched = true`, mais la méthode reçue (`DELETE`) ne correspond pas à la méthode enregistrée (`GET`).
> Différence importante avec 404 : la ressource **existe**, c'est juste la méthode qui est interdite.

---

**Q2. Que contient `$matches` après `preg_match` ? Pourquoi fait-on `array_filter` ?**

> ```php
> $matches = [
>     0     => '/api/produits/42',  // clé INT : match complet
>     1     => '42',                // clé INT : 1er groupe capturé
>     'id'  => '42',                // clé STRING : groupe nommé (?P<id>...)
> ]
> ```
> On fait `array_filter(!is_int($key))` pour **supprimer les clés numériques** (0 et 1) et ne garder que les clés nommées (`'id' => '42'`).
> Sans ça, le Controller recevrait du bruit inutile dans ses paramètres.

---

**Q3. Pourquoi nettoyer l'URI avec `strtok($uri, '?')` ?**

> Sans ça, `/api/produits?sort=prix` ne matcherait **jamais** le pattern `/api/produits`.
> `strtok` coupe tout ce qui est après `?` — on ne compare que le chemin pur, sans les query strings.

---

### 📄 `public/index.php`

**Q1. Pourquoi le navigateur envoie-t-il `OPTIONS` avant un `POST` cross-origin ?**

> C'est le mécanisme **CORS preflight**. Le navigateur se méfie des requêtes cross-origin et envoie d'abord `OPTIONS` pour demander au serveur *"est-ce que tu m'autorises ?"*.
> Si le serveur ne répond pas correctement → le navigateur **bloque la vraie requête**, le frontend ne fonctionne pas.
> ⚠️ Mot clé : **"preflight CORS — vérification avant la vraie requête"**

---

**Q2. Pourquoi `\Throwable` plutôt que `\Exception` ?**

> - `\Exception` → attrape uniquement les exceptions applicatives "prévues"
> - `\Throwable` → attrape **tout** : exceptions ET erreurs PHP fatales (`TypeError`, `ParseError`, erreurs mémoire…)
> Sans `\Throwable`, une erreur PHP fatale afficherait une page blanche au lieu d'un JSON propre au client.

---

**Q3. Inconvénient de `new Controller()` à chaque route ? Amélioration ?**

> **Inconvénient** : PHP crée autant d'objets que de routes déclarées pour le même Controller, tous en mémoire, alors qu'un seul sera utilisé par requête.
> **Amélioration** :
> - Instance partagée : `$catalogue = new CatalogueController();` une fois, réutilisée sur toutes ses routes
> - Lazy loading : créer l'instance uniquement si la route matche (à l'intérieur du handler)

---


---

## ÉTAPE 2 — Entités

---

### 📄 `src/Entities/Categorie.php`

**Q1. Que garantit `readonly` ? Que se passe-t-il si on écrit `\\->id = 5\` depuis l'extérieur ?**

> `readonly` = la propriété ne peut être assignée **qu'une seule fois**, dans le constructeur.
> Toute tentative de modification ultérieure → PHP lance une **Error** : *"Cannot modify readonly property"*.
> De plus, la propriété est `private` → l'accès direct depuis l'extérieur est déjà interdit.
> ⚠️ Mot clé : **"assigné une fois dans le constructeur, immuable ensuite"**

---

**Q2. Pourquoi `toArray()` plutôt qu'accéder directement aux propriétés ?**

> 1. Les propriétés sont `private` → `\\->id\` depuis un Controller = **erreur PHP**
> 2. `json_encode()` ne peut pas sérialiser des propriétés `private` — il verrait un objet vide `{}`
> 3. `toArray()` donne une **représentation contrôlée** : on choisit exactement ce qui est exposé

---

**Q3. Quelle Entité de l'Étape 2 a une vraie logique métier ? Pour quelle règle ?**

> - **`Produit.php`** → méthode `estDisponible()` → **RG-001** : si stock = 0, retourne `false`
> - **`Client.php`** → méthode `verifierMotDePasse()` → compare le mot de passe saisi au hash bcrypt
> ⚠️ À retenir : l'examinateur peut demander *"Quelle logique métier est dans vos Entités ?"*

---

### 📄 `src/Entities/Sauce.php` & `src/Entities/TailleBoisson.php`

**Q1. Pourquoi deux classes séparées plutôt qu'une classe générique `Tag` ?**

> - **Sémantique métier** : une `Sauce` et une `Categorie` sont deux concepts différents — chacun peut évoluer indépendamment (ex: allergènes pour Sauce, icône pour Categorie)
> - **Typage strict** : PHP refuse de passer une `Categorie` là où une `Sauce` est attendue — la généricité supprimerait cette sécurité
> - **Lisibilité** : `PanierService::ajouterSauce(Sauce \\\)` est explicite, `Tag` ne l'est pas
> ⚠️ Règle : **"un concept métier = une classe"**

---

**Q2. Pourquoi `float` pour `supplementPrix` ? Pourquoi `supplement_prix` dans `toArray()` ?**

> - `float` → les montants monétaires ont des décimales (+0,50€). Un `int` ne peut pas représenter 0.50.
> - `supplement_prix` (snake_case) dans `toArray()` → **pont de convention** : PHP utilise camelCase (`supplementPrix`), la BDD et le JSON utilisent snake_case (`supplement_prix`).
> - `toArray()` traduit camelCase PHP → snake_case JSON/BDD pour la cohérence frontend/BDD.
> **RG-003** : boisson 50cl = +0,50€ via ce champ.

---

### `src/Entities/Produit.php` (fichier cle - sera teste PHPUnit)

**Q1. new Produit(prix: -5) - que se passe-t-il ?**

> InvalidArgumentException lancee dans le constructeur
> remonte au catch(\Throwable) dans index.php
> Response::error(500) - JSON propre au client garanti.

**Q2. Pourquoi estDisponible() dans l'Entite et pas ailleurs ?**

> 1. Encapsulation : l'Entite connait son propre etat (stock)
> 2. Regle centralisee : si la regle change, on modifie un seul endroit
> 3. Testabilite : PHPUnit sans BDD, sans Docker
> RG-001 : stock = 0 -> estDisponible() retourne false

**Q3. ?string image - que signifie le ? ?**

> Nullable : valeur peut etre string OU null.
> Un produit sans image uploadee aura image = null.
> json_encode serialise null en "image": null - le frontend gere.

---

### `src/Entities/Client.php`

**Q1. Pourquoi pas de getMotDePasse() ?**

> Le hash bcrypt ne doit JAMAIS sortir de l'Entite.
> Sans getter, impossible de l'inclure dans toArray() et donc dans le JSON.
> Securite : le hash ne transite jamais vers le frontend.

**Q2. Comment password_verify() sait quel algo utiliser ?**

> Le hash lui-meme contient l'information dans son prefixe :
> `2y` = bcrypt. password_verify() lit ce prefixe et applique le bon algo.
> Si l'algo change plus tard, les anciens hashs restent verifiables.

**Q3. RG-009 - client_id NULL - ou concretement ?**

> - `PANIER.client_id FK NULL` : panier d'un visiteur non connecte
> - `COMMANDE.client_id FK NULL` : commande passee sans compte
> Consequence : pas de client_id = pas de points fidelite (RG-009).

---

### `Panier.php` / `PanierLigne.php` / `Commande.php`

**Q1. A quoi sert `details` dans PanierLigne ?**

> Champ JSON flexible qui stocke les options variables par ligne :
> - Sauces choisies : `{"sauces": [1, 3]}` (max 2 - RG-002)
> - Taille boisson : `{"taille_id": 2, "supplement": 0.50}` (RG-003)
> Sans ce champ, il faudrait des tables supplementaires complexes.

**Q2. Pourquoi floor() et pas round() dans calculerPointsFidelite() ?**

> RG-005 : "1 euro DEPENSE = 1 point" - 1 euro complet uniquement.
> floor(12.70) = 12 (arrondi toujours vers le bas)
> round(12.70) = 13 (arrondi au plus proche - erreur metier !)
> floor() est intentionnel - avantage le commerce, pas le client.

**Q3. Les deux client_id NULL correspondent-ils a la meme regle ?**

> Oui, les deux = RG-009 : client anonyme = client_id NULL.
> - PANIER.client_id NULL : visiteur sans compte navigue et ajoute
> - COMMANDE.client_id NULL : commande passee sans connexion
> Consequence : CommandeService verifie NULL avant d'attribuer des points.

---

## ÉTAPE 3 — Repositories

---

### 📄 `src/Repositories/CategorieRepository.php`

**Q1. Pourquoi faire une boucle `foreach` au lieu d'utiliser directement le résultat de `fetchAll()` ?**

> `fetchAll()` retourne un tableau de tableaux associatifs : `[['id' => 1, 'nom' => 'Burgers'], ...]`
> Mais le Controller et le JSON ont besoin d'objets `Categorie`.
> Le `foreach` **transforme** chaque ligne en objet via le constructeur.
> Bénéfice : typage strict, `toArray()` pour sérialisation JSON, encapsulation.

**Q2. Pourquoi `findById()` retourne `?Categorie` (nullable) au lieu de lever une exception ?**

> Retourner `null` = la catégorie n'existe pas, c'est **normal**, pas une erreur.
> Le Controller reçoit `null` et gère proprement : `if ($cat === null) Response::notFound()`.
> Les exceptions doivent être réservées aux véritables erreurs (BDD crashée, etc).
> ⚠️ Nullable = gestion d'absence élégante, pas lazy exception handling.

**Q3. À quoi sert `lastInsertId()` dans la méthode `create()` ?**

> Après un `INSERT`, MariaDB génère automatiquement un `id`.
> `lastInsertId()` le récupère pour construire l'objet `Categorie` complet avec l'ID.
> Sans ça, on aurait `Categorie(id: ?, nom: 'Pizza')` ou il faudrait une requête `SELECT` supplémentaire.
> ⚠️ Note : `lastInsertId()` retourne STRING, d'où le cast `(int)`.

---

### 📄 `src/Repositories/SauceRepository.php`

**Structure identique à CategorieRepository — juste la table change.**

**Q1. Pourquoi faire un `SauceRepository` séparé au lieu d'une classe générique ?**

> **Sémantique métier** : une `Sauce` et une `Categorie` sont deux concepts différents.
> Chacun peut évoluer indépendamment (ex: allergènes pour Sauce, icône pour Categorie).
> **Typage strict** : PHP refuse de passer une `Sauce` là où une `Categorie` est attendue.
> **Lisibilité** : `PanierService::ajouterSauce(Sauce $sauce)` est explicite.
> Pour un petit projet, un `GenericRepository` suffirait, mais on préfère la clarté.

**Q2. Comment le Repository **empêche** l'injection SQL ?**

> Avec `prepare()` et les paramètres nommés `:id` :
> ```php
> $stmt = $this->pdo->prepare('SELECT id, nom FROM SAUCE WHERE id = :id');
> $stmt->execute(['id' => $id]);  // ← id et requête envoyés SÉPARÉMENT à MariaDB
> ```
> **Injection bloquée :** si `$id = "1 OR 1=1"`, la BDD la cherche LITTÉRALEMENT, pas comme SQL.
> ⚠️ Clé : **prepare réel côté serveur** (pas émulation PHP) grâce à `ATTR_EMULATE_PREPARES => false`.

**Q3. Si on oubliait `ORDER BY nom ASC`, qu'est-ce qui changerait ?**

> Les sauces arriveraient dans l'ordre **imprévisible** (ordre interne BDD).
> Ce n'est pas un problème technique, mais **UX dégradée** : le user voudrait les voir alphabétiques.
> `ORDER BY` = règle de présentation, pas de recherche/performance.

---

### 📄 `src/Repositories/ProduitRepository.php` (le plus complexe)

**Table :** `PRODUIT: id, nom, description, prix, stock, id_categorie FK, image, date_creation`

**Méthodes :**
- `findAll()` — tous les produits
- `findById(int $id)` — un produit avec détails
- `findByCategorie(int $categorieId)` — produits d'une catégorie
- `create(...)` — insérer un produit
- `updateStock(int $id, int $quantite)` — décrémente le stock (RG-008)

**Q1. Pourquoi extraire une méthode `hydrateProduit()` privée ?**

> **DRY** (Don't Repeat Yourself) — transformation objet.
> Au lieu de dupliquer `new Produit(id: ..., nom: ...)` dans `findAll()`, `findById()`, `findByCategorie()`,
> on la centralise dans une méthode privée appelée 3 fois.
> Bénéfice : si la structure Produit change, une seule modification.
> Définition : ligne 131, `private function hydrateProduit(array $row): Produit`

**Q2. `updateStock()` retourne `true/false`. Pourquoi ?**

> Pour que le Service sache si le décrement a **réellement fonctionné** :
> ```php
> if (!$produitRepo->updateStock($id, 1)) {
>     throw new StockInsuffisantException(...);
> }
> ```
> Sans le `return`, le Service recevrait `null` et ne pourrait pas vérifier l'erreur.
> ⚠️ Toujours retourner un booléen ou une exception, jamais `null` pour l'erreur.

**Q3. Où vois-tu la transaction pour RG-008 ?**

> **Pas ici !** Cette méthode est juste une requête SQL `UPDATE`.
> La **transaction** se fait dans `CommandeService` (prochaine étape) :
> ```php
> $pdo->beginTransaction();
> try {
>     $produitRepo->updateStock($id, 1);  // ← Décrémente
>     $commandeRepo->create($commande);   // ← Crée commande
>     $pdo->commit();  // ← Valide tout
> } catch (Exception) {
>     $pdo->rollback();  // ← Annule tout
> }
> ```
> **Atomicité** : tout réussit ou tout échoue. Pas de situation "commande sans stock décrémenté".

---

### 📄 `src/Repositories/ClientRepository.php`

**Table :** `CLIENT: id, prenom, nom, email UNIQUE, mot_de_passe (bcrypt), points_fidelite, date_creation`

**Méthodes :**
- `findById(int $id)` — chercher un client par ID
- `findByEmail(string $email)` — pour l'authentification
- `create(...)` — créer un nouveau compte
- `addFidelityPoints(int $id, int $points)` — ajouter des points (RG-005)

**Q1. Pourquoi `findByEmail()` au lieu de `findByEmailAndPassword()` ?**

> **Séparation sécurité/données :**
> - ❌ Repository ne compare **jamais** les mots de passe (dangereux)
> - ✅ Repository retourne juste le Client avec son hash
> - ✅ L'Entité `Client::verifierMotDePasse()` fait la vérification via `password_verify()`
> Jamais le mdp en clair ne quitte le processus de vérification.

**Q2. Propriété `motDePasseHash` sans getter. Comment le Service vérifie le mdp ?**

> 1. Service appelle : `$client = $repo->findByEmail($email)`
> 2. Repository retourne l'objet Client **avec son hash**
> 3. Service appelle : `$client->verifierMotDePasse($passwordSaisi)` (méthode Entité)
> 4. L'Entité fait : `password_verify($passwordSaisi, $this->motDePasseHash)` → true/false
> ⚠️ Le hash sort du Repository (il faut bien l'avoir), mais passe par l'Entité pour vérification.

**Q3. Qui appelle `addFidelityPoints()` et quand ?**

> **Le Service `CommandeService`** lors de la création d'une commande :
> ```php
> $points = floor($montantTotal);  // RG-005 : 1€ = 1 point
> $this->clientRepo->addFidelityPoints($client->id, $points);
> ```
> ⚠️ Toujours : client_id NULL (anonyme) = pas d'appel (RG-009).

---

**Q3. Où vois-tu la transaction pour RG-008 ?**

> **Pas ici !** Cette méthode est juste une requête SQL `UPDATE`.
> La **transaction** se fait dans `CommandeService` (prochaine étape) :
> ```php
> $pdo->beginTransaction();
> try {
>     $produitRepo->updateStock($id, 1);  // ← Décrémente
>     $commandeRepo->create($commande);   // ← Crée commande
>     $pdo->commit();  // ← Valide tout
> } catch (Exception) {
>     $pdo->rollback();  // ← Annule tout
> }
> ```
> **Atomicité** : tout réussit ou tout échoue. Pas de situation "commande sans stock décrémenté".

---

**Identique aux précédents mais avec colonnes supplémentaires : `volume` et `supplement_prix`.**

**Q1. Pourquoi caster en `(int)` pour volume et `(float)` pour supplementPrix ?**

> PDO retourne **tout en string** depuis la BDD. Pour faire des calculs (ajouter le prix, comparer volume),
> il faut convertir en types numériques :
> - `(int)$row['volume']` → 50 (nombre, pas "50" string)
> - `(float)$row['supplement_prix']` → 0.50 (nombre décimal, pas "0.50" string)
> Sans cast : PHP concatène les strings au lieu d'additionner.

**Q2. RG-003 dit "Boisson 50cl = +0,50€". Où voit-on cette logique dans le Repository ?**

> **Nulle part — et c'est normal !**
> Le Repository = **accès BDD** (aucune logique métier).
> La logique "ajouter le supplement" se fait dans le **Service**.
> Si on change la règle (50cl = +0.80€), on modifie la table BDD, pas le Repository.
> Avantage : Repository reste stable, logic métier centralisée dans Service.

**Q3. Si on créait une taille avec `supplementPrix: -0.50`, le Repository la refuse-t-il ?**

> Non, le Repository accepte **n'importe quoi** de la BDD (pas son rôle de valider).
> La validation devrait être dans l'**Entité** `TailleBoisson` :
> ```php
> if ($supplementPrix < 0) throw new InvalidArgumentException(...);
> ```
> Principe : une `TailleBoisson` ne peut jamais être invalide. Si on essaie d'en créer une cassée,
> elle refuse d'exister (exception au constructeur).

---

## ÉTAPE 4 — Services

---

### `src/Services/AuthService.php`

**Q1. Pourquoi le message d'erreur de `login()` est-il volontairement vague ?**

> Technique de sécurité : **user enumeration**.
> Si le message disait "email inconnu", un attaquant pourrait scanner des milliers d'emails pour savoir lesquels sont enregistrés.
> Message vague = zéro information utile pour l'attaquant, expérience identique pour l'utilisateur légitime.

---

**Q2. Que fait exactement `$_SESSION['client_id'] = $client->getId()` dans `register()` ?**

> 1. `clientRepo->create()` → fait le `INSERT` → MariaDB génère l'id (AUTO_INCREMENT)
> 2. `$client->getId()` → **lit** cet id déjà généré par la BDD
> 3. `$_SESSION['client_id'] = ...` → stocke cet id comme marqueur "ce client est connecté"
> ⚠️ PHP ne crée pas l'id — il le récupère depuis l'objet déjà construit par le Repository.

---

**Q3. Pourquoi `getClientConnecte()` fait un `findById()` à chaque requête au lieu de stocker l'objet Client en session ?**

> **Raison principale : fraîcheur des données.**
> Si l'objet était stocké en session et que la BDD changeait (ex: points fidélité mis à jour), la session renverrait des données périmées.
> `findById()` à chaque requête = toujours les données en temps réel depuis MariaDB.
> Raisons secondaires : sérialisation d'objets PHP fragile, session inutilement lourde.

---
