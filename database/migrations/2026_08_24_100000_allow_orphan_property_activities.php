<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Registo de "ficha apagada" sem imóvel
|--------------------------------------------------------------------------
|
| Quando um imóvel é apagado, a chave estrangeira em cascata leva com ele todo
| o seu histórico — e a linha "Apagada" que o observer tentava escrever violava
| a chave estrangeira, porque o imóvel já não existia (erro ao apagar no
| backoffice). A cascata mantém-se: o histórico de um imóvel morre com ele.
| O que muda é que a linha da eliminação passa a poder ficar sem imóvel, com a
| referência guardada no detalhe, para o "Actualizações" da dashboard registar
| que alguém apagou a ficha.
|
| Feito pelo Blueprint, não por SQL: o "ALTER COLUMN … DROP NOT NULL" era do
| PostgreSQL e o MySQL escreve-o de outra maneira. Assim serve aos dois.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_activities', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::statement('DELETE FROM property_activities WHERE property_id IS NULL');

        Schema::table('property_activities', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable(false)->change();
        });
    }
};
