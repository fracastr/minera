<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Valles extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'tipo'
    ];

    public function procesos()
    {
        return $this->hasMany(Procesos::class, 'valle_id');
    }
}
