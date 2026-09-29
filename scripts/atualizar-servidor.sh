#!/bin/sh
# Aplica um pacote novo da Multifuturo no cPanel, para contas SEM Terminal/SSH.
#
# O pacote (scripts/deploy-pacote.ps1) traz este guião como
# ~/multifuturo/atualizar.sh e um manifesto (~/multifuturo/deploy-manifest.txt)
# com a versão e a lista dos ficheiros de código. Um Cron Job pode ficar a
# correr de minuto a minuto com:
#   tr -d '\r' < $HOME/multifuturo/atualizar.sh | /bin/sh > $HOME/cron-debug.txt 2>&1
# Só faz alguma coisa quando o manifesto traz uma versão ainda não aplicada:
# depois de extrair um ZIP novo, a atualização fica feita no minuto seguinte.
#
# O que faz, por esta ordem:
#   1. apaga os ficheiros de código que já não fazem parte do pacote (extrair
#      um ZIP por cima não apaga nada, e um ficheiro antigo esquecido em app/
#      voltaria a valer no site);
#   2. corre as migrações da base de dados;
#   3. garante o atalho das fotografias em public_html/storage;
#   4. volta a gerar as caches e corrige as permissões.
# O resultado fica em storage/atualizacao.log (última linha FIM ou FALHOU).
#
# O .env, o storage/ (fotografias, documentos, cópias de segurança) e a base de
# dados nunca são tocados.

APP="$HOME/multifuturo"
WEB="$HOME/public_html"
MANIFEST="$APP/deploy-manifest.txt"
APLICADO="$APP/storage/deploy-aplicado.txt"
LOG="$APP/storage/atualizacao.log"

cd "$APP" || exit 1

# O Cron usa a versão de PHP do sistema, que pode não ser a do domínio.
PHP=""
for candidato in /opt/cpanel/ea-php83/root/usr/bin/php /usr/local/bin/ea-php83 /usr/bin/ea-php83; do
    if [ -x "$candidato" ]; then PHP="$candidato"; break; fi
done
[ -z "$PHP" ] && PHP="php"

[ -f "$MANIFEST" ] || exit 0

VERSAO=$(head -n 1 "$MANIFEST" | tr -d '\r')
[ -f "$APLICADO" ] && [ "$(tr -d '\r' < "$APLICADO")" = "$VERSAO" ] && exit 0

exec > "$LOG" 2>&1

# Cada passo é verificado aqui: "set -e" não serve, porque o sh ignora-o em
# vários contextos e o guião seguiria em frente depois de um erro.
passo() {
    nome="$1"; shift
    echo "== $nome"
    if ! "$@"; then
        echo "FALHOU no passo: $nome"
        exit 1
    fi
}

echo "== $(date)"
echo "== versão: $VERSAO"
echo "== PHP: $PHP"

# 1. Ficheiros que saíram do pacote. Só nas pastas de código: storage/,
#    vendor/, .env e as fotografias nunca são tocados.
echo "== ficheiros removidos do pacote"
tail -n +2 "$MANIFEST" | tr -d '\r' | sort > "$APP/storage/.manifesto-novo"
find app bootstrap config database lang resources routes -type f ! -path 'bootstrap/cache/*' | sort > "$APP/storage/.manifesto-atual"
comm -13 "$APP/storage/.manifesto-novo" "$APP/storage/.manifesto-atual" | while IFS= read -r antigo; do
    echo "   apagado: $antigo"
    rm -f "$antigo"
done
find app resources database lang -type d -empty -delete
rm -f "$APP/storage/.manifesto-novo" "$APP/storage/.manifesto-atual"

# Caches do pacote anterior (configuração, rotas, vistas, pacotes).
rm -f bootstrap/cache/*.php

# Sobra de um envio antigo: com uma pasta public/ dentro do projeto, o
# bootstrap/app.php deixa de usar o public_html e o storage:link e os assets do
# Filament vão parar a uma pasta fora da web. Avisa-se, mas não se apaga nada.
if [ -d "$APP/public" ]; then
    echo "AVISO: existe $APP/public, de um envio anterior."
    echo "       A pasta pública é $WEB — apague $APP/public no Gestor de Ficheiros."
fi

passo "migrate" "$PHP" artisan migrate --force

# 3. Fotografias dos imóveis: public_html/storage aponta para storage/app/public.
if [ ! -e "$WEB/storage" ]; then
    echo "== atalho das fotografias"
    ln -s "$APP/storage/app/public" "$WEB/storage"
fi

echo "== permissoes"
find "$APP" "$WEB" -path "$WEB/storage" -prune -o -type d -exec chmod 755 {} +
find "$APP" "$WEB" -path "$WEB/storage" -prune -o -type f -exec chmod 644 {} +
chmod 600 "$APP/.env"
chmod -R 775 "$APP/storage" "$APP/bootstrap/cache"

passo "package:discover" "$PHP" artisan package:discover
passo "optimize" "$PHP" artisan optimize
passo "filament:optimize" "$PHP" artisan filament:optimize

echo "$VERSAO" > "$APLICADO"
echo "FIM - versão $VERSAO aplicada"
