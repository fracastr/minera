<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balances', function (Blueprint $table) {
            // Nullable: balances saved before this change have no period.
            $table->date('periodo_inicio')->nullable()->after('tipo');
            $table->date('periodo_fin')->nullable()->after('periodo_inicio');
            $table->index(['proceso_id', 'periodo_inicio']);
        });
    }

    public function down(): void
    {
        Schema::table('balances', function (Blueprint $table) {
            $table->dropIndex(['proceso_id', 'periodo_inicio']);
            $table->dropColumn(['periodo_inicio', 'periodo_fin']);
        });
    }
};
