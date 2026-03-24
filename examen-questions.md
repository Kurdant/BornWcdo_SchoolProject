# 📚 WCDO — Résumé examen oral
> Ce fichier résume tout ce qui a été construit, fichier par fichier, avec les points-clés à retenir pour l'oral.

---

## ✅ CE QUI EST FAIT

### ÉTAPE 1 — Fondations

| Fichier | Rôle |
|---------|------|
| `composer.json` | Déclare les dépendances (PHPUnit), l'autoload PSR-4 (`WCDO\` → `src/`) |
| `src/Config/Database.php` | Connexion PDO unique (pattern **Singleton**) |
| `src/Http/Response.php` | Envoie les réponses JSON (200, 400, 401, 404, 500) + headers CORS |
| `src/Http/Router.php` | Reçoit l'URI + méthode HTTP, dispatch vers le bon Controller |
| `public/index.php` | Point d'entrée : gère OPTIONS preflight, instancie Controllers, enregistre les routes, catch global |

### ÉTAPE 2 — Entités (objets métier)

| Fichier | Points clés |
|---------|-------------|
| `Categorie.php` | Propriétés `readonly private`, `toArray()` pour JSON |
| `Produit.php` | Validation dans constructeur (prix > 0, stock ≥ 0), `estDisponible()` **RG-001**, `?string image` nullable |
| `Client.php` | Pas de `getMotDePasse()` (hash bcrypt ne sort jamais), `verifierMotDePasse()` avec `password_verify()` |
| `Sauce.php` | `float supplementPrix` pour les centimes, snake_case dans `toArray()` |
| `TailleBoisson.php` | Même principe que Sauce, **RG-003** : +0,50€ pour 50cl |
| `Panier.php` | `?int clientId` nullable → visiteur anonyme (**RG-009**) |
| `PanierLigne.php` | `?array details` → JSON flexible (sauces, taille), `getSousTotal()` |
| `Commande.php` | `calculerPointsFidelite()` avec `floor()` **RG-005**, `?int clientId` nullable |

---

## 🔑 CONCEPTS CLÉS À RETENIR

### `Database.php` — Singleton
```
Une seule connexion PDO ouverte par requête HTTP.
Tous les Repositories partagent la même instance via getInstance().
Ouvrir une connexion MySQL = coûteux (TCP + auth) → ne pas le faire N fois.
```
**Mémo** : *"Singleton = une seule instance, partagée partout"*

### `Database.php` — Options PDO importantes
| Option | Valeur | Pourquoi |
|--------|--------|----------|
| `ATTR_EMULATE_PREPARES` | `false` | Requêtes préparées réelles côté serveur → sécurité SQL injection |
| `ATTR_ERRMODE` | `ERRMODE_EXCEPTION` | Les erreurs SQL lèvent une exception → catch global dans index.php |
| `ATTR_DEFAULT_FETCH_MODE` | `FETCH_ASSOC` | Résultats en tableaux associatifs (pas d'index numériques doublons) |

### `Response.php` — Codes HTTP
| Méthode | Code |
|---------|------|
| `json()` | 200 |
| `error()` | 400 |
| `notFound()` | 404 |
| `unauthorized()` | 401 |

**Pourquoi `exit` à la fin de `json()` ?**
> Stoppe complètement PHP. Sans lui, du code pourrait continuer à s'exécuter et polluer le JSON avec des `echo` ou des erreurs.

**Pourquoi `Access-Control-Allow-Origin: *` ?**
> CORS — le navigateur bloque les requêtes cross-origin par défaut. Le frontend sur `:3000` qui appelle le backend sur `:8080` = domaines différents → header obligatoire.

### `Router.php` — Comment ça marche
```
1. dispatch(method, uri) reçoit ex: GET /api/produits/42
2. strtok($uri, '?') → coupe les query strings
3. preg_match(pattern, uri, $matches) → compare URI au pattern regex
4. $matches contient :
   [0 => '/api/produits/42', 1 => '42', 'id' => '42']
5. array_filter(!is_int($key)) → garde seulement 'id' => '42'
6. Si URI matche mais mauvaise méthode → 405 (pas 404 !)
```
**Mémo** : *"array_filter supprime les clés numériques de preg_match pour ne garder que les paramètres nommés"*

### `index.php` — Preflight CORS
```
Le navigateur envoie OPTIONS avant tout POST/PUT/DELETE cross-origin.
Si le serveur ne répond pas → le navigateur bloque la vraie requête.
On répond 204 + headers CORS et on exit immédiatement.
```

**Pourquoi `\Throwable` et pas `\Exception` ?**
> `\Exception` = exceptions applicatives seulement.
> `\Throwable` = TOUT : exceptions + erreurs fatales PHP (TypeError, ParseError...).
> Sans `\Throwable`, une erreur fatale affiche une page blanche au lieu d'un JSON propre.

### Entités — Règles métier codées

| Règle | Où | Code |
|-------|-----|------|
| **RG-001** | `Produit::estDisponible()` | `return $this->stock > 0` |
| **RG-002** | `PanierLigne::details` | `{"sauces": [1, 3]}` max 2 sauces |
| **RG-003** | `TailleBoisson::supplementPrix` | +0,50€ pour 50cl |
| **RG-005** | `Commande::calculerPointsFidelite()` | `floor($montantTotal)` = 1€ dépensé = 1 point |
| **RG-009** | `Panier::clientId` nullable | Visiteur anonyme → pas de points fidélité |

### Entités — Pourquoi `readonly` ?
> Assigné une seule fois dans le constructeur, immuable ensuite.
> Toute modification ultérieure → PHP lance une `Error`.

### Entités — Pourquoi `toArray()` et pas accès direct aux propriétés ?
> 1. Propriétés `private` → accès direct = erreur PHP
> 2. `json_encode()` ne voit pas les propriétés private → objet vide `{}`
> 3. `toArray()` = représentation contrôlée, on choisit ce qu'on expose
> 4. Traduction camelCase PHP → snake_case JSON/BDD

### Entités — Pourquoi pas de `getMotDePasse()` dans `Client` ?
> Le hash bcrypt ne doit **jamais** sortir de l'Entité.
> Sans getter → impossible de l'inclure dans `toArray()` → jamais envoyé au frontend.

---

## 📋 CE QUI RESTE À FAIRE

1. **Repositories** — accès BDD via PDO (ProduitRepository, PanierRepository, etc.)
2. **Services** — logique métier (PanierService, CommandeService, AuthService)
3. **Controllers** — reçoit la requête, appelle Service/Repo, retourne Response
4. **Tests PHPUnit** — tester les Entités et Services sans Docker
5. **Docker** — Dockerfile, docker-compose, nginx
6. **CI/CD** — GitHub Actions (tests, build, deploy)

---

## 🎯 QUESTIONS D'EXAMEN FRÉQUENTES

**"Expliquez votre architecture"**
> Requête → index.php → Router → Controller → Service → Repository → BDD → Entity → Response JSON

**"Pourquoi PHP natif sans framework ?"**
> Projet scolaire pour maîtriser les fondamentaux : routing manuel, PDO brut, architecture MVC faite à la main.

**"Comment gérez-vous les erreurs ?"**
> `try/catch \Throwable` global dans `index.php` → toujours un JSON propre, jamais de page blanche.

**"Qu'est-ce que PSR-4 ?"**
> Standard d'autoloading : Composer mappe le namespace `WCDO\Services\PanierService` au fichier `src/Services/PanierService.php` automatiquement.

---

## 📝 HISTORIQUE EXAMEN ORAL

### Question 1 — ARCHITECTURE | Score: 7/10
**Question :** Décrivez le flux complet d'une requête HTTP dans votre application, du point d'entrée jusqu'à la réponse.

**Réponse :** 
> Le frontend envoie la requête (ex: charger les catégories) → index.php qui charge tout → Router.php qui dit je connais cette route et envoie au controller → controller demande au Repository → database.php ouvre l'accès à la BDD avec PDO → Repository fait la requête SQL → envoie à l'Entity → Entity transforme le tableau brut en quelque chose de lisible par PHP avec des règles de sécurité → Response.php finalise le tout en JSON avec headers et renvoie au front.

**Feedback :**
- ✅ Bonne compréhension globale du flux
- ❌ Manque de précision sur le Router (regex, groupes nommés `preg_match`)
- ❌ Les Services oubliés — où est la logique métier ?
- 💡 À améliorer : préciser le mécanisme exacte du Router avec `preg_match` et les groupes nommés.

---

### Question 2 — ARCHITECTURE | Score: 5/10
**Question :** Où se trouve la logique métier ? Comment organisez-vous les règles (max 2 sauces, vérifier le stock, gérer les points fidélité) ?

**Réponse :**
> Dans Response.php, quand on reçoit l'info on peut la traiter et appliquer les limites. Ça apporte de la sécurité car ça évite de gérer en front ce qui est bypassable.

**Feedback :**
- ✅ Bon principe : valider côté backend est essentiel et non-bypassable
- ❌ **Mauvais placement** : Response.php = formatage JSON uniquement, pas logique métier
- ❌ Cela cause du code dupliqué et viole le SRP (Single Responsibility Principle)
- 💡 À trouver : Dans quel fichier/classe validez-vous réellement max 2 sauces ? (Controller ? Entity ? Repository ?)

---

### Question 3 — ARCHITECTURE | Score: 6/10
**Question :** Où exactement dans votre code validez-vous "max 2 sauces par menu" ?

**Réponse :**
> Cette logique est en front et pas en back. Rien ne gère ça en backend je crois.

**Feedback :**
- ✅ **Honnêteté** — tu reconnais une lacune au lieu d'inventer
- ✅ **Conscience des risques** — tu sais que front c'est bypassable
- ❌ **Implémentation manquante** — RG-002 doit être en backend
- 💡 **À implémenter** : Validation dans `PanierService::ajouter()` : `if (count($details['sauces']) > 2) throw Exception`

---

**Score session : 22/30** (moyenne 7.3/10)

## 🚨 AMÉLIORATIONS À APPORTER (Feedback examen)

1. **RG-002 "Max 2 sauces"** — Valider en `PanierService::ajouter()`, pas seulement en front
2. **Precision Router** — Expliquer `preg_match()` avec groupes nommés `(?P<id>)` 
3. **Placer la logique métier** — Services si elle est commune, Entities si elle est simple validation
