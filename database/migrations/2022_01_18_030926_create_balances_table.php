<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBalancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo');
            $table->json('matriz_nodos')->nullable();
            $table->json('nombres_flujos')->nullable();
            $table->json('descripciones_flujos')->nullable();
            $table->json('nombres_nodos')->nullable();
            $table->json('descripciones_nodos')->nullable();
            $table->json('tags')->nullable();
            $table->json('indices_delta_stock')->nullable();
            $table->unsignedBigInteger('user_id');

            $table->foreign('user_id')->references('id')->on('users');
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
        Schema::dropIfExists('balances');
    }
}
