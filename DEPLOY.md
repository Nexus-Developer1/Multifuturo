# Pôr o site em produção

Guia para instalar e manter o site e o backoffice da Multifuturo Propriedades
num servidor próprio, com Docker — a mesma base que corre em desenvolvimento,
sem surpresas. Do zero até ao site no ar são cerca de **uma hora**, a maior
parte à espera de construções e do DNS.

O que é preciso ter antes de começar está na secção 1. Se ainda faltar algum
dado (AMI, SMTP), a secção 3 diz o que acontece sem ele.

---

## 1. O que é preciso

| | Recomendação |
|---|---|
| **Servidor (VPS)** | 2 vCPU · 4 GB RAM · 40 GB SSD · Ubuntu 24.04 LTS. Chega para a agência inteira; em Portugal e na Europa custa 8–15 €/mês (Hetzner, OVH, Scaleway, ou o alojador que a agência já tiver, desde que dê acesso root e permita Docker). |
| **Domínio** | Ex.: `multifuturo.pt`, com acesso ao painel de DNS. |
| **Email SMTP** | Servidor, porta, utilizador e palavra-passe de um email do domínio (`site@multifuturo.pt`). Sem isto os pedidos do site não chegam a ninguém. |
| **Licença AMI** | O número. **A aplicação recusa arrancar em produção sem ele** (é obrigatório por lei em toda a comunicação de mediação). |
| **Acesso** | SSH ao servidor (utilizador com `sudo`) e acesso ao repositório no GitHub. |

> A aplicação corre em contentores com o seu próprio **Apache** — não é preciso
> instalar PHP no servidor. Para o HTTPS, o Apache (ou o painel) do anfitrião
> fica à frente a fazer proxy: ver a secção 10.

---

## 2. Preparar o servidor (uma vez)

Ligar por SSH e instalar o Docker (o guião oficial, um comando):

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"   # sair e voltar a entrar para fazer efeito
docker --version && docker compose version
```

Firewall: só SSH, HTTP e HTTPS.

```bash
sudo ufw allow OpenSSH && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp && sudo ufw --force enable
```

Clonar o projeto:

```bash
sudo mkdir -p /srv && sudo chown "$USER" /srv
cd /srv && git clone git@github.com:<organização>/<repositório>.git multifuturo
cd /srv/multifuturo
```

**DNS:** no painel do domínio, apontar `multifuturo.pt` (registo A) e
`www.multifuturo.pt` (A ou CNAME) para o IP do servidor. Fazer isto cedo —
pode levar de minutos a horas a propagar, e o certificado HTTPS (secção 10)
só é emitido quando o domínio já responder neste IP.

---

## 3. Configurar (`.env`)

```bash
cp .env.production.example .env
nano .env
```

Preencher o que está marcado **[OBRIGATÓRIO]**:

| Variável | O quê |
|---|---|
| `APP_KEY` | gerar: `docker compose -f compose.production.yaml run --rm --no-deps app php artisan key:generate --show` e colar |
| `APP_URL` | `https://multifuturo.pt` |
| `SITE_DOMAIN` | `multifuturo.pt` |
| `DB_PASSWORD` | `openssl rand -base64 32` |
| `MAIL_*` | os dados SMTP |
| `AGENCY_AMI` | o número da licença |
| `AGENCY_EMAIL`, `AGENCY_PHONE`, `AGENCY_ADDRESS` | contactos (aparecem no rodapé, contactos e textos legais) |

Sem AMI é possível arrancar num endereço de **pré-produção** para a agência
ver o site antes de abrir ao público: pôr `APP_ENV=staging` no `.env`. Nesse
modo o site funciona todo, mas o `robots.txt` bloqueia os motores de busca e o
rodapé mostra "Licença AMI: por atribuir". Ao mudar para `APP_ENV=production`
já tem de haver AMI.

> O `.env` **nunca** entra no Git (está no `.gitignore`). Guardar uma cópia
> num sítio seguro — é o que permite reconstruir o servidor.

---

## 4. Primeiro arranque

```bash
docker compose -f compose.production.yaml build          # ~5 min na primeira vez
docker compose -f compose.production.yaml up -d
docker compose -f compose.production.yaml exec app php artisan migrate --force
docker compose -f compose.production.yaml exec app php artisan make:filament-user
```

O último comando cria o **primeiro utilizador do backoffice** (nome, email,
palavra-passe). Depois, marcá-lo como administrador — é o administrador que
recebe os emails dos pedidos e gere os outros utilizadores:

```bash
docker compose -f compose.production.yaml exec app php artisan tinker --execute='App\Models\User::first()->forceFill(["is_admin" => true])->save(); echo "ok";'
```

Verificar:

```bash
docker compose -f compose.production.yaml ps            # cinco serviços "running"/"healthy"
curl -I http://localhost:8080/up                         # HTTP 200 (o contentor)
curl -I https://multifuturo.pt/up                        # HTTP 200 (com o proxy da secção 10 no ar)
```

Depois, no browser:

1. `https://multifuturo.pt` — site (vazio de imóveis, é normal).
2. `https://multifuturo.pt/admin` — entrar com o utilizador criado.
3. Criar a primeira ficha de imóvel, publicar, ver no site.
4. Fazer um pedido de informação no site e confirmar que o email chega à
   caixa do administrador. Se não chegar: secção 8.

---

## 5. Atualizar (cada vez que há código novo)

```bash
cd /srv/multifuturo && ./deploy/deploy.sh
```

O guião traz o código, **faz uma cópia de segurança antes de mexer**,
reconstrói as imagens, substitui os contentores, corre as migrações, reinicia
a fila e confirma que o site responde. Se algo falhar, pára nesse passo e o
que já estava a correr continua a correr. Demora 2–5 minutos; o site não fica
em baixo — os contentores novos substituem os antigos.

> **Porquê reiniciar a fila:** o worker que envia os emails é um processo de
> longa duração e não apanha código novo sozinho. O guião faz `queue:restart`
> por si; se fizer um deploy à mão, não se esquecer.

---

## 6. Cópias de segurança

- **Automáticas, todos os dias** à hora do `BACKUP_AT` (03:30): base de dados
  (`.sql.gz`) e ficheiros carregados (`.tar.gz`), guardadas `BACKUP_KEEP_DAYS`
  dias (14) no volume `backups`.
- **À mão**, antes de algo arriscado:
  `docker compose -f compose.production.yaml exec app php artisan backup:run`
- **Repor** uma cópia (base de dados e ficheiros, com confirmação):
  `./deploy/restore.sh --list` e depois `./deploy/restore.sh 2026-08-28_033000`

> **Uma cópia no próprio servidor não protege de um disco avariado nem de um
> servidor apagado.** Copiar as cópias para fora, uma vez por dia — o mais
> simples é um `rsync` a partir de outro computador, ou o serviço de snapshots
> do alojador (Hetzner, OVH e Scaleway têm, por 1–2 €/mês):
>
> ```bash
> # noutro computador, de madrugada (cron):
> rsync -az servidor:/var/lib/docker/volumes/multifuturo_backups/_data/ ~/backups-multifuturo/
> ```

---

## 7. Onde estão as coisas

| | |
|---|---|
| Código | `/srv/multifuturo` |
| Configuração | `/srv/multifuturo/.env` |
| Base de dados | volume Docker `multifuturo_mysql` |
| Fotografias das fichas | volume `multifuturo_storage_public` |
| Documentos das fichas (privados) | volume `multifuturo_storage_private` |
| Cópias de segurança | volume `multifuturo_backups` (`/var/lib/docker/volumes/multifuturo_backups/_data`) |
| Certificados HTTPS | no anfitrião, geridos pelo certbot (secção 10) |
| Logs | `docker compose -f compose.production.yaml logs -f app` (ou `queue`, `scheduler`) |

Os cinco serviços: `app` (Apache + PHP, a aplicação e os estáticos), `queue`
(emails), `scheduler` (cópias), `mysql`, `redis`. Todos arrancam
sozinhos com o servidor (`restart: unless-stopped`).

---

## 8. Se algo correr mal

| Sintoma | Ver |
|---|---|
| Site não abre / certificado inválido | O DNS já aponta para o servidor? `dig +short multifuturo.pt`. Portas 80 e 443 abertas na firewall? O proxy da secção 10 está no ar (`sudo systemctl status apache2`)? O contentor responde (`curl -I http://localhost:8080/up`)? |
| "A aplicação recusa arrancar" | `AGENCY_AMI` vazio com `APP_ENV=production`. Preencher ou usar `staging`. |
| Erro 500 | `docker compose -f compose.production.yaml logs --tail 200 app`. Com `APP_DEBUG=false` o visitante vê uma página genérica; o erro está no log. |
| Pedidos chegam ao backoffice mas não há email | 1) `MAIL_*` certos? Testar: `docker compose -f compose.production.yaml exec app php artisan tinker --execute='Mail::raw("teste", fn($m) => $m->to("o-seu@email.pt")->subject("teste"));'` 2) A fila está a correr? `docker compose -f compose.production.yaml ps queue` 3) Trabalhos falhados: `exec app php artisan queue:failed` (e `queue:retry all` depois de corrigir). |
| Emails caem no spam | O remetente (`MAIL_FROM_ADDRESS`) tem de ser do domínio, e o domínio precisa de registos **SPF** e **DKIM** no DNS — o fornecedor de email indica quais. |
| Fotografias não aparecem | `docker compose -f compose.production.yaml exec app php artisan storage:link` e ver permissões do volume. |
| Mudei o `.env` e não fez efeito | As caches geram-se no arranque de cada contentor: `docker compose -f compose.production.yaml restart app queue scheduler`. |
| Espaço em disco | `docker system df`; limpar imagens antigas com `docker image prune -f` (o deploy já o faz). |
| Voltar à versão anterior | `git log --oneline -5`, `git checkout <commit>` e `./deploy/deploy.sh`; se houve migrações destrutivas, `./deploy/restore.sh` com a cópia feita antes do deploy. |

---

## 9. Testar tudo isto localmente (opcional)

A mesma configuração de produção corre no computador de desenvolvimento, para
validar antes de ter servidor — com um certificado interno e noutras portas,
para não colidir com o ambiente Sail:

```bash
cp .env.production.example .env.prodlocal
# no .env.prodlocal: SITE_DOMAIN=localhost, APP_URL=http://localhost:8081, HTTP_PORT=8081,
# APP_ENV=staging, DB_PASSWORD=qualquer, APP_KEY gerada
export ENV_FILE=.env.prodlocal
alias dcp='docker compose -f compose.production.yaml -p multifuturo-prod --env-file .env.prodlocal'
dcp up -d --build
dcp exec app php artisan migrate --force
curl -I http://localhost:8081/up
dcp down -v   # limpar (apaga os volumes deste teste, não os do Sail)
```

---

## 10. O Apache do anfitrião e o HTTPS

O contentor `app` serve o site em HTTP na porta `HTTP_PORT` (8080). Quem fala
com o mundo é o **Apache do servidor anfitrião**, que termina o HTTPS e faz
proxy para o contentor — o Laravel já confia nos cabeçalhos `X-Forwarded-*`
de redes privadas, por isso os URLs saem certos.

Num VPS Ubuntu (uma vez):

```bash
sudo apt install -y apache2 certbot python3-certbot-apache
sudo a2enmod proxy proxy_http headers
sudo tee /etc/apache2/sites-available/multifuturo.conf > /dev/null <<'CONF'
<VirtualHost *:80>
    ServerName multifuturo.pt
    ServerAlias www.multifuturo.pt

    ProxyPreserveHost On
    ProxyPass        / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/
    RequestHeader set X-Forwarded-Proto "http"
</VirtualHost>
CONF
sudo a2ensite multifuturo && sudo a2dissite 000-default && sudo systemctl reload apache2

# Certificado (cria o vhost :443, ativa o redirecionamento e renova sozinho):
sudo certbot --apache -d multifuturo.pt -d www.multifuturo.pt
```

Depois do certbot, acrescentar ao vhost `*:443` que ele criou
(`/etc/apache2/sites-available/multifuturo-le-ssl.conf`):

```apache
    RequestHeader set X-Forwarded-Proto "https"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

e `sudo systemctl reload apache2`.

**Alojamento com painel (cPanel/Plesk):** a mesma ideia — o painel gere o
certificado e cria-se um proxy do domínio para `http://127.0.0.1:8080`. Sem
Docker no alojamento, o caminho é a secção 11: o código vai num pacote já
pronto e não é preciso nem Redis nem contentores.

O `docker/production/vhost.conf` (o Apache de dentro do contentor) já trata
dos cabeçalhos de segurança, da compressão e das caches dos estáticos.

## 11. Alojamento cPanel (é onde o site está)

`multifuturo.pt` vive num alojamento partilhado com cPanel, sem Docker e sem
Terminal: os comandos do servidor correm por **Cron Job**. O alojamento não
precisa de Node nem de Composer — o pacote vai já pronto daqui.

A conta está arrumada assim:

```
/home/multifut/multifuturo/    o projeto (vendor de produção), fora do alcance da web
/home/multifut/public_html/    o conteúdo de public/ (assets, Filament, index.php
                               a apontar para ../multifuturo) e o atalho storage/
```

O `bootstrap/app.php` reconhece esta arrumação (sem `public/` no projeto, com
`../public_html` ao lado) e usa o `public_html` como pasta pública. Em
desenvolvimento e no servidor em contentores nada muda.

O `.env` do alojamento faz-se a partir de
[.env.cpanel.example](.env.cpanel.example): sem Redis, com as sessões, a cache
e a fila na base de dados.

### Gerar e enviar o pacote

```powershell
.\scripts\deploy-pacote.ps1      # gera dist\multifuturo-AAAAMMDD-HHMM.zip
```

Precisa do Docker a correr (o Composer e o Vite correm dentro do contentor).
Enviar o ZIP para `/home/multifut` no Gestor de Ficheiros e fazer **Extract**
por cima.

### O Cron que aplica

Um Cron Job "Once Per Minute" fica permanentemente com:

```
tr -d '\r' < /home/multifut/multifuturo/atualizar.sh | /bin/sh > /home/multifut/cron-debug.txt 2>&1
```

O `atualizar.sh` só atua quando o `deploy-manifest.txt` do pacote traz uma
versão ainda não aplicada: apaga os ficheiros de código que saíram do pacote
(extrair por cima não os apaga), corre as migrações, garante o atalho das
fotografias, regenera as caches e corrige as permissões. O resultado fica em
`multifuturo/storage/atualizacao.log`, cuja última linha é `FIM` ou `FALHOU`.

O `.env`, o `storage/` (fotografias, documentos, cópias) e a base de dados
nunca são tocados.

### Os outros dois Cron Jobs

```
# Tarefas agendadas: cópia de segurança diária e limpeza dos consentimentos
* * * * * /opt/alt/php83/usr/bin/php /home/multifut/multifuturo/artisan schedule:run >/dev/null 2>&1

# Fila: os emails dos pedidos do site
* * * * * /opt/alt/php83/usr/bin/php /home/multifut/multifuturo/artisan queue:work --stop-when-empty --max-time=50 --tries=3 >/dev/null 2>&1
```

### Primeira instalação

Extrair o pacote, criar o `.env` (do `.env.cpanel.example`, com a base de dados
de *MySQL Databases* e a conta de email de *Email Accounts*) e pôr o Cron a
correr o `instalar.sh` em vez do `atualizar.sh`, uma vez. Ele gera a `APP_KEY`,
cria as tabelas, põe o atalho das fotografias e gera as caches; o resultado
fica em `multifuturo/storage/instalacao.log`. Depois troca-se o Cron para o
`atualizar.sh` e cria-se a primeira conta da equipa.

### A versão de PHP e o `.htaccess`

O domínio tem de estar em **PHP 8.3 (alt-php83)** no *MultiPHP Manager* do
cPanel — o PHP do sistema neste alojamento é o 7.4, e é o que vale para quem não
escolher. A escolha fica escrita num bloco no `public_html/.htaccess`, com um
nome parecido com este (o exato é o que o cPanel lá puser):

```apache
# php -- BEGIN cPanel-generated handler, do not edit
AddHandler application/x-httpd-alt-php83___lsphp .php .php8 .phtml
# php -- END cPanel-generated handler, do not edit
```

As **extensões** do PHP 8.3 escolhem-se em *Select PHP Version* → *Extensions*
(o seletor do CloudLinux, que vale para o site e para o Cron). O projeto precisa
de: `ctype`, `dom`, `fileinfo`, `filter`, `iconv`, `intl`, `libxml`, `mbstring`,
`openssl`, `pdo_mysql` (com `mysqlnd`), `session`, `tokenizer`, `xml`,
`xmlreader` e `zip`. Os guiões verificam-nas antes de começar e, se faltar
alguma, o registo diz quais.

Por isso o pacote **não leva** `.htaccess`: leva `.htaccess.modelo`, que o
`atualizar.sh` só usa quando não existe nenhum. Substituí-lo numa atualização
faria o domínio cair para o PHP do sistema (7.4) e o site respondia com
*"Composer detected issues in your platform"*, que é o aviso das dependências
a exigirem 8.3.

### Notas do alojamento

- O editor de ficheiros do cPanel grava com fins de linha do Windows — daí o
  `tr -d '\r'` no Cron, e os guiões tolerarem `APP_KEY=` seguido de `\r`.
- `www.multifuturo.pt` é reencaminhado para `multifuturo.pt`
  (`RedirectToCanonicalHost`): com os dois endereços, o login dava "Page
  Expired", porque o cookie de sessão não segue de um para o outro.
