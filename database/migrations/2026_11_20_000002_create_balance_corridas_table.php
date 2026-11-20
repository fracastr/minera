<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_corridas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datos_entrada_id')->constrained('datos_entradas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 16); // importacion | corrida | restauracion
            $table->longText('snapshot'); // datos_entrada JSON as it was after this step
            $table->timestamp('created_at')->useCurrent();
            $table->index(['datos_entrada_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_corridas');
    }
};
