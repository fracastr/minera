<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('datos_entradas', function (Blueprint $table) {
            // {ok, mensaje, iteraciones} of the last solver call; null = unknown (old Flask, v1 runs, restored versions)
            $table->json('solver_estado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('datos_entradas', function (Blueprint $table) {
            $table->dropColumn('solver_estado');
        });
    }
};
