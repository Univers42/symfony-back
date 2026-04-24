#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# init-symfony — bootstrap a Symfony project & a first landing page
# Strategy: build the project in /tmp (container-local FS, fast on all OSes),
# then bulk-copy into /var/www/html (bind mount). Avoids slow per-file ops on
# Windows/macOS bind mounts.
# Idempotent: skips creation if project already present.
# ---------------------------------------------------------------------------
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"
BUILD_DIR="/tmp/symfony_build"

echo "==> Bootstrapping Symfony in $APP_DIR"

if [ -f "$APP_DIR/composer.json" ] && [ -d "$APP_DIR/src" ]; then
    echo "    Symfony project already present -- skipping creation."
else
    rm -rf "$BUILD_DIR"
    mkdir -p "$BUILD_DIR"
    cd "$BUILD_DIR"

    echo "==> [1/3] composer create-project symfony/skeleton (in $BUILD_DIR)"
    composer create-project \
        symfony/skeleton:^7.1 \
        . \
        --no-interaction \
        --prefer-dist \
        --no-progress

    echo "==> [2/3] composer require webapp + maker"
    composer require --no-interaction --no-progress webapp
    composer require --no-interaction --no-progress --dev maker

    echo "==> [3/3] Bulk-copying project into $APP_DIR"
    mkdir -p "$APP_DIR"
    # Copy everything including dotfiles, then clean staging area
    ( cd "$BUILD_DIR" && tar cf - . ) | ( cd "$APP_DIR" && tar xf - )
    rm -rf "$BUILD_DIR"
fi

cd "$APP_DIR"

# ----- First landing page ---------------------------------------------------
CTRL_FILE="src/Controller/HomeController.php"
TPL_FILE="templates/home/index.html.twig"

mkdir -p "$(dirname "$CTRL_FILE")" "$(dirname "$TPL_FILE")"

if [ ! -f "$CTRL_FILE" ]; then
    echo "==> Creating HomeController"
    cat > "$CTRL_FILE" <<'PHP'
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'title'   => 'Symfony Backend',
            'php'     => PHP_VERSION,
            'symfony' => \Symfony\Component\HttpKernel\Kernel::VERSION,
        ]);
    }
}
PHP
fi

if [ ! -f "$TPL_FILE" ]; then
    echo "==> Creating home template"
    cat > "$TPL_FILE" <<'TWIG'
{% extends 'base.html.twig' %}

{% block title %}{{ title }}{% endblock %}

{% block body %}
<style>
    body { font-family: system-ui, sans-serif; background:#0f172a; color:#e2e8f0;
           display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
    .card { background:#1e293b; padding:3rem; border-radius:1rem;
            box-shadow:0 10px 40px rgba(0,0,0,.4); text-align:center; max-width:480px; }
    h1   { margin:0 0 1rem; color:#38bdf8; font-size:2rem; }
    code { background:#0f172a; padding:.2rem .5rem; border-radius:.3rem; color:#fbbf24; }
    p    { line-height:1.6; }
</style>
<div class="card">
    <h1>{{ title }}</h1>
    <p>Dockerized Symfony stack is up and running.</p>
    <p>PHP <code>{{ php }}</code> &middot; Symfony <code>{{ symfony }}</code></p>
</div>
{% endblock %}
TWIG
fi

mkdir -p var
chmod -R 0777 var || true

echo "==> Done. Visit http://localhost:8080"