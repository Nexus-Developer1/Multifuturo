#!/bin/sh
# Primeira instalação da Multifuturo no cPanel, para contas SEM Terminal/SSH.
#
# Corre uma vez, pelo mesmo mecanismo de Cron Job do atualizar.sh:
#   tr -d '\r' < $HOME/multifuturo/instalar.sh | /bin/sh > $HOME/cron-debug.txt 2>&1
#
# Antes de correr: extrair o pacote em $HOME e criar o $HOME/multifuturo/.env a
# partir do .env.cpanel.example, com a base de dados e o email do cPanel já
# preenchidos. A APP_KEY pode ficar vazia — é gerada aqui.
#
# O que faz: gera a APP_KEY (se faltar), cria a base de dados por migrações,
# põe o atalho das fotografias, gera as caches e corrige as permissões. Não
# apaga nada e pode correr outra vez sem estragar o que já existe.
# O resultado fica em storage/instalacao.log.

APP="$HOME/multifuturo"
WEB="$HOME/public_html"
LOG="$APP/storage/instalacao.log"

cd "$APP" || exit 1

PHP=""
for candidato in /opt/cpanel/ea-php83/root/usr/bin/php /usr/local/bin/ea-php83 /usr/bin/ea-php83; do
    if [ -x "$candidato" ]; then PHP="$candidato"; break; fi
done
[ -z "$PHP" ] && PHP="php"

exec > "$LOG" 2>&1

passo() {
    nome="$1"; shift
    echo "== $nome"
    if ! "$@"; then
        echo "FALHOU no passo: $nome"
        exit 1
    fi
}

echo "== $(date)"
echo "== PHP: $PHP"

if [ ! -f "$APP/.env" ]; then
    echo "FALHOU - falta o .env (copiar do .env.cpanel.example e preencher)"
    exit 1
fi

# O editor do cPanel grava com fins de linha do Windows: "APP_KEY=" pode vir
# seguido de \r e parecer preenchido.
CHAVE=$(grep '^APP_KEY=' "$APP/.env" | head -n 1 | cut -d= -f2- | tr -d '\r')
if [ -z "$CHAVE" ]; then
    passo "key:generate" "$PHP" artisan key:generate --force
fi

passo "migrate" "$PHP" artisan migrate --force

# O .htaccess vem no pacote como modelo, para não pisar o bloco que o cPanel
# escreve nele com a versão de PHP do domínio.
if [ ! -f "$WEB/.htaccess" ] && [ -f "$WEB/.htaccess.modelo" ]; then
    cp "$WEB/.htaccess.modelo" "$WEB/.htaccess"
    echo "== .htaccess criado do modelo"
fi

if [ ! -e "$WEB/storage" ]; then
    echo "== atalho das fotografias"
    ln -s "$APP/storage/app/public" "$WEB/storage"
fi

echo "== permissoes"
find "$APP" "$WEB" -path "$WEB/storage" -prune -o -type d -exec chmod 755 {} +
find "$APP" "$WEB" -path "$WEB/storage" -prune -o -type f -exec chmod 644 {} +
chmod 600 "$APP/.env"
chmod -R 775 "$APP/storage" "$APP/bootstrap/cache"

passo "optimize" "$PHP" artisan optimize
passo "filament:optimize" "$PHP" artisan filament:optimize

[ -f "$APP/deploy-manifest.txt" ] && head -n 1 "$APP/deploy-manifest.txt" | tr -d '\r' > "$APP/storage/deploy-aplicado.txt"

echo "FIM - instalação concluída"
echo "Falta criar a primeira conta da equipa (Utilizadores, no portal) e trocar o Cron para o atualizar.sh."
