<?php

namespace App\Http\Controllers;

use App\Models\BalanceCorrida;
use App\Models\Balances;
use App\Services\BalanceEventService;
use App\Models\Datos_entrada;
use App\Models\Procesos;
use App\Models\User;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BalancesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        dd("testea");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Balances  $balances
     * @return \Illuminate\Http\Response
     */
    public function show(Balances $balances)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Balances  $balances
     * @return \Illuminate\Http\Response
     */
    public function edit(Balances $balances)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Balances  $balances
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Balances $balances)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Balances  $balances
     * @return \Illuminate\Http\Response
     */
    public function destroy(Balances $balances)
    {
        //
    }

    public function createTableMedicionesFields($mediciones, $flujos){
        $tabla_mediciones_fields = array();
        foreach($mediciones as $key_mediciones => $value_mediciones){
            if(sizeof($value_mediciones) == 4){
                $tabla_mediciones_fields[0] = (object) ['field' => 'Flujos', 'resizable' => true, 'width' => 200, 'pinned' => 'left', 'editable' => false];
                $tabla_mediciones_fields[1] = (object) ['field' => 'TMS medido', 'resizable' => true, 'width' => 100, 'editable' => true];
                $tabla_mediciones_fields[2] = (object) ['field' => 'TMS balance', 'resizable' => true, 'width' => 100, 'editable' => false, 'cellClass' => 'balance'];
                $tabla_mediciones_fields[3] = (object) ['field' => 'Fet [%] Medido', 'resizable' => true, 'width' => 100, 'editable' => true];
                $tabla_mediciones_fields[4] = (object) ['field' => 'Fet [%] Balance', 'resizable' => true, 'width' => 100, 'editable' => false, 'cellClass' => 'balance'];
            }
            else if(sizeof($value_mediciones) == 6){
                $tabla_mediciones_fields[0] = (object) ['field' => 'Flujos', 'resizable' => true, 'width' => 200, 'pinned' => 'left', 'editable' => false];
                $tabla_mediciones_fields[1] = (object) ['field' => 'TMS medido', 'resizable' => true, 'width' => 100, 'editable' => true];
                $tabla_mediciones_fields[2] = (object) ['field' => 'TMS balance', 'resizable' => true, 'width' => 100, 'editable' => false, 'cellClass' => 'balance'];
                $tabla_mediciones_fields[3] = (object) ['field' => 'Fet [%] Medido', 'resizable' => true, 'width' => 100, 'editable' => true];
                $tabla_mediciones_fields[4] = (object) ['field' => 'Fet [%] Balance', 'resizable' => true, 'width' => 100, 'editable' => false, 'cellClass' => 'balance'];
                $tabla_mediciones_fields[5] = (object) ['field' => 'FeMag [%] Medido', 'resizable' => true, 'width' => 100, 'editable' => true];
                $tabla_mediciones_fields[6] = (object) ['field' => 'FeMag [%] Balance', 'resizable' => true, 'width' => 100, 'editable' => false, 'cellClass' => 'balance'];
            }
        }
        array_unshift($tabla_mediciones_fields, (object) ['field' => 'Row', 'resizable' => true, 'width' => 50, 'pinned' => 'left', 'editable' => false, 'valueGetter' => 'node.rowIndex + 1']);
        return $tabla_mediciones_fields;
    }

    public function createTableMediciones($mediciones, $flujos){
        $array_mediciones = array();
        foreach($mediciones as $key_mediciones => $value_mediciones){
            $object_mediciones = array();
            if(sizeof($value_mediciones) == 4){
                $object_mediciones['Flujos'] = $flujos[$key_mediciones];
                $object_mediciones['TMS medido'] = number_format($value_mediciones[0], 0, '.', '');
                $object_mediciones['TMS balance'] = number_format($value_mediciones[1], 0, '.', '');
                $object_mediciones['Fet [%] Medido'] = number_format($value_mediciones[2], 2);
                $object_mediciones['Fet [%] Balance'] = number_format($value_mediciones[3], 2);
            }
            else if(sizeof($value_mediciones) == 6){
                $object_mediciones['Flujos'] = $flujos[$key_mediciones];
                $object_mediciones['TMS medido'] = number_format($value_mediciones[0], 0, '.', '');
                $object_mediciones['TMS balance'] = number_format($value_mediciones[1], 0, '.', '');
                $object_mediciones['Fet [%] Medido'] = number_format($value_mediciones[2], 2);
                $object_mediciones['Fet [%] Balance'] = number_format($value_mediciones[3], 2);
                $object_mediciones['FeMag [%] Medido'] = number_format($value_mediciones[4], 2);
                $object_mediciones['FeMag [%] Balance'] = number_format($value_mediciones[5], 2);
            }
            array_push($array_mediciones, (object)$object_mediciones);
        }

        return $array_mediciones;
    }

    public function createTableRestriccionesFields($restricciones, $jerarquia){
        $tabla_restricciones_fields = array();
        if(sizeof($restricciones[0]) == 4){
            $tabla_restricciones_fields[0] = (object) ['field' => 'Flujos', 'resizable' => true, 'width' => 200, 'pinned' => 'left', 'editable' => false];
            $tabla_restricciones_fields[1] = (object) ['field' => 'TMS Inf a[%]', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[2] = (object) ['field' => 'TMS Inf [%]', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[3] = (object) ['field' => 'TMS Sup a[%]', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[4] = (object) ['field' => 'TMS Sup [%]', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[5] = (object) ['field' => 'Fet [%] aInf', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[6] = (object) ['field' => 'Fet [%] Inf', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[7] = (object) ['field' => 'Fet [%] aSup', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[8] = (object) ['field' => 'Fet [%] Sup', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[9] = (object) ['field' => 'Jerarquia', 'resizable' => true, 'width' => 100, 'editable' => true];
        }
        else if(sizeof($restricciones[0]) == 6){
            $tabla_restricciones_fields[0] = (object) ['field' => 'Flujos', 'resizable' => true, 'width' => 200, 'pinned' => 'left', 'editable' => false];
            $tabla_restricciones_fields[1] = (object) ['field' => 'TMS Inf a[%]', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[2] = (object) ['field' => 'TMS Inf [%]', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[3] = (object) ['field' => 'TMS Sup a[%]', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[4] = (object) ['field' => 'TMS Sup [%]', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[5] = (object) ['field' => 'Fet [%] aInf', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[6] = (object) ['field' => 'Fet [%] Inf', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[7] = (object) ['field' => 'Fet [%] aSup', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[8] = (object) ['field' => 'Fet [%] Sup', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[9] = (object) ['field' => 'FeMag [%] aInf', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[10] = (object) ['field' => 'FeMag [%] Inf', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[11] = (object) ['field' => 'FeMag [%] aSup', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[12] = (object) ['field' => 'FeMag [%] Sup', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[13] = (object) ['field' => 'Jerarquia', 'resizable' => true, 'width' => 100, 'editable' => true];
        }
        array_unshift($tabla_restricciones_fields, (object) ['field' => 'Row', 'resizable' => true, 'width' => 50, 'pinned' => 'left', 'editable' => false, 'valueGetter' => 'node.rowIndex + 1']);
        return $tabla_restricciones_fields;
    }

    public function createTableRestriccionesData($restricciones, $jerarquia, $desviaciones, $flujos){
        $tabla_restricciones = array();
        foreach ($restricciones as $key_restricciones => $value_restricciones) {
            $object_restricciones = array();
            if(sizeof($value_restricciones) == 4){
                $object_restricciones['Flujos'] = $flujos[$key_restricciones];
                $object_restricciones['TMS Inf a[%]'] = $value_restricciones[0];
                $object_restricciones['TMS Inf [%]'] = $desviaciones[$key_restricciones][0];
                $object_restricciones['TMS Sup a[%]'] = $value_restricciones[1];
                $object_restricciones['TMS Sup [%]'] = $desviaciones[$key_restricciones][1];
                $object_restricciones['Fet [%] aInf'] = $value_restricciones[2];
                $object_restricciones['Fet [%] Inf'] = $desviaciones[$key_restricciones][2];
                $object_restricciones['Fet [%] aSup'] = $value_restricciones[3];
                $object_restricciones['Fet [%] Sup'] = $desviaciones[$key_restricciones][3];
                $object_restricciones['Jerarquia'] = $jerarquia[$key_restricciones];
            }
            else if(sizeof($value_restricciones) == 6){
                $object_restricciones['Flujos'] = $flujos[$key_restricciones];
                $object_restricciones['TMS Inf a[%]'] = $value_restricciones[0];
                $object_restricciones['TMS Inf [%]'] = $desviaciones[$key_restricciones][0];
                $object_restricciones['TMS Sup a[%]'] = $value_restricciones[1];
                $object_restricciones['TMS Sup [%]'] = $desviaciones[$key_restricciones][1];
                $object_restricciones['Fet [%] aInf'] = $value_restricciones[2];
                $object_restricciones['Fet [%] Inf'] = $desviaciones[$key_restricciones][2];
                $object_restricciones['Fet [%] aSup'] = $value_restricciones[3];
                $object_restricciones['Fet [%] Sup'] = $desviaciones[$key_restricciones][3];
                $object_restricciones['FeMag [%] aInf'] = $value_restricciones[4];
                $object_restricciones['FeMag [%] Inf'] = $desviaciones[$key_restricciones][4];
                $object_restricciones['FeMag [%] aSup'] = $value_restricciones[5];
                $object_restricciones['FeMag [%] Sup'] = $desviaciones[$key_restricciones][5];
                $object_restricciones['Jerarquia'] = $jerarquia[$key_restricciones];
            }
            array_push($tabla_restricciones, (object)$object_restricciones);
        }

        return $tabla_restricciones;
    }

    public function createTableBalanceNodosFields($componentes){
        $array_nodos_fields = array();
        $array_nodos_fields[0] = (object) ['field' => 'Nodos', 'resizable' => true, 'editable' => false, 'width' => 200, 'pinned' => 'left'];
        $array_nodos_fields[1] = (object) ['field' => 'TMS', 'resizable' => true, 'editable' => false];
        $array_nodos_fields[2] = (object) ['field' => 'Finos FeT', 'resizable' => true, 'editable' => false];
        if(sizeof($componentes) == 2){
            $array_nodos_fields[3] = (object) ['field' => 'Finos FeMag', 'resizable' => true, 'editable' => false];
        }

        return $array_nodos_fields;
    }

    public function createTableBalanceNodos($balance_nodos, $nodos_data, $nodos, $componentes){
        $array_balance_nodos = array();
        foreach ($balance_nodos as $key_balance_nodos => $value_balance_nodos) {
            $object_balance_nodos = array();
            $object_balance_nodos['Nodos'] = $nodos[$key_balance_nodos];
            $object_balance_nodos['TMS'] = str_replace(",",".",str_replace(".","",$nodos_data[$key_balance_nodos][0]));
            $object_balance_nodos['TMS'] = number_format($object_balance_nodos['TMS'], 0, '.', '');
            $object_balance_nodos['Finos FeT'] = str_replace(",",".",str_replace(".","",$nodos_data[$key_balance_nodos][1]));
            $object_balance_nodos['Finos FeT'] = number_format($object_balance_nodos['Finos FeT'], 0, '.', '');
            if(sizeof($componentes) == 2){
                $object_balance_nodos['Finos FeMag'] = str_replace(",",".",str_replace(".","",$nodos_data[$key_balance_nodos][2]));
                $object_balance_nodos['Finos FeMag'] = number_format($object_balance_nodos['Finos FeMag'], 0, '.', '');
            }
            array_push($array_balance_nodos, (object)$object_balance_nodos);
        }

        return $array_balance_nodos;
    }

    public function createTableInventariosFields($componentes){
        $tabla_inventarios_fields = array();

        $tabla_inventarios_fields[0] = (object) ['field' => 'Inventario', 'resizable' => true, 'editable' => false, 'width' => 200, 'pinned' => 'left'];
        $tabla_inventarios_fields[1] = (object) ['field' => 'TMH INI', 'resizable' => true, 'editable' => false, 'width' => 100];
        $tabla_inventarios_fields[2] = (object) ['field' => 'TMH FIN', 'resizable' => true, 'editable' => false, 'width' => 100];
        $tabla_inventarios_fields[3] = (object) ['field' => 'TMH Delta', 'resizable' => true, 'cellClass' => 'calculated', 'width' => 100];
        $tabla_inventarios_fields[4] = (object) ['field' => 'Humedad [%]', 'resizable' => true, 'editable' => true, 'width' => 120];
        $tabla_inventarios_fields[5] = (object) ['field' => 'TMS INI', 'resizable' => true, 'editable' => true, 'width' => 100];
        $tabla_inventarios_fields[6] = (object) ['field' => 'TMS FIN', 'resizable' => true, 'editable' => true, 'width' => 100];
        $tabla_inventarios_fields[7] = (object) ['field' => 'TMS Delta', 'resizable' => true, 'cellClass' => 'calculated', 'width' => 100];
        // Aqui falta la que viene desde la base de datos
        foreach ($componentes as $key => $value) {
            $tabla_inventarios_fields[sizeof($tabla_inventarios_fields)] = (object) ['field' => $value . ' [%]', 'resizable' => true, 'editable'=> true,'width' => 70];
        }

        return $tabla_inventarios_fields;
    }

    public function createTableInventariosData($data, $componentes){
        $inventarios = $data->datos_entrada['inventarios'];
        $tmh_ini = $data->datos_entrada['tmh_ini'];
        $tmh_fin = $data->datos_entrada['tmh_fin'];
        $tmh_delta = $data->datos_entrada['tmh_delta'];
        $humedad_inventario = $data->datos_entrada['humedad_inventario'];
        $tms_ini = $data->datos_entrada['tms_ini'];
        $tms_fin = $data->datos_entrada['tms_fin'];
        $tms_delta = $data->datos_entrada['tms_delta'];
        $componentes_inventario = $data->datos_entrada['componentes_inventario'];

        $tabla_inventarios_data = array();
        foreach ($inventarios as $key_inventarios => $value_inventarios) {
            $object_inventarios = array();

            $object_inventarios['Inventario'] = $value_inventarios;
            $object_inventarios['TMH INI'] = $tmh_ini[$key_inventarios];
            $object_inventarios['TMH FIN'] = $tmh_fin[$key_inventarios];
            $object_inventarios['TMH Delta'] = $tmh_delta[$key_inventarios];
            $object_inventarios['Humedad [%]'] = $humedad_inventario[$key_inventarios];
            $object_inventarios['TMS INI'] = $tms_ini[$key_inventarios];
            $object_inventarios['TMS FIN'] = $tms_fin[$key_inventarios];
            $object_inventarios['TMS Delta'] = $tms_delta[$key_inventarios];
            foreach ($componentes as $key => $value) {
                $object_inventarios[$value.' [%]'] = $componentes_inventario[$key_inventarios][$key];
            }

            array_push($tabla_inventarios_data, (object)$object_inventarios);
        }

        return $tabla_inventarios_data;
    }

    public function import(Request $request)
    {
        $request->validate([
            'proceso_id' => 'required|integer|exists:procesos,id',
            'file' => app()->environment('local') ? 'nullable' : 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            $user_id = $user->id;
            $timestamp = now()->format('YmdHis');

            if (app()->environment('local')) {
                $path = "/home/ubuntu/minera/storage/app/public/I6lpMJiywp3X2ifsT7M3ujh9WWsxPeZgarZbK4Jp.xlsx";
                $originalFileName = "I6lpMJiywp3X2ifsT7M3ujh9WWsxPeZgarZbK4Jp.xlsx";
            } else {
                $file = $request->file('file');
                $originalFileName = $file->getClientOriginalName();
            }

            // Construir nuevo nombre: nombre_original_timestamp_userid.extension
            $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
            $fileNameWithoutExtension = pathinfo($originalFileName, PATHINFO_FILENAME);
            $newFileName = $fileNameWithoutExtension . '_' . $timestamp . '_' . $user_id . '.' . $extension;

            if (!app()->environment('local')) {
                // Guardar archivo con el nuevo nombre
                $file = $request->file('file');
                $path = $file->storeAs('public', $newFileName);
                $path = '/home/ubuntu/minera/storage/app/'. $path;
            }

        $proceso_id = $request->proceso_id;
        $proceso = Procesos::find($proceso_id);
        $nombre_proceso = $proceso->nombre;
        $url = config('services.flask.url') . '/get_balance';
        $myBody['path_name'] = $path;
        $response = Http::acceptJson()->post($url, [
            'path_name' => $path,
            'nombre_proceso' => $nombre_proceso
        ])->throw();

        $data = json_decode($response->getBody()->getContents());
        foreach ($data as $key => &$value) {
            $value = json_decode($value, true);
        }
        unset($value);

        // logica para tabla mediciones
        $mediciones = $data->datos_entrada['mediciones'];
        $flujos = $data->datos_entrada['flujos'];
        $desviaciones = $data->desviaciones;
        $balances_finales = $data->balances_finales;


        $table_mediciones_fields = $this->createTableMedicionesFields($mediciones, $flujos);
        $array_mediciones = $this->createTableMediciones($mediciones, $flujos);

        // logica tabla restricciones
        $restricciones = $data->datos_entrada['restricciones'];
        $jerarquia = $data->datos_entrada['jerarquia'];

        // logica tabla restricciones fields
        $tabla_restricciones_fields = $this->createTableRestriccionesFields($restricciones, $jerarquia);
        // logica tabla restricciones data
        $tabla_restricciones = $this->createTableRestriccionesData($restricciones, $jerarquia, $desviaciones, $flujos);

        // logica tabla nodos
        $nodos_data = $data->nodos_data;
        foreach ($nodos_data as $key_nodos_data => &$value_nodos_data) {
            foreach($value_nodos_data as $key_value_nodos_data => &$value_nodos_data_item){
                $value_nodos_data_item = number_format($value_nodos_data_item, 2, ',', '.');
            }
        }

        // logica tabla balance nodos
        $balance_nodos = $data->datos_entrada['matriz'];
        $jerarquia = $data->datos_entrada['jerarquia'];
        $nodos = $data->datos_entrada['nodos'];

        $componentes = json_decode($proceso->componentes);
        $componentes = $componentes->data;

        $balance_nodos_fields = $this->createTableBalanceNodosFields($componentes);
        $array_balance_nodos = $this->createTableBalanceNodos($balance_nodos, $nodos_data, $nodos, $componentes);

        // logica tabla inventarios

        $inventarios_fields = $this->createTableInventariosFields($componentes);
        $inventarios_data = $this->createTableInventariosData($data, $componentes);

        $data_entrada = $data->datos_entrada;
        $data_entrada['desviaciones'] = $data->desviaciones;
        $data_entrada['nodos_data'] = $data->nodos_data;
        $datos_entrada_model = new Datos_entrada();
        $datos_entrada_model->datos_entrada = json_encode($data_entrada);
        $datos_entrada_model->proceso_id = $proceso_id;
        $datos_entrada_model->valle_id = $proceso->valle_id;

        // Guardar el nombre del archivo con timestamp y user_id
        $datos_entrada_model->file_path = $newFileName;
        $datos_entrada_model->user_id = $user_id;

        $datos_entrada_model->save();

        BalanceEventService::logImported($datos_entrada_model, $user);
        BalanceCorrida::record($datos_entrada_model, $user, BalanceCorrida::TIPO_IMPORTACION);

        $datos_entrada_id = $datos_entrada_model->id;

        return [
        'data' => $data,
        'balances_table' => $array_mediciones,
        'balances_fields' => $table_mediciones_fields,
        'nodos_data' => $nodos_data,
        'restricciones_table' => $tabla_restricciones,
        'balance_nodos' => $array_balance_nodos,
        'restricciones_fields' => $tabla_restricciones_fields,
        'balance_nodos_fields' => $balance_nodos_fields,
        'inventarios_fields' => $inventarios_fields,
        'inventarios_data' => $inventarios_data,
        'path' => $path,
        'datos_entrada_id' => $datos_entrada_id];
        } catch (HttpClientException $e) {
            return $this->solverError('import', $e);
        }
    }

    private function solverError(string $action, \Throwable $e)
    {
        Log::error("Balance solver failed on {$action}", ['error' => $e->getMessage()]);

        return response()->json(['message' => 'El servicio de cálculo no respondió correctamente.'], 502);
    }

    /**
     * Writes the edited inventory grid rows back into datos_entrada so the solver sees them.
     * Only keys that already exist in datos_entrada are touched.
     */
    public static function applyInventariosTable(array $datosEntrada, $inventariosTable, array $componentes): array
    {
        $columns = [
            'tmh_ini' => 'TMH INI',
            'tmh_fin' => 'TMH FIN',
            'tmh_delta' => 'TMH Delta',
            'humedad_inventario' => 'Humedad [%]',
            'tms_ini' => 'TMS INI',
            'tms_fin' => 'TMS FIN',
            'tms_delta' => 'TMS Delta',
        ];
        $toNumber = fn ($v) => floatval(str_replace(',', '', (string) $v));

        foreach ((array) $inventariosTable as $i => $row) {
            $row = (array) $row;
            foreach ($columns as $key => $field) {
                if (array_key_exists($field, $row) && isset($datosEntrada[$key]) && array_key_exists($i, $datosEntrada[$key])) {
                    $datosEntrada[$key][$i] = $toNumber($row[$field]);
                }
            }
            foreach ($componentes as $k => $componente) {
                $field = $componente . ' [%]';
                if (array_key_exists($field, $row) && isset($datosEntrada['componentes_inventario'][$i])
                    && array_key_exists($k, $datosEntrada['componentes_inventario'][$i])) {
                    $datosEntrada['componentes_inventario'][$i][$k] = $toNumber($row[$field]);
                }
            }
        }

        return $datosEntrada;
    }

    public function get_listado(Request $request)
    {
        $listado = Balances::query()
            ->select(['id', 'nombre', 'tipo', 'periodo_inicio', 'periodo_fin', 'proceso_id', 'user_id', 'created_at', 'updated_at'])
            ->with([
                'user:id,nombre',
                'datosEntrada.user:id,nombre',
                'proceso:id,nombre,valle_id',
                'proceso.valle:id,nombre',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->values()
            ->map(function (Balances $balance, int $index) {
                $creator = $balance->creador();

                return [
                    'numero' => $index + 1,
                    'id' => $balance->id,
                    'nombre' => $balance->nombre,
                    'tipo' => $balance->tipo,
                    'periodo_inicio' => optional($balance->periodo_inicio)->format('Y-m-d'),
                    'periodo_fin' => optional($balance->periodo_fin)->format('Y-m-d'),
                    'proceso_id' => $balance->proceso_id,
                    'user_id' => $creator ? $creator->id : $balance->user_id,
                    'created_at' => $balance->created_at,
                    'updated_at' => $balance->updated_at,
                    'proceso' => $balance->proceso,
                    'usuario_nombre' => $creator ? $creator->nombre : 'Sin registrar',
                    'user' => $creator ? [
                        'id' => $creator->id,
                        'nombre' => $creator->nombre,
                    ] : null,
                ];
            });

        return response()->json(['listado' => $listado]);
    }

    public function correr_balance(Request $request)
    {
        try {

        $datos_entrada_id = $request->datos_entrada_id;
        $datos_entrada = $request->datos_entrada;
        $balances_table = $request->balances_table;
        $restricciones_table = $request->restricciones_table;
        $proceso_id = $request->proceso_id;
        $inventarios_table = $request->inventarios_table;

        $proceso = Procesos::find($proceso_id);
        $componentes = json_decode($proceso->componentes)->data;
        $datos_entrada = self::applyInventariosTable($datos_entrada, $inventarios_table, $componentes);

        $new_mediciones = array();

        foreach($balances_table as $key => $value){
            $new_mediciones_array = array();
            foreach($value as $key2 => $value2){
                if($key2 != "Flujos"){
                    array_push($new_mediciones_array, floatval(str_replace(",","",$value2)));
                }
            }
            array_push($new_mediciones, $new_mediciones_array);
        }

        //actualizo las mediciones
        $datos_entrada['mediciones'] = $new_mediciones;
        $new_restricciones = array();
        $new_jerarquia = array();


        foreach($restricciones_table as $key_restricciones => $value_restricciones){
            $contador = 0;
            $new_restricciones_array = array();
            foreach($value_restricciones as $key_value_restricciones => $value_restricciones_data){
                if($contador % 2 != 0 && $key_value_restricciones != "Jerarquia" && $key_value_restricciones != "Flujos"){
                    array_push($new_restricciones_array, floatval(str_replace(",","",$value_restricciones_data)));
                }
                else if($key_value_restricciones == "Jerarquia"){
                    array_push($new_jerarquia, intval($value_restricciones_data));
                }
                $contador++;
            }

            array_push($new_restricciones, $new_restricciones_array);
        }
        // actualiza las restricciones
        $datos_entrada['restricciones'] = $new_restricciones;
        $datos_entrada['jerarquia'] = $new_jerarquia;

        //update el json en la tabla de la base de datos
        $datos_entrada_data = Datos_entrada::find($datos_entrada_id);
        $datos_entrada_data->datos_entrada = json_encode($datos_entrada);
        $datos_entrada_data->save();

        BalanceEventService::logCorrer($datos_entrada_data, $request->user());

        $url = config('services.flask.url') . '/correr_balance';
        $myBody['datos_entrada_id'] = $datos_entrada_id;
        $response = Http::acceptJson()->post($url, [
            'datos_entrada_id' => $datos_entrada_id,
        ])->throw();

        $data = json_decode($response->getBody()->getContents());

        foreach ($data as $key => &$value) {
            $value = json_decode($value, true);
        }
        unset($value);



        // logica para tabla mediciones
        $mediciones = $data->datos_entrada['mediciones'];
        $flujos = $data->datos_entrada['flujos'];
        $desviaciones = $data->desviaciones;
        $balances_finales = $data->balances_finales;
        $mediciones = $balances_finales;

        //update el json en la tabla de la base de datos (incluye extras para reabrir)
        $datos_entrada_data = Datos_entrada::find($datos_entrada_id);
        $data->datos_entrada['mediciones'] = $data->balances_finales;
        $data->datos_entrada['desviaciones'] = $data->desviaciones;
        $data->datos_entrada['nodos_data'] = $data->nodos_data;
        $datos_entrada_data->datos_entrada = json_encode($data->datos_entrada);
        $datos_entrada_data->save();
        BalanceCorrida::record($datos_entrada_data, $request->user(), BalanceCorrida::TIPO_CORRIDA);

        $table_mediciones_fields = $this->createTableMedicionesFields($mediciones, $flujos);
        $array_mediciones = $this->createTableMediciones($mediciones, $flujos);

        // logica tabla restricciones
        $restricciones = $data->datos_entrada['restricciones'];
        $jerarquia = $data->datos_entrada['jerarquia'];

        // logica tabla restricciones fields
        $tabla_restricciones_fields = $this->createTableRestriccionesFields($restricciones, $jerarquia);
        // logica tabla restricciones data
        $tabla_restricciones = $this->createTableRestriccionesData($restricciones, $jerarquia, $desviaciones, $flujos);

        // logica tabla nodos
        $nodos_data = $data->nodos_data;
        foreach ($nodos_data as $key_nodos_data => &$value_nodos_data) {
            foreach($value_nodos_data as $key_value_nodos_data => &$value_nodos_data_item){
                $value_nodos_data_item = number_format($value_nodos_data_item, 2, ',', '.');
            }
        }

        // logica tabla balance nodos
        $balance_nodos = $data->datos_entrada['matriz'];
        $jerarquia = $data->datos_entrada['jerarquia'];
        $nodos = $data->datos_entrada['nodos'];



        $balance_nodos_fields = $this->createTableBalanceNodosFields($componentes);
        $array_balance_nodos = $this->createTableBalanceNodos($balance_nodos, $nodos_data, $nodos, $componentes);

        // logica tabla inventarios


        $inventarios_fields = $this->createTableInventariosFields($componentes);
        $inventarios_data = $this->createTableInventariosData($data, $componentes);

        return [
        'data' => $data,
        'balances_table' => $array_mediciones,
        'balances_fields' => $table_mediciones_fields,
        'nodos_data' => $nodos_data,
        'restricciones_table' => $tabla_restricciones,
        'balance_nodos' => $array_balance_nodos,
        'restricciones_fields' => $tabla_restricciones_fields,
        'balance_nodos_fields' => $balance_nodos_fields,
        'inventarios_fields' => $inventarios_fields,
        'inventarios_data' => $inventarios_data,
        'path' => "",
        'datos_entrada_id' => $datos_entrada_id];
        } catch (HttpClientException $e) {
            return $this->solverError('correr_balance', $e);
        }
    }

    public function paint_tables(Request $request)
    {
        try {
            $datos_entrada_id = $request->datos_entrada_id;
            $rowIndex = $request->rowIndex;

            $url = config('services.flask.url') . '/paint_tables';

            $response = Http::acceptJson()->post($url, [
                'datos_entrada_id' => $datos_entrada_id,
                'rowIndex' => $rowIndex
            ])->throw();

            $data = json_decode($response->getBody()->getContents());
            foreach ($data as $key => &$value) {
                $value = json_decode($value, true);
            }
            unset($value);


            $yellow = array();
            $green = array();
            foreach($data->indices as $key => $value){
                if($key == 0){
                   $yellow = $value;
                }
                else{
                    $green = $value;
                }
            }
            return[
                "yellow" => $yellow,
                "green" => $green
            ];
        } catch (\Throwable $th) {
            return $this->solverError('paint_tables', $th);
        }
    }

    public function load_balance($id)
    {
        $balance = Balances::query()
            ->with(['proceso:id,nombre,valle_id', 'proceso.valle:id,nombre', 'datosEntrada'])
            ->find($id);

        if (!$balance) {
            return response()->json(['message' => 'Balance no encontrado'], 404);
        }

        if (!$balance->datosEntrada) {
            return response()->json(['message' => 'El balance no tiene datos asociados'], 404);
        }

        $payload = $this->buildTablesFromDatosEntrada($balance->datosEntrada, $balance->proceso_id);
        $payload['balance'] = [
            'id' => $balance->id,
            'nombre' => $balance->nombre,
            'tipo' => $balance->tipo,
            'periodo_inicio' => optional($balance->periodo_inicio)->format('Y-m-d'),
            'periodo_fin' => optional($balance->periodo_fin)->format('Y-m-d'),
            'proceso_id' => $balance->proceso_id,
            'valle_id' => optional($balance->proceso)->valle_id,
        ];

        return response()->json($payload);
    }

    public function clone_session(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $original = Datos_entrada::find($request->datos_entrada_id);
        if (!$original) {
            return response()->json(['message' => 'Datos de entrada no encontrados'], 404);
        }

        $clone = $original->replicate();
        $clone->balance_id = null;
        $clone->user_id = $user->id;
        $clone->save();
        BalanceCorrida::copyHistory($original->id, $clone->id);

        return response()->json(['datos_entrada_id' => $clone->id]);
    }

    public function corridas($datos_entrada_id)
    {
        $corridas = BalanceCorrida::query()
            ->select(['id', 'tipo', 'user_id', 'created_at'])
            ->with('user:id,nombre')
            ->where('datos_entrada_id', $datos_entrada_id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (BalanceCorrida $c) => [
                'id' => $c->id,
                'tipo' => $c->tipo,
                'usuario_nombre' => optional($c->user)->nombre,
                'created_at' => $c->created_at,
            ]);

        return response()->json(['corridas' => $corridas]);
    }

    public function restaurar_corrida(Request $request, $corrida_id)
    {
        $request->validate(['datos_entrada_id' => 'required|integer|exists:datos_entradas,id']);

        $corrida = BalanceCorrida::with('datosEntrada:id,proceso_id')->findOrFail($corrida_id);
        $target = Datos_entrada::findOrFail($request->datos_entrada_id);

        // The target may be a clone of the corrida's session; only the process must match.
        if ((int) $corrida->datosEntrada->proceso_id !== (int) $target->proceso_id) {
            return response()->json(['message' => 'La versión pertenece a otro proceso.'], 422);
        }

        $target->datos_entrada = $corrida->snapshot;
        $target->save();
        BalanceCorrida::record($target, $request->user(), BalanceCorrida::TIPO_RESTAURACION);

        return response()->json($this->buildTablesFromDatosEntrada($target));
    }

    public function save_balance(Request $request){
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'nombre_balance' => 'required|string|max:255',
            'tipo' => 'required|in:' . implode(',', Balances::TIPOS),
            'periodo_inicio' => 'required|date',
            'periodo_fin' => 'required|date|after_or_equal:periodo_inicio',
        ]);
        $periodo = $request->only(['tipo', 'periodo_inicio', 'periodo_fin']);

        $datos_entrada = Datos_entrada::find($request->datos_entrada_id);
        if (!$datos_entrada) {
            return response()->json(['message' => 'Datos de entrada no encontrados'], 404);
        }

        $overwrite = filter_var($request->overwrite, FILTER_VALIDATE_BOOLEAN);
        if ($overwrite && $request->balance_id) {
            $balance = Balances::find($request->balance_id);
            if (!$balance) {
                return response()->json(['message' => 'Balance no encontrado'], 404);
            }

            $balance->nombre = $request->nombre_balance;
            $balance->fill($periodo);
            if ($request->proceso_id) {
                $balance->proceso_id = $request->proceso_id;
            }
            $balance->save();
        } else {
            $balance = new Balances();
            $balance->nombre = $request->nombre_balance;
            $balance->fill($periodo);
            $balance->proceso_id = $request->proceso_id;
            $balance->user_id = $user->id;
            $balance->save();
        }

        $datos_entrada->balance_id = $balance->id;
        $datos_entrada->proceso_id = $balance->proceso_id;
        if (!$datos_entrada->user_id) {
            $datos_entrada->user_id = $user->id;
        }
        $proceso = Procesos::find($balance->proceso_id);
        $datos_entrada->valle_id = $proceso->valle_id;
        $datos_entrada->save();

        BalanceEventService::logSaved($datos_entrada, $user, $balance->id);

        return ["balance_id" => $balance->id];
    }

    private function decodeDatosEntradaPayload(Datos_entrada $datosEntrada): array
    {
        $raw = $datosEntrada->datos_entrada;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function buildTablesFromDatosEntrada(Datos_entrada $datosEntrada, $procesoId = null): array
    {
        $datos = $this->decodeDatosEntradaPayload($datosEntrada);
        $procesoId = $procesoId ?: $datosEntrada->proceso_id;
        $proceso = Procesos::find($procesoId);
        $componentes = json_decode($proceso->componentes)->data;

        $mediciones = $datos['mediciones'] ?? [];
        $flujos = $datos['flujos'] ?? [];
        $restricciones = $datos['restricciones'] ?? [];
        $jerarquia = $datos['jerarquia'] ?? [];
        $nodos = $datos['nodos'] ?? [];
        $matriz = $datos['matriz'] ?? [];

        $desviaciones = $datos['desviaciones'] ?? null;
        if ($desviaciones === null) {
            $desviaciones = [];
            foreach ($restricciones as $restriccion) {
                $desviaciones[] = array_fill(0, is_array($restriccion) ? count($restriccion) : 0, 0);
            }
        }

        $nodos_data = $datos['nodos_data'] ?? null;
        if ($nodos_data === null) {
            $nodos_data = [];
            foreach ($matriz as $index => $_) {
                $nodos_data[$index] = array_fill(0, count($componentes) + 1, 0);
            }
        }

        $nodos_data_formatted = $nodos_data;
        foreach ($nodos_data_formatted as &$value_nodos_data) {
            foreach ($value_nodos_data as &$value_nodos_data_item) {
                if (is_numeric($value_nodos_data_item)) {
                    $value_nodos_data_item = number_format((float) $value_nodos_data_item, 2, ',', '.');
                }
            }
        }
        unset($value_nodos_data, $value_nodos_data_item);

        $data = (object) ['datos_entrada' => $datos];

        return [
            'data' => $data,
            'balances_table' => $this->createTableMediciones($mediciones, $flujos),
            'balances_fields' => $this->createTableMedicionesFields($mediciones, $flujos),
            'nodos_data' => $nodos_data_formatted,
            'restricciones_table' => $this->createTableRestriccionesData($restricciones, $jerarquia, $desviaciones, $flujos),
            'balance_nodos' => $this->createTableBalanceNodos($matriz, $nodos_data_formatted, $nodos, $componentes),
            'restricciones_fields' => $this->createTableRestriccionesFields($restricciones, $jerarquia),
            'balance_nodos_fields' => $this->createTableBalanceNodosFields($componentes),
            'inventarios_fields' => $this->createTableInventariosFields($componentes),
            'inventarios_data' => $this->createTableInventariosData($data, $componentes),
            'path' => '',
            'datos_entrada_id' => $datosEntrada->id,
        ];
    }
}
