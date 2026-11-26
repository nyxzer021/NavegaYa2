#!/bin/sh
# Restaura un archivo producido por backup-database.sh.
# Reemplaza los datos de la base del VPS. Exige --confirmo para no ejecutarse por accidente.
set -eu

if [ "${1:-}" = "" ] || [ "${2:-}" != "--confirmo" ]; then
  echo "Uso: restore-database.sh /ruta/navegaya-FECHA.dump --confirmo" >&2
  echo "Reemplaza la base de este VPS. No lo apuntes a Railway." >&2
  exit 1
fi

dump=$1
if [ ! -s "$dump" ]; then
  echo "El archivo de respaldo no existe o está vacío." >&2
  exit 1
fi

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

project=$(read_var COMPOSE_PROJECT_NAME)

compose() {
  if [ -n "$project" ]; then
    docker compose -p "$project" --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
  else
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
  fi
}

compose stop app worker scheduler
set +e
compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-acl --exit-on-error' < "$dump"
status=$?
set -e
compose start app worker scheduler

if [ "$status" -ne 0 ]; then
  echo "La restauración falló. Los servicios volvieron a arrancar." >&2
  exit "$status"
fi

echo "Restauración terminada. Revisa la ruta /up del sitio."
