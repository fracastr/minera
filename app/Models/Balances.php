<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Balances extends Model
{
    use HasFactory;

    public const TIPOS = ['quincenal', 'mensual'];

    public $fillable = ['nombre', 'tipo', 'periodo_inicio', 'periodo_fin', 'proceso_id', 'user_id'];

    protected $casts = [
        'periodo_inicio' => 'date:Y-m-d',
        'periodo_fin' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function proceso()
    {
        return $this->belongsTo(Procesos::class);
    }

    public function datosEntrada()
    {
        return $this->hasOne(Datos_entrada::class, 'balance_id')->latestOfMany();
    }
}
