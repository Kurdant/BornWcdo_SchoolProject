# 📦 Contexte complet pour implémenter le Bloc 3 — App RH Wacdo (Symfony 7)

> Ce document contient **tout le contexte nécessaire** pour compléter le plan d'architecture `plan_bloc3.md`.  
> Il inclut : l'état actuel du projet, les fichiers existants, les configs Docker, les credentials BDD, et le cahier des charges complet.

---

## 1. État actuel du projet Symfony

Le squelette Symfony existe déjà dans `Bloc3/symfony/`. Voici ce qui est en place :

### Fichiers existants

```
Bloc3/symfony/
├── .editorconfig
├── .env                    ← APP_ENV=dev, APP_SECRET= (vide)
├── .env.dev
├── .gitignore
├── bin/
│   └── console
├── composer.json           ← Symfony 7.4.*, PHP >=8.2 (squelette minimal)
├── composer.lock
├── config/
│   ├── bundles.php
│   ├── packages/
│   │   ├── cache.yaml
│   │   ├── framework.yaml
│   │   └── routing.yaml
│   ├── preload.php
│   ├── reference.php
│   ├── routes/
│   ├── routes.yaml
│   └── services.yaml
├── public/
│   └── index.php
├── src/
│   ├── Controller/         ← VIDE (aucun controller créé)
│   └── Kernel.php
├── symfony.lock
├── var/
└── vendor/
```

### composer.json actuel (dépendances minimales)

```json
{
    "require": {
        "php": ">=8.2",
        "ext-ctype": "*",
        "ext-iconv": "*",
        "symfony/console": "7.4.*",
        "symfony/dotenv": "7.4.*",
        "symfony/flex": "^2",
        "symfony/framework-bundle": "7.4.*",
        "symfony/runtime": "7.4.*",
        "symfony/yaml": "7.4.*"
    },
    "require-dev": {}
}
```

**⚠️ Il manque toutes les dépendances métier :**
- `symfony/orm-pack` (Doctrine ORM + DBAL + Migrations)
- `symfony/twig-pack` (Twig)
- `symfony/security-bundle` (Auth)
- `symfony/form` (Formulaires)
- `symfony/validator` (Validation)
- `symfony/asset` (Assets dans Twig)
- `symfony/maker-bundle` (dev — générateur)
- `doctrine/doctrine-fixtures-bundle` (dev — données de démo)
- `symfony/test-pack` (dev — PHPUnit)
- `symfony/profiler-pack` (dev — debug toolbar)
- `symfony/debug-bundle` (dev — erreurs détaillées)

### .env Symfony actuel

```env
APP_ENV=dev
APP_SECRET=
APP_SHARE_DIR=var/share
DEFAULT_URI=http://localhost
```

**⚠️ `APP_SECRET` est vide — il faut le remplir.**  
**⚠️ `DATABASE_URL` n'est pas encore configuré.**

---

## 2. Infrastructure Docker existante

### docker-compose.dev.yml (chemin : `dossier_pr_docker_etc/docker-compose.dev.yml`)

```yaml
services:
  db:
    image: mariadb:10.11
    container_name: wcdo-db
    env_file: .env.dev
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: ${MYSQL_DATABASE}
      MYSQL_USER: ${MYSQL_USER}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
      - ./docker/mariadb/init.sql:/docker-entrypoint-initdb.d/init.sql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
    networks:
      - wcdo-dev

  php:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: wcdo-php
    volumes:
      - ..:/app
    working_dir: /app/Backend
    depends_on:
      db: { condition: service_healthy }
    env_file: .env.dev
    networks:
      - wcdo-dev

  nginx:
    image: nginx:alpine
    container_name: wcdo-nginx
    volumes:
      - ..:/app
      - ./docker/nginx/nginx.dev.conf:/etc/nginx/conf.d/default.conf
    ports:
      - "8080:80"   # Frontend Bloc 1
      - "8081:81"   # API Backend Bloc 2
    depends_on:
      - php
    networks:
      - wcdo-dev

  phpmyadmin:
    image: phpmyadmin:latest
    container_name: wcdo-phpmyadmin
    environment:
      PMA_HOST: db
      PMA_USER: ${MYSQL_USER:-wcdo_user}
      PMA_PASSWORD: ${MYSQL_PASSWORD:-wcdo_pass}
    ports:
      - "8082:80"   # phpMyAdmin
    depends_on:
      db: { condition: service_healthy }
    env_file: .env.dev
    networks:
      - wcdo-dev

volumes:
  db_data:

networks:
  wcdo-dev:
    driver: bridge
```

### Credentials BDD (.env.dev)

```env
MYSQL_ROOT_PASSWORD=root
MYSQL_DATABASE=wcdo
MYSQL_USER=wcdo_user
MYSQL_PASSWORD=wcdo_pass
DB_HOST=db
DB_NAME=wcdo
DB_USER=wcdo_user
DB_PASS=wcdo_pass
```

### Dockerfile PHP existant (`dossier_pr_docker_etc/Dockerfile`)

```dockerfile
FROM php:8.2-fpm-alpine
WORKDIR /app
RUN apk add --no-cache git curl \
    && docker-php-ext-install pdo pdo_mysql
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN chmod +x /usr/bin/composer
EXPOSE 9000
CMD ["php-fpm"]
```

### Dossier docker/symfony/ — EXISTE MAIS VIDE

Le dossier `dossier_pr_docker_etc/docker/symfony/` existe déjà mais est vide. Il faut y créer :
- `Dockerfile` (PHP-FPM pour Symfony avec extensions intl, zip, opcache)
- `nginx.conf` (config Nginx pour Symfony front controller)

### Ports utilisés

| Port | Service | Usage |
|------|---------|-------|
| 8080 | wcdo-nginx | Frontend Bloc 1 (HTML/CSS/JS) |
| 8081 | wcdo-nginx | API Backend Bloc 2 (PHP natif) |
| 8082 | wcdo-phpmyadmin | phpMyAdmin |
| **8090** | **wcdo-symfony-nginx** | **App RH Bloc 3 (Symfony) — À CRÉER** |

---

## 3. Ce qu'il faut ajouter au Docker

### 3.1 Nouveau Dockerfile : `dossier_pr_docker_etc/docker/symfony/Dockerfile`

```dockerfile
FROM php:8.2-fpm-alpine

WORKDIR /app

RUN apk add --no-cache \
    git curl unzip icu-dev libzip-dev \
    && docker-php-ext-install \
        pdo pdo_mysql intl zip opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN chmod +x /usr/bin/composer

EXPOSE 9000
CMD ["php-fpm"]
```

### 3.2 Nouvelle config Nginx : `dossier_pr_docker_etc/docker/symfony/nginx.conf`

```nginx
server {
    listen 80;
    server_name _;
    root /app/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass symfony-php:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 3.3 Deux services à ajouter dans docker-compose.dev.yml

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
      - "8090:80"
    depends_on:
      - symfony-php
    networks:
      - wcdo-dev
```

### 3.4 SQL à ajouter à la fin de `docker/mariadb/init.sql`

```sql
-- ========================================
-- Base de données Bloc 3 — App RH Symfony
-- ========================================
CREATE DATABASE IF NOT EXISTS `wcdo_rh`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `wcdo_rh`.* TO 'wcdo_user'@'%';
FLUSH PRIVILEGES;
```

### 3.5 DATABASE_URL pour Symfony

À mettre dans `Bloc3/symfony/.env.local` (ou `.env`) :

```env
DATABASE_URL="mysql://wcdo_user:wcdo_pass@db:3306/wcdo_rh?serverVersion=mariadb-10.11.0&charset=utf8mb4"
APP_SECRET="a-random-secret-key-here-32chars"
```

---

## 4. Cahier des charges — Sujet 1 : Framework Back (copie intégrale)

### Objectif
Wacdo souhaite disposer d'une application pour gérer les affectations des collaborateurs dans les différents restaurants.

Il s'agit de gérer : les collaborateurs, les restaurants, les fonctions, les affectations.

L'application est utilisée uniquement par des collaborateurs en ayant les autorisations (au niveau de la table des collaborateurs, on gère une information pour savoir s'il a le droit d'utiliser l'application, et si oui un mot de passe pour se connecter).

### Technologies et Outils imposés
- **Framework** : Framework back (Symfony)
- **Langage** : PHP
- **Moteur de template** : Twig
- **Base de données** : SQL (MariaDB)
- **ORM** : Doctrine
- **Sécurité** : Authentification et autorisation des utilisateurs

### Modèle de données (4 entités)

**Collaborateur :**
- nom (string, NOT NULL)
- prénom (string, NOT NULL)
- email (string, NOT NULL, UNIQUE)
- date de première embauche (date, NOT NULL)
- administrateur (boolean, NOT NULL, default false)
- mot de passe (string, NULLABLE — rempli uniquement si administrateur=true)

**Restaurant :**
- nom (string, NOT NULL)
- adresse (string, NOT NULL)
- code postal (string, NOT NULL, regex: 5 chiffres)
- ville (string, NOT NULL)

**Fonction (les postes chez Wacdo) :**
- intitulé du poste (string, NOT NULL, UNIQUE)

**Affectation :**
- collaborateur (ManyToOne → Collaborateur)
- restaurant (ManyToOne → Restaurant)
- poste/fonction (ManyToOne → Fonction)
- date début (date, NOT NULL)
- date fin (date, NULLABLE — NULL = affectation active/en cours)

### Fonctionnalités exigées

**Authentification :**
- Seuls les Collaborateur avec `administrateur=true` et un mot de passe peuvent se connecter
- Login par email + mot de passe

**Menu principal :** 4 sections
1. Gestion des restaurants
2. Gestion des collaborateurs
3. Gestion des fonctions
4. Recherche des affectations

**Gestion des restaurants :**
- Liste avec filtres (nom, code postal, ville)
- Bouton créer un restaurant
- Éléments cliquables → détail du restaurant avec liste des collaborateurs **en poste actuel** (affectation sans date_fin), filtrable par poste, nom, date de début
- Bouton "modifier" → historique de TOUTES les affectations (filtrable) + formulaire pour affecter un nouveau collaborateur

**Gestion des collaborateurs :**
- Liste avec filtres (nom, prénom, email)
- Bouton créer un collaborateur
- Bouton rechercher les collaborateurs **non affectés**
- Éléments cliquables → détail avec affectations en cours + historique, filtrable par poste et date de début
- Bouton modifier le collaborateur + affecter à un nouveau poste
- Affectations en cours **modifiables**

**Gestion des fonctions :**
- Liste des fonctions
- Bouton créer une fonction
- Chaque fonction éditable (modifier l'intitulé)

**Recherche des affectations :**
- Liste avec filtres (poste, date début, date fin, ville)

**Validation :**
- Les champs de formulaires doivent être vérifiés
- Une étape ne peut pas être finalisée s'il manque des informations

### Tests exigés
- Tests d'interface utilisateur
- Tests fonctionnels
- Tests de sécurité

### Livrables
- Application fonctionnelle et déployée sur un serveur
- Argumentation du fonctionnement du framework, spécificités, architecture, choix des dépendances
- Le jury demandera des modifications ou ajouts de code en live

---

## 5. Stack technique choisie

| Brique | Choix | Justification |
|--------|-------|---------------|
| Framework | **Symfony 7.4** | Imposé par le sujet, le plus utilisé en France |
| Templates | **Twig** | Natif Symfony, pédagogique (pas EasyAdmin — on code les vues) |
| ORM | **Doctrine** | Imposé, mapping objet-relationnel |
| BDD | **MariaDB 10.11** | Déjà en place dans Docker |
| Auth | **Symfony Security** | Firewall + form_login + bcrypt |
| CSS | **Bootstrap 5 (CDN)** | Rendu rapide pour back-office |
| Tests | **PHPUnit + WebTestCase** | Standard Symfony |
| Fixtures | **doctrine/doctrine-fixtures-bundle** | Données de démo |

---

## 6. Données de démo (Fixtures)

### Fonctions (5)
1. Équipier polyvalent
2. Caissier
3. Manager
4. Préparateur
5. Directeur de restaurant

### Restaurants (3)
| Nom | Adresse | CP | Ville |
|-----|---------|-----|-------|
| Wacdo Nice Centre | 12 avenue Jean Médecin | 06000 | Nice |
| Wacdo Marseille Vieux-Port | 45 quai du Port | 13002 | Marseille |
| Wacdo Lyon Part-Dieu | 8 rue de la Part-Dieu | 69003 | Lyon |

### Collaborateurs (8)
| Nom | Prénom | Email | Embauche | Admin | MDP |
|-----|--------|-------|----------|-------|-----|
| Admin | Système | admin@wcdo.fr | 2020-01-15 | ✅ | admin123 |
| Dupont | Marie | marie.dupont@wcdo.fr | 2021-03-10 | ✅ | manager1 |
| Martin | Lucas | lucas.martin@wcdo.fr | 2022-06-01 | ❌ | — |
| Bernard | Sophie | sophie.bernard@wcdo.fr | 2021-09-15 | ❌ | — |
| Petit | Thomas | thomas.petit@wcdo.fr | 2023-01-20 | ❌ | — |
| Leroy | Emma | emma.leroy@wcdo.fr | 2022-11-05 | ❌ | — |
| Moreau | Antoine | antoine.moreau@wcdo.fr | 2020-07-12 | ❌ | — |
| Garcia | Camille | camille.garcia@wcdo.fr | 2023-05-01 | ❌ | — |

**Login principal :** admin@wcdo.fr / admin123

### Affectations (10)
| Collaborateur | Restaurant | Fonction | Début | Fin | Statut |
|--------------|-----------|----------|-------|-----|--------|
| Lucas Martin | Nice Centre | Équipier | 2022-06-15 | — | ✅ Active |
| Sophie Bernard | Nice Centre | Caissier | 2021-10-01 | — | ✅ Active |
| Thomas Petit | Marseille VP | Équipier | 2023-02-01 | — | ✅ Active |
| Emma Leroy | Marseille VP | Manager | 2023-01-10 | — | ✅ Active |
| Antoine Moreau | Lyon PD | Directeur | 2020-08-01 | — | ✅ Active |
| Antoine Moreau | Nice Centre | Manager | 2020-08-01 | 2022-12-31 | ❌ Terminée |
| Lucas Martin | Marseille VP | Préparateur | 2022-06-15 | 2023-01-31 | ❌ Terminée |
| Sophie Bernard | Lyon PD | Équipier | 2021-10-01 | 2022-03-15 | ❌ Terminée |
| Marie Dupont | Nice Centre | Manager | 2021-03-15 | — | ✅ Active |
| Camille Garcia | Lyon PD | Caissier | 2023-05-15 | — | ✅ Active |

---

## 7. Pipeline d'implémentation (ordre des étapes)

### Phase 1 — Infrastructure
1. Créer `dossier_pr_docker_etc/docker/symfony/Dockerfile`
2. Créer `dossier_pr_docker_etc/docker/symfony/nginx.conf`
3. Ajouter les 2 services dans `docker-compose.dev.yml`
4. Ajouter `CREATE DATABASE wcdo_rh` dans `init.sql`
5. Configurer `.env.local` avec `DATABASE_URL`
6. `docker compose up -d --build` → vérifier http://localhost:8090

### Phase 2 — Dépendances Composer
```bash
# DANS le container symfony-php :
composer require symfony/orm-pack
composer require symfony/twig-pack
composer require symfony/security-bundle
composer require symfony/form
composer require symfony/validator
composer require symfony/asset
composer require --dev symfony/maker-bundle
composer require --dev doctrine/doctrine-fixtures-bundle
composer require --dev symfony/test-pack
composer require --dev symfony/profiler-pack
composer require --dev symfony/debug-bundle
```

### Phase 3 — Entités Doctrine (dans cet ordre)
1. `Fonction` (aucune dépendance)
2. `Restaurant` (aucune dépendance)
3. `Collaborateur` (implémente UserInterface)
4. `Affectation` (ManyToOne vers les 3 autres)
5. `make:migration` + `doctrine:migrations:migrate`

### Phase 4 — Sécurité
1. Configurer `security.yaml` (password_hashers, providers, firewalls, access_control)
2. Créer `SecurityController` (login/logout)
3. Créer `templates/security/login.html.twig`

### Phase 5 — Controllers + Templates (ordre recommandé)
1. `base.html.twig` + `_navbar.html.twig` (layout Bootstrap 5)
2. `DashboardController` + template
3. `FonctionController` + templates (CRUD le plus simple)
4. `RestaurantController` + templates (CRUD + affectations)
5. `CollaborateurController` + templates (CRUD + non-affectés + affecter)
6. `AffectationController` + templates (liste + filtres)

### Phase 6 — FormTypes
- `RestaurantType`, `RestaurantFilterType`
- `CollaborateurType`, `CollaborateurFilterType`
- `FonctionType`
- `AffectationType`, `AffectationFilterType`

### Phase 7 — Fixtures + Tests
1. Créer `AppFixtures.php` avec les données ci-dessus
2. `doctrine:fixtures:load`
3. Écrire les tests (Security, Controllers, Repositories)
4. `php bin/phpunit`

---

## 8. Commandes de référence

```bash
# Docker
docker compose -f dossier_pr_docker_etc/docker-compose.dev.yml up -d --build
docker compose -f dossier_pr_docker_etc/docker-compose.dev.yml exec symfony-php bash

# Symfony (dans le container)
php bin/console make:entity
php bin/console make:controller
php bin/console make:form
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
php bin/console debug:router
php bin/console cache:clear

# Tests
php bin/phpunit
```
