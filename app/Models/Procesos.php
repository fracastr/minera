<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procesos extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'tipo', 'componentes', 'valle_id'];

    public function valle()
    {
        return $this->belongsTo(Valles::class, 'valle_id');
    }

    public function balances()
    {
        return $this->hasMany(Balances::class, 'proceso_id');
    }
}
