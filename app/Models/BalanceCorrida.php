<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class BalanceCorrida extends Model
{
    public const TIPO_IMPORTACION = 'importacion';
    public const TIPO_CORRIDA = 'corrida';
    public const TIPO_RESTAURACION = 'restauracion';

    public $timestamps = false;

    protected $fillable = ['datos_entrada_id', 'user_id', 'tipo', 'snapshot', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public static function record(Datos_entrada $datosEntrada, ?User $user, string $tipo): self
    {
        return self::create([
            'datos_entrada_id' => $datosEntrada->id,
            'user_id' => $user ? $user->id : $datosEntrada->user_id,
            'tipo' => $tipo,
            'snapshot' => $datosEntrada->datos_entrada,
            'created_at' => now(),
        ]);
    }

    /** Copies the history of one session to its clone without loading snapshots into PHP. */
    public static function copyHistory(int $fromDatosEntradaId, int $toDatosEntradaId): void
    {
        DB::table('balance_corridas')->insertUsing(
            ['datos_entrada_id', 'user_id', 'tipo', 'snapshot', 'created_at'],
            DB::table('balance_corridas')
                ->selectRaw('?, user_id, tipo, snapshot, created_at', [$toDatosEntradaId])
                ->where('datos_entrada_id', $fromDatosEntradaId)
                ->orderBy('id')
        );
    }

    public function datosEntrada(): BelongsTo
    {
        return $this->belongsTo(Datos_entrada::class, 'datos_entrada_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
