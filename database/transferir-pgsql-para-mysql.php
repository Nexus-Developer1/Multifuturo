<?php

/*
|--------------------------------------------------------------------------
| Passagem dos dados do PostgreSQL para o MySQL
|--------------------------------------------------------------------------
|
| Correr com o Tinker:
|
|     php artisan tinker database/transferir-pgsql-para-mysql.php
|
| Três modos, escolhidos pela variável TRANSFERENCIA:
|
|   (vazia)     cópia directa PostgreSQL → MySQL, quando a mesma máquina tem
|               os dois drivers (é o caso do ambiente de desenvolvimento);
|               a ligação antiga vem de PGSQL_ANTIGA_HOST (por omissão "pgsql");
|   exportar    lê a ligação por omissão (o PostgreSQL, na imagem antiga) e
|               escreve um ficheiro JSON por tabela em storage/backups/transferencia;
|   importar    lê esses ficheiros e grava-os na ligação por omissão (o MySQL,
|               na imagem nova).
|
| Os dois últimos existem porque no servidor a imagem antiga só tem o driver
| do PostgreSQL e a nova só o do MySQL: exporta-se com uma, importa-se com a
| outra, e a pasta das cópias sobrevive à troca de contentor.
|
| Os ids mantêm-se. O JSON vai tal como está: o PostgreSQL entrega texto JSON
| e o MySQL aceita-o. Não é um comando permanente — é o guião de uma mudança
| que se faz uma vez, e fica aqui para se ver o que se fez.
|
*/

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

$modo = getenv('TRANSFERENCIA') ?: '';
$pasta = storage_path('backups/transferencia');

// Pela ordem das chaves estrangeiras: primeiro quem é referido.
$tabelas = [
    'users', 'module_access', 'properties', 'property_activities', 'property_views',
    'zones', 'contacts', 'events', 'leads', 'consent_logs', 'reference_prices', 'mfa_codes',
];

// O PostgreSQL entrega as datas com fuso ("2026-09-09 10:00:00+00"); o MySQL,
// em modo estrito, recusa o "+00". Passam a UTC sem sufixo. O resto vai tal
// como vem: JSON é texto, booleanos são 0/1, números são números.
$normalizar = function ($valor) {
    if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d+)?[+-]\d{2}(:?\d{2})?$/', $valor)) {
        return Carbon::parse($valor)->utc()->format('Y-m-d H:i:s');
    }

    return $valor;
};

$gravar = function ($ligacao, string $tabela, iterable $linhas) use ($normalizar): int {
    $ligacao->table($tabela)->truncate();

    foreach (collect($linhas)->chunk(200) as $bloco) {
        $ligacao->table($tabela)->insert(
            $bloco->map(fn ($l) => array_map($normalizar, (array) $l))->all()
        );
    }

    return $ligacao->table($tabela)->count();
};

$resumo = [];

if ($modo === 'exportar') {
    File::ensureDirectoryExists($pasta);

    foreach ($tabelas as $tabela) {
        $linhas = DB::table($tabela)->orderBy('id')->get();
        File::put("{$pasta}/{$tabela}.json", json_encode($linhas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $resumo[] = sprintf('%-22s %5d linha(s) → %s.json', $tabela, $linhas->count(), $tabela);
    }
} elseif ($modo === 'importar') {
    $nova = DB::connection();
    $nova->statement('SET FOREIGN_KEY_CHECKS=0');

    foreach ($tabelas as $tabela) {
        $ficheiro = "{$pasta}/{$tabela}.json";

        if (! File::exists($ficheiro)) {
            $resumo[] = sprintf('%-22s (sem ficheiro — saltada)', $tabela);

            continue;
        }

        $linhas = json_decode(File::get($ficheiro), true);
        $resumo[] = sprintf('%-22s %5d → %5d', $tabela, count($linhas), $gravar($nova, $tabela, $linhas));
    }

    $nova->statement('SET FOREIGN_KEY_CHECKS=1');
} else {
    config()->set('database.connections.pgsql_antiga', [
        'driver' => 'pgsql',
        'host' => env('PGSQL_ANTIGA_HOST', 'pgsql'),
        'port' => env('PGSQL_ANTIGA_PORT', 5432),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'charset' => 'utf8',
        'prefix' => '',
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ]);

    $antiga = DB::connection('pgsql_antiga');
    $nova = DB::connection();
    $nova->statement('SET FOREIGN_KEY_CHECKS=0');

    foreach ($tabelas as $tabela) {
        $linhas = $antiga->table($tabela)->orderBy('id')->get();
        $resumo[] = sprintf('%-22s %5d → %5d', $tabela, $linhas->count(), $gravar($nova, $tabela, $linhas));
    }

    $nova->statement('SET FOREIGN_KEY_CHECKS=1');
}

echo implode(PHP_EOL, $resumo).PHP_EOL;
