<?php

namespace App\Domain;

/**
 * The one place that knows the `datos_entradas.datos_entrada` JSON layout (shared with v1 and the Flask solver).
 *
 * Per flow i:
 *   mediciones[i]    = [tms_med, tms_rec, ley1_med, ley1_rec, (ley2_med, ley2_rec)]
 *                      A negative TMS marks a stock flow drawn the other way (the solver reads the sign), so
 *                      values are written back exactly as given: the sign is the user's to keep or change.
 *   restricciones[i] = [tms_inf, tms_sup, ley1_inf, ley1_sup, ...]  tolerances in %
 *   desviaciones[i]  = same shape as restricciones, from the last run
 *   jerarquia[i]     = 1 (bounded by its tolerances) or 2
 * Per node n: nodos_data[n] = [|residuo tms|, |residuo finos k|...]. Per inventory: parallel arrays
 * (tmh_ini, tmh_fin, tmh_delta, humedad_inventario, tms_ini, tms_fin, tms_delta, componentes_inventario).
 * Keys are never renamed or removed: www reads the same rows.
 */
final class SesionMapper
{
    public static function decode(mixed $raw): array
    {
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_string($data)) { // some old rows are double-encoded
            $data = json_decode($data, true);
        }

        return is_array($data) ? $data : [];
    }

    /** Number of grade components, from the measurement width (what the solver uses). */
    public static function nComponentes(array $d, int $fallback = 1): int
    {
        $width = count($d['mediciones'][0] ?? []);

        return $width >= 4 ? intdiv($width, 2) - 1 : $fallback;
    }

    public static function toDto(array $d, array $componentes): array
    {
        $k = self::nComponentes($d, max(1, count($componentes)));
        $mediciones = $d['mediciones'] ?? [];
        $restricciones = $d['restricciones'] ?? [];
        $desviaciones = $d['desviaciones'] ?? self::desviaciones($mediciones, $k);
        $stock = array_flip(array_map(fn ($pair) => (int) ($pair[1] ?? 0) - 1, $d['indices_stock'] ?? []));

        $flujos = [];
        foreach ($d['flujos'] ?? [] as $i => $nombre) {
            $m = $mediciones[$i] ?? [];
            $r = $restricciones[$i] ?? [];
            $dv = $desviaciones[$i] ?? [];
            $var = fn (int $c) => [
                'medido' => self::num($m[$c] ?? 0),
                'reconciliado' => self::num($m[$c + 1] ?? 0),
                'tol_inf' => self::num($r[$c] ?? 0),
                'tol_sup' => self::num($r[$c + 1] ?? 0),
                'desv_inf' => self::num($dv[$c] ?? 0),
                'desv_sup' => self::num($dv[$c + 1] ?? 0),
            ];
            $flujos[] = [
                'idx' => $i,
                'nombre' => (string) $nombre,
                'tag' => isset($d['tags'][$i]) ? (string) $d['tags'][$i] : null,
                'descripcion' => isset($d['descripcion'][$i]) ? (string) $d['descripcion'][$i] : null,
                'jerarquia' => (int) ($d['jerarquia'][$i] ?? 1),
                'stock' => isset($stock[$i]),
                'tms' => $var(0),
                'leyes' => array_map(fn ($j) => $var(2 + 2 * $j), range(0, $k - 1)),
            ];
        }

        $matriz = array_map(fn ($row) => array_map(fn ($c) => (int) $c, $row), $d['matriz'] ?? []);
        $nodosData = $d['nodos_data'] ?? self::residuos($mediciones, $matriz, $k);
        $nodos = [];
        foreach ($d['nodos'] ?? [] as $n => $nombre) {
            $row = array_map([self::class, 'num'], $nodosData[$n] ?? []);
            $nodos[] = [
                'idx' => $n,
                'nombre' => (string) $nombre,
                'residuo_tms' => $row[0] ?? 0.0,
                'residuo_finos' => array_map(fn ($j) => $row[$j + 1] ?? 0.0, range(0, $k - 1)),
            ];
        }

        $inventarios = [];
        foreach ($d['inventarios'] ?? [] as $i => $nombre) {
            $col = fn (string $key) => self::num($d[$key][$i] ?? 0);
            $inventarios[] = [
                'idx' => $i,
                'nombre' => (string) $nombre,
                'tmh_ini' => $col('tmh_ini'),
                'tmh_fin' => $col('tmh_fin'),
                'tmh_delta' => $col('tmh_delta'),
                'humedad' => $col('humedad_inventario'),
                'tms_ini' => $col('tms_ini'),
                'tms_fin' => $col('tms_fin'),
                'tms_delta' => $col('tms_delta'),
                'leyes' => array_map(fn ($j) => self::num($d['componentes_inventario'][$i][$j] ?? 0), range(0, $k - 1)),
            ];
        }

        return [
            'componentes' => array_values(array_pad(array_slice($componentes, 0, $k), $k, '')),
            'flujos' => $flujos,
            'nodos' => $nodos,
            'matriz' => $matriz,
            'indices_stock' => array_map(fn ($p) => array_map('intval', $p), $d['indices_stock'] ?? []),
            'inventarios' => $inventarios,
        ];
    }

    /** Validation rules for an edits body, sized to this session. */
    public static function editRules(array $d): array
    {
        $k = self::nComponentes($d);
        $maxFlujo = max(0, count($d['flujos'] ?? []) - 1);
        $maxInv = max(0, count($d['inventarios'] ?? []) - 1);

        return [
            'flujos' => 'sometimes|array',
            'flujos.*.idx' => "required|integer|between:0,{$maxFlujo}|distinct",
            'flujos.*.tms_medido' => 'required|numeric',
            'flujos.*.leyes_medido' => "required|array|size:{$k}",
            'flujos.*.leyes_medido.*' => 'required|numeric|min:0',
            'flujos.*.tms_tol_inf' => 'required|numeric|min:0',
            'flujos.*.tms_tol_sup' => 'required|numeric|min:0',
            'flujos.*.leyes_tol' => "required|array|size:{$k}",
            'flujos.*.leyes_tol.*' => 'required|array|size:2',
            'flujos.*.leyes_tol.*.*' => 'required|numeric|min:0',
            'flujos.*.jerarquia' => 'required|integer|in:1,2',
            'inventarios' => 'sometimes|array',
            'inventarios.*.idx' => "required|integer|between:0,{$maxInv}|distinct",
            'inventarios.*.humedad' => 'required|numeric|min:0|lt:100',
            'inventarios.*.tms_ini' => 'required|numeric',
            'inventarios.*.tms_fin' => 'required|numeric',
            'inventarios.*.leyes' => "required|array|size:{$k}",
            'inventarios.*.leyes.*' => 'required|numeric|min:0',
        ];
    }

    /** Writes validated edits into the stored layout. Reconciled values and deviations are left as the last run left them. */
    public static function applyEdits(array $d, array $edits): array
    {
        foreach ($edits['flujos'] ?? [] as $f) {
            $i = (int) $f['idx'];
            $d['mediciones'][$i][0] = self::num($f['tms_medido']);
            $d['restricciones'][$i][0] = self::num($f['tms_tol_inf']);
            $d['restricciones'][$i][1] = self::num($f['tms_tol_sup']);
            foreach (array_values($f['leyes_medido']) as $j => $ley) {
                $d['mediciones'][$i][2 + 2 * $j] = self::num($ley);
            }
            foreach (array_values($f['leyes_tol']) as $j => [$inf, $sup]) {
                $d['restricciones'][$i][2 + 2 * $j] = self::num($inf);
                $d['restricciones'][$i][3 + 2 * $j] = self::num($sup);
            }
            $d['jerarquia'][$i] = (int) $f['jerarquia'];
        }

        foreach ($edits['inventarios'] ?? [] as $inv) {
            $i = (int) $inv['idx'];
            $h = self::num($inv['humedad']);
            $ini = self::num($inv['tms_ini']);
            $fin = self::num($inv['tms_fin']);
            $tmh = fn (float $tms) => $tms / (1 - $h / 100);
            $d['humedad_inventario'][$i] = $h;
            $d['tms_ini'][$i] = $ini;
            $d['tms_fin'][$i] = $fin;
            $d['tms_delta'][$i] = $fin - $ini;
            $d['tmh_ini'][$i] = $tmh($ini);
            $d['tmh_fin'][$i] = $tmh($fin);
            $d['tmh_delta'][$i] = $tmh($fin) - $tmh($ini);
            foreach (array_values($inv['leyes']) as $j => $ley) {
                $d['componentes_inventario'][$i][$j] = self::num($ley);
            }
        }

        return $d;
    }

    /** Same rule as the solver's desviaciones(): % the reconciled value moved below (inf) or above (sup) the measurement. */
    public static function desviaciones(array $mediciones, int $k): array
    {
        return array_map(function ($m) use ($k) {
            $out = [];
            for ($c = 0; $c < 2 + 2 * $k; $c += 2) {
                $med = self::num($m[$c] ?? 0);
                $rec = self::num($m[$c + 1] ?? 0);
                $pct = $med == 0 ? 0.0 : ($rec - $med) / $med * 100;
                $out[] = $pct < 0 ? -$pct : 0.0;
                $out[] = $pct > 0 ? $pct : 0.0;
            }

            return $out;
        }, $mediciones);
    }

    /** Same rule as the solver's nodos(): |A·tms_rec| and |A·finos_rec| per node, for rows saved without nodos_data. */
    public static function residuos(array $mediciones, array $matriz, int $k): array
    {
        $abs = array_map(fn ($m) => array_map(fn ($v) => abs(self::num($v)), $m), $mediciones);
        $leyEnPct = collect($abs)->contains(fn ($m) => ($m[2] ?? 0) > 1);

        return array_map(function ($row) use ($abs, $k, $leyEnPct) {
            $out = array_fill(0, $k + 1, 0.0);
            foreach ($row as $j => $c) {
                $tms = $abs[$j][1] ?? 0.0;
                $out[0] += $c * $tms;
                for ($q = 0; $q < $k; $q++) {
                    $ley = $abs[$j][3 + 2 * $q] ?? 0.0;
                    $out[$q + 1] += $c * $tms * ($leyEnPct ? $ley / 100 : $ley);
                }
            }

            return array_map('abs', $out);
        }, $matriz);
    }

    /** Stored numbers are numbers, but v1 once wrote "1.234,56" strings; read both. */
    public static function num(mixed $v): float
    {
        if (is_string($v) && str_contains($v, ',')) {
            $v = str_replace(',', '.', str_replace('.', '', $v));
        }

        return is_numeric($v) ? (float) $v : 0.0;
    }
}
