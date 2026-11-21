<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proceso_diagramas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proceso_id')->unique()->constrained('procesos')->cascadeOnDelete();
            $table->json('layout'); // {nodo_nombre: {x, y}}
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proceso_diagramas');
    }
};
