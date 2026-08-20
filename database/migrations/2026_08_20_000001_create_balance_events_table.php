<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBalanceEventsTable extends Migration
{
    public function up()
    {
        Schema::create('balance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datos_entrada_id')->constrained('datos_entradas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valle_id')->nullable()->constrained('valles')->nullOnDelete();
            $table->foreignId('proceso_id')->nullable()->constrained('procesos')->nullOnDelete();
            $table->string('event_type', 32);
            $table->foreignId('balance_id')->nullable()->constrained('balances')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'event_type']);
            $table->index(['valle_id', 'event_type']);
            $table->index(['proceso_id', 'event_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('balance_events');
    }
}
