<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAnalyticsIndexesToDatosEntradasTable extends Migration
{
    public function up()
    {
        Schema::table('datos_entradas', function (Blueprint $table) {
            $table->index(['created_at', 'valle_id', 'user_id'], 'datos_entradas_analytics_idx');
        });
    }

    public function down()
    {
        Schema::table('datos_entradas', function (Blueprint $table) {
            $table->dropIndex('datos_entradas_analytics_idx');
        });
    }
}
