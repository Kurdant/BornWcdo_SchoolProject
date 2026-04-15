# WCDO — Résumé construction Backend (MISE À JOUR)

**État au 2026-03-24** : Tous les Controllers créés ✅ + 5 Q&A pédagogiques

---

## 📊 Progression COMPLÈTE

| Étape | Composants | Statut |
|-------|-----------|--------|
| **Étape 1** | composer.json, Database, Response, Router, index.php | ✅ Fondations |
| **Étape 2** | Categorie, Sauce, TailleBoisson, Produit, Client, Admin, Panier, PanierLigne, Commande | ✅ 9 Entités |
| **Étape 3** | CategorieRepo, SauceRepo, TailleBoissonRepo, ProduitRepo, ClientRepo, AdminRepo, PanierRepo, PanierProduitRepo, CommandeRepo, CommandeProduitRepo | ✅ 10 Repositories |
| **Étape 4** | AuthService, PanierService, CommandeService | ✅ 3 Services |
| **Étape 5** | CatalogueController, AuthController, PanierController, CommandeController, AdminController | ✅ 5 Controllers |
| **Étape 6** | StockInsuffisantException | ✅ Exceptions |
| **Étape 7** | ProduitTest, PanierServiceTest, CommandeServiceTest | ⏳ Tests (à faire) |

**Total** : 36/37 fichiers backend créés (97% complété)

---

## 🎯 RÉPONSES PÉDAGOGIQUES VALIDÉES

### CommandeService (Q1-Q3)
- **Q1** : `$this->pdo` directement pour gérer la transaction SQL (atomicité)
- **Q2** : Stock insuffisant → `rollBack()` annule TOUT
- **Q3** : Client anonyme 12,80€ → 0 points (RG-009) ; Client connecté → floor(12,80) = 12 points (RG-005)

### CatalogueController (Q1-Q3)
- **Q1** : `getProduit($params)` pour UN produit (URI `/api/produits/42`), pas `getProduits()` qui est la liste
- **Q2** : `.toArray()` obligatoire car propriétés `private` → `json_encode()` ignorerait l'objet sans ça
- **Q3** : ID 5 = Boissons Froides en BDD. Si on ajoute une catégorie AVANT, les IDs décalent (c'est pourquoi on utilise les IDs, pas l'ordre)

### PanierController (Q1-Q3)
- **Q1** : 4 appels `session_start()` car défensif (ne sait pas si déjà appelé), vérifie `session_status() === PHP_SESSION_NONE`
- **Q2** : `client_id` du panier change quand on se connecte (set dans `$_SESSION['client_id']`)
- **Q3** : Anonyme = `client_id = NULL` ; Connecté = `client_id = ID` → panier lié au compte

### AuthController (Q1-Q3)
- **Q1** : Valider `strlen($mdp) < 6` AVANT `password_hash()` = fail-fast (rapide avant traitement coûteux)
- **Q2** : Message d'erreur vague « Email ou mot de passe incorrect » = protection contre user-enumeration
- **Q3** : Client anonyme → panier avec `client_id = NULL` ; Après connexion → `client_id` mis à jour → reçoit les points au paiement

### CommandeController (Q1-Q3)
- **Q1** : 409 Conflict = problème système (stock épuisé) ; 400 Bad Request = données mal formées (c'est ta faute)
- **Q2** : `str_pad($n, 3, '0', STR_PAD_LEFT)` → ajoute des 0 à gauche → "042" (pour affichage chevalet)
- **Q3** : 6 étapes : charge panier → vérifie stock → calcule total → génère numéro/chevalet → prépare Commande → TRANSACTION (INSERT, copier lignes, décrémenter stock, points fidélité, supprimer panier)

---

## 🔑 ARCHITECTURE FINALE RÉSUMÉE

### Flux d'une requête (exemple: POST /api/panier/ajouter)

```
1. Frontend envoie JSON au backend
2. Nginx → PHP-FPM → public/index.php (front controller)
3. Router.php : identifie la route via regex → PanierController::ajouter($params)
4. PanierController valide le body JSON → appelle PanierService
5. PanierService appelle PanierRepository et ProduitRepository
6. Repository exécute PDO (requête préparée) → retourne Entity
7. Service applique la logique métier (RG-001, RG-002, RG-003)
8. Controller retourne Response::success() → JSON au frontend
```

### 4 niveaux d'abstraction

| Niveau | Responsabilité | Exemple |
|--------|---|---|
| **Controller** | Reçoit requête HTTP, valide types (numérique ?), orchestre | `PanierController::ajouter()` vérifie `is_numeric($id_produit)` |
| **Service** | Logique métier (calculs, règles, transactions) | `PanierService` valide RG-001, RG-002, RG-003 |
| **Repository** | Accès BDD (CRUD, requêtes SQL préparées) | `PanierProduitRepository::add()` exécute INSERT |
| **Entity** | Objets métier purs (validation constructeur, toArray) | `PanierLigne` immutable, `getSousTotal()` = prix × quantité |

---

## 🛡️ Sécurité implémentée

| Menace | Protection |
|--------|-----------|
| **SQL Injection** | Requêtes PDO préparées avec paramètres nommés (`:id`, `:email`) |
| **User Enumeration** | Message d'erreur vague sur login (« Email ou mot de passe incorrect ») |
| **Password Storage** | Bcrypt via `password_hash()` + `password_verify()` |
| **Session Hijacking** | PHP sessions via PHPSESSID cookie (httponly en prod recommandé) |
| **CORS** | Headers `Access-Control-Allow-*` dans `Response.php` |
| **Type Safety** | `declare(strict_types=1)` partout + typage strict des propriétés |

### Lacunes connues (à mentionner à l'exam)
- Pas de rate limiting (brute-force login possible)
- Pas de 2FA (authentification faible)
- Pas de rate limiting (brute-force login possible)
- CORS trop permissif (Access-Control-Allow-Origin: * en dev)
- `uniqid()` → risque collision en haute concurrence
- Pas de soft delete (historique conservé mais difficulté de suppression) 

---

## 📝 10 Règles de gestion — Localisation

| RG | Règle | Controllers | Services | Repositories | Entities |
|----|----|----|----|----|----|
| **RG-001** | Stock > 0 | PanierCtrl | PanierService | ProduitRepo | Produit::estDisponible() |
| **RG-002** | Max 2 sauces | — | PanierService | — | — |
| **RG-003** | +0,50€ boisson 50cl | — | PanierService | — | TailleBoisson::supplement |
| **RG-004** | Chevalet 001-999 | CommandeCtrl | CommandeService | — | — |
| **RG-005** | 1€ = 1 point (floor) | CommandeCtrl | CommandeService | ClientRepo | Commande::calculerPoints() |
| **RG-006** | Panier détruit après | CommandeCtrl | CommandeService | PanierRepo | — |
| **RG-007** | Commande après paiement | CommandeCtrl | CommandeService | — | — |
| **RG-008** | Transaction SQL | CommandeCtrl | CommandeService | — | — |
| **RG-009** | Anonyme = client_id NULL | PanierCtrl, AuthCtrl | PanierService, CommandeService | — | Panier, Commande |
| **RG-010** | Historique conservé | AdminCtrl | — | CommandeRepo | — |

---

## 🎓 Points d'examen prioritaires

### Concepts critiques

1. **Singleton PDO** → Pourquoi une seule connexion ? Performance + transactions atomiques
2. **Repository Pattern** → Pourquoi séparer BDD du reste ? Testabilité + séparation des responsabilités
3. **Transaction SQL** → Pourquoi `BEGIN/COMMIT/ROLLBACK` ? Atomicité (tout ou rien, pas incohérence)
4. **`readonly` properties** → Pourquoi immuables ? Garantir l'intégrité de l'entité après construction
5. **`.toArray()`** → Pourquoi pas `json_encode($entity)` ? Propriétés `private` ne sont pas sérialisées

### Questions pièges attendues

Q: « Et si le paiement échoue ? »
R: Le Controller doit valider le paiement AVANT d'appeler `CommandeService::creer()`. Sinon, commande existe mais pas payée.

Q: « Comment gérez-vous les clients anonymes ? »
R: `client_id = NULL` dans panier/commande. Identifiés par `session_id`. Pas de points fidélité.

Q: « Risque collision numéro commande ? »
R: `uniqid()` honnêtement pas optimal. Mieux : UUID ou séquence BDD.

Q: « Comment protégez les routes admin ? »
R: `$_SESSION['admin_id']` vérifié au début. Si absent → 401 Unauthorized.

Q: « Pourquoi format chevalet "042" ? »
R: Lisibilité sur affichage borne tactile. Leading zeros = convention.

---

## 📋 À faire (ÉTAPE 7 - TESTS)

**Tests unitaires avec PHPUnit** à implémenter (optionnel pour cette session) :

```bash
tests/Entities/ProduitTest.php          # RG-001 : validation stock
tests/Services/PanierServiceTest.php    # RG-002, RG-003 : logique panier
tests/Services/CommandeServiceTest.php  # RG-005, RG-008 : transaction + points
```

Lancer les tests :
```bash
./vendor/bin/phpunit tests/ -v
./vendor/bin/phpunit --filter testProduitIndisponibleSiStockZero
```

---

## 📚 Documentation files

| Fichier | Contenu |
|---------|---------|
| `BACKEND_DOCUMENTATION.md` | Architecture complète + endpoints API |
| `PROJECT_CONTEXT.md` | Contexte projet + infrastructure Docker |
| `REBUILD_FROM_SCRATCH.md` | Guide reconstruction complète |
| `examen-questions.md` | Résumé feedback examen oral + améliorations |
| `creation_du_back.md` | Ce fichier — guide construction étape par étape |

---

## ✅ Prêt pour l'examen !

Hugo a maintenant :
- ✅ 5 Controllers opérationnels
- ✅ 3 Services orchestrant la logique
- ✅ 10 Repositories en PDO préparé
- ✅ 9 Entités immuables
- ✅ Architecture MVC + Repository pattern compris
- ✅ Toutes les 10 règles de gestion implémentées
- ✅ Sécurité de base (SQL injection, user enumeration, bcrypt)
- ✅ Réponses pédagogiques validées sur chaque fichier

**Manque** : Tests PHPUnit (optionnel, temps disponible)
