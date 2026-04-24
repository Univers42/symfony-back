# Symfony Backend — Dockerized Stack

Apache 2.4 · PHP-FPM 8.3 · MySQL 8 · Composer · Symfony CLI

Fully cross-platform (Linux/macOS/Windows). No host dependency required other
than **Docker Desktop** (or Docker Engine + Compose v2) and **GNU Make**
(use *Git Bash* on Windows, or run the commands directly).

## Architecture

```
        ┌──────────┐   :8080      ┌──────────┐  fcgi:9000   ┌──────────┐
host ──▶│  apache  │─────────────▶│   php    │─────────────▶│  mysql   │
        │  (httpd) │              │  (fpm)   │              │   (8.0)  │
        └──────────┘              └──────────┘              └──────────┘
                  shared bind-mount: ./app  ──────────────▶ /var/www/html
```

Two **utility** images (profile `tools`, started on demand only):

- `composer` — runs `composer …`
- `symfony`  — runs `symfony …` and the `init-symfony` bootstrap script

## Quick start

```bash
make setup     # copies .env, creates ./app and ./secrets
make build     # builds all images in parallel (BuildKit + Bake cache)
make init      # creates Symfony project + first page in ./app
make up        # starts apache + php + mysql
```

Open <http://localhost:8080> — the welcome page is served.

## Common commands

```bash
make help                          # list every target
make logs                          # tail logs
make shell-php                     # shell into the php container
make composer ARGS="require twig/extra-bundle"
make symfony  ARGS="console make:controller"
make migrate                       # run Doctrine migrations
make reset                         # nuke + rebuild + restart
```

## Secrets

DB passwords are read from `./secrets/*.txt` files exposed to the MySQL
container as Docker secrets (`/run/secrets/mysql_*`). `./secrets/` and
`.env` are git-ignored — never commit them.

## Build-speed optimisations

- Alpine-based images (php, apache, composer, symfony-cli)
- `# syntax=docker/dockerfile:1.7` + BuildKit cache mounts on `apk` & `pear`
- Composer pulled from the official `composer:2` image (no rebuild)
- `COMPOSE_BAKE=true` + `--parallel` build all services concurrently
- Persistent `composer_cache` volume → instant subsequent `composer install`s
- Single `RUN` layer per Dockerfile to maximise cache reuse
