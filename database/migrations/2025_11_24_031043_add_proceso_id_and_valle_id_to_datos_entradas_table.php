<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProcesoIdAndValleIdToDatosEntradasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('datos_entradas', function (Blueprint $table) {
            $table->unsignedBigInteger('proceso_id')->nullable()->after('balance_id');
            $table->unsignedBigInteger('valle_id')->nullable()->after('proceso_id');

            $table->foreign('proceso_id')->references('id')->on('procesos');
            $table->foreign('valle_id')->references('id')->on('valles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('datos_entradas', function (Blueprint $table) {
            $table->dropForeign(['proceso_id']);
            $table->dropForeign(['valle_id']);
            $table->dropColumn(['proceso_id', 'valle_id']);
        });
    }
}
