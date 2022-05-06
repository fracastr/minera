<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalidaBalancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('salida_balances', function (Blueprint $table) {
            $table->id();
            $table->json('mediciones_balanceadas')->nullable();
            $table->json('resultado_restricciones')->nullable();
            $table->json('balance_nodos')->nullable();
            $table->unsignedBigInteger('balance_id');

            $table->foreign('balance_id')->references('id')->on('balances');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('salida_balances');
    }
}
