<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Balances;
use App\Models\Valles;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class BalancesController extends Controller
{
    public function catalogo()
    {
        $valles = Valles::query()->select(['id', 'nombre'])->with('procesos:id,nombre,valle_id,componentes')->orderBy('id')->get()
            ->map(fn (Valles $v) => [
                'id' => $v->id,
                'nombre' => $v->nombre,
                'procesos' => $v->procesos->sortBy('id')->values()->map(fn ($p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'componentes' => json_decode($p->componentes ?? '', true)['data'] ?? [],
                ]),
            ]);

        return response()->json(['valles' => $valles]);
    }

    private const ORDEN = ['nombre', 'tipo', 'periodo_inicio', 'created_at', 'proceso', 'valle', 'usuario'];

    public function listado(Request $request)
    {
        $f = $request->validate([
            'valle_id' => 'nullable|integer',
            'proceso_id' => 'nullable|integer',
            'tipo' => 'nullable|in:'.implode(',', Balances::TIPOS),
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'user_id' => 'nullable|integer',
            'q' => 'nullable|string|max:100',
            'orden' => 'nullable|in:'.implode(',', self::ORDEN),
            'dir' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Period filter: balances whose period overlaps [desde, hasta]; balances without a period fall back to created_at.
        $rows = Balances::query()
            ->select(['id', 'nombre', 'tipo', 'periodo_inicio', 'periodo_fin', 'proceso_id', 'user_id', 'created_at'])
            ->with(['user:id,nombre', 'datosEntrada.user:id,nombre', 'proceso:id,nombre,valle_id', 'proceso.valle:id,nombre'])
            ->when($f['proceso_id'] ?? null, fn ($q, $v) => $q->where('proceso_id', $v))
            ->when($f['valle_id'] ?? null, fn ($q, $v) => $q->whereHas('proceso', fn ($p) => $p->where('valle_id', $v)))
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('periodo_fin', '>=', $v)
                ->orWhere(fn ($n) => $n->whereNull('periodo_fin')->whereDate('created_at', '>=', $v))))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('periodo_inicio', '<=', $v)
                ->orWhere(fn ($n) => $n->whereNull('periodo_inicio')->whereDate('created_at', '<=', $v))))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('nombre', 'like', '%'.addcslashes($v, '%_\\').'%'))
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Balances $b) {
                $creador = $b->creador();

                return [
                    'id' => $b->id,
                    'nombre' => $b->nombre,
                    'tipo' => $b->tipo,
                    'periodo_inicio' => optional($b->periodo_inicio)->format('Y-m-d'),
                    'periodo_fin' => optional($b->periodo_fin)->format('Y-m-d'),
                    'proceso' => $b->proceso ? ['id' => $b->proceso->id, 'nombre' => $b->proceso->nombre] : null,
                    'valle' => $b->proceso?->valle ? ['id' => $b->proceso->valle->id, 'nombre' => $b->proceso->valle->nombre] : null,
                    'usuario' => $creador ? ['id' => $creador->id, 'nombre' => $creador->nombre] : null,
                    'created_at' => $b->created_at?->toIso8601String(),
                    'datos_entrada_id' => $b->datosEntrada?->id,
                ];
            });

        // shortcut: the owner is resolved in PHP (v1 heuristic), so user filter, sort and paging run on the collection;
        // fine for hundreds of balances, move to SQL if the table grows to tens of thousands.
        $usuarios = $rows->pluck('usuario')->filter()->unique('id')->sortBy('nombre')->values();
        if ($f['user_id'] ?? null) {
            $rows = $rows->filter(fn ($r) => ($r['usuario']['id'] ?? null) === (int) $f['user_id']);
        }

        $orden = $f['orden'] ?? 'created_at';
        $key = fn ($r) => match ($orden) {
            'proceso', 'valle', 'usuario' => mb_strtolower($r[$orden]['nombre'] ?? ''),
            'nombre' => mb_strtolower($r['nombre'] ?? ''),
            default => $r[$orden] ?? '',
        };
        $rows = $rows->sortBy($key, SORT_NATURAL, ($f['dir'] ?? 'desc') === 'desc')->values();

        $perPage = (int) ($f['per_page'] ?? 25);
        $page = (int) ($f['page'] ?? 1);
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'usuarios' => $usuarios,
        ]);
    }

    public function sesion(int $balance)
    {
        $id = Balances::query()->find($balance)?->datosEntrada?->id;

        return $id
            ? response()->json(['sesion_id' => $id])
            : response()->json(['message' => 'El balance no existe o no tiene datos asociados.'], 404);
    }
}
