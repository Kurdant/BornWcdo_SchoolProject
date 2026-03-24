# WCDO — Guide de reconstruction complète du projet
## Borne de Commande McDonald's — Repartir de zéro

---

## Vue d'ensemble

Ce guide explique **TOUT** ce qu'il faut faire pour reconstruire le projet WCDO
depuis le frontend existant. Tu conserves :

- `Front/` — le frontend HTML/CSS/JS (tel quel)
- La logique BDD (`docker/mariadb/init.sql`)
- La documentation backend

Tu **reconstruis** :

- Le backend PHP (`src/`, `public/`)
- La configuration Docker complète
- Le pipeline CI/CD GitHub Actions
- L'infrastructure (Traefik, Registry privé)

---

## Structure complète du nouveau projet

```
mon-nouveau-projet/
├── .env                          ← Variables locales (NE PAS commiter)
├── .env.example                  ← Modèle à commiter
├── .gitignore
├── composer.json
├── composer.lock                 ← Généré par composer install
├── Dockerfile                    ← Image PHP-FPM
├── docker-compose.yml            ← Stack locale (dev)
├── docker-compose.registry.yml   ← Registry privé Docker (sur VPS)
│
├── Front/                        ← Frontend (copie depuis l'ancien projet)
│   ├── accueil.html
│   ├── images/
│   └── ...
│
├── public/
│   └── index.php                 ← Point d'entrée backend
│
├── src/                          ← Code PHP (namespace WCDO\)
│   ├── Config/
│   │   └── Database.php
│   ├── Controllers/
│   │   ├── CatalogueController.php
│   │   ├── PanierController.php
│   │   ├── CommandeController.php
│   │   ├── AuthController.php
│   │   └── AdminController.php
│   ├── Entities/
│   │   ├── Produit.php
│   │   ├── Categorie.php
│   │   ├── Panier.php
│   │   ├── PanierLigne.php
│   │   ├── Commande.php
│   │   ├── Client.php
│   │   ├── Sauce.php
│   │   └── TailleBoisson.php
│   ├── Repositories/             ← Accès BDD (PDO)
│   │   ├── ProduitRepository.php
│   │   ├── CategorieRepository.php
│   │   ├── PanierRepository.php
│   │   ├── PanierProduitRepository.php
│   │   ├── CommandeRepository.php
│   │   ├── CommandeProduitRepository.php
│   │   ├── ClientRepository.php
│   │   ├── AdminRepository.php
│   │   ├── SauceRepository.php
│   │   └── TailleBoissonRepository.php
│   ├── Services/                 ← Logique métier
│   │   ├── PanierService.php
│   │   ├── CommandeService.php
│   │   └── AuthService.php
│   ├── Http/
│   │   ├── Router.php
│   │   └── Response.php
│   └── Exceptions/
│       └── StockInsuffisantException.php
│
├── tests/                        ← Tests PHPUnit
│
├── docker/
│   ├── nginx/
│   │   └── nginx.conf
│   └── mariadb/
│       └── init.sql              ← Script BDD complet (seed inclus)
│
├── traefik/                      ← Config reverse proxy (sur VPS uniquement)
│   ├── traefik.yml
│   ├── dynamic.yml
│   ├── htpasswd                  ← Généré (ne pas commiter)
│   └── acme.json                 ← Généré par Let's Encrypt (ne pas commiter)
│
├── registry/
│   └── config.yml                ← Config du registry Docker privé
│
├── auth/
│   └── htpasswd                  ← Auth du registry (généré, ne pas commiter)
│
├── data/
│   └── registry/                 ← Données du registry (ne pas commiter)
│
└── .github/
    └── workflows/
        ├── tests.yml             ← Tests sur PR
        ├── dev-cicd.yml          ← Build + deploy sur stark (branche dev)
        └── deploy.yml            ← Deploy prod sur vision (branche prod)
```

---

## Étape 1 — Initialiser le projet

```bash
mkdir mon-nouveau-projet
cd mon-nouveau-projet
git init
git remote add origin https://github.com/TON_USERNAME/TON_REPO.git
```

Créer un `.gitignore` :

```
vendor/
.env
.env.prod
traefik/acme.json
traefik/htpasswd
auth/htpasswd
data/
```

---

## Étape 2 — composer.json

```json
{
    "name": "wcdo/backend",
    "description": "Backend API for WCDO - McDonald's order terminal",
    "license": "proprietary",
    "require": {
        "php": ">=8.2"
    },
    "require-dev": {
        "phpunit/phpunit": "^10"
    },
    "autoload": {
        "psr-4": {
            "WCDO\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

Puis :

```bash
composer install
```

---

## Étape 3 — Variables d'environnement

Copier `.env.example` en `.env` et remplir les valeurs :

```env
# Base de données MariaDB
MYSQL_ROOT_PASSWORD=un_mot_de_passe_fort
MYSQL_DATABASE=wcdo
MYSQL_USER=wcdo_user
MYSQL_PASSWORD=un_autre_mdp

# Connexion PHP → DB
DB_HOST=db
DB_NAME=wcdo
DB_USER=wcdo_user
DB_PASS=un_autre_mdp

# Docker Registry
REGISTRY_HOST=hugo-registry.a3n.fr
REGISTRY_USER=ton_user
REGISTRY_PASSWORD=ton_mdp

# GitHub Actions Runner
GITHUB_TOKEN=ghp_...
```

> Le `.env` ne doit **jamais** être commité. Seul `.env.example` va dans Git.

---

## Étape 4 — Dockerfile

Ce fichier construit l'image PHP-FPM pour le backend.

```dockerfile
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    zip \
    curl \
    git \
    mysql-client \
    && docker-php-ext-install pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . /app

RUN composer install --no-dev --optimize-autoloader

EXPOSE 9000

CMD ["php-fpm"]
```

> Le fichier `Dockerfile` est à la **racine** du projet.

---

## Étape 5 — docker-compose.yml (stack de développement)

Ce fichier démarre **tous les services** en local et sur le VPS de dev (stark).

```yaml
services:

  # Base de données MariaDB
  db:
    image: mariadb:10.11
    container_name: wcdo-db
    restart: unless-stopped
    env_file: .env
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
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - internal

  # Backend PHP-FPM
  php:
    build: .
    image: hugo-registry.a3n.fr/wcdo:dev
    container_name: wcdo-php
    restart: unless-stopped
    volumes:
      - .:/app
    depends_on:
      db:
        condition: service_healthy
    env_file: .env
    networks:
      - internal

  # Nginx (sert le front + passe les requêtes PHP-FPM)
  nginx:
    image: nginx:alpine
    container_name: wcdo-nginx
    restart: unless-stopped
    volumes:
      - .:/app
      - ./docker/nginx/nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=admin_proxy"
      # Frontend
      - "traefik.http.routers.wcdo-front.rule=Host(`wakdo-front.acadenice.fr`)"
      - "traefik.http.routers.wcdo-front.entrypoints=websecure"
      - "traefik.http.routers.wcdo-front.tls.certresolver=letsencrypt"
      - "traefik.http.routers.wcdo-front.service=wcdo-front"
      - "traefik.http.services.wcdo-front.loadbalancer.server.port=80"
      # Backend API
      - "traefik.http.routers.wcdo-back.rule=Host(`wakdo-back.acadenice.fr`)"
      - "traefik.http.routers.wcdo-back.entrypoints=websecure"
      - "traefik.http.routers.wcdo-back.tls.certresolver=letsencrypt"
      - "traefik.http.routers.wcdo-back.service=wcdo-back"
      - "traefik.http.services.wcdo-back.loadbalancer.server.port=80"
    networks:
      - internal
      - admin_proxy

  # phpMyAdmin (interface visuelle BDD)
  phpmyadmin:
    image: phpmyadmin:latest
    container_name: wcdo-phpmyadmin
    restart: unless-stopped
    env_file: .env
    environment:
      PMA_HOST: db
      PMA_USER: ${MYSQL_USER}
      PMA_PASSWORD: ${MYSQL_PASSWORD}
    depends_on:
      db:
        condition: service_healthy
    networks:
      - internal

  # GitHub Actions Runner (auto-hébergé sur VPS stark)
  runner:
    image: myoung34/github-runner:latest
    container_name: wcdo-runner
    restart: unless-stopped
    env_file: .env
    environment:
      - REPO_URL=https://github.com/TON_USERNAME/TON_REPO
      - RUNNER_NAME=stark
      - RUNNER_WORKDIR=/tmp/runner/work
      - RUNNER_GROUP=Default
      - RUNNER_LABELS=self-hosted,stark
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - runner_work:/tmp/runner
    networks:
      - internal

volumes:
  db_data:
  runner_work:

networks:
  internal:
  admin_proxy:
    external: true
```

> Remplace `TON_USERNAME/TON_REPO` par ton vrai repo GitHub.

---

## Étape 6 — docker-compose.registry.yml (Registry privé Docker)

Ce fichier tourne **uniquement sur le VPS** (pas en local).
Il expose le registry Docker privé via Traefik.

```yaml
services:
  registry:
    image: registry:2
    container_name: registry
    restart: unless-stopped
    environment:
      - REGISTRY_STORAGE_FILESYSTEM_ROOTDIRECTORY=/var/lib/registry
    volumes:
      - ./data/registry:/var/lib/registry
      - ./auth/htpasswd:/auth/htpasswd:ro
      - ./registry/config.yml:/etc/docker/registry/config.yml:ro
    networks:
      - registry-net
      - admin_proxy
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=admin_proxy"
      - "traefik.http.routers.wcdo-registry.rule=Host(`hugo-registry.a3n.fr`)"
      - "traefik.http.routers.wcdo-registry.entrypoints=websecure"
      - "traefik.http.routers.wcdo-registry.tls=true"
      - "traefik.http.routers.wcdo-registry.tls.certresolver=letsencrypt"
      - "traefik.http.services.wcdo-registry.loadbalancer.server.port=5000"
      - "traefik.http.routers.wcdo-registry.middlewares=wcdo-registry-ratelimit@docker"
      - "traefik.http.middlewares.wcdo-registry-ratelimit.ratelimit.average=20"
      - "traefik.http.middlewares.wcdo-registry-ratelimit.ratelimit.burst=50"

  registry-ui:
    image: joxit/docker-registry-ui:latest
    container_name: registry-ui
    restart: unless-stopped
    environment:
      - REGISTRY_TITLE=Registry-Privé
      - NGINX_PROXY_PASS_URL=http://registry:5000
      - DELETE_IMAGES=true
    networks:
      - registry-net
      - admin_proxy
    depends_on:
      - registry
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=admin_proxy"
      - "traefik.http.routers.wcdo-registry-ui.rule=Host(`hugo-registry-ui.a3n.fr`)"
      - "traefik.http.routers.wcdo-registry-ui.entrypoints=websecure"
      - "traefik.http.routers.wcdo-registry-ui.tls=true"
      - "traefik.http.routers.wcdo-registry-ui.tls.certresolver=letsencrypt"
      - "traefik.http.services.wcdo-registry-ui.loadbalancer.server.port=80"

networks:
  registry-net:
    driver: bridge
  admin_proxy:
    external: true
```

---

## Étape 7 — Nginx (docker/nginx/nginx.conf)

Nginx sert **deux serveurs virtuels** :
- Le **frontend** statique (HTML/CSS/JS dans `Front/`)
- Le **backend API** (PHP-FPM via FastCGI)

```nginx
server {
    listen 80;
    server_name wakdo-front.acadenice.fr;

    root /app/Front;
    index accueil.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location /images/ {
        root /app/Front;
        try_files $uri =404;
    }
}

server {
    listen 80;
    server_name wakdo-back.acadenice.fr;

    root /app/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location /api {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## Étape 8 — Traefik (reverse proxy HTTPS)

Traefik tourne **sur le VPS** et gère automatiquement les certificats TLS.

### traefik/traefik.yml

```yaml
api:
  dashboard: true

entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https
  websecure:
    address: ":443"

certificatesResolvers:
  letsencrypt:
    acme:
      email: ton@email.fr
      storage: /acme.json
      tlsChallenge: {}

providers:
  docker:
    exposedByDefault: false
  file:
    filename: /etc/traefik/dynamic.yml
    watch: true

log:
  level: INFO
```

> Remplace `ton@email.fr` par ton email réel pour Let's Encrypt.

### traefik/dynamic.yml

```yaml
http:
  middlewares:
    dashboard-auth:
      basicAuth:
        usersFile: /etc/traefik/htpasswd
```

### Générer le fichier htpasswd (auth dashboard Traefik)

```bash
# Sur le VPS, dans le dossier traefik/
htpasswd -nb ton_user ton_mot_de_passe > traefik/htpasswd
```

### Créer acme.json avec les bonnes permissions

```bash
touch traefik/acme.json
chmod 600 traefik/acme.json
```

---

## Étape 9 — Registry privé Docker (registry/config.yml)

```yaml
version: 0.1
log:
  level: info
storage:
  filesystem:
    rootdirectory: /var/lib/registry
  delete:
    enabled: true
  cache:
    blobdescriptor: inmemory
http:
  addr: :5000
  headers:
    X-Content-Type-Options: [nosniff]
    Access-Control-Allow-Origin: ['https://hugo-registry-ui.a3n.fr']
    Access-Control-Allow-Methods: ['HEAD', 'GET', 'OPTIONS', 'DELETE']
    Access-Control-Allow-Credentials: [true]
    Access-Control-Allow-Headers: ['Authorization', 'Accept', 'Cache-Control']
auth:
  htpasswd:
    realm: Registry
    path: /auth/htpasswd
health:
  storagedriver:
    enabled: true
    interval: 10s
    threshold: 3
```

### Générer le htpasswd du Registry

```bash
# Sur le VPS, dans le dossier du projet
mkdir -p auth
htpasswd -Bbn ton_user ton_mot_de_passe > auth/htpasswd
```

---

## Étape 10 — Base de données (docker/mariadb/init.sql)

Le fichier `init.sql` est **automatiquement exécuté** au premier démarrage du
conteneur MariaDB. Il crée toutes les tables et insère les données de base.

Tables créées :
- `CATEGORIE` — catégories de produits
- `SAUCE` — sauces disponibles
- `TAILLE_BOISSON` — tailles + suppléments prix
- `ADMIN` — administrateurs (mot de passe bcrypt)
- `CLIENT` — clients avec points de fidélité
- `PRODUIT` — catalogue produits avec stock et image
- `PANIER` — paniers (liés à une session ou un client)
- `PANIER_PRODUIT` — lignes de panier (pivot)
- `COMMANDE` — commandes passées
- `COMMANDE_PRODUIT` — lignes de commande (pivot)

> Le fichier `init.sql` complet est dans `docker/mariadb/init.sql`.
> Il contient aussi le **seed** (données initiales : produits, admins, clients test).

Comptes créés par défaut :
- Admin : `admin@wcdo.fr` / `admin123`
- Admin : `hugo@wcdo.fr` / `admin123`
- Client : `jean.dupont@mail.fr` / `client123`
- Client : `sophie.martin@mail.fr` / `client123`

---

## Étape 11 — CI/CD GitHub Actions

### Workflow 1 : Tests sur Pull Request (.github/workflows/tests.yml)

Déclenché sur toute PR vers `dev`, `prod` ou `main`.
Lance PHPUnit sur PHP 8.2.

```yaml
name: Tests PHPUnit - Pull Request

on:
  pull_request:
    branches: [dev, prod, main]

jobs:
  test:
    name: PHPUnit PHP 8.2
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: json, pdo, pdo_mysql
          coverage: xdebug

      - name: Validate composer.json
        run: composer validate --no-check-lock --no-check-publish

      - name: Cache Composer packages
        uses: actions/cache@v4
        with:
          path: vendor
          key: ${{ runner.os }}-php-8.2-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-php-8.2-

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run PHPUnit tests
        run: ./vendor/bin/phpunit tests/
```

---

### Workflow 2 : Dev CI/CD (.github/workflows/dev-cicd.yml)

Déclenché sur push sur la branche `dev`.
Tourne sur le runner **self-hosted** (stark.a3n.fr).
Pipeline : Tests → Build image Docker → Push registry → Deploy docker compose.

```yaml
name: CI/CD - Dev (stark.a3n.fr)

on:
  push:
    branches: [dev]

jobs:
  test-build-deploy:
    name: Test, Build & Deploy on stark
    runs-on: self-hosted

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Install Composer dependencies
        run: docker run --rm -v ${{ github.workspace }}:/app -w /app composer:latest composer install --prefer-dist --no-progress
        continue-on-error: true

      - name: Run PHPUnit tests
        run: docker run --rm -v ${{ github.workspace }}:/app -w /app php:8.2-cli ./vendor/bin/phpunit tests/
        continue-on-error: true

      - name: Login to registry
        run: |
          echo "${{ secrets.REGISTRY_PASSWORD }}" | docker login hugo-registry.a3n.fr \
            -u "${{ secrets.REGISTRY_USER }}" --password-stdin

      - name: Build and push Docker image
        run: |
          docker build -t hugo-registry.a3n.fr/wcdo:dev .
          docker push hugo-registry.a3n.fr/wcdo:dev

      - name: Deploy with docker compose
        run: |
          docker compose pull
          docker compose up -d

      - name: Verify containers are running
        run: |
          sleep 10
          docker compose ps --filter status=running
          echo "Deploy on stark done"
```

---

### Workflow 3 : Deploy prod (.github/workflows/deploy.yml)

Déclenché sur push sur la branche `prod`.
Re-tague l'image `:dev` en `:prod` puis déploie sur `vision.a3n.fr`.
Effectue un health check, puis merge `prod` → `main` si tout est OK.

```yaml
name: CI/CD - Deploy to prod (vision)

on:
  push:
    branches: [prod]

jobs:
  tag-prod-image:
    name: Tag :dev as :prod on registry
    runs-on: self-hosted

    steps:
      - name: Login to registry
        run: |
          echo "${{ secrets.REGISTRY_PASSWORD }}" | docker login hugo-registry.a3n.fr \
            -u "${{ secrets.REGISTRY_USER }}" --password-stdin

      - name: Retag dev image as prod
        run: |
          docker pull hugo-registry.a3n.fr/wcdo:dev
          docker tag hugo-registry.a3n.fr/wcdo:dev hugo-registry.a3n.fr/wcdo:prod
          docker push hugo-registry.a3n.fr/wcdo:prod

  deploy-vision:
    name: Deploy :prod on vision.a3n.fr
    needs: tag-prod-image
    runs-on: ubuntu-latest

    steps:
      - name: Deploy via SSH on vision
        uses: appleboy/ssh-action@v1
        env:
          REGISTRY_USER: ${{ secrets.REGISTRY_USER }}
          REGISTRY_PASSWORD: ${{ secrets.REGISTRY_PASSWORD }}
        with:
          host: vision.a3n.fr
          username: hugo
          key: ${{ secrets.VISION_SSH_KEY }}
          passphrase: ${{ secrets.VISION_SSH_PASSPHRASE }}
          envs: REGISTRY_USER,REGISTRY_PASSWORD
          script: |
            set -e
            cd /home/hugo/wcdo

            echo "$REGISTRY_PASSWORD" | docker login hugo-registry.a3n.fr \
              -u "$REGISTRY_USER" --password-stdin

            docker compose pull
            docker compose up -d

            sleep 15
            docker compose ps --filter status=running

  health-check:
    name: Health Check
    needs: deploy-vision
    runs-on: ubuntu-latest

    steps:
      - name: Wait for container to be ready
        run: sleep 10

      - name: Check app is responding
        run: |
          STATUS=$(curl -o /dev/null -s -w "%{http_code}" \
            --max-time 30 \
            https://wakdo-back.acadenice.fr/api/health || echo "000")
          echo "HTTP status: $STATUS"
          if [ "$STATUS" != "200" ] && [ "$STATUS" != "302" ]; then
            echo "Health check failed with status $STATUS"
            exit 1
          fi
          echo "App is up and running"

  merge-to-main:
    name: Merge prod into main
    needs: health-check
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
          token: ${{ secrets.GH_TOKEN }}

      - name: Merge prod into main
        run: |
          git config user.name "github-actions[bot]"
          git config user.email "github-actions[bot]@users.noreply.github.com"
          git checkout main
          git merge origin/prod --ff-only
          git push origin main
```

---

## Étape 12 — Secrets GitHub à configurer

Dans ton repo GitHub → **Settings → Secrets and variables → Actions** :

| Secret | Description | Exemple |
|--------|-------------|---------|
| `REGISTRY_USER` | Login du registry privé | `hugo` |
| `REGISTRY_PASSWORD` | Mot de passe du registry | `monmdp` |
| `VISION_SSH_KEY` | Clé SSH privée pour vision.a3n.fr | Contenu de `~/.ssh/id_rsa` |
| `VISION_SSH_PASSPHRASE` | Passphrase de la clé SSH | `mapassphrase` |
| `GH_TOKEN` | Personal Access Token GitHub (scope: repo) | `ghp_...` |

### Générer une clé SSH pour le déploiement

```bash
# Sur ta machine locale
ssh-keygen -t ed25519 -C "github-actions-wcdo" -f ~/.ssh/wcdo_deploy

# Copier la clé publique sur le VPS vision
ssh-copy-id -i ~/.ssh/wcdo_deploy.pub hugo@vision.a3n.fr

# Copier la clé PRIVÉE dans le secret GitHub VISION_SSH_KEY
cat ~/.ssh/wcdo_deploy
```

---

## Étape 13 — Schéma de branches Git

```
main   ←── merge automatique depuis prod (via GitHub Actions)
prod   ←── merge manuel depuis dev après validation
dev    ←── branche de travail quotidienne
```

Workflow typique :

```bash
git checkout dev
# ... développement ...
git push origin dev        # → déclenche dev-cicd.yml (build + deploy stark)

# Quand dev est prêt pour la prod :
git checkout prod
git merge dev
git push origin prod       # → déclenche deploy.yml (deploy vision + health check)
```

---

## Étape 14 — Architecture backend PHP (à reconstruire)

### Pattern utilisé : MVC + Repository + Service

```
Requête HTTP
    ↓
public/index.php     ← Charge l'autoloader, définit les routes
    ↓
src/Http/Router.php  ← Dispatch vers le bon Controller
    ↓
src/Controllers/     ← Reçoit la requête, valide, appelle le Service
    ↓
src/Services/        ← Logique métier (calculs, règles)
    ↓
src/Repositories/    ← Accès BDD via PDO (requêtes SQL)
    ↓
src/Entities/        ← Objets métier (pas de logique, juste les données)
    ↓
src/Http/Response.php ← Retourne le JSON au client
```

### Routes API disponibles

#### Catalogue (lecture publique)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/categories` | Liste toutes les catégories |
| GET | `/api/produits` | Liste tous les produits |
| GET | `/api/produits/{id}` | Détail d'un produit |
| GET | `/api/boissons` | Liste les boissons |
| GET | `/api/tailles-boissons` | Tailles de boissons + prix |
| GET | `/api/sauces` | Liste les sauces |

#### Panier (session)

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/panier` | Récupère le panier actuel |
| POST | `/api/panier/ajouter` | Ajoute un produit |
| DELETE | `/api/panier/ligne/{id}` | Supprime une ligne |
| DELETE | `/api/panier` | Vide le panier |

#### Commande

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/api/commande` | Passe la commande (depuis le panier) |
| GET | `/api/commande/{numero}` | Récupère une commande par numéro |

#### Auth Client

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/api/auth/register` | Création de compte |
| POST | `/api/auth/login` | Connexion |
| POST | `/api/auth/logout` | Déconnexion |
| GET | `/api/auth/me` | Profil de l'utilisateur connecté |

#### Admin (authentification requise)

| Méthode | Route | Description |
|---------|-------|-------------|
| POST | `/api/admin/login` | Connexion admin |
| POST | `/api/admin/logout` | Déconnexion admin |
| GET | `/api/admin/produits` | Liste des produits (admin) |
| POST | `/api/admin/produits` | Créer un produit |
| PUT | `/api/admin/produits/{id}` | Modifier un produit |
| DELETE | `/api/admin/produits/{id}` | Supprimer un produit |
| GET | `/api/admin/commandes` | Liste des commandes |

> **A AJOUTER** : `GET /api/health` retournant `{"status":"ok"}` et HTTP 200.
> Nécessaire pour le health check du pipeline CI/CD.

---

## Étape 15 — Démarrer le projet en local

```bash
# 1. Cloner le repo
git clone https://github.com/TON_USERNAME/TON_REPO.git
cd TON_REPO

# 2. Copier et configurer le .env
cp .env.example .env
# Éditer .env avec tes valeurs locales

# 3. Démarrer la stack Docker
docker compose up -d --build

# 4. Vérifier que tout tourne
docker compose ps

# 5. Tester le backend
curl http://localhost/api/categories
```

---

## Étape 16 — Démarrer le projet sur le VPS (stark)

```bash
# 1. Se connecter au VPS
ssh hugo@stark.a3n.fr

# 2. Cloner le repo
git clone https://github.com/TON_USERNAME/TON_REPO.git /home/hugo/wcdo
cd /home/hugo/wcdo

# 3. Créer le .env de production
cp .env.example .env
nano .env  # Remplir avec les vraies valeurs

# 4. Créer le réseau Docker partagé (si pas déjà fait)
docker network create admin_proxy

# 5. Démarrer Traefik (si pas déjà lancé)
# Voir section Traefik ci-dessus

# 6. Démarrer le registry (si pas déjà lancé)
docker compose -f docker-compose.registry.yml up -d

# 7. Démarrer la stack principale
docker compose up -d --build

# 8. Vérifier
docker compose ps
```

---

## Étape 17 — Configurer le GitHub Actions Runner self-hosted

Le runner tourne dans un conteneur Docker sur le VPS stark.
Il est défini dans `docker-compose.yml` (service `runner`).

```bash
# Le runner démarre automatiquement avec docker compose up -d
# Vérifier son état :
docker logs wcdo-runner

# Dans GitHub : Settings → Actions → Runners
# Tu devrais voir "stark" en ligne (Online)
```

> Le `GITHUB_TOKEN` dans le `.env` doit être un PAT avec le scope `repo`.

---

## Checklist finale avant exam

- [ ] `Front/` copié depuis l'ancien projet
- [ ] `docker/mariadb/init.sql` en place (BDD + seed)
- [ ] `docker/nginx/nginx.conf` configuré
- [ ] `Dockerfile` à la racine
- [ ] `docker-compose.yml` configuré avec le bon repo GitHub
- [ ] `.env` rempli avec les vraies valeurs
- [ ] `.env.example` commité dans Git
- [ ] Secrets GitHub configurés (REGISTRY_USER, REGISTRY_PASSWORD, VISION_SSH_KEY, VISION_SSH_PASSPHRASE, GH_TOKEN)
- [ ] `registry/config.yml` et `auth/htpasswd` en place sur le VPS
- [ ] `traefik/` configuré avec ton email et ton htpasswd
- [ ] Workflows GitHub Actions dans `.github/workflows/`
- [ ] Runner self-hosted visible dans GitHub
- [ ] Route `/api/health` ajoutée au backend (pour le health check CI/CD)
- [ ] Tests PHPUnit dans `tests/`

---

## Points importants à ne pas oublier

1. **Le réseau `admin_proxy`** doit exister sur le VPS avant de lancer les conteneurs :
   ```bash
   docker network create admin_proxy
   ```

2. **`acme.json` doit avoir les permissions 600** sinon Traefik refuse de démarrer :
   ```bash
   chmod 600 traefik/acme.json
   ```

3. **L'image Docker est tagguée `hugo-registry.a3n.fr/wcdo:dev`** — si tu changes
   le nom du projet, mets à jour le tag dans le `docker-compose.yml` et les workflows.

4. **Le `init.sql` ne s'exécute qu'au premier démarrage** du conteneur MariaDB
   (quand le volume `db_data` est vide). Si tu veux réinitialiser la BDD :
   ```bash
   docker compose down -v   # Supprime le volume
   docker compose up -d     # Recrée tout
   ```

5. **CORS** : Le Router PHP gère déjà les preflight OPTIONS avec les bons headers.
   Si le front appelle le back depuis un autre domaine, ça devrait fonctionner.
