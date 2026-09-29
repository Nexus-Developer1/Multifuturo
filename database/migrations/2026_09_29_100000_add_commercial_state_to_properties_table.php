<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Situação comercial do imóvel: Reservado, Vendido, Arrendado — e o que mais a
 * agência precisar, porque o campo é texto livre e não uma lista fechada.
 *
 * Fica ao lado do motivo do estado interno (`status_reason`), que é outra
 * coisa: aquele é da angariação, este é do negócio e vê-se no site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('commercial_state', 32)->nullable()->after('status_reason');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('commercial_state');
        });
    }
};
