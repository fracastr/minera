<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEntradaBalancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('entrada_balances', function (Blueprint $table) {
            $table->id();
            $table->json('datos_entrada');
            $table->json('restricciones')->nullable();
            $table->json('jerarquia')->nullable();
            $table->json('maximos')->nullable();
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
        Schema::dropIfExists('entrada_balances');
    }
}
