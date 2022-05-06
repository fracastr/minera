<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventariosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->json('tms_inventario')->nullable();
            $table->json('tmh_inventario')->nullable();
            $table->json('humedad')->nullable();
            $table->json('componentes')->nullable();
            $table->json('signo')->nullable();
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
        Schema::dropIfExists('inventarios');
    }
}
