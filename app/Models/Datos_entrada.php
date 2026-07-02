<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Datos_entrada extends Model
{
    use HasFactory;

    protected $fillable = [
        'datos_entrada',
        'proceso_id',
        'valle_id',
        'balance_id',
        'file_path',
        'user_id',
    ];

    public function balance()
    {
        return $this->belongsTo(Balances::class, 'balance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function proceso()
    {
        return $this->belongsTo(Procesos::class, 'proceso_id');
    }

    public function valle()
    {
        return $this->belongsTo(Valles::class, 'valle_id');
    }

}
