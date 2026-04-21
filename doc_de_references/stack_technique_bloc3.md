# 📄 Stack Technique — Projet Bloc 3 : Application RH Wacdo

> **Projet :** Application de gestion des Ressources Humaines — Wacdo  
> **Candidat :** Hugo Kurdant  
> **Formation :** RNCP Niveau 5 — Développeur Web et Web Mobile  
> **Organisme :** AcadéNice

---

## 🎯 Objectif du projet

Développer une **application web back-office** permettant à des administrateurs de gérer les collaborateurs, restaurants, postes et affectations de la chaîne Wacdo. Ce projet démontre la maîtrise d'un **framework back-end** (exigence du Bloc 3 de l'examen).

---

## 🛠️ Technologies retenues

### 1. Symfony 7 — Framework PHP

| | |
|---|---|
| **Rôle** | Framework principal côté serveur (back-end) |
| **Pourquoi ?** | Framework PHP le plus utilisé en France, architecture MVC structurée, composants intégrés (routing, formulaires, sécurité). Imposé par le sujet d'examen (framework back). |
| **Ce qu'il apporte** | Organisation du code en contrôleurs, entités et services. Gestion automatique des routes, des formulaires et de la sécurité. |

### 2. Twig — Moteur de templates

| | |
|---|---|
| **Rôle** | Génération des pages HTML côté serveur |
| **Pourquoi ?** | Moteur de templates natif de Symfony. Permet de coder les vues manuellement, ce qui démontre la maîtrise technique lors de l'examen. Syntaxe claire et sécurisée (échappement automatique). |
| **Ce qu'il apporte** | Héritage de templates (un layout commun réutilisé par toutes les pages), insertion dynamique de données PHP dans le HTML. |

### 3. Doctrine ORM — Gestion de la base de données

| | |
|---|---|
| **Rôle** | Mapping objet-relationnel (ORM) — gère les données sans SQL manuel |
| **Pourquoi ?** | Imposé par le sujet (gestion des entités avec un ORM). Standard dans l'écosystème Symfony. |
| **Ce qu'il apporte** | Les tables SQL sont créées automatiquement à partir de classes PHP. Les migrations gèrent l'évolution du schéma. Les requêtes complexes sont écrites en PHP (QueryBuilder) au lieu de SQL brut. |

### 4. Symfony Security — Authentification et autorisation

| | |
|---|---|
| **Rôle** | Gestion du login, des droits d'accès et de la sécurité |
| **Pourquoi ?** | Imposé par le sujet (authentification et autorisation des utilisateurs). Solution intégrée et reconnue comme sécurisée. |
| **Ce qu'il apporte** | Formulaire de connexion sécurisé, hachage des mots de passe (bcrypt), protection CSRF, restriction d'accès par rôle (seuls les administrateurs peuvent se connecter). |

### 5. MariaDB 10.11 — Base de données

| | |
|---|---|
| **Rôle** | Stockage des données (collaborateurs, restaurants, affectations, etc.) |
| **Pourquoi ?** | Déjà en place sur l'infrastructure Docker du projet. Base distincte créée pour le Bloc 3 (`wcdo_rh`). |
| **Ce qu'il apporte** | Base relationnelle fiable et performante, compatible MySQL, gérée via Doctrine. |

### 6. Docker — Conteneurisation

| | |
|---|---|
| **Rôle** | Environnement de développement et de déploiement |
| **Pourquoi ?** | Déjà utilisé pour les Blocs 1 et 2. Assure un environnement identique entre développement et production. |
| **Ce qu'il apporte** | Lancement de l'application en une commande (`docker compose up`). Services isolés : PHP, Nginx (serveur web), MariaDB, phpMyAdmin. |

### 7. Bootstrap 5 — Framework CSS

| | |
|---|---|
| **Rôle** | Mise en page et style de l'interface admin |
| **Pourquoi ?** | Permet un rendu professionnel rapide pour un back-office. Le focus du Bloc 3 est sur le PHP/Symfony, pas sur le CSS. |
| **Ce qu'il apporte** | Composants prêts à l'emploi (tableaux, formulaires, navigation, boutons), responsive design automatique. |

### 8. PHPUnit — Tests

| | |
|---|---|
| **Rôle** | Tests automatisés (unitaires, fonctionnels, sécurité) |
| **Pourquoi ?** | Imposé par le sujet. Framework de tests standard en PHP, intégré nativement dans Symfony. |
| **Ce qu'il apporte** | Vérification automatique que les fonctionnalités marchent correctement, tests des routes, des formulaires et de la sécurité d'accès. |

---

## 📊 Résumé visuel

```
┌─────────────────────────────────────────────┐
│              NAVIGATEUR (Admin)              │
└──────────────────┬──────────────────────────┘
                   │ HTTP
┌──────────────────▼──────────────────────────┐
│              Nginx (serveur web)             │
│              Port 8090                       │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│         Symfony 7 (PHP 8.2)                  │
│  ┌────────────┐ ┌──────────┐ ┌───────────┐  │
│  │ Controllers│ │ Security │ │   Forms   │  │
│  └─────┬──────┘ └──────────┘ └───────────┘  │
│        │                                     │
│  ┌─────▼──────┐              ┌───────────┐  │
│  │  Doctrine   │◄────────────│   Twig    │  │
│  │   (ORM)     │  données    │ (vues)    │  │
│  └─────┬──────┘              └───────────┘  │
└────────┼────────────────────────────────────┘
         │ SQL
┌────────▼────────────────────────────────────┐
│        MariaDB 10.11                         │
│        Base : wcdo_rh                        │
└─────────────────────────────────────────────┘
```

---

## ✅ Justification globale

Cette stack est **100% conforme aux exigences du sujet d'examen** :

- ✅ **Framework back** → Symfony 7
- ✅ **ORM** → Doctrine
- ✅ **Moteur de templates** → Twig
- ✅ **Authentification** → Symfony Security
- ✅ **Tests** → PHPUnit
- ✅ **Base de données relationnelle** → MariaDB
- ✅ **Conteneurisation** → Docker

Toutes ces technologies sont des **standards de l'industrie**, largement utilisées en entreprise et reconnues par la communauté PHP française.
