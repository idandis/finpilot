#!/usr/bin/env bash
set -euo pipefail

# Esporta l'intero database MySQL di FinPilot (schema + dati di TUTTE le
# tabelle, incluse quelle aggiunte di recente) in un file .sql, applicando
# prima le migration pendenti e assicurandosi che le categorie di
# transazione standard (vedi database/seeders/TransactionCategorySeeder.php)
# siano presenti.
#
# Uso:
#   scripts/export-db.sh [file_di_output]

cd "$(dirname "${BASH_SOURCE[0]}")/.."

ENV_FILE=".env"
if [ ! -f "$ENV_FILE" ]; then
    echo "Errore: file .env non trovato." >&2
    exit 1
fi

env_var() {
    grep -E "^$1=" "$ENV_FILE" | tail -n1 | cut -d '=' -f2- | tr -d '"' || true
}

DB_DATABASE=$(env_var DB_DATABASE)
DB_USERNAME=$(env_var DB_USERNAME)
DB_PASSWORD=$(env_var DB_PASSWORD)
# In Sail, DB_HOST/DB_PORT in .env sono valori interni alla rete Docker (es. "mysql").
# Dall'host si usa invece la porta pubblicata da compose.yaml (FORWARD_DB_PORT, default 3306).
FORWARD_DB_PORT=$(env_var FORWARD_DB_PORT)

if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
    echo "Errore: DB_DATABASE o DB_USERNAME mancanti in .env." >&2
    exit 1
fi

OUTPUT_DIR="database/backups"
mkdir -p "$OUTPUT_DIR"
OUTPUT_FILE="${1:-$OUTPUT_DIR/finpilot-mysql-export_$(date +%Y%m%d_%H%M%S).sql}"

SAIL="./vendor/bin/sail"
use_sail=false

if [ ! -x "$SAIL" ]; then
    echo "==> Diagnostica: $SAIL non trovato o non eseguibile - uso PHP locale."
elif ! command -v docker >/dev/null 2>&1; then
    echo "==> Diagnostica: comando 'docker' non trovato nel PATH - uso PHP locale."
else
    RUNNING_CONTAINERS=$(docker ps --format '{{.Names}}' 2>/dev/null || true)
    echo "==> Diagnostica: container Docker attivi rilevati:"
    if [ -z "$RUNNING_CONTAINERS" ]; then
        echo "    (nessuno - 'docker ps' non ha restituito container, o Docker non è in esecuzione)"
    else
        echo "$RUNNING_CONTAINERS" | sed 's/^/    /'
    fi

    if grep -qi "laravel.test" <<< "$RUNNING_CONTAINERS"; then
        use_sail=true
    else
        echo "==> Diagnostica: nessun container con 'laravel.test' nel nome tra quelli attivi - uso PHP locale."
    fi
fi

if [ "$use_sail" = true ]; then
    echo "==> Uso Sail (docker exec) per i comandi artisan."
else
    echo "==> Uso PHP locale per i comandi artisan (DB_HOST=$( env_var DB_HOST ):$( env_var DB_PORT ))."
fi

echo "==> Applico le migration (garantisce che le tabelle create di recente esistano prima del dump)..."
if [ "$use_sail" = true ]; then
    "$SAIL" artisan migrate --force
else
    php artisan migrate --force
fi

echo "==> Verifico che le categorie standard siano seedate..."
if [ "$use_sail" = true ]; then
    "$SAIL" artisan db:seed --class=TransactionCategorySeeder --force
else
    php artisan db:seed --class=TransactionCategorySeeder --force
fi

echo "==> Esporto il database '$DB_DATABASE' in $OUTPUT_FILE..."

# Dump completo: schema + dati di tutte le tabelle, routine, trigger ed eventi.
# --single-transaction garantisce uno snapshot coerente senza bloccare le tabelle InnoDB.
MYSQLDUMP_OPTS=(
    --single-transaction
    --routines
    --triggers
    --events
    --add-drop-table
    --default-character-set=utf8mb4
    --skip-comments
    --column-statistics=0
    --no-tablespaces
    --set-gtid-purged=OFF
)

if [ "$use_sail" = true ]; then
    # Esegue mysqldump DENTRO il container mysql, cosi' il client ha sempre
    # la stessa versione del server - un mysqldump piu' recente sull'host
    # (es. da Homebrew) puo' interrogare tabelle di information_schema che
    # non esistono ancora sul server 8.0 di Sail e far fallire il dump.
    # Usa root (MYSQL_ROOT_PASSWORD in compose.yaml = DB_PASSWORD): l'utente
    # applicativo "sail" non ha i privilegi RELOAD/FLUSH_TABLES che
    # mysqldump richiede internamente anche con --single-transaction.
    docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" mysql \
        mysqldump --user=root "${MYSQLDUMP_OPTS[@]}" "$DB_DATABASE" > "$OUTPUT_FILE"
else
    MYSQL_PWD="$DB_PASSWORD" mysqldump \
        --host="127.0.0.1" \
        --port="${FORWARD_DB_PORT:-3306}" \
        --user="$DB_USERNAME" \
        "${MYSQLDUMP_OPTS[@]}" \
        "$DB_DATABASE" > "$OUTPUT_FILE"
fi

echo "==> Export completato: $OUTPUT_FILE ($(du -h "$OUTPUT_FILE" | cut -f1))"
