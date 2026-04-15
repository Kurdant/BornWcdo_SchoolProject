# WCDO Back-Office — Front Admin

Dossier : `Front/admin/`  
Stack : HTML5 + CSS3 + JavaScript natif (vanilla) — tout inline dans chaque fichier HTML.  
Backend : `http://localhost/api/` (PHP natif, sessions PHP via cookie `PHPSESSID`).

---

## Pages et rôles

| Fichier            | Titre                        | Rôles autorisés                     | Description |
|--------------------|------------------------------|-------------------------------------|-------------|
| `login.html`       | Connexion                    | *(tous — page publique)*            | Formulaire de connexion, redirection automatique selon rôle. |
| `index.html`       | Dashboard                    | `administration`                    | Stats du jour (commandes, CA), tableau des 10 dernières commandes. |
| `produits.html`    | Gestion des produits         | `administration`                    | CRUD complet : liste, recherche, ajout, modification, suppression. |
| `menus.html`       | Gestion des menus            | `administration`                    | CRUD menus composés avec gestion des compositions (produits + quantités). |
| `utilisateurs.html`| Gestion des utilisateurs     | `administration`                    | CRUD comptes internes : nom, email, rôle, mot de passe. |
| `preparation.html` | Préparation des commandes    | `preparation`, `administration`     | Vue temps réel des commandes à préparer, auto-refresh 30s, son de notification. |
| `accueil-bo.html`  | Accueil / Comptoir           | `accueil`, `administration`         | Remise des commandes prêtes + saisie de commandes comptoir. |

---

## Rôles disponibles

| Rôle             | Accès |
|------------------|-------|
| `administration` | Toutes les pages |
| `preparation`    | `preparation.html` uniquement |
| `accueil`        | `accueil-bo.html` uniquement |

---

## Authentification

- Connexion via `POST /api/admin/login`
- La session PHP est maintenue par le cookie `PHPSESSID` (toutes les requêtes font `credentials: 'include'`)
- Après connexion, `admin_role`, `admin_nom`, `admin_id` sont stockés dans `sessionStorage`
- Toutes les pages protégées vérifient le rôle au chargement → redirection vers `login.html` si absent
- Déconnexion via `POST /api/admin/logout` + `sessionStorage.clear()`

---

## Endpoints API utilisés

```
POST   /api/admin/login
POST   /api/admin/logout

GET    /api/admin/commandes
POST   /api/admin/commandes
PUT    /api/admin/commandes/{id}/preparer
PUT    /api/admin/commandes/{id}/livrer

GET    /api/admin/produits
POST   /api/admin/produits
PUT    /api/admin/produits/{id}
DELETE /api/admin/produits/{id}

GET    /api/admin/menus
POST   /api/admin/menus
PUT    /api/admin/menus/{id}
DELETE /api/admin/menus/{id}

GET    /api/admin/utilisateurs
POST   /api/admin/utilisateurs
PUT    /api/admin/utilisateurs/{id}
DELETE /api/admin/utilisateurs/{id}

GET    /api/categories
GET    /api/produits
```

---

## Style

- Fond sombre `#1a1a2e`, sidebar `#16213e`, accent rouge `#e63946`
- Police : `'Segoe UI', Arial, sans-serif`
- Cards `border-radius: 8px`, `box-shadow: 0 2px 8px rgba(0,0,0,0.15)`
- Toasts auto-disparaissant (3s) en bas à droite
- Spinner pendant les appels API
