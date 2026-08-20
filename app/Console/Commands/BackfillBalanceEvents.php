<?php

namespace App\Console\Commands;

use App\Models\BalanceEvent;
use App\Models\Balances;
use App\Models\Datos_entrada;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BackfillBalanceEvents extends Command
{
    protected $signature = 'balance-events:backfill {--dry-run : Preview without writing}';

    protected $description = 'Backfill balance_events from existing datos_entradas records';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        Datos_entrada::query()
            ->orderBy('id')
            ->chunkById(200, function ($sessions) use ($dryRun, &$created, &$skipped) {
                $sessionIds = $sessions->pluck('id');
                $existing = BalanceEvent::query()
                    ->whereIn('datos_entrada_id', $sessionIds)
                    ->get(['datos_entrada_id', 'event_type'])
                    ->groupBy('datos_entrada_id')
                    ->map(fn ($events) => $events->pluck('event_type')->all());

                $balanceIds = $sessions->pluck('balance_id')->filter()->unique();
                $balances = Balances::query()
                    ->whereIn('id', $balanceIds)
                    ->pluck('created_at', 'id');

                $rows = [];

                foreach ($sessions as $session) {
                    $types = $existing->get($session->id, []);

                    if (in_array(BalanceEvent::TYPE_IMPORTED, $types, true)) {
                        $skipped++;
                    } else {
                        $rows[] = $this->row($session, BalanceEvent::TYPE_IMPORTED, $session->created_at);
                        $created++;
                    }

                    if ($session->updated_at && $session->created_at
                        && $session->updated_at->gt($session->created_at->copy()->addMinute())
                        && !in_array(BalanceEvent::TYPE_CORRER, $types, true)) {
                        $rows[] = $this->row($session, BalanceEvent::TYPE_CORRER, $session->updated_at);
                        $created++;
                    }

                    if ($session->balance_id && !in_array(BalanceEvent::TYPE_SAVED, $types, true)) {
                        $savedAt = $balances[$session->balance_id] ?? $session->updated_at;
                        $rows[] = $this->row($session, BalanceEvent::TYPE_SAVED, $savedAt, $session->balance_id);
                        $created++;
                    }
                }

                if (!$dryRun && count($rows) > 0) {
                    BalanceEvent::insert($rows);
                }
            });

        $this->info(($dryRun ? '[dry-run] Would create' : 'Created') . " {$created} events ({$skipped} sessions already had import events).");

        return 0;
    }

    private function row(Datos_entrada $session, string $type, $timestamp, ?int $balanceId = null): array
    {
        return [
            'datos_entrada_id' => $session->id,
            'user_id' => $session->user_id,
            'valle_id' => $session->valle_id,
            'proceso_id' => $session->proceso_id,
            'event_type' => $type,
            'balance_id' => $type === BalanceEvent::TYPE_SAVED ? $balanceId : null,
            'metadata' => null,
            'created_at' => Carbon::parse($timestamp)->toDateTimeString(),
        ];
    }
}
