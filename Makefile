.DEFAULT_GOAL := help
DC  := docker compose
APP := $(DC) exec app

help: ## Lista os comandos
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-14s\033[0m %s\n", $$1, $$2}'

setup: ## 1ª execução após o clone: sobe tudo, instala deps, gera chaves e migra
	@test -f .env || cp .env.example .env
	$(DC) up -d --build
	$(APP) composer install --no-interaction
	$(APP) php artisan key:generate
	$(APP) php artisan jwt:secret --force || true
	$(APP) php artisan migrate --seed
	@echo "\n✅ API em http://localhost:$${APP_PORT:-8080} | Docs: http://localhost:$${APP_PORT:-8080}/docs/api"

up: ## Sobe os containers
	$(DC) up -d

down: ## Derruba os containers
	$(DC) down

sh: ## Shell dentro do container da aplicação
	$(APP) sh

migrate: ## Roda migrations
	$(APP) php artisan migrate

fresh: ## Recria o banco do zero com seeds
	$(APP) php artisan migrate:fresh --seed

test: ## Testes (unitários + integração)
	$(APP) php artisan test

coverage: ## Testes com gate de cobertura mínima de 80%
	$(APP) php artisan test --coverage --min=80

lint: ## Verifica code style (Pint)
	$(APP) ./vendor/bin/pint --test

fix: ## Corrige code style
	$(APP) ./vendor/bin/pint

stan: ## Análise estática (Larastan)
	$(APP) ./vendor/bin/phpstan analyse --memory-limit=1G

audit: ## Vulnerabilidades conhecidas nas dependências
	$(APP) composer audit

check: lint stan coverage audit ## Tudo que o CI roda (use antes de abrir PR)

.PHONY: help setup up down sh migrate fresh test coverage lint fix stan audit check
