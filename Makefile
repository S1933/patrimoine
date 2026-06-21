.PHONY: help up down build rebuild logs ps migrate fresh seed migrate-seed test test-unit test-feature shell shell-frontend key generate

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Build and start all containers (detached)
	docker compose up -d --build

down: ## Stop and remove containers
	docker compose down

build: ## Build images
	docker compose build

rebuild: ## Rebuild from scratch (no cache)
	docker compose build --no-cache

logs: ## Tail logs (all)
	docker compose logs -f --tail=100

ps: ## List containers
	docker compose ps

migrate: ## Run migrations
	docker compose exec app php artisan migrate --force

fresh: ## Fresh migrate (dev only) — drops all tables
	docker compose exec app php artisan migrate:fresh --force

seed: ## Run seeders
	docker compose exec app php artisan db:seed --force

migrate-seed: ## Fresh + seed
	docker compose exec app php artisan migrate:fresh --seed --force

test: ## Run Pest tests
	docker compose exec \
	  -e AUTH_BYPASS=false \
	  -e DB_CONNECTION=sqlite \
	  -e DB_DATABASE=:memory: \
	  -e CACHE_STORE=array \
	  -e QUEUE_CONNECTION=sync \
	  app php artisan test

test-unit: ## Pest unit only
	docker compose exec \
	  -e AUTH_BYPASS=false \
	  -e DB_CONNECTION=sqlite \
	  -e DB_DATABASE=:memory: \
	  -e CACHE_STORE=array \
	  -e QUEUE_CONNECTION=sync \
	  app php artisan test --testsuite=Unit

test-feature: ## Pest feature only
	docker compose exec \
	  -e AUTH_BYPASS=false \
	  -e DB_CONNECTION=sqlite \
	  -e DB_DATABASE=:memory: \
	  -e CACHE_STORE=array \
	  -e QUEUE_CONNECTION=sync \
	  app php artisan test --testsuite=Feature

shell: ## Shell into backend container
	docker compose exec app sh

shell-frontend: ## Shell into frontend container
	docker compose exec frontend sh

key: ## Generate APP_KEY
	docker compose exec app php artisan key:generate

generate: ## Generate something (usage: make generate cmd=make:migration)
	@test -n "$(cmd)" || (echo "usage: make generate cmd=<artisan-command>" && exit 1)
	docker compose exec app php artisan $(cmd)
