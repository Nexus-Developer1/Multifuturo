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

# O Cron corre com o PHP do sistema, que aqui é o 7.4 — o domínio é que está
# em 8.3. Neste alojamento o 8.3 é o alt-php83 (CloudLinux); as outras
# hipóteses ficam para o caso de a conta mudar de servidor.
PHP=""
for candidato in /opt/alt/php83/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /usr/local/bin/ea-php83 /usr/bin/ea-php83; do
    if [ -x "$candidato" ]; then PHP="$candidato"; break; fi
done
[ -z "$PHP" ] && PHP="php"

[ -f "$MANIFEST" ] || exit 0

VERSAO=$(head -n 1 "$MANIFEST" | tr -d '\r')
[ -f "$APLICADO" ] && [ "$(tr -d '\r' < "$APLICADO")" = "$VERSAO" ] && exit 0

exec > "$LOG" 2>&1

# O PHP encontrado tem de ser o do domínio: com o 7.4 do sistema, o artisan
# estoirava a meio e o registo não explicava porquê.
VERSAO_PHP=$("$PHP" -r 'echo PHP_VERSION;' 2>/dev/null)
case "$VERSAO_PHP" in
    8.3*|8.4*|8.5*) ;;
    *)
        echo "FALHOU - o PHP encontrado é o $VERSAO_PHP ($PHP); o projeto precisa do 8.3."
        echo "         Corrija o caminho na lista de candidatos, no topo deste guião."
        exit 1
        ;;
esac

# As extensões do PHP que o projeto usa (composer.lock + base de dados). Com
# uma a faltar, o artisan estoirava no primeiro passo que a usasse; assim o
# registo diz logo todas as que faltam.
FALTAM=$("$PHP" -r 'echo implode(" ", array_filter(["ctype", "dom", "fileinfo", "filter", "iconv", "intl", "libxml", "mbstring", "openssl", "pdo_mysql", "session", "tokenizer", "xmlreader", "zip"], fn ($e) => ! extension_loaded($e)));')
if [ -n "$FALTAM" ]; then
    echo "FALHOU - faltam extensões do PHP: $FALTAM"
    echo "         Ativar no cPanel, em Select PHP Version > Extensions (versão 8.3)."
    exit 1
fi

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
echo "== PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;' 2>/dev/null))"

# O .htaccess do public_html nunca se substitui: além das regras do Laravel,
# leva o bloco que o cPanel escreve para escolher a versão de PHP do domínio.
# Só se cria se faltar, a partir do modelo que vem no pacote.
if [ ! -f "$WEB/.htaccess" ] && [ -f "$WEB/.htaccess.modelo" ]; then
    cp "$WEB/.htaccess.modelo" "$WEB/.htaccess"
    echo "== .htaccess criado do modelo"
    echo "   Confirme a versão de PHP do domínio no MultiPHP Manager (8.3)."
fi

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
