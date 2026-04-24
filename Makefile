# =============================================================================
#  Symfony Backend Stack — Makefile (cross-platform: Windows + Linux/macOS)
# =============================================================================

export DOCKER_BUILDKIT          := 1
export COMPOSE_DOCKER_CLI_BUILD := 1
export COMPOSE_BAKE             := true

# -- OS detection ------------------------------------------------------------
# Three supported environments:
#   * Native Windows (cmd / PowerShell)        -> docker compose       + setup.ps1
#   * WSL bash on a Windows host w/ Docker     -> docker.exe compose   + setup.sh
#   * Native Linux / macOS                     -> docker compose       + setup.sh
ifeq ($(OS),Windows_NT)
    PWSH       := powershell -NoProfile -ExecutionPolicy Bypass
    SETUP      := $(PWSH) -File scripts/setup.ps1
    DOCKER_BIN := docker
    IS_WSL     :=
else
    SETUP      := bash scripts/setup.sh
    # `uname -r` contains "microsoft" inside WSL1/WSL2.
    IS_WSL     := $(shell uname -r 2>/dev/null | grep -qiE 'microsoft|wsl' && echo 1)
    ifeq ($(IS_WSL),1)
        # Docker Desktop exposes docker.exe via Win32 PATH when WSL integration is on.
        # Falling back to docker if docker.exe is somehow not on PATH.
        DOCKER_BIN := $(shell command -v docker.exe >/dev/null 2>&1 && echo docker.exe || echo docker)
    else
        DOCKER_BIN := docker
    endif
endif

DC          := $(DOCKER_BIN) compose
DC_TOOLS    := $(DOCKER_BIN) compose --profile tools
PHP_EXEC    := $(DC) exec -T php
COMPOSER    := $(DC_TOOLS) run --rm composer
SYMFONY     := $(DC_TOOLS) run --rm --entrypoint symfony symfony
SF_INIT     := $(DC_TOOLS) run --rm --entrypoint init-symfony symfony

.DEFAULT_GOAL := help
.PHONY: help all dev dev-full setup build up up-fast down restart logs ps init shell shell-php shell-postgres shell-mongo psql mongo-shell \
        composer symfony cache-clear migrate db-create db-drop db-reset db-seed test clean prune reset \
        models-validate models-generate models-pipeline jwt-keys fix-perms doctor \
        urls status open wait wait-http wait-db wait-mongo

# -- Help -------------------------------------------------------------------
help:
	@echo.
	@echo   Symfony Backend Stack -- targets
	@echo   --------------------------------
	@echo   make all          One-shot: setup + build + up + init  (recommended first run)
	@echo   make setup        Create .env, ./app, ./secrets
	@echo   make build        Build all images in parallel
	@echo   make up           Start the stack
	@echo   make init         Bootstrap Symfony project + first page (requires stack up)
	@echo   make down         Stop the stack
	@echo   make restart      Restart
	@echo   make logs         Tail logs
	@echo   make ps           List services
	@echo   make status       Services + URLs
	@echo   make shell-php       Shell into php container
	@echo   make psql            psql shell into Postgres
	@echo   make mongo-shell     mongosh into MongoDB (app db)
	@echo   make composer ARGS=...  Run composer
	@echo   make symfony  ARGS=...  Run symfony CLI
	@echo   make models-validate  Validate every YAML model under ./models
	@echo   make models-generate  Generate Doctrine entities + ODM documents from ./models
	@echo   make models-pipeline  models-generate + migrate + db-seed
	@echo   make jwt-keys         Generate Lexik JWT keypair (idempotent)
	@echo   make cache-clear / db-create / db-drop / migrate / db-seed / db-reset / test
	@echo   make clean        Remove containers (keep volumes)
	@echo   make prune        Remove containers + volumes (DESTROYS DATA)
	@echo   make reset        prune + build + up + init
	@echo.

# -- One-shot ---------------------------------------------------------------
all: setup build init up status

# -- Dev one-shot: fast path (no build, no recreate). Use `make dev-full` to rebuild.
dev: setup doctor up-fast wait-db wait-mongo fix-perms wait-http status
	@echo.
	@echo Stack ready -- open http://localhost:$${HTTP_HOST_PORT:-8080}

# Sanity check: can we actually reach the Docker daemon?
# Inside WSL we expect docker.exe (Docker Desktop interop). If it fails we print
# actionable guidance instead of a cryptic socket error.
doctor:
	@echo "==> docker binary: $(DOCKER_BIN)"
ifeq ($(OS),Windows_NT)
	@$(DOCKER_BIN) info --format '{{.ServerVersion}}' >NUL 2>&1 || ( echo "ERROR: cannot reach Docker daemon. Start Docker Desktop and retry." && exit 1 )
else
	@$(DOCKER_BIN) info --format '{{.ServerVersion}}' >/dev/null 2>&1 || ( \
	  echo "ERROR: cannot reach Docker daemon via '$(DOCKER_BIN)'."; \
	  if [ "$(IS_WSL)" = "1" ]; then \
	    echo "  You are inside WSL. Open Docker Desktop -> Settings -> Resources -> WSL Integration"; \
	    echo "  and enable integration for this distro, then re-open your shell."; \
	  else \
	    echo "  Start the Docker daemon (e.g. 'sudo systemctl start docker') and retry."; \
	  fi; \
	  exit 1 )
endif
	@echo "Docker OK"

# -- Full bring-up including build + bootstrap (use after Dockerfile/composer changes)
dev-full: setup doctor build up wait-db wait-mongo init jwt-keys models-pipeline fix-perms wait-http status
	@echo.
	@echo Stack ready -- open http://localhost:$${HTTP_HOST_PORT:-8080}

# Fix var/ ownership so PHP-FPM (www-data) can write hydrators/cache/log.
fix-perms:
	-$(DC) exec -T -u 0:0 php sh -lc "chown -R www-data:www-data /var/www/html/var && chmod -R u+rwX,g+rwX /var/www/html/var"

# -- Bootstrap --------------------------------------------------------------
setup:
	@$(SETUP)

# -- Build / lifecycle ------------------------------------------------------
build:
	$(DC) --profile tools build --parallel

up:
	$(DC) up -d

# Idempotent up: don't recreate containers whose config hasn't changed.
up-fast:
	$(DC) up -d --no-recreate

down:
	$(DC) down

restart: down up

logs:
	$(DC) logs -f --tail=100

ps:
	$(DC) ps

status: ps urls

urls open:
	@echo App:      http://localhost:$${HTTP_HOST_PORT:-8080}
	@echo Postgres: 127.0.0.1:$${POSTGRES_HOST_PORT:-5432}
	@echo Mongo:    127.0.0.1:$${MONGO_HOST_PORT:-27017}

# -- Readiness probes -------------------------------------------------------
wait-db:
	@echo "==> Waiting for Postgres to be healthy..."
ifeq ($(OS),Windows_NT)
	@$(PWSH) -Command "for ($$i=0; $$i -lt 60; $$i++) { $$s = ($(DOCKER_BIN) inspect -f '{{.State.Health.Status}}' backend_postgres 2>$$null); if ($$s -eq 'healthy') { Write-Host 'Postgres healthy'; exit 0 }; Start-Sleep -Seconds 2 }; Write-Host 'Postgres not healthy'; exit 1"
else
	@for i in $$(seq 1 60); do s=$$($(DOCKER_BIN) inspect -f '{{.State.Health.Status}}' backend_postgres 2>/dev/null); if [ "$$s" = "healthy" ]; then echo "Postgres healthy"; exit 0; fi; sleep 2; done; echo "Postgres not healthy"; exit 1
endif

wait-mongo:
	@echo "==> Waiting for Mongo to be healthy..."
ifeq ($(OS),Windows_NT)
	@$(PWSH) -Command "for ($$i=0; $$i -lt 60; $$i++) { $$s = ($(DOCKER_BIN) inspect -f '{{.State.Health.Status}}' backend_mongo 2>$$null); if ($$s -eq 'healthy') { Write-Host 'Mongo healthy'; exit 0 }; Start-Sleep -Seconds 2 }; Write-Host 'Mongo not healthy'; exit 1"
else
	@for i in $$(seq 1 60); do s=$$($(DOCKER_BIN) inspect -f '{{.State.Health.Status}}' backend_mongo 2>/dev/null); if [ "$$s" = "healthy" ]; then echo "Mongo healthy"; exit 0; fi; sleep 2; done; echo "Mongo not healthy"; exit 1
endif

wait-http:
	@echo "==> Waiting for HTTP on http://localhost:$${HTTP_HOST_PORT:-8080}/ ..."
ifeq ($(OS),Windows_NT)
	@$(PWSH) -Command "for ($$i=0; $$i -lt 60; $$i++) { try { $$r = Invoke-WebRequest -UseBasicParsing -Uri 'http://localhost:8080/' -TimeoutSec 15; if ($$r.StatusCode -eq 200) { Write-Host ('HTTP 200 OK ({0} bytes)' -f $$r.RawContentLength); exit 0 } } catch {}; Start-Sleep -Seconds 3 }; Write-Host 'HTTP not ready'; exit 1"
else
	@for i in $$(seq 1 60); do code=$$(curl -s -m 15 -o /dev/null -w '%{http_code}' http://localhost:$${HTTP_HOST_PORT:-8080}/ || true); if [ "$$code" = "200" ]; then echo "HTTP 200 OK"; exit 0; fi; sleep 3; done; echo "HTTP not ready"; exit 1
endif

# -- Symfony bootstrap ------------------------------------------------------
init:
	$(SF_INIT)

shell: shell-php

shell-php:
	$(DC) exec php sh

shell-postgres psql:
	$(DC) exec -e PGPASSWORD=$${POSTGRES_PASSWORD:-baas_app_pwd} postgres psql -U $${POSTGRES_USER:-baas} -d $${POSTGRES_DB:-baas}

shell-mongo mongo-shell:
	$(DC) exec mongo mongosh --quiet -u $${MONGO_APP_USER:-baas} -p $${MONGO_APP_PASSWORD:-baas_app_pwd} --authenticationDatabase $${MONGO_DB:-baas} $${MONGO_DB:-baas}

composer:
	$(COMPOSER) $(ARGS)

symfony:
	$(SYMFONY) $(ARGS)

cache-clear:
	$(PHP_EXEC) php bin/console cache:clear

# -- Database ---------------------------------------------------------------
db-create:
	$(PHP_EXEC) php bin/console doctrine:database:create --if-not-exists

db-drop:
	$(PHP_EXEC) php bin/console doctrine:database:drop --force --if-exists --full-database

migrate:
	$(PHP_EXEC) php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

db-seed:
	-$(PHP_EXEC) php bin/console doctrine:fixtures:load --no-interaction --purge-with-truncate
	-$(PHP_EXEC) php bin/console app:mongo:schema:update
	-$(PHP_EXEC) php bin/console app:mongo:seed --no-interaction

db-reset: db-drop db-create migrate db-seed

# -- Models / generator -----------------------------------------------------
models-validate:
	$(PHP_EXEC) php bin/console app:models:validate

models-generate:
	$(PHP_EXEC) php bin/console app:models:generate --force

models-pipeline: models-generate migrate db-seed

# -- JWT --------------------------------------------------------------------
jwt-keys:
	-$(PHP_EXEC) sh -c 'test -f config/jwt/private.pem || php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction'

# -- Tests ------------------------------------------------------------------
test:
	$(PHP_EXEC) php bin/phpunit

# -- Cleanup ----------------------------------------------------------------
clean:
	$(DC) down --remove-orphans

prune:
	$(DC) down -v --remove-orphans

reset: prune build up init
	@echo Stack reset -- http://localhost:8080
