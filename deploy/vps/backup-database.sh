#!/bin/sh
# Respaldo diario de PostgreSQL, corrido en el VPS (fuera del contenedor).
# No imprime contraseñas. Escribe el archivo en BACKUP_DIR, en el disco del servidor.
set -eu

ROOT=$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)
ENV_FILE=${VPS_ENV_FILE:-$ROOT/.env.vps}
COMPOSE_FILE=${COMPOSE_FILE:-$ROOT/docker-compose.prod.yml}

if [ ! -f "$ENV_FILE" ]; then
  echo "Falta el archivo de variables del VPS." >&2
  exit 1
fi

read_var() {
  sed -n "s/^$1=//p" "$ENV_FILE" | tail -n 1 | sed 's/^"//; s/"$//; s/^'"'"'//; s/'"'"'$//'
}

backup_dir=$(read_var BACKUP_DIR)
if [ -z "$backup_dir" ]; then
  backup_dir=/var/backups/navegaya
fi
keep_days=$(read_var BACKUP_KEEP_DAYS)
if [ -z "$keep_days" ]; then
  keep_days=14
fi
project=$(read_var COMPOSE_PROJECT_NAME)

mkdir -p "$backup_dir"
stamp=$(date -u +%Y%m%dT%H%M%SZ)
out="$backup_dir/navegaya-$stamp.dump"

compose() {
  if [ -n "$project" ]; then
    docker compose -p "$project" --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
  else
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
  fi
}

compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc --no-owner --no-acl' > "$out"

if [ ! -s "$out" ]; then
  rm -f "$out"
  echo "El respaldo salió vacío. No se guardó nada." >&2
  exit 1
fi

find "$backup_dir" -type f -name 'navegaya-*.dump' -mtime +"$keep_days" -delete
echo "Respaldo listo: $out"
