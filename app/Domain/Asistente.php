<?php

namespace App\Domain;

/**
 * What the balance assistant sends to the model and accepts back: the system prompt, the compact session context
 * (built from the session DTO and the cached diagnosis) and the one tool, `proponer_accion`, whose proposals are
 * validated here. Nothing in here executes a proposal; the user applies it from the UI.
 */
final class Asistente
{
    public const TOP_FLUJOS = 30;
    /** Same defaults as Flask's diagnostico.py and web/src/domain/diagnostico.ts. */
    private const TOL_DEFECTO_PCT = 5;
    private const ERROR_GRUESO_PCT = 25;
    private const ALFA = 0.05;

    public const SYSTEM = <<<'TXT'
Eres un ingeniero senior de balances metalúrgicos que apoya a los operadores de CMP (Compañía Minera del Pacífico, hierro) en la reconciliación de balances de masa. Los componentes son leyes de hierro: Fe T (hierro total) y, cuando existe, Fe Mag (hierro magnético), en %. TMS son toneladas métricas secas.

Tu tarea es ayudar a encontrar mediciones con error grueso y decidir qué cambiar antes de volver a correr el balance. Trabajas solo con el contexto JSON que viene a continuación: la sesión, el diagnóstico estadístico (prueba global χ², z por nodo, sospechosos con su valor sugerido y sus equivalentes), la tendencia histórica de ajustes y los flujos con mayor ajuste.

Reglas:
- Nunca inventes números. Cita solo valores que aparecen en el contexto, con el nombre del flujo tal como aparece.
- Si un sospechoso tiene "equivalentes", los datos no permiten distinguir cuál de esos flujos tiene el error: dilo explícitamente y sugiere cómo discriminar (verificación en terreno, calibración, muestreo).
- Recomienda verificación en terreno (pesómetro, muestreo, análisis químico) cuando la evidencia sea débil, el flujo no sea redundante o haya sesgo recurrente.
- Una tolerancia amplia puede ocultar errores; no propongas ampliar tolerancias para forzar el cierre si la evidencia apunta a un error de medición.
- Si el solver no convergió (solver.ok = false), los valores reconciliados no son confiables: dilo.
- Sé conciso: respuestas breves en español, en párrafos cortos o listas con "- ". Sin tablas ni encabezados.
- Para cada cambio concreto que recomiendes, llama a la herramienta proponer_accion (una vez por cambio, como máximo 5). El usuario decide si lo aplica; tú no ejecutas nada. No repitas en el texto los detalles de cada propuesta: basta una referencia breve.
TXT;

    public const TOOL = [
        'name' => 'proponer_accion',
        'description' => 'Propone al usuario un cambio concreto en una medición de la sesión. No ejecuta nada: el usuario lo revisa y decide si aplicarlo en la grilla y volver a correr el balance. '
            .'tipo "corregir_valor": valor es el nuevo valor medido. tipo "ampliar_tolerancia": valor es el valor que la medición debe poder alcanzar (como valor_sugerido del diagnóstico); la aplicación calcula la tolerancia. '
            .'tipo "revisar_medicion": verificación en terreno, sin valor. Usa solo flujos y valores presentes en el contexto.',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'flujo' => ['type' => 'string', 'description' => 'Nombre del flujo exactamente como aparece en el contexto.'],
                'variable' => ['type' => 'string', 'enum' => ['tms', 'ley_1', 'ley_2'], 'description' => 'tms, ley_1 (primer componente) o ley_2 (segundo componente).'],
                'tipo' => ['type' => 'string', 'enum' => ['corregir_valor', 'ampliar_tolerancia', 'revisar_medicion']],
                'valor' => ['type' => 'number', 'description' => 'Obligatorio para corregir_valor y ampliar_tolerancia; positivo, en t o en %.'],
                'motivo' => ['type' => 'string', 'description' => 'Una o dos frases que justifican la propuesta con valores del contexto.'],
            ],
            'required' => ['flujo', 'variable', 'tipo', 'motivo'],
            'additionalProperties' => false,
        ],
    ];

    /**
     * Compact, deterministic JSON (same session version → same bytes, so the prompt cache hits on every turn).
     *
     * @param array $dto  SesionMapper::toDto() output plus proceso/valle/solver/corrida
     * @param array $diag DiagnosticoService::deSesion() output
     */
    public static function contexto(array $dto, array $diag): string
    {
        $d = $diag['diagnostico'];
        $comp = $dto['componentes'];
        $varNombre = fn (string $v) => $v === 'tms' ? 'TMS' : ($comp[(int) substr($v, 4) - 1] ?? $v);
        $r = fn ($x, int $p = 3) => is_numeric($x) ? round((float) $x, $p) : null;
        $prueba = fn (array $g) => ['gamma' => $r($g['gamma'] ?? null), 'gl' => $g['gl'] ?? null, 'critico' => $r($g['critico'] ?? null), 'pasa' => (bool) ($g['pasa'] ?? false)];
        $nodoNombre = fn ($n) => is_int($n) ? ($dto['nodos'][$n]['nombre'] ?? "Nodo {$n}") : (string) $n;

        $sesgos = [];
        foreach ($diag['tendencia'] ?? [] as $clave => $t) { // keyed "nombre|tag"
            foreach ([['tms', $t], ...array_map(fn ($k, $v) => ['ley_'.($k + 1), $v], array_keys($t['leyes'] ?? []), $t['leyes'] ?? [])] as [$var, $v]) {
                if ($v['sesgo_recurrente'] ?? false) {
                    $sesgos[] = [
                        'flujo' => (string) ($t['flujo'] ?? strtok((string) $clave, '|')), 'tag' => $t['tag'] ?? null, 'variable' => $var, 'nombre_variable' => $varNombre($var),
                        'direccion' => ($v['signo'] ?? 0) > 0 ? 'el balance lo corrige hacia arriba' : 'el balance lo corrige hacia abajo',
                        'ultimos_ajustes_pct' => array_map(fn ($p) => $r($p['ajuste_pct'], 2), array_slice($v['serie'] ?? [], -4)),
                    ];
                }
            }
        }

        // Top flows by the largest |adjustment %| of any of their variables (magnitudes: a negative TMS only marks a stock).
        $vars = fn (array $f) => ['tms' => $f['tms'], ...array_combine(array_map(fn ($k) => 'ley_'.($k + 1), array_keys($f['leyes'])), $f['leyes'])];
        $ajuste = fn (array $x) => abs($x['medido']) > 0 ? (abs($x['reconciliado']) - abs($x['medido'])) / abs($x['medido']) * 100 : 0.0;
        $flujos = array_map(fn ($f) => [
            'flujo' => $f['nombre'], 'tag' => $f['tag'],
            'max' => max(array_map(fn ($x) => abs($ajuste($x)), $vars($f))),
            'vars' => array_map(fn ($x) => [
                'medido' => $r($x['medido']), 'reconciliado' => $r($x['reconciliado']),
                'ajuste_pct' => $r($ajuste($x), 2), 'tol_inf_pct' => $r($x['tol_inf'], 2), 'tol_sup_pct' => $r($x['tol_sup'], 2),
            ], $vars($f)),
        ], $dto['flujos']);
        usort($flujos, fn ($a, $b) => $b['max'] <=> $a['max'] ?: strcmp($a['flujo'], $b['flujo']));
        $top = array_map(fn ($f) => ['flujo' => $f['flujo'], 'tag' => $f['tag']] + $f['vars'], array_slice($flujos, 0, self::TOP_FLUJOS));

        return json_encode([
            'proceso' => $dto['proceso']['nombre'] ?? null,
            'valle' => $dto['proceso']['valle']['nombre'] ?? null,
            'componentes' => array_combine(array_map(fn ($k) => 'ley_'.($k + 1), array_keys($comp)), $comp) ?: (object) [],
            'balance_corrido' => (bool) ($dto['corrida'] ?? false),
            'solver' => $dto['solver'] ?? null,
            'prueba_global' => $prueba($d['global'] ?? []),
            'prueba_global_tras_quitar_sospechosos' => $prueba($d['global_final'] ?? []),
            'finos_por_componente' => array_map(fn ($c) => ['componente' => $comp[$c['componente_idx'] ?? 0] ?? null, 'prueba_global' => $prueba($c['global'] ?? [])], $d['por_componente'] ?? []),
            'nodos' => array_map(fn ($n) => ['nodo' => $nodoNombre($n['nodo'] ?? ''), 'z' => $r($n['z'] ?? null, 2), 'residuo_t' => $r($n['residuo'] ?? null, 1), 'no_cierra' => (bool) ($n['sospechoso'] ?? false)], $d['nodos'] ?? []),
            'sospechosos' => array_map(fn ($s) => [
                'flujo' => $s['flujo'] ?? null, 'tag' => $s['tag'] ?? null, 'variable' => $s['variable'] ?? null, 'nombre_variable' => $varNombre((string) ($s['variable'] ?? '')),
                'z' => $r($s['z'] ?? null, 2), 'valor_medido' => $r($s['valor_medido'] ?? null), 'valor_sugerido' => $r($s['valor_sugerido'] ?? null),
                'reduccion_gamma' => $r($s['reduccion_gamma'] ?? null, 2), 'accion' => $s['accion'] ?? null,
                'equivalentes' => array_map(fn ($e) => ['flujo' => $e['flujo'] ?? null, 'variable' => $e['variable'] ?? null, 'valor_sugerido' => $r($e['valor_sugerido'] ?? null)], $s['equivalentes'] ?? []),
            ], $d['sospechosos'] ?? []),
            'sin_redundancia' => array_values($d['no_redundantes'] ?? []),
            'excluidos_del_diagnostico' => array_values($d['excluidos'] ?? []),
            'sesgo_recurrente' => $sesgos,
            'tolerancias_amplias' => self::toleranciasAmplias($dto, $varNombre),
            'flujos_mayor_ajuste' => $top,
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /** Port of toleranciasAmplias() in web/src/domain/diagnostico.ts: tolerances so wide a 25 % error cannot be flagged. */
    private static function toleranciasAmplias(array $dto, \Closure $varNombre): array
    {
        $enBalance = fn (int $j) => collect($dto['matriz'])->contains(fn ($row) => ($row[$j] ?? 0) != 0);
        $medidos = array_filter($dto['flujos'], fn ($f) => $f['tms']['medido'] != 0 && $enBalance($f['idx']));
        $tolMax = self::ERROR_GRUESO_PCT * 1.96 / self::sidakZ(count($medidos));
        $out = [];
        foreach ($medidos as $f) {
            foreach (['tms' => $f['tms'], ...array_combine(array_map(fn ($k) => 'ley_'.($k + 1), array_keys($f['leyes'])), $f['leyes'])] as $v => $x) {
                $tol = ($x['tol_inf'] + $x['tol_sup']) / 2;
                $tolPct = $tol > 0 ? $tol : self::TOL_DEFECTO_PCT;
                if ($x['medido'] && $tolPct > $tolMax) {
                    $out[] = ['flujo' => $f['nombre'], 'variable' => $v, 'nombre_variable' => $varNombre($v), 'tol_pct' => round($tolPct, 2)];
                }
            }
        }

        return ['umbral_pct' => round($tolMax, 2), 'flujos' => $out];
    }

    /** Two-sided critical z with Šidák correction over n tests (diagnostico.py's sidak_z). */
    public static function sidakZ(int $n): float
    {
        return self::normPpf(1 - (1 - (1 - self::ALFA) ** (1 / max($n, 1))) / 2);
    }

    /** Standard normal quantile (Acklam), as in diagnostico.py. */
    public static function normPpf(float $p): float
    {
        $a = [-39.69683028665376, 220.9460984245205, -275.9285104469687, 138.357751867269, -30.66479806614716, 2.506628277459239];
        $b = [-54.47609879822406, 161.5858368580409, -155.6989798598866, 66.80131188771972, -13.28068155288572];
        $c = [-0.007784894002430293, -0.3223964580411365, -2.400758277161838, -2.549732539343734, 4.374664141464968, 2.938163982698783];
        $d = [0.007784695709041462, 0.3224671290700398, 2.445134137142996, 3.754408661907416];
        $pl = 0.02425;
        if ($p < $pl) {
            $q = sqrt(-2 * log($p));

            return ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
        }
        if ($p > 1 - $pl) {
            return -self::normPpf(1 - $p);
        }
        $q = $p - 0.5;
        $r = $q * $q;

        return ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q / ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1);
    }

    /**
     * A proposal the client may show, or the reason it is dropped. Server-side checks: the flow is in the session,
     * the variable fits its component count, and a value (when the action needs one) is finite, positive and plausible.
     *
     * @return array{0: ?array, 1: ?string} [proposal, rejection reason]
     */
    public static function validarPropuesta(mixed $input, array $dto): array
    {
        if (!is_array($input)) {
            return [null, 'entrada no es un objeto'];
        }
        $flujo = $input['flujo'] ?? null;
        $variable = $input['variable'] ?? null;
        $tipo = $input['tipo'] ?? null;
        $motivo = is_string($input['motivo'] ?? null) ? trim($input['motivo']) : '';
        $k = count($dto['componentes']);

        if (!is_string($flujo) || !in_array($flujo, array_column($dto['flujos'], 'nombre'), true)) {
            return [null, 'flujo inexistente en la sesión'];
        }
        if (!is_string($variable) || !preg_match('/^(tms|ley_([1-9]))$/', $variable, $m) || (isset($m[2]) && (int) $m[2] > $k)) {
            return [null, 'variable no válida para los componentes de la sesión'];
        }
        if (!in_array($tipo, ['corregir_valor', 'ampliar_tolerancia', 'revisar_medicion'], true)) {
            return [null, 'tipo no válido'];
        }
        if ($motivo === '') {
            return [null, 'sin motivo'];
        }
        $valor = null;
        if ($tipo !== 'revisar_medicion') {
            $v = $input['valor'] ?? null;
            // is_int/is_float, not is_numeric: the schema says number, and a string here means the model went off-script.
            if (!(is_int($v) || is_float($v)) || !is_finite((float) $v) || $v <= 0 || $v > SesionMapper::MAX_ABS) {
                return [null, 'valor ausente, no finito o no positivo'];
            }
            if ($variable !== 'tms' && $v > 100) {
                return [null, 'ley sobre 100 %'];
            }
            $valor = (float) $v;
        }

        return [['flujo' => $flujo, 'variable' => $variable, 'tipo' => $tipo, 'valor' => $valor, 'motivo' => mb_substr($motivo, 0, 600)], null];
    }
}
