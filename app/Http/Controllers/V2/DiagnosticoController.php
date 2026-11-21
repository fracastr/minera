<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Datos_entrada;
use App\Services\DiagnosticoService;

class DiagnosticoController extends Controller
{
    public function show(int $sesion)
    {
        // Only the columns the key and the trend need: datos_entrada is a large longText Flask reads itself.
        $s = Datos_entrada::query()->select(['id', 'proceso_id', 'updated_at'])->find($sesion);
        if (!$s) {
            return response()->json(['message' => 'La sesión no existe.'], 404);
        }

        return response()->json(DiagnosticoService::deSesion($s));
    }
}
