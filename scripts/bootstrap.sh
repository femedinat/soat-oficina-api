#!/usr/bin/env bash
# ============================================================================
# Executado UMA ÚNICA VEZ por quem cria o repositório.
# Gera o esqueleto do Laravel 13 e aplica este scaffold por cima.
# Os demais integrantes só precisam: git clone + make setup
# Requisitos: Docker + Docker Compose v2. Nada de PHP/Composer na máquina.
# ============================================================================
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$(pwd)"
TMP="laravel-skeleton"

if [ -f artisan ]; then
  echo "⚠️  Laravel já existe neste diretório. Abortando."; exit 1
fi

echo "▶ 1/6 Gerando skeleton Laravel 13 (via container composer)..."
docker run --rm -u "$(id -u):$(id -g)" -v "$ROOT":/app -w /app composer:2 \
  create-project laravel/laravel:^13.0 "$TMP" --prefer-dist --no-interaction --no-scripts

echo "▶ 2/6 Mesclando skeleton (arquivos do scaffold têm prioridade)..."
cp -Rn "$TMP"/. .
rm -rf "$TMP"
rm -f database/database.sqlite
# README do Laravel não sobrescreve o nosso (cp -n), mas remove o CHANGELOG padrão se vier
rm -f CHANGELOG.md

echo "▶ 3/6 Subindo containers..."
cp -n .env.example .env || true
docker compose up -d --build

APP="docker compose exec -T app"

echo "▶ 4/6 Registrando namespace de domínio (Oficina\\ -> src/)..."
$APP php -r '
$f = "composer.json";
$c = json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR);
$c["autoload"]["psr-4"] = ["App\\" => "app/", "Oficina\\" => "src/"] + $c["autoload"]["psr-4"];
file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
'

echo "▶ 5/6 Instalando dependências..."
$APP composer install --no-interaction
# JWT (fork mantido do tymon/jwt-auth, que foi abandonado)
$APP composer require php-open-source-saver/jwt-auth --no-interaction \
  || echo "❗ jwt-auth incompatível no momento: avaliem firebase/php-jwt atrás de uma porta TokenIssuer (ver docs/adr)."
# OpenAPI gerado a partir do código (sem annotations manuais)
$APP composer require dedoc/scramble --no-interaction \
  || echo "❗ scramble incompatível no momento: alternativa darkaonline/l5-swagger."
$APP composer require --dev larastan/larastan --no-interaction
$APP php artisan install:api --no-interaction || true
$APP php artisan vendor:publish --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider" || true
$APP composer dump-autoload

echo "▶ 6/6 Chaves, migrations e verificação..."
$APP php artisan key:generate
$APP php artisan jwt:secret --force || true
$APP php artisan migrate --no-interaction
$APP ./vendor/bin/pint --quiet || true
$APP php artisan test

echo ""
echo "✅ Projeto pronto. Próximo passo: ./scripts/setup-github.sh <org-ou-usuario>/<repo>"
