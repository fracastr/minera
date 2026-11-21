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

    /**
     * Who made the balance: the session's user, else the user id in the uploaded file name
     * (nombre_YYYYmmddHHMMSS_userid.xlsx), else the balance's user unless it is the legacy default (1).
     * Expects datosEntrada.user and user to be eager-loaded.
     */
    public function creador(): ?User
    {
        $datosEntrada = $this->datosEntrada;

        if ($datosEntrada) {
            if ($datosEntrada->user) {
                return $datosEntrada->user;
            }

            if ($datosEntrada->file_path && preg_match('/_(\d{14})_(\d+)\.[^.]+$/', $datosEntrada->file_path, $m) && (int) $m[2] > 0) {
                $user = User::query()->select('id', 'nombre')->find((int) $m[2]);
                if ($user) {
                    return $user;
                }
            }
        }

        if ((int) $this->user_id === 1) {
            return null;
        }

        return $this->user;
    }
}
