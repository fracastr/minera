<?php

namespace App\Services;

use App\Models\BalanceEvent;
use App\Models\Datos_entrada;
use App\Models\User;
use Illuminate\Support\Carbon;

class BalanceEventService
{
    public static function logImported(Datos_entrada $datosEntrada, User $user): BalanceEvent
    {
        return self::createEvent($datosEntrada, BalanceEvent::TYPE_IMPORTED, $user);
    }

    public static function logCorrer(Datos_entrada $datosEntrada, ?User $user): BalanceEvent
    {
        return self::createEvent($datosEntrada, BalanceEvent::TYPE_CORRER, $user);
    }

    public static function logExported(Datos_entrada $datosEntrada, ?User $user): BalanceEvent
    {
        return self::createEvent($datosEntrada, BalanceEvent::TYPE_EXPORTED, $user);
    }

    public static function logSaved(Datos_entrada $datosEntrada, User $user, int $balanceId): BalanceEvent
    {
        $event = self::createEvent($datosEntrada, BalanceEvent::TYPE_SAVED, $user);
        $event->balance_id = $balanceId;
        $event->save();

        return $event;
    }

    private static function createEvent(Datos_entrada $datosEntrada, string $eventType, ?User $user): BalanceEvent
    {
        $event = BalanceEvent::create([
            'datos_entrada_id' => $datosEntrada->id,
            'user_id' => $user ? $user->id : $datosEntrada->user_id,
            'valle_id' => $datosEntrada->valle_id,
            'proceso_id' => $datosEntrada->proceso_id,
            'event_type' => $eventType,
            'balance_id' => $datosEntrada->balance_id,
            'created_at' => Carbon::now(),
        ]);

        BalanceAnalyticsService::bumpCacheVersion();

        return $event;
    }
}
