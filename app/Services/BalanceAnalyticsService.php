<?php

namespace App\Services;

use App\Models\BalanceEvent;
use App\Models\Datos_entrada;
use App\Models\Procesos;
use App\Models\User;
use App\Models\Valles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BalanceAnalyticsService
{
    private const CACHE_TTL_SECONDS = 600;

    private const FILTER_OPTIONS_TTL_SECONDS = 3600;

    public function parseFilters(array $input): array
    {
        return [
            'from' => $input['from'] ?? null,
            'to' => $input['to'] ?? null,
            'valle_id' => !empty($input['valle_id']) ? (int) $input['valle_id'] : null,
            'proceso_id' => !empty($input['proceso_id']) ? (int) $input['proceso_id'] : null,
            'user_id' => !empty($input['user_id']) ? (int) $input['user_id'] : null,
            'metric' => $input['metric'] ?? 'imported',
            'group_by' => $input['group_by'] ?? 'month',
        ];
    }

    public static function bumpCacheVersion(): void
    {
        $version = (int) Cache::get('balance_analytics:version', 1) + 1;
        Cache::forever('balance_analytics:version', $version);
    }

    public function dashboard(array $filters, bool $fresh = false): array
    {
        $compute = function () use ($filters) {
            return [
                'summary' => $this->computeSummary($filters),
                'by_user' => $this->computeByUser($filters)->values(),
                'by_valle' => $this->computeByValle($filters)->values(),
                'by_proceso' => $this->computeByProceso($filters)->values(),
                'timeseries' => $this->computeTimeseries($filters)->values(),
                'filters' => $filters,
                'cached_at' => now()->toIso8601String(),
            ];
        };

        if ($fresh) {
            return $compute();
        }

        return $this->remember('dashboard_v3', $filters, $compute);
    }

    public function summary(array $filters): array
    {
        return $this->remember('summary', $filters, fn () => $this->computeSummary($filters));
    }

    public function byUser(array $filters): Collection
    {
        return $this->remember('by_user', $filters, fn () => $this->computeByUser($filters));
    }

    public function byValle(array $filters): Collection
    {
        return $this->remember('by_valle', $filters, fn () => $this->computeByValle($filters));
    }

    public function byProceso(array $filters): Collection
    {
        return $this->remember('by_proceso_v2', $filters, fn () => $this->computeByProceso($filters));
    }

    public function timeseries(array $filters): Collection
    {
        return $this->remember('timeseries', $filters, fn () => $this->computeTimeseries($filters));
    }

    private function computeSummary(array $filters): array
    {
            $events = $this->eventsJoinedQuery($filters);

            $imported = (clone $events)
                ->where('balance_events.event_type', BalanceEvent::TYPE_IMPORTED)
                ->count('balance_events.id');

            $correr = (clone $events)
                ->where('balance_events.event_type', BalanceEvent::TYPE_CORRER)
                ->count('balance_events.id');

            $saved = (clone $events)
                ->where('balance_events.event_type', BalanceEvent::TYPE_SAVED)
                ->count('balance_events.id');

            $activeUserIds = (clone $events)
                ->whereIn('balance_events.event_type', [BalanceEvent::TYPE_IMPORTED, BalanceEvent::TYPE_CORRER])
                ->whereNotNull('balance_events.user_id')
                ->distinct()
                ->pluck('balance_events.user_id');

            if ($imported === 0) {
                $imported = $this->sessionsQuery($filters)->count();
            }

            return [
                'sessions_imported' => $imported,
                'balances_corridos' => $correr,
                'balances_guardados' => $saved,
                'usuarios_activos' => $activeUserIds->count(),
            ];
    }

    private function computeByUser(array $filters): Collection
    {
            $metric = $filters['metric'] ?? 'imported';
            $events = $this->eventsJoinedQuery($filters)
                ->whereNotNull('balance_events.user_id');

            $this->applyMetricFilter($events, $metric, 'balance_events.event_type');

            $rows = $events
                ->select('balance_events.user_id', DB::raw('count(*) as total'))
                ->groupBy('balance_events.user_id')
                ->orderByDesc('total')
                ->limit(20)
                ->get();

            $users = User::query()
                ->whereIn('id', $rows->pluck('user_id'))
                ->pluck('nombre', 'id');

            return $rows->map(function ($row) use ($users) {
                return [
                    'user_id' => $row->user_id,
                    'nombre' => $users[$row->user_id] ?? 'Sin nombre',
                    'total' => (int) $row->total,
                ];
            });
    }

    private function computeByValle(array $filters): Collection
    {
            $metric = $filters['metric'] ?? 'imported';
            $events = $this->eventsJoinedQuery($filters)
                ->whereNotNull('balance_events.valle_id');

            $this->applyMetricFilter($events, $metric, 'balance_events.event_type');

            $rows = $events
                ->select('balance_events.valle_id', DB::raw('count(*) as total'))
                ->groupBy('balance_events.valle_id')
                ->orderByDesc('total')
                ->get();

            $valles = Valles::query()
                ->whereIn('id', $rows->pluck('valle_id'))
                ->pluck('nombre', 'id');

            return $rows->map(function ($row) use ($valles) {
                return [
                    'valle_id' => $row->valle_id,
                    'nombre' => $valles[$row->valle_id] ?? 'Sin valle',
                    'total' => (int) $row->total,
                ];
            });
    }

    private function computeByProceso(array $filters): Collection
    {
            $metric = $filters['metric'] ?? 'imported';

            if ($metric === 'imported') {
                $rows = $this->sessionsQuery($filters)
                    ->whereNotNull('proceso_id')
                    ->select('proceso_id', DB::raw('count(*) as total'))
                    ->groupBy('proceso_id')
                    ->orderByDesc('total')
                    ->get();
            } else {
                $events = $this->eventsJoinedQuery($filters)
                    ->where('balance_events.event_type', BalanceEvent::TYPE_CORRER)
                    ->whereNotNull('datos_entradas.proceso_id');

                $rows = $events
                    ->select('datos_entradas.proceso_id', DB::raw('count(*) as total'))
                    ->groupBy('datos_entradas.proceso_id')
                    ->orderByDesc('total')
                    ->get();
            }

            $procesos = Procesos::query()
                ->whereIn('id', $rows->pluck('proceso_id'))
                ->get(['id', 'nombre', 'valle_id'])
                ->keyBy('id');

            $valles = Valles::query()
                ->whereIn('id', $procesos->pluck('valle_id')->unique())
                ->pluck('nombre', 'id');

            return $rows->map(function ($row) use ($procesos, $valles) {
                $proceso = $procesos->get($row->proceso_id);
                $valleNombre = $proceso ? ($valles[$proceso->valle_id] ?? '') : '';

                return [
                    'proceso_id' => $row->proceso_id,
                    'nombre' => $proceso ? $proceso->nombre : 'Sin proceso',
                    'valle_id' => $proceso ? $proceso->valle_id : null,
                    'valle_nombre' => $valleNombre,
                    'label' => $proceso && $valleNombre
                        ? $proceso->nombre . ' (' . $valleNombre . ')'
                        : ($proceso ? $proceso->nombre : 'Sin proceso'),
                    'total' => (int) $row->total,
                ];
            });
    }

    private function computeTimeseries(array $filters): Collection
    {
            $metric = $filters['metric'] ?? 'imported';
            $groupBy = $filters['group_by'] ?? 'month';
            $format = $this->periodFormat($groupBy);

            if ($metric === 'correr') {
                $rows = $this->eventsJoinedQuery($filters)
                    ->where('balance_events.event_type', BalanceEvent::TYPE_CORRER)
                    ->select(
                        DB::raw("DATE_FORMAT(datos_entradas.created_at, '{$format}') as period"),
                        DB::raw('count(*) as total')
                    )
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
            } else {
                $rows = $this->sessionsQuery($filters)
                    ->select(
                        DB::raw("DATE_FORMAT(created_at, '{$format}') as period"),
                        DB::raw('count(*) as total')
                    )
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get();
            }

            return $rows->map(function ($row) use ($groupBy) {
                return [
                    'period' => $row->period,
                    'period_label' => $this->formatPeriodLabel($row->period, $groupBy),
                    'total' => (int) $row->total,
                ];
            });
    }

    public function filterOptions(bool $fresh = false): array
    {
        if ($fresh) {
            return $this->computeFilterOptions();
        }

        $version = (int) Cache::get('balance_analytics:version', 1);

        return Cache::remember(
            "balance_analytics:v{$version}:filter_options_v2",
            self::FILTER_OPTIONS_TTL_SECONDS,
            fn () => $this->computeFilterOptions()
        );
    }

    private function computeFilterOptions(): array
    {
                $userIds = BalanceEvent::query()
                    ->whereNotNull('user_id')
                    ->distinct()
                    ->pluck('user_id');

                $users = User::query()
                    ->whereIn('id', $userIds)
                    ->orderBy('nombre')
                    ->get(['id', 'nombre']);

                $valles = Valles::query()->orderBy('nombre')->get(['id', 'nombre']);

                $procesos = Procesos::query()
                    ->orderBy('nombre')
                    ->get(['id', 'nombre', 'valle_id']);

                return [
                    'users' => $users,
                    'valles' => $valles,
                    'procesos' => $procesos,
                ];
    }

    private function remember(string $section, array $filters, callable $callback)
    {
        $version = (int) Cache::get('balance_analytics:version', 1);
        $key = 'balance_analytics:v' . $version . ':' . $section . ':' . md5(json_encode($filters));

        return Cache::remember($key, self::CACHE_TTL_SECONDS, $callback);
    }

    private function sessionsQuery(array $filters): Builder
    {
        $query = Datos_entrada::query();
        $this->applySessionFilters($query, $filters);

        return $query;
    }

    private function eventsJoinedQuery(array $filters): Builder
    {
        $query = BalanceEvent::query()
            ->join('datos_entradas', 'balance_events.datos_entrada_id', '=', 'datos_entradas.id');

        $this->applySessionFilters($query, $filters, 'datos_entradas');

        return $query;
    }

    private function applySessionFilters(Builder $query, array $filters, string $table = 'datos_entradas'): Builder
    {
        if (!empty($filters['from'])) {
            $query->whereDate("{$table}.created_at", '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate("{$table}.created_at", '<=', $filters['to']);
        }

        if (!empty($filters['valle_id'])) {
            $query->where("{$table}.valle_id", $filters['valle_id']);
        }

        if (!empty($filters['proceso_id'])) {
            $query->where("{$table}.proceso_id", $filters['proceso_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where("{$table}.user_id", $filters['user_id']);
        }

        return $query;
    }

    private function applyMetricFilter(Builder $query, string $metric, string $column): void
    {
        if ($metric === 'imported') {
            $query->where($column, BalanceEvent::TYPE_IMPORTED);
        } elseif ($metric === 'correr') {
            $query->where($column, BalanceEvent::TYPE_CORRER);
        } else {
            $query->whereIn($column, [BalanceEvent::TYPE_IMPORTED, BalanceEvent::TYPE_CORRER]);
        }
    }

    private function formatPeriodLabel(string $period, string $groupBy): string
    {
        if ($groupBy === 'month' && preg_match('/^(\d{4})-(\d{2})$/', $period)) {
            return ucfirst(Carbon::createFromFormat('Y-m', $period)->locale('es')->translatedFormat('F Y'));
        }

        if ($groupBy === 'day' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            return Carbon::createFromFormat('Y-m-d', $period)->locale('es')->translatedFormat('d M Y');
        }

        return $period;
    }

    private function periodFormat(string $groupBy): string
    {
        switch ($groupBy) {
            case 'day':
                return '%Y-%m-%d';
            case 'week':
                return '%x-W%v';
            case 'month':
            default:
                return '%Y-%m';
        }
    }
}
