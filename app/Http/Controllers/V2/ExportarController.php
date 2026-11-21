<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Datos_entrada;
use App\Services\BalanceEventService;
use App\Services\DriveUploader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Excel export of a session: Flask /get_excel fills the process template (public/Export/excelnode.js), plus
 * optional PNGs from the browser that excelnode appends as new sheets. Streams the file and, when Drive works,
 * also uploads it and returns the link in X-Drive-Link.
 */
class ExportarController extends Controller
{
    private const FLASK_TIMEOUT = 60;
    private const NODE_TIMEOUT = 120;

    /** Upload field => [sheet, title, max display width in px]. */
    private const IMAGENES = [
        'diagrama' => ['Diagrama Sistema', 'Diagrama del proceso', 1800],
        'desbalance_nodos' => ['Gráficos Sistema', 'Desbalance medido por nodo', 1000],
        'uso_tolerancia' => ['Gráficos Sistema', 'Uso de tolerancia por medición', 1000],
        'tendencia' => ['Gráficos Sistema', 'Tendencia del ajuste de los principales sospechosos', 1000],
    ];

    public function exportar(Request $request, int $sesion)
    {
        // Real PNG by content (finfo), ≤ 8 MB, ≤ 12000 px a side.
        $request->validate(array_fill_keys(array_keys(self::IMAGENES), 'nullable|file|mimetypes:image/png|max:8192|dimensions:max_width=12000,max_height=12000'), [
            'mimetypes' => 'La imagen :attribute debe ser un PNG.',
            'max' => 'La imagen :attribute pesa más de 8 MB.',
            'dimensions' => 'La imagen :attribute mide más de 12000 px por lado.',
        ]);

        $s = Datos_entrada::query()->with(['proceso.valle', 'balance'])->find($sesion);
        if (!$s) {
            return response()->json(['message' => 'La sesión no existe.'], 404);
        }
        $plantilla = config('exportar.plantillas')[$s->proceso_id] ?? null;
        if (!$plantilla || !$s->proceso) {
            return response()->json(['message' => 'Este proceso no tiene una plantilla de exportación.'], 422);
        }
        $node = config('services.node.path');
        if (!$node) {
            Log::error('NODEPATH is not set');
            return response()->json(['message' => 'La exportación no está configurada en el servidor. Avisa al administrador.'], 500);
        }

        $componentes = json_decode($s->proceso->componentes ?? '', true)['data'] ?? [];
        $datos = $this->flask($s->id, $componentes);

        $tmp = storage_path('app/tmp');
        File::ensureDirectoryExists($tmp);
        $id = 'export_'.$s->id.'_'.Str::random(16);
        $out = "{$tmp}/{$id}.xlsx";
        $borrar = []; // the uploaded PNGs; $out too unless it is sent
        $listo = false;
        try {
            $hojas = [];
            foreach (self::IMAGENES as $campo => [$hoja, $titulo, $ancho]) {
                if (!$file = $request->file($campo)) {
                    continue;
                }
                $file->move($tmp, "{$id}_{$campo}.png");
                $borrar[] = $path = "{$tmp}/{$id}_{$campo}.png";
                $hojas[$hoja] ??= ['name' => $hoja, 'images' => []];
                $hojas[$hoja]['images'][] = ['title' => $titulo, 'path' => $path, 'maxWidth' => $ancho];
            }

            $public = public_path('Export');
            $args = [$node, "{$public}/excelnode.js", json_encode($datos['matriz']), json_encode($datos['data_extra']), $id, $public, "{$public}/{$plantilla}", $tmp];
            if ($hojas) {
                $args[] = json_encode(array_values($hojas), JSON_UNESCAPED_UNICODE);
            }
            $result = Process::timeout(self::NODE_TIMEOUT)->run($args);
            if (!$result->successful() || !is_file($out)) {
                Log::error('excelnode failed', ['sesion' => $s->id, 'exit' => $result->exitCode(), 'stderr' => mb_substr($result->errorOutput(), 0, 2000)]);
                return response()->json(['message' => 'No se pudo generar el Excel con la plantilla del proceso. Intenta nuevamente; si persiste, avisa al administrador.'], 500);
            }
            $listo = true;
        } catch (ProcessTimedOutException) {
            Log::error('excelnode timed out', ['sesion' => $s->id]);
            return response()->json(['message' => 'La generación del Excel tardó demasiado. Intenta nuevamente.'], 504);
        } finally {
            foreach ($listo ? $borrar : [...$borrar, $out] as $f) {
                @unlink($f);
            }
        }

        $nombre = $this->nombre($s);
        $headers = ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $stream = fopen($out, 'r');
        try {
            // Drive names keep v1's shape (…_<user>_<timestamp>) so uploads never collide.
            $headers['X-Drive-Link'] = app(DriveUploader::class)->upload(
                pathinfo($nombre, PATHINFO_FILENAME).'_'.$request->user()->id.'_'.now()->format('YmdHis').'.xlsx',
                $stream,
            );
        } catch (\Throwable $e) {
            Log::warning('Excel export: Drive upload failed, download only', ['sesion' => $s->id, 'error' => $e->getMessage()]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        BalanceEventService::logExported($s, $request->user());

        // Streamed from disk and deleted once sent: no second copy in memory.
        return response()->download($out, $nombre, $headers)->deleteFileAfterSend();
    }

    /** `<valle>_<proceso>_<periodo or today>.xlsx`, ASCII and file-safe. */
    private function nombre(Datos_entrada $s): string
    {
        $b = $s->balance;
        $periodo = $b && $b->periodo_inicio
            ? $b->periodo_inicio->format('Y-m-d').($b->periodo_fin && !$b->periodo_fin->equalTo($b->periodo_inicio) ? '_'.$b->periodo_fin->format('Y-m-d') : '')
            : now()->format('Y-m-d');

        return Str::slug($s->proceso->valle->nombre ?? 'valle', '_').'_'.Str::slug($s->proceso->nombre, '_').'_'.$periodo.'.xlsx';
    }

    /** Flask /get_excel, as v1 calls it: {matriz, data_extra}. */
    private function flask(int $id, array $componentes): array
    {
        $url = config('services.flask.url');
        if (!$url) {
            Log::error('FLASK_API_URL is not set');
            $this->fail(502, 'El solver no está configurado. Avisa al administrador.');
        }
        try {
            $raw = Http::acceptJson()->connectTimeout(5)->timeout(self::FLASK_TIMEOUT)
                ->post($url.'/get_excel', ['datos_entrada_id' => $id, 'componentes' => $componentes])->throw()->json();
            if (!is_array($raw) || !is_array($raw['matriz'] ?? null) || !array_key_exists('data_extra', $raw)) {
                throw new \UnexpectedValueException('matriz or data_extra missing');
            }

            return $raw;
        } catch (ConnectionException $e) {
            Log::error('Solver /get_excel unreachable', ['error' => $e->getMessage()]);
            $this->fail(502, 'El solver no respondió. Intenta nuevamente en unos minutos; si persiste, avisa al administrador.');
        } catch (RequestException $e) {
            Log::error('Solver /get_excel failed', ['status' => $e->response->status(), 'error' => $e->getMessage()]);
            $this->fail(502, 'El solver no pudo preparar los datos del Excel. Corre el balance y vuelve a intentar.');
        } catch (\UnexpectedValueException $e) {
            Log::error('Solver /get_excel returned an invalid payload', ['error' => $e->getMessage()]);
            $this->fail(502, 'El solver devolvió una respuesta inválida para el Excel.');
        }
    }

    private function fail(int $status, string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}
