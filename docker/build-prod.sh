#!/bin/sh
# Build de produção: PHP primeiro, nginx depois (mesmo Dockerfile, targets distintos).
# No Coolify o compose já usa target php/nginx; este script só é necessário se o
# painel ainda construir os dois serviços em paralelo e estourar RAM.
set -eu

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"

docker compose -f "$COMPOSE_FILE" build app
docker compose -f "$COMPOSE_FILE" build nginx
