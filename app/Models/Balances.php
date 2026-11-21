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
     * Expects datosEntrada.user and user to be eager-loaded. Pass $usuariosPorArchivo (id => User, from
     * idsDeArchivo over a whole list) to avoid one query per balance.
     */
    public function creador(?\Illuminate\Support\Collection $usuariosPorArchivo = null): ?User
    {
        $datosEntrada = $this->datosEntrada;

        if ($datosEntrada) {
            if ($datosEntrada->user) {
                return $datosEntrada->user;
            }

            $id = self::userIdDeArchivo($datosEntrada->file_path);
            $user = $id ? ($usuariosPorArchivo ? $usuariosPorArchivo->get($id) : User::query()->select('id', 'nombre')->find($id)) : null;
            if ($user) {
                return $user;
            }
        }

        if ((int) $this->user_id === 1) {
            return null;
        }

        return $this->user;
    }

    public static function userIdDeArchivo(?string $filePath): ?int
    {
        return $filePath && preg_match('/_(\d{14})_(\d+)\.[^.]+$/', $filePath, $m) && (int) $m[2] > 0 ? (int) $m[2] : null;
    }
}
