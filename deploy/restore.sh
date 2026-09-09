#!/usr/bin/env bash
#
# Repor uma cópia de segurança em produção. Correr NO SERVIDOR:
#
#     ./deploy/restore.sh 2026-08-28_033000
#
# (o nome da pasta em storage/backups — listar com ./deploy/restore.sh --list)
#
# Repõe a base de dados E os ficheiros carregados tal como estavam nessa cópia.
# O que foi feito depois dessa hora perde-se: pede confirmação explícita.
set -euo pipefail

cd "$(dirname "$0")/.."
COMPOSE="docker compose -f compose.production.yaml"

if [ "${1:-}" = "--list" ] || [ -z "${1:-}" ]; then
    echo "Cópias disponíveis:"
    $COMPOSE exec -T app sh -c 'ls -1 storage/backups'
    exit 0
fi

CARIMBO="$1"

if ! $COMPOSE exec -T app sh -c "test -d storage/backups/${CARIMBO}"; then
    echo "Não existe storage/backups/${CARIMBO}." >&2
    exit 1
fi

echo "Vai repor a base de dados e os ficheiros de ${CARIMBO}."
echo "Tudo o que foi feito depois dessa cópia PERDE-SE."
read -r -p "Escrever REPOR para continuar: " resposta
[ "$resposta" = "REPOR" ] || { echo "Cancelado."; exit 1; }

echo "→ Site em manutenção"
$COMPOSE exec -T app php artisan down --retry=60 || true
$COMPOSE stop queue scheduler >/dev/null

echo "→ Base de dados"
# A palavra-passe vai por MYSQL_PWD, nunca na linha de comandos. A base é
# recriada vazia com a colação portuguesa e o ficheiro entra por cima.
$COMPOSE exec -T app sh -c '
    set -e
    f=$(ls storage/backups/'"$CARIMBO"'/*.sql.gz | head -1)
    export MYSQL_PWD="$DB_PASSWORD"
    mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -e "DROP DATABASE IF EXISTS \`$DB_DATABASE\`; CREATE DATABASE \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
    gunzip -c "$f" | mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" "$DB_DATABASE"
'

echo "→ Ficheiros carregados"
# O arquivo traz "public/" e "private/" na raiz, que são as pastas de
# storage/app (ver BackupRun::arquivarFicheiros). Extrair para /var/www/html
# punha as fotografias dentro da raiz web — tem de ser para storage/app.
$COMPOSE exec -T app sh -c '
    set -e
    f=$(ls storage/backups/'"$CARIMBO"'/*.tar.gz | head -1)
    tar -xzf "$f" -C /var/www/html/storage/app
'

echo "→ Caches e arranque"
$COMPOSE exec -T app php artisan optimize:clear >/dev/null
$COMPOSE exec -T app php artisan optimize >/dev/null
$COMPOSE start queue scheduler >/dev/null
$COMPOSE exec -T app php artisan up

echo "Reposto de ${CARIMBO}."
