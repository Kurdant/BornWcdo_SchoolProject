# WCDO — Back-Office : Documentation Complète

> **Bloc 2 — Développement Back-End**  
> Borne de commande WCDO — Interface d'administration interne  
> Stack : PHP 8.2 natif · MariaDB 10.11 · HTML/CSS/JS natif · Docker

---

## Sommaire

1. [Contexte et objectif](#1-contexte-et-objectif)
2. [Comptes et rôles](#2-comptes-et-rôles)
3. [Schéma de base de données](#3-schéma-de-base-de-données)
4. [Architecture back-end](#4-architecture-back-end)
5. [Endpoints API back-office](#5-endpoints-api-back-office)
6. [Workflow des commandes](#6-workflow-des-commandes)
7. [Pages front back-office](#7-pages-front-back-office)
8. [Sécurité](#8-sécurité)
9. [Ce qui reste à coder](#9-ce-qui-reste-à-coder)

---

## 1. Contexte et objectif

Le back-office WCDO est l'interface d'administration réservée aux équipes internes du restaurant. Il se distingue de la **borne client** (accès public) par :

- Un système d'authentification séparé (table `ADMIN` dédiée)
- Des rôles hiérarchiques contrôlant les accès
- Un workflow de gestion des commandes (en_attente → préparée → livrée)
- Un CRUD complet sur les produits, menus et utilisateurs internes

---

## 2. Comptes et rôles

### 3 rôles distincts

| Rôle | Description | Droits |
|------|-------------|--------|
| `administration` | Gestionnaire complet | Produits · Menus · Utilisateurs internes · Toutes les commandes |
| `preparation` | Responsable cuisine | Voir commandes `en_attente` · Marquer "préparée" |
| `accueil` | Équipier comptoir / call center | Saisir commandes · Voir commandes `preparee` · Marquer "livrée" |

### Comptes seed en base (mot de passe : `admin123`)

| Email | Nom | Rôle |
|-------|-----|------|
| `admin@wcdo.fr` | Administrateur | `administration` |
| `hugo@wcdo.fr` | Hugo Manager | `administration` |
| `prep@wcdo.fr` | Chef Cuisine | `preparation` |
| `accueil@wcdo.fr` | Hôtesse Accueil | `accueil` |

> **Hash bcrypt** : `$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`  
> Généré avec `password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12])`

---

## 3. Schéma de base de données

### Modifications apportées aux tables existantes

#### Table `ADMIN` — ajout colonne `role`
```sql
`role` ENUM('administration','preparation','accueil') NOT NULL DEFAULT 'administration'
```

#### Table `COMMANDE` — ajout colonnes `statut` et `heure_livraison`
```sql
`statut`          ENUM('en_attente','preparee','livree') NOT NULL DEFAULT 'en_attente'
`heure_livraison` TIMESTAMP NULL DEFAULT NULL
```
- `statut` : état courant dans le workflow
- `heure_livraison` : heure cible (pour trier les commandes dans la vue préparation)

### Nouvelles tables

#### Table `MENU`
```sql
CREATE TABLE `MENU` (
    `id`            BIGINT        NOT NULL AUTO_INCREMENT,
    `nom`           VARCHAR(200)  NOT NULL,
    `description`   TEXT          NULL,
    `prix`          DECIMAL(10,2) NOT NULL,          -- prix fixe du menu
    `image`         VARCHAR(255)  NULL,
    `disponible`    TINYINT(1)    NOT NULL DEFAULT 1, -- 1=actif, 0=désactivé
    `date_creation` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `chk_menu_prix` CHECK (`prix` > 0)
)
```

#### Table `MENU_PRODUIT` (table de jointure)
```sql
CREATE TABLE `MENU_PRODUIT` (
    `id_menu`    BIGINT NOT NULL,
    `id_produit` BIGINT NOT NULL,
    `quantite`   INT    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_menu`, `id_produit`),
    FOREIGN KEY (`id_menu`)    REFERENCES `MENU`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`id_produit`) REFERENCES `PRODUIT`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_mp_quantite` CHECK (`quantite` > 0)
)
```

### Schéma complet (tables concernées par le back-office)

```
ADMIN
 ├── id, nom, email, mot_de_passe
 └── role ENUM(administration|preparation|accueil)  ← NOUVEAU

MENU
 ├── id, nom, description, prix, image, disponible
 └── date_creation

MENU_PRODUIT (jointure)
 ├── id_menu  → MENU.id
 └── id_produit → PRODUIT.id + quantite

COMMANDE
 ├── id, numero_commande, numero_chevalet
 ├── type_commande, mode_paiement, montant_total
 ├── statut ENUM(en_attente|preparee|livree)        ← NOUVEAU
 ├── heure_livraison TIMESTAMP NULL                 ← NOUVEAU
 ├── date_creation
 └── client_id → CLIENT.id (nullable = anonyme)
```

### Données seed menus (5 menus d'exemple)

| id | Nom | Composition | Prix |
|----|-----|-------------|------|
| 1 | Menu Big Mac | Big Mac + Frite Moy. + Coca | 8,00 € |
| 2 | Menu Big Tasty | Big Tasty + Frite Moy. + Coca | 10,60 € |
| 3 | Menu MC Crispy | MC Crispy + Frite Moy. + Coca | 7,20 € |
| 4 | Menu Nuggets | Nuggets x4 + Frite Moy. + Coca | 7,50 € |
| 5 | Menu CBO | CBO + Frite Moy. + Coca | 10,90 € |

---

## 4. Architecture back-end

Pattern : **MVC + Repository + Service** (PHP natif, sans framework)

### Fichiers à créer / modifier

#### Entités (`src/Entities/`)

| Fichier | Action | Description |
|---------|--------|-------------|
| `Admin.php` | **Modifier** | Ajouter `role`, getter `getRole()`, méthode `hasRole(string ...$roles): bool` |
| `Commande.php` | **Modifier** | Ajouter `statut`, `heureLivraison`, constantes `STATUS_*`, getter |
| `Menu.php` | **Créer** | Entité Menu avec `id, nom, description, prix, image, disponible, produits[]` |

#### Repositories (`src/Repositories/`)

| Fichier | Action | Description |
|---------|--------|-------------|
| `AdminRepository.php` | **Modifier** | `findAll()`, `create()` avec role, `update()`, `delete()` |
| `ProduitRepository.php` | **Modifier** | Ajouter `update()` et `delete()` (manquants) |
| `CommandeRepository.php` | **Modifier** | `findByStatut()`, `updateStatut()`, `findOrderedByHeureLivraison()` |
| `MenuRepository.php` | **Créer** | `findAll()`, `findById()` (avec produits), `create()`, `update()`, `delete()` |

#### Services (`src/Services/`)

| Fichier | Action | Description |
|---------|--------|-------------|
| `CommandeAdminService.php` | **Créer** | `marquerPreparee(int $id)`, `marquerLivree(int $id)` — vérifie les transitions de statut |

#### Controllers (`src/Controllers/`)

| Fichier | Action | Description |
|---------|--------|-------------|
| `AdminController.php` | **Modifier** | Remplacer `verifierAdminConnecte()` par `verifierRole(string ...$roles)` |
| `AdminController.php` | **Modifier** | Implémenter `updateProduit()` et `deleteProduit()` |
| `AdminController.php` | **Modifier** | Ajouter CRUD menus, CRUD utilisateurs, gestion statuts commandes |

---

## 5. Endpoints API back-office

### Authentification

| Méthode | Route | Rôle requis | Description |
|---------|-------|-------------|-------------|
| `POST` | `/api/admin/login` | — | Connexion (retourne role dans session) |
| `POST` | `/api/admin/logout` | tout | Déconnexion |

### Produits

| Méthode | Route | Rôle requis | Description |
|---------|-------|-------------|-------------|
| `GET` | `/api/admin/produits` | `administration` | Liste tous les produits |
| `POST` | `/api/admin/produits` | `administration` | Créer un produit |
| `PUT` | `/api/admin/produits/{id}` | `administration` | Modifier un produit *(à implémenter)* |
| `DELETE` | `/api/admin/produits/{id}` | `administration` | Supprimer un produit *(à implémenter)* |

### Menus *(à créer)*

| Méthode | Route | Rôle requis | Description |
|---------|-------|-------------|-------------|
| `GET` | `/api/admin/menus` | `administration` | Liste tous les menus avec compositions |
| `POST` | `/api/admin/menus` | `administration` | Créer un menu |
| `PUT` | `/api/admin/menus/{id}` | `administration` | Modifier un menu |
| `DELETE` | `/api/admin/menus/{id}` | `administration` | Supprimer un menu |
| `GET` | `/api/menus` | — (public) | Liste menus dispos pour la borne client |

### Utilisateurs internes *(à créer)*

| Méthode | Route | Rôle requis | Description |
|---------|-------|-------------|-------------|
| `GET` | `/api/admin/utilisateurs` | `administration` | Liste des comptes internes |
| `POST` | `/api/admin/utilisateurs` | `administration` | Créer un compte (avec rôle) |
| `PUT` | `/api/admin/utilisateurs/{id}` | `administration` | Modifier (nom, email, rôle, mdp) |
| `DELETE` | `/api/admin/utilisateurs/{id}` | `administration` | Supprimer |

### Commandes — workflow *(à créer)*

| Méthode | Route | Rôle requis | Description |
|---------|-------|-------------|-------------|
| `GET` | `/api/admin/commandes` | `administration` | Toutes les commandes |
| `GET` | `/api/admin/commandes/preparation` | `preparation` | Commandes `en_attente` triées par heure_livraison |
| `PUT` | `/api/admin/commandes/{id}/preparer` | `preparation` | Passer `en_attente` → `preparee` |
| `PUT` | `/api/admin/commandes/{id}/livrer` | `accueil` | Passer `preparee` → `livree` |
| `POST` | `/api/admin/commandes` | `accueil` | Saisir une commande au comptoir |

---

## 6. Workflow des commandes

```
[Borne / Accueil]          [Cuisine]              [Accueil]
      │                        │                      │
      ▼                        ▼                      ▼
  POST /api/commande      GET .../preparation    GET .../livraison
  POST /api/admin/        PUT .../preparer       PUT .../livrer
  commandes               (role: preparation)    (role: accueil)
      │                        │                      │
      ▼                        ▼                      ▼
  statut: en_attente  →  statut: preparee  →  statut: livree
```

**Règles de transition :**
- `en_attente` → `preparee` : uniquement par rôle `preparation`
- `preparee` → `livree` : uniquement par rôle `accueil`
- Toute autre transition est rejetée (HTTP 422)

---

## 7. Pages front back-office

Emplacement : `Front/admin/`

| Fichier | Rôle cible | Fonctionnalités |
|---------|-----------|-----------------|
| `login.html` | tous | Formulaire email/mdp · Redirige selon rôle vers la bonne page |
| `index.html` | tous | Dashboard · Navigation conditionnelle selon rôle |
| `produits.html` | `administration` | Tableau produits · Formulaire création/édition · Suppression |
| `menus.html` | `administration` | Tableau menus · Composition (sélection de produits) · CRUD |
| `utilisateurs.html` | `administration` | Tableau comptes internes · CRUD avec sélection du rôle |
| `preparation.html` | `preparation` | Liste temps réel des commandes `en_attente` · Bouton "Marquer prête" |
| `accueil.html` | `accueil` | Formulaire saisie commande comptoir · Liste `preparee` · Bouton "Livrée" |

### Navigation selon rôle (après login)

```
role = administration → index.html (accès à tout)
role = preparation    → preparation.html (accès uniquement préparation)
role = accueil        → accueil.html (accès accueil + saisie)
```

---

## 8. Sécurité

| Menace | Protection actuelle | Évolution back-office |
|--------|--------------------|-----------------------|
| SQL Injection | PDO préparé partout | ✅ Maintenu |
| Authentification | `password_hash()` bcrypt | ✅ Maintenu |
| Autorisation | `verifierAdminConnecte()` | → `verifierRole('administration')` |
| User Enumeration | Message d'erreur vague | ✅ Maintenu |
| Session | `$_SESSION['admin_id']` | + `$_SESSION['admin_role']` |
| CORS | `Access-Control-Allow-Origin: *` en dev | ⚠️ À restreindre en prod |

**Principe clé :** chaque endpoint vérifie le rôle **en premier** avant toute logique métier.

```php
// Exemple dans AdminController
public function getMenus(): never {
    $this->verifierRole('administration');  // ← arrête si pas le bon rôle
    // ...
}

public function getCommandesPreparation(): never {
    $this->verifierRole('preparation', 'administration');  // les deux rôles peuvent
    // ...
}
```

---

## 9. Ce qui reste à coder

### Priorité 1 — PHP back-end

- [ ] `Admin.php` — ajouter `role` + `hasRole()`
- [ ] `Commande.php` — ajouter `statut` + `heureLivraison`
- [ ] `Menu.php` — nouvelle entité
- [ ] `AdminRepository.php` — `findAll()`, `update()`, `delete()`
- [ ] `ProduitRepository.php` — `update()`, `delete()`
- [ ] `CommandeRepository.php` — `findByStatut()`, `updateStatut()`
- [ ] `MenuRepository.php` — CRUD complet avec JOIN produits
- [ ] `CommandeAdminService.php` — transitions statuts
- [ ] `AdminController.php` — `verifierRole()`, implémenter update/delete produit, CRUD menus, CRUD users, workflow commandes
- [ ] `public/index.php` — ajouter toutes les nouvelles routes

### Priorité 2 — Front back-office

- [ ] `Front/admin/login.html`
- [ ] `Front/admin/index.html`
- [ ] `Front/admin/produits.html`
- [ ] `Front/admin/menus.html`
- [ ] `Front/admin/utilisateurs.html`
- [ ] `Front/admin/preparation.html`
- [ ] `Front/admin/accueil.html`

### Priorité 3 — Tests

- [ ] `tests/Entities/ProduitTest.php`
- [ ] `tests/Services/PanierServiceTest.php`
- [ ] `tests/Services/CommandeServiceTest.php`

---

## Fichier SQL de référence

```
dossier_pr_docker_etc/docker/mariadb/init.sql
```

Ce fichier est le **script DDL complet** utilisé par Docker au démarrage du container MariaDB. Il contient :
- Toutes les tables (y compris MENU, MENU_PRODUIT nouvellement ajoutées)
- Toutes les contraintes et index
- Les données seed (catégories, produits, sauces, comptes admin, menus)
