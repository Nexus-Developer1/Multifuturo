<?php

/*
|--------------------------------------------------------------------------
| Passagem dos dados do PostgreSQL para o MySQL
|--------------------------------------------------------------------------
|
| Correr com o Tinker, com o MySQL já migrado e vazio e o PostgreSQL ainda
| de pé:
|
|     php artisan tinker database/transferir-pgsql-para-mysql.php
|
| Lê cada tabela na ligação antiga (PGSQL_ANTIGA_HOST, por omissão "pgsql")
| e grava-a na ligação por omissão (o MySQL), com os mesmos ids. O JSON vai
| tal como está: o PostgreSQL entrega texto JSON e o MySQL aceita-o. Não é um
| comando permanente — é o guião de uma mudança que se faz uma vez, e fica
| aqui para se ver o que se fez.
|
*/

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

// Pela ordem das chaves estrangeiras: primeiro quem é referido.
$tabelas = [
    'users', 'module_access', 'properties', 'property_activities', 'property_views',
    'zones', 'contacts', 'events', 'leads', 'consent_logs', 'reference_prices', 'mfa_codes',
];

$resumo = [];

// O PostgreSQL entrega as datas com fuso ("2026-09-09 10:00:00+00"); o MySQL,
// em modo estrito, recusa o "+00". Passam a UTC sem sufixo. O resto vai tal
// como vem: JSON é texto, booleanos são 0/1, números são números.
$normalizar = function ($valor) {
    if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d+)?[+-]\d{2}(:?\d{2})?$/', $valor)) {
        return Carbon::parse($valor)->utc()->format('Y-m-d H:i:s');
    }

    return $valor;
};

$nova->statement('SET FOREIGN_KEY_CHECKS=0');

foreach ($tabelas as $tabela) {
    $linhas = $antiga->table($tabela)->orderBy('id')->get();
    $nova->table($tabela)->truncate();

    foreach ($linhas->chunk(200) as $bloco) {
        $nova->table($tabela)->insert(
            $bloco->map(fn ($l) => array_map($normalizar, (array) $l))->all()
        );
    }

    $resumo[] = sprintf('%-22s %5d → %5d', $tabela, $linhas->count(), $nova->table($tabela)->count());
}

$nova->statement('SET FOREIGN_KEY_CHECKS=1');

echo implode(PHP_EOL, $resumo).PHP_EOL;
