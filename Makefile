# ============================================================
# Makefile — Commandes de développement WCDO
# Toutes les commandes s'exécutent depuis la racine du projet
# ============================================================

COMPOSE = docker compose -f dossier_pr_docker_etc/docker-compose.dev.yml

.PHONY: up down build restart logs php db composer test ps

## Lance la stack dev (construit si nécessaire)
up:
	$(COMPOSE) up -d --build

## Lance la stack sans rebuild
start:
	$(COMPOSE) up -d

## Arrête et supprime les containers (les volumes sont conservés)
down:
	$(COMPOSE) down

## Arrête + supprime containers ET volume BDD (reset complet)
reset:
	$(COMPOSE) down -v

## Reconstruit l'image PHP et relance
build:
	$(COMPOSE) build php
	$(COMPOSE) up -d

## Redémarre tous les services
restart:
	$(COMPOSE) restart

## Affiche les logs en temps réel (Ctrl+C pour quitter)
logs:
	$(COMPOSE) logs -f

## Logs d'un seul service : make logs-php | make logs-nginx | make logs-db
logs-php:
	$(COMPOSE) logs -f php

logs-nginx:
	$(COMPOSE) logs -f nginx

logs-db:
	$(COMPOSE) logs -f db

## Ouvre un shell dans le container PHP
php:
	docker exec -it wcdo-php sh

## Ouvre le client MariaDB
db:
	docker exec -it wcdo-db mariadb -u wcdo_user -pwcdo_pass wcdo

## Lance composer install dans le container PHP
composer:
	docker exec -it wcdo-php composer install --working-dir=/app/Backend

## Lance les tests PHPUnit
test:
	docker exec -it wcdo-php vendor/bin/phpunit --testdox --working-dir=/app/Backend

## Affiche l'état des containers
ps:
	$(COMPOSE) ps

## Aide
help:
	@echo ""
	@echo "  make up          → Démarre la stack (build auto)"
	@echo "  make down        → Arrête la stack"
	@echo "  make reset       → Arrête + supprime la BDD (reset complet)"
	@echo "  make build       → Rebuild image PHP"
	@echo "  make logs        → Logs en temps réel"
	@echo "  make php         → Shell dans le container PHP"
	@echo "  make db          → Client MariaDB"
	@echo "  make composer    → composer install"
	@echo "  make test        → PHPUnit"
	@echo "  make ps          → État des containers"
	@echo ""
	@echo "  Frontend   → http://localhost:8080"
	@echo "  API        → http://localhost:8081/api/health"
	@echo "  phpMyAdmin → http://localhost:8082"
	@echo ""
