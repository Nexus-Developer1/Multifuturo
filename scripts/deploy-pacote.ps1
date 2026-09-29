# Gera o ZIP para enviar para o cPanel, com as dependencias de producao e os
# assets ja compilados (o alojamento nao precisa de Node nem de Composer).
#
# O ZIP vem com a estrutura da pasta inicial da conta (~) e descompacta-se
# diretamente em /home/multifut:
#
#   multifuturo/    o projeto (sem public/), fora do alcance da web
#   public_html/    o conteudo de public/, com o index.php a apontar para ../multifuturo
#
# O composer e o npm correm dentro do contentor do Sail, que e onde este
# projeto tem o PHP e o Node certos (no Windows o rollup do Vite nao compila).
# O Docker Desktop tem de estar a correr.
#
# Uso, na raiz do projeto:  .\scripts\deploy-pacote.ps1
# Resultado:                dist\multifuturo-AAAAMMDD-HHMM.zip

# Nao usar 'Stop': no PowerShell 5.1 qualquer linha que o composer/npm escrevam
# no stderr (mesmo informativa) seria tratada como erro. As falhas verificam-se
# pelo $LASTEXITCODE de cada passo.
$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$stamp = Get-Date -Format 'yyyyMMdd-HHmm'
$dist = Join-Path $root 'dist'
$zip = Join-Path $dist "multifuturo-$stamp.zip"
$stage = Join-Path $env:TEMP "multifuturo-deploy-$stamp"
New-Item -ItemType Directory -Force -Path $dist | Out-Null

function Invoke-NoContentor([string[]] $comando) {
    docker compose exec -T -u sail multifuturo.test @comando
}

function Copy-Tree($from, $to, [string[]] $excludeDirs = @(), [string[]] $excludeFiles = @()) {
    # /XJ: nao seguir atalhos (public/storage e um atalho para as fotografias)
    $rcArgs = @($from, $to, '/E', '/XJ', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
    if ($excludeDirs) { $rcArgs += '/XD'; $rcArgs += $excludeDirs }
    if ($excludeFiles) { $rcArgs += '/XF'; $rcArgs += $excludeFiles }
    robocopy @rcArgs | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy falhou ($from)" }
}

docker compose ps --status running --format '{{.Service}}' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'O Docker nao esta a responder. Arranque o Docker Desktop e ".\sail.ps1 up -d".' }

Write-Host '1/5  Dependencias PHP de producao (sem ferramentas de desenvolvimento)...'
Invoke-NoContentor @('composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction')
if ($LASTEXITCODE -ne 0) { throw 'composer install falhou' }

try {
    Write-Host '2/5  A compilar CSS e JS...'
    # Como root: o utilizador sail nao escreve em public/build a partir do Windows.
    docker compose exec -T multifuturo.test npm run build
    if ($LASTEXITCODE -ne 0) { throw 'npm run build falhou' }

    Write-Host '3/5  A montar a estrutura do cPanel...'
    $app = Join-Path $stage 'multifuturo'
    $web = Join-Path $stage 'public_html'

    # Caminhos completos em /XD: um nome solto (ex.: "public") excluiria
    # tambem as pastas com esse nome dentro de vendor/.
    Copy-Tree $root $app `
        -excludeDirs @(
            (Join-Path $root '.git'),
            (Join-Path $root '.github'),
            (Join-Path $root 'node_modules'),
            (Join-Path $root 'dist'),
            (Join-Path $root 'tests'),
            (Join-Path $root 'public'),
            (Join-Path $root 'docker'),
            (Join-Path $root 'deploy'),
            (Join-Path $root 'docs'),
            (Join-Path $root 'scripts'),
            (Join-Path $root '.phpunit.cache'),
            # Fotografias: as do servidor ficam la e nunca sao substituidas.
            (Join-Path $root 'storage\app\public\imoveis'),
            (Join-Path $root 'storage\app\public\demo'),
            (Join-Path $root 'storage\app\private\livewire-tmp'),
            (Join-Path $root 'storage\backups')
        ) `
        -excludeFiles @(
            # Nenhum .env vai no pacote: o do servidor nao se toca, e os daqui
            # (.env, .env.prodlocal) sao desta maquina. O modelo do cPanel e
            # copiado a seguir, a mao, para o instalar.sh o ter a jeito.
            '.env*', '.phpunit.result.cache', 'phpunit.xml',
            'compose.yaml', 'compose.production.yaml', 'sail.ps1',
            'package.json', 'package-lock.json', 'vite.config.js',
            'CHANGELOG.md', 'DEPLOY.md', 'README.md'
        )

    Copy-Item (Join-Path $root '.env.cpanel.example') (Join-Path $app '.env.cpanel.example') -Force

    # Ficheiros gerados em runtime: fora, mas as pastas tem de existir.
    # bootstrap\cache: as caches do PC (configuracao, rotas, pacotes) nao podem
    # ir para o servidor — o Laravel volta a gera-las la.
    foreach ($dir in 'storage\logs', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'bootstrap\cache') {
        Get-ChildItem (Join-Path $app $dir) -File -Recurse -ErrorAction SilentlyContinue |
            Where-Object Name -ne '.gitignore' | Remove-Item -Force
    }

    Copy-Tree (Join-Path $root 'public') $web `
        -excludeDirs @((Join-Path $root 'public\storage')) `
        -excludeFiles @('hot')

    # O index.php passa a procurar o projeto em ../multifuturo em vez de ..
    $index = Join-Path $web 'index.php'
    $php = [IO.File]::ReadAllText($index)
    $corrigido = $php.Replace("__DIR__.'/../", "__DIR__.'/../multifuturo/")
    if ($corrigido -eq $php) { throw 'index.php: nao encontrei os caminhos a corrigir' }
    [IO.File]::WriteAllText($index, $corrigido, (New-Object Text.UTF8Encoding $false))

    # Os guioes do servidor na raiz do projeto (correm por Cron Job, ver
    # DEPLOY.md) e o manifesto: versao + ficheiros de codigo do pacote, para o
    # atualizar.sh apagar no servidor o que deixou de existir.
    $lf = New-Object Text.UTF8Encoding $false
    foreach ($par in @(@('atualizar-servidor.sh', 'atualizar.sh'), @('instalar-servidor.sh', 'instalar.sh'))) {
        $texto = [IO.File]::ReadAllText((Join-Path $root "scripts\$($par[0])")).Replace("`r`n", "`n")
        [IO.File]::WriteAllText((Join-Path $app $par[1]), $texto, $lf)
    }
    $ficheiros = foreach ($dir in 'app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes') {
        Get-ChildItem (Join-Path $app $dir) -File -Recurse |
            ForEach-Object { $_.FullName.Substring($app.Length + 1).Replace('\', '/') } |
            Where-Object { $_ -notlike 'bootstrap/cache/*' }
    }
    $manifesto = @($stamp) + ($ficheiros | Sort-Object)
    [IO.File]::WriteAllText((Join-Path $app 'deploy-manifest.txt'), ($manifesto -join "`n") + "`n", $lf)

    Write-Host '4/5  A criar o ZIP...'
    tar -a -c -f $zip -C $stage multifuturo public_html
    if ($LASTEXITCODE -ne 0) { throw 'tar falhou' }
}
finally {
    Write-Host '5/5  A repor as dependencias de desenvolvimento no PC...'
    Invoke-NoContentor @('composer', 'install', '--no-interaction') 2>$null | Out-Null
    if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
}

$tamanho = '{0:N1} MB' -f ((Get-Item $zip).Length / 1MB)
Write-Host ''
Write-Host "Pacote pronto: $zip ($tamanho)"
Write-Host 'Enviar para /home/multifut no Gestor de Ficheiros e fazer Extract por cima.'
