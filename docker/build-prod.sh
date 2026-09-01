#!/bin/sh
# Build de produção em duas etapas: PHP (pesado) primeiro, nginx depois (leve).
# No Coolify, use como comando de build se o deploy falhar com OOM no BuildKit.
set -eu

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"

docker compose -f "$COMPOSE_FILE" build app
docker compose -f "$COMPOSE_FILE" build nginx
