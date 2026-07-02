<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Balances extends Model
{
    use HasFactory;

    public $fillable = ['nombre', 'tipo', 'proceso_id', 'user_id'];

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
