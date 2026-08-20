<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceEvent extends Model
{
    public const TYPE_IMPORTED = 'imported';
    public const TYPE_CORRER = 'correr';
    public const TYPE_EXPORTED = 'exported';
    public const TYPE_SAVED = 'saved';

    public $timestamps = false;

    protected $fillable = [
        'datos_entrada_id',
        'user_id',
        'valle_id',
        'proceso_id',
        'event_type',
        'balance_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function datosEntrada(): BelongsTo
    {
        return $this->belongsTo(Datos_entrada::class, 'datos_entrada_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function valle(): BelongsTo
    {
        return $this->belongsTo(Valles::class, 'valle_id');
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Procesos::class, 'proceso_id');
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(Balances::class, 'balance_id');
    }
}
