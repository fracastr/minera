<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUserIdToDatosEntradas extends Migration
{
    public function up()
    {
        Schema::table('datos_entradas', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('balance_id');
            $table->foreign('user_id')->references('id')->on('users');
        });

        DB::table('datos_entradas')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $userId = $this->extractUserIdFromFilePath($row->file_path);
                    if ($userId) {
                        DB::table('datos_entradas')
                            ->where('id', $row->id)
                            ->update(['user_id' => $userId]);
                    }
                }
            });

        DB::table('balances')
            ->where('user_id', 1)
            ->orderBy('id')
            ->chunkById(200, function ($balances) {
                foreach ($balances as $balance) {
                    $datosEntrada = DB::table('datos_entradas')
                        ->where('balance_id', $balance->id)
                        ->whereNotNull('user_id')
                        ->orderByDesc('id')
                        ->first();

                    if ($datosEntrada && (int) $datosEntrada->user_id !== 1) {
                        DB::table('balances')
                            ->where('id', $balance->id)
                            ->update(['user_id' => $datosEntrada->user_id]);
                    }
                }
            });
    }

    public function down()
    {
        Schema::table('datos_entradas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    private function extractUserIdFromFilePath(?string $filePath): ?int
    {
        if (!$filePath) {
            return null;
        }

        if (!preg_match('/_(\d{14})_(\d+)\.[^.]+$/', $filePath, $matches)) {
            return null;
        }

        $userId = (int) $matches[2];

        return $userId > 0 ? $userId : null;
    }
}
