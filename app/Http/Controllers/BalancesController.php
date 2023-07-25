<?php

namespace App\Http\Controllers;

use App\Imports\BalancesImport;
use App\Models\Balances;
use App\Models\Datos_entrada;
use App\Models\Procesos;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use stdClass;

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
<<<<<<< HEAD
            $tabla_restricciones_fields[1] = (object) ['field' => 'TMS Inf Ingreso [%]', 'resizable' => true, 'width' => 120, 'editable' => true];
            $tabla_restricciones_fields[2] = (object) ['field' => 'TMS Inf [%]', 'resizable' => true, 'width' => 120, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[3] = (object) ['field' => 'TMS Sup Ingreso [%]', 'resizable' => true, 'width' => 120, 'editable' => true];
            $tabla_restricciones_fields[4] = (object) ['field' => 'TMS Sup [%]', 'resizable' => true, 'width' => 120, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[5] = (object) ['field' => 'FeT Inf Ingreso [%]', 'resizable' => true, 'width' => 120, 'editable' => true];
            $tabla_restricciones_fields[6] = (object) ['field' => 'FeT Inf [%]', 'resizable' => true, 'width' => 120, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[7] = (object) ['field' => 'FeT Sup Ingreso [%]', 'resizable' => true, 'width' => 120, 'editable' => true];
            $tabla_restricciones_fields[8] = (object) ['field' => 'FeT Sup [%]', 'resizable' => true, 'width' => 120, 'cellClass' => 'calculated'];
=======
            $tabla_restricciones_fields[1] = (object) ['field' => 'TMS inf[%]', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[2] = (object) ['field' => 'TMS inf[%] ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[3] = (object) ['field' => 'TMS sup[%]', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[4] = (object) ['field' => 'TMS sup[%] ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[5] = (object) ['field' => 'Fet [%] inf', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[6] = (object) ['field' => 'Fet [%] inf ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[7] = (object) ['field' => 'Fet [%] sup', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[8] = (object) ['field' => 'Fet [%] sup ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
>>>>>>> parent of 87cbd8c (Update BalancesController.php)
            $tabla_restricciones_fields[9] = (object) ['field' => 'Jerarquia', 'resizable' => true, 'width' => 100, 'editable' => true];
        }
        else if(sizeof($restricciones[0]) == 6){
            $tabla_restricciones_fields[0] = (object) ['field' => 'Flujos', 'resizable' => true, 'width' => 200, 'pinned' => 'left', 'editable' => false];
            $tabla_restricciones_fields[1] = (object) ['field' => 'TMS inf[%]', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[2] = (object) ['field' => 'TMS inf[%] ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[3] = (object) ['field' => 'TMS sup[%]', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[4] = (object) ['field' => 'TMS sup[%] ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[5] = (object) ['field' => 'Fet [%] inf', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[6] = (object) ['field' => 'Fet [%] inf ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[7] = (object) ['field' => 'Fet [%] sup', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[8] = (object) ['field' => 'Fet [%] sup ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[9] = (object) ['field' => 'FeMag [%] Inf', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[10] = (object) ['field' => 'FeMag [%] Inf ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
            $tabla_restricciones_fields[11] = (object) ['field' => 'FeMag [%] sup', 'resizable' => true, 'width' => 100, 'editable' => true];
            $tabla_restricciones_fields[12] = (object) ['field' => 'FeMag [%] sup ', 'resizable' => true, 'width' => 100, 'cellClass' => 'calculated'];
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
<<<<<<< HEAD
                $object_restricciones['TMS Inf Ingreso [%]'] = $value_restricciones[0];
                $object_restricciones['TMS Inf [%]'] = $desviaciones[$key_restricciones][0];
                $object_restricciones['TMS Sup Ingreso [%]'] = $value_restricciones[1];
                $object_restricciones['TMS Sup [%]'] = $desviaciones[$key_restricciones][1];
                $object_restricciones['FeT Inf Ingreso [%]'] = $value_restricciones[2];
                $object_restricciones['FeT Inf [%]'] = $desviaciones[$key_restricciones][2];
                $object_restricciones['FeT Sup Ingreso [%]'] = $value_restricciones[3];
                $object_restricciones['FeT Sup [%]'] = $desviaciones[$key_restricciones][3];
=======
                $object_restricciones['TMS inf[%]'] = $value_restricciones[0];
                $object_restricciones['TMS inf[%] '] = $desviaciones[$key_restricciones][0];
                $object_restricciones['TMS sup[%]'] = $value_restricciones[1];
                $object_restricciones['TMS sup[%] '] = $desviaciones[$key_restricciones][1];
                $object_restricciones['Fet [%] inf'] = $value_restricciones[2];
                $object_restricciones['Fet [%] inf '] = $desviaciones[$key_restricciones][2];
                $object_restricciones['Fet [%] sup'] = $value_restricciones[3];
                $object_restricciones['Fet [%] sup '] = $desviaciones[$key_restricciones][3];
>>>>>>> parent of 87cbd8c (Update BalancesController.php)
                $object_restricciones['Jerarquia'] = $jerarquia[$key_restricciones];
            }
            else if(sizeof($value_restricciones) == 6){
                $object_restricciones['Flujos'] = $flujos[$key_restricciones];
                $object_restricciones['TMS inf[%]'] = $value_restricciones[0];
                $object_restricciones['TMS inf[%] '] = $desviaciones[$key_restricciones][0];
                $object_restricciones['TMS sup[%]'] = $value_restricciones[1];
                $object_restricciones['TMS sup[%] '] = $desviaciones[$key_restricciones][1];
                $object_restricciones['Fet [%] inf'] = $value_restricciones[2];
                $object_restricciones['Fet [%] inf '] = $desviaciones[$key_restricciones][2];
                $object_restricciones['Fet [%] sup'] = $value_restricciones[3];
                $object_restricciones['Fet [%] sup '] = $desviaciones[$key_restricciones][3];
                $object_restricciones['FeMag [%] Inf'] = $value_restricciones[4];
                $object_restricciones['FeMag [%] Inf '] = $desviaciones[$key_restricciones][4];
                $object_restricciones['FeMag [%] sup'] = $value_restricciones[5];
                $object_restricciones['FeMag [%] sup '] = $desviaciones[$key_restricciones][5];
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
            $tabla_inventarios_fields[sizeof($tabla_inventarios_fields)] = (object) ['field' => $value, 'resizable' => true, 'editable'=> true,'width' => 70];
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
                $object_inventarios[$value] = $componentes_inventario[$key_inventarios][$key];
            }

            array_push($tabla_inventarios_data, (object)$object_inventarios);
        }

        return $tabla_inventarios_data;
    }

    public function import(Request $request)
    {
        try {
            // $file = $request->file('file');
            // $path = $request->file('file')->storeAs(
            //     null,
            //     $file->getClientOriginalName(),
            //     'google'
            // );
            // dd($path, Auth::id());
            $path = $request->file('file')->store('public');
            $path = '/home/ubuntu/minera/storage/app/'. $path;

            // $path = '/home/ubuntu/minera/storage/app/public/uZebC6Bd1IB7x4Tv0jfAkxUlEC3fiXbwTnyMT8QL.xlsx';

        $proceso_id = $request->proceso_id;
        $proceso = Procesos::find($proceso_id);
        $nombre_proceso = $proceso->nombre;
        $url = 'http://34.229.82.49:8080/flaskapi/get_balance';
        $myBody['path_name'] = $path;
        // $response = Http::acceptJson()->post($url, array($myBody));
        $response = Http::acceptJson()->post($url, [
            'path_name' => $path,
            'nombre_proceso' => $nombre_proceso
        ]);
        //dd($response);
        //dd(json_decode($response->getBody()->getContents()));
        $data = json_decode($response->getBody()->getContents());
        foreach ($data as $key => &$value) {
            // $value = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $value), true );
            $value = json_decode($value, true);
        }

        // logica para tabla mediciones
        $mediciones = $data->datos_entrada['mediciones'];
        $flujos = $data->datos_entrada['flujos'];
        $desviaciones = $data->desviaciones;
        $balances_finales = $data->balances_finales;
        //$mediciones = $balances_finales;

        // foreach ($desviaciones as $key_desviaciones => &$value_desviaciones) {
        //     foreach($value_desviaciones as $key_value_desviaciones => &$value_desviaciones_item){
        //         $value_desviaciones_item = number_format($value_desviaciones_item * 100, 2);
        //     }
        // }

        $table_mediciones_fields = $this->createTableMedicionesFields($mediciones, $flujos);
        $array_mediciones = $this->createTableMediciones($mediciones, $flujos);

        // logica tabla restricciones
        $restricciones = $data->datos_entrada['restricciones'];
        $jerarquia = $data->datos_entrada['jerarquia'];
        //$array_restricciones = $this->createTableRestricciones($restricciones, $jerarquia);

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

        $data_entrada = $balance_nodos = $data->datos_entrada;
        $datos_entrada_model = new Datos_entrada();
        $datos_entrada_model->datos_entrada = json_encode($data_entrada);
        $datos_entrada_model->save();

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
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "ERROR";
            return $e->getResponse()->getBody()->getContents();
        }

        return response()->json(['message' => 'uploaded successfully'], 200);
    }

    public function get_listado()
    {
        $listado = Balances::all();

        return ['listado' => $listado];

    }

    public function correr_balance(Request $request)
    {
        try {
        //     $datos_entrada = $request->datos_entrada;
        //     $datos_entrada = json_encode($datos_entrada);
        //dd($request->datos_entrada["mediciones"]);
        $datos_entrada_id = $request->datos_entrada_id;
        $datos_entrada = $request->datos_entrada;
        $balances_table = $request->balances_table;
        $restricciones_table = $request->restricciones_table;
        $proceso_id = $request->proceso_id;
        $inventarios_table = $request->inventarios_table;
        //dd($request->all());
        //dd($request->balances_table);
        //$size_mediciones = sizeof($request->datos_entrada["mediciones"][0]);

        //dd($size_mediciones);
        $tmh_ini = array();
        $tmh_fin = array();
        $tmh_delta = array();
        $tms_ini = array();
        $tms_fin = array();
        $tms_delta = array();
        $humedad = array();
        foreach ($inventarios_table as $key_inventarios_table => $value_inventarios_table) {
            switch ($key_inventarios_table) {
                case 'TMH INI':
                    array_push($tmh_ini, $value_inventarios_table);
                    break;
                case 'TMH FIN':
                    array_push($tmh_fin, $value_inventarios_table);
                    break;
                case 'TMH Delta':
                    array_push($tmh_fin, $value_inventarios_table);
                    break;
                case 'TMS INI':
                    array_push($tms_ini, $value_inventarios_table);
                    break;
                case 'TMS FIN':
                    array_push($tms_fin, $value_inventarios_table);
                    break;
                case 'TMS Delta':
                    array_push($tms_fin, $value_inventarios_table);
                    break;
                case 'Humedad [%]':
                    array_push($humedad, $value_inventarios_table);
                    break;

                default:
                    # code...
                    break;
            }
        }

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

        //dd($datos_entrada_data->mediciones);


        $url = 'http://34.229.82.49:8080/flaskapi/correr_balance';
        $myBody['datos_entrada_id'] = $datos_entrada_id;
        //$response = Http::acceptJson()->post($url, array($myBody));
        $response = Http::acceptJson()->post($url, [
            'datos_entrada_id' => $datos_entrada_id,
        ]);
        //$response = Http::acceptJson()->get($url);
        //dd($response);
        //dd(json_decode($response->getBody()->getContents()));
        $data = json_decode($response->getBody()->getContents());
        //dd($data);
        foreach ($data as $key => &$value) {
            // $value = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $value), true );
            $value = json_decode($value, true);
        }



        // logica para tabla mediciones
        $mediciones = $data->datos_entrada['mediciones'];
        $flujos = $data->datos_entrada['flujos'];
        $desviaciones = $data->desviaciones;
        $balances_finales = $data->balances_finales;
        $mediciones = $balances_finales;

        //update el json en la tabla de la base de datos
        $datos_entrada_data = Datos_entrada::find($datos_entrada_id);
        $data->datos_entrada['mediciones'] = $data->balances_finales;
        $datos_entrada_data->datos_entrada = json_encode($data->datos_entrada);
        $datos_entrada_data->save();

        // foreach ($desviaciones as $key_desviaciones => &$value_desviaciones) {
        //     foreach($value_desviaciones as $key_value_desviaciones => &$value_desviaciones_item){
        //         $value_desviaciones_item = number_format($value_desviaciones_item * 100, 2);
        //     }
        // }

        $table_mediciones_fields = $this->createTableMedicionesFields($mediciones, $flujos);
        $array_mediciones = $this->createTableMediciones($mediciones, $flujos);

        // logica tabla restricciones
        $restricciones = $data->datos_entrada['restricciones'];
        $jerarquia = $data->datos_entrada['jerarquia'];
        //$array_restricciones = $this->createTableRestricciones($restricciones, $jerarquia);

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



        $proceso = Procesos::find($proceso_id);
        $componentes = json_decode($proceso->componentes);
        $componentes = $componentes->data;

        $balance_nodos_fields = $this->createTableBalanceNodosFields($componentes);
        $array_balance_nodos = $this->createTableBalanceNodos($balance_nodos, $nodos_data, $nodos, $componentes);

        // logica tabla inventarios


        $inventarios_fields = $this->createTableInventariosFields($componentes);
        $inventarios_data = $this->createTableInventariosData($data, $componentes);

        // $data_entrada = $balance_nodos = $data->datos_entrada;
        // $datos_entrada_model = new Datos_entrada();
        // $datos_entrada_model->datos_entrada = json_encode($data_entrada);
        // $datos_entrada_model->save();

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
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "ERROR";
            return $e->getResponse()->getBody()->getContents();
        }

        return response()->json(['message' => 'uploaded successfully'], 200);
    }

    public function paint_tables(Request $request)
    {
        try {
            $datos_entrada_id = $request->datos_entrada_id;
            $rowIndex = $request->rowIndex;

            $url = 'http://34.229.82.49:8080/flaskapi/paint_tables';

            //$response = Http::acceptJson()->post($url, array($myBody));
            $response = Http::acceptJson()->post($url, [
                'datos_entrada_id' => $datos_entrada_id,
                'rowIndex' => $rowIndex
            ]);
            //$response = Http::acceptJson()->get($url);
            //dd($response);
            //dd(json_decode($response->getBody()->getContents()));
            $data = json_decode($response->getBody()->getContents());
            foreach ($data as $key => &$value) {
                // $value = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $value), true );
                $value = json_decode($value, true);
            }
            //dd($data);
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
            //throw $th;
            return "ERROR";
        }
    }

    public function save_balance(Request $request){
        $balance = new Balances();
        $balance->nombre = $request->nombre_balance;
        $balance->tipo = "quincenal";
        $balance->proceso_id = $request->proceso_id;
        $balance->user_id = 1;
        $balance->save();

        $datos_entrada = Datos_entrada::find($request->datos_entrada_id);
        $datos_entrada->balance_id = $balance->id;
        $datos_entrada->save();

        return ["balance_id" => $balance->id];
    }
}
