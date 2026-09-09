<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest
|--------------------------------------------------------------------------
|
| Todos os testes Feature correm com a base de dados de testes (MySQL
| "testing", criada pelo Sail) migrada de fresco — o schema usa JSON nativo e CHECK,
| por isso não se testa em SQLite.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
