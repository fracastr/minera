<?php

namespace App\Http\Controllers;

use App\Imports\BalancesImport;
use App\Models\Balances;
use App\Models\Datos_entrada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
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
        //
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
                $tabla_mediciones_fields[0] = (object) ['field' => 'Flujos', 'resizable' => true, 'editable' => false];
                $tabla_mediciones_fields[1] = (object) ['field' => 'TMS medido', 'resizable' => true, 'editable' => true];
                $tabla_mediciones_fields[2] = (object) ['field' => 'TMS balance', 'resizable' => true, 'editable' => false];
                $tabla_mediciones_fields[3] = (object) ['field' => 'Fet [%] Medido', 'resizable' => true, 'editable' => true];
                $tabla_mediciones_fields[4] = (object) ['field' => 'Fet [%] Balance', 'resizable' => true, 'editable' => false];
            }
            else if(sizeof($value_mediciones) == 6){
                $tabla_mediciones_fields[0] = (object) ['field' => 'Flujos', 'resizable' => true, 'editable' => false];
                $tabla_mediciones_fields[1] = (object) ['field' => 'TMS medido', 'resizable' => true, 'editable' => true];
                $tabla_mediciones_fields[2] = (object) ['field' => 'TMS balance', 'resizable' => true, 'editable' => false];
                $tabla_mediciones_fields[3] = (object) ['field' => 'Fet [%] Medido', 'resizable' => true, 'editable' => true];
                $tabla_mediciones_fields[4] = (object) ['field' => 'Fet [%] Balance', 'resizable' => true, 'editable' => false];
                $tabla_mediciones_fields[5] = (object) ['field' => 'FeMag [%] Medido', 'resizable' => true, 'editable' => true];
                $tabla_mediciones_fields[6] = (object) ['field' => 'FeMag [%] Balance', 'resizable' => true, 'editable' => false];
            }
        }

        return $tabla_mediciones_fields;
    }

    public function createTableMediciones($mediciones, $flujos){
        $array_mediciones = array();
        foreach($mediciones as $key_mediciones => $value_mediciones){
            $object_mediciones = array();
            if(sizeof($value_mediciones) == 4){
                $object_mediciones['Flujos'] = $flujos[$key_mediciones];
                $object_mediciones['TMS medido'] = number_format($value_mediciones[0], 10,);
                $object_mediciones['TMS balance'] = number_format($value_mediciones[1], 10);
                $object_mediciones['Fet [%] Medido'] = number_format($value_mediciones[2], 4);
                $object_mediciones['Fet [%] Balance'] = number_format($value_mediciones[3], 4);
            }
            else if(sizeof($value_mediciones) == 6){
                $object_mediciones['Flujos'] = $flujos[$key_mediciones];
                $object_mediciones['TMS medido'] = number_format($value_mediciones[0]);
                $object_mediciones['TMS balance'] = number_format($value_mediciones[1]);
                $object_mediciones['Fet [%] Medido'] = number_format($value_mediciones[2] * 100, 2);
                $object_mediciones['Fet [%] Balance'] = number_format($value_mediciones[3] * 100, 2);
                $object_mediciones['FeMag [%] Medido'] = number_format($value_mediciones[4] * 100, 2);
                $object_mediciones['FeMag [%] Balance'] = number_format($value_mediciones[5] * 100, 2);
            }
            array_push($array_mediciones, (object)$object_mediciones);
        }

        return $array_mediciones;
    }

    public function createTableRestricciones($restricciones, $jerarquia){
        $array_restricciones = array();
        foreach ($restricciones as $key_restricciones => $value_restricciones) {
            $object_restricciones = array();
            if(sizeof($value_restricciones) == 4){
                $object_restricciones['TMS inf[%]'] = $value_restricciones[0];
                $object_restricciones['TMS sup[%]'] = $value_restricciones[1];
                $object_restricciones['Fet [%] inf'] = $value_restricciones[2];
                $object_restricciones['Fet [%] sup'] = $value_restricciones[3];
                $object_restricciones['Jerarquia'] = $jerarquia[$key_restricciones];
            }
            else if(sizeof($value_restricciones) == 6){
                $object_restricciones['TMS inf[%]'] = $value_restricciones[0];
                $object_restricciones['TMS sup[%]'] = $value_restricciones[1];
                $object_restricciones['Fet [%] inf'] = $value_restricciones[2];
                $object_restricciones['Fet [%] sup'] = $value_restricciones[3];
                $object_restricciones['FeMag [%] Inf'] = $value_restricciones[4];
                $object_restricciones['FeMag [%] sup'] = $value_restricciones[5];
                $object_restricciones['Jerarquia'] = $jerarquia[$key_restricciones];
            }
            array_push($array_restricciones, (object)$object_restricciones);
        }

        return $array_restricciones;
    }

    public function createTableRestriccionesFields($restricciones, $jerarquia){
        $tabla_restricciones_fields = array();
        if(sizeof($restricciones[0]) == 4){
            $tabla_restricciones_fields[0] = (object) ['field' => 'TMS inf[%]', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[1] = (object) ['field' => 'TMS inf[%] ', 'resizable' => true];
            $tabla_restricciones_fields[2] = (object) ['field' => 'TMS sup[%]', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[3] = (object) ['field' => 'TMS sup[%] ', 'resizable' => true];
            $tabla_restricciones_fields[4] = (object) ['field' => 'Fet [%] inf', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[5] = (object) ['field' => 'Fet [%] inf ', 'resizable' => true];
            $tabla_restricciones_fields[6] = (object) ['field' => 'Fet [%] sup', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[7] = (object) ['field' => 'Fet [%] sup ', 'resizable' => true];
            $tabla_restricciones_fields[8] = (object) ['field' => 'Jerarquia', 'resizable' => true];
        }
        else if(sizeof($restricciones[0]) == 6){
            $tabla_restricciones_fields[0] = (object) ['field' => 'TMS inf[%]', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[1] = (object) ['field' => 'TMS inf[%] ', 'resizable' => true];
            $tabla_restricciones_fields[2] = (object) ['field' => 'TMS sup[%]', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[3] = (object) ['field' => 'TMS sup[%] ', 'resizable' => true];
            $tabla_restricciones_fields[4] = (object) ['field' => 'Fet [%] inf', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[5] = (object) ['field' => 'Fet [%] inf ', 'resizable' => true];
            $tabla_restricciones_fields[6] = (object) ['field' => 'Fet [%] sup', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[7] = (object) ['field' => 'Fet [%] sup ', 'resizable' => true];
            $tabla_restricciones_fields[8] = (object) ['field' => 'FeMag [%] Inf', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[9] = (object) ['field' => 'FeMag [%] Inf ', 'resizable' => true];
            $tabla_restricciones_fields[10] = (object) ['field' => 'FeMag [%] sup', 'resizable' => true, 'editable' => true];
            $tabla_restricciones_fields[11] = (object) ['field' => 'FeMag [%] sup ', 'resizable' => true];
            $tabla_restricciones_fields[12] = (object) ['field' => 'Jerarquia', 'resizable' => true];
        }

        return $tabla_restricciones_fields;
    }

    public function createTableRestriccionesData($restricciones, $jerarquia, $desviaciones){
        $tabla_restricciones = array();
        foreach ($restricciones as $key_restricciones => $value_restricciones) {
            $object_restricciones = array();
            if(sizeof($value_restricciones) == 4){
                $object_restricciones['TMS inf[%]'] = $value_restricciones[0];
                $object_restricciones['TMS inf[%] '] = $desviaciones[$key_restricciones][0];
                $object_restricciones['TMS sup[%]'] = $value_restricciones[1];
                $object_restricciones['TMS sup[%] '] = $desviaciones[$key_restricciones][1];
                $object_restricciones['Fet [%] inf'] = $value_restricciones[2];
                $object_restricciones['Fet [%] inf '] = $desviaciones[$key_restricciones][2];
                $object_restricciones['Fet [%] sup'] = $value_restricciones[3];
                $object_restricciones['Fet [%] sup '] = $desviaciones[$key_restricciones][3];
                $object_restricciones['Jerarquia'] = $jerarquia[$key_restricciones];
            }
            else if(sizeof($value_restricciones) == 6){
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

    public function createTableBalanceNodosFields(){
        $array_nodos_fields = array();
        $array_nodos_fields[0] = (object) ['field' => 'Nodos', 'resizable' => true, 'editable' => false];
        $array_nodos_fields[1] = (object) ['field' => 'TMS', 'resizable' => true, 'editable' => false];
        $array_nodos_fields[2] = (object) ['field' => 'Finos FeT', 'resizable' => true, 'editable' => false];

        return $array_nodos_fields;
    }

    public function createTableBalanceNodos($balance_nodos, $nodos_data, $nodos){
        $array_balance_nodos = array();
        foreach ($balance_nodos as $key_balance_nodos => $value_balance_nodos) {
            $object_balance_nodos = array();
            $object_balance_nodos['Nodos'] = $nodos[$key_balance_nodos];
            $object_balance_nodos['TMS'] = $nodos_data[$key_balance_nodos][0];
            $object_balance_nodos['Finos FeT'] = $nodos_data[$key_balance_nodos][1];
            array_push($array_balance_nodos, (object)$object_balance_nodos);
        }

        return $array_balance_nodos;
    }

    public function import(Request $request)
    {
        try {
            // $path = $request->file('file')->store('public');
            // $path = '/home/ubuntu/minera/storage/app/'. $path;
            $path = '/home/ubuntu/minera/storage/app/public/dc0pZ7f2h9gbF4oQ1q7tZWuMlbcY5pbBBRKRUTfM.xlsx';


        $url = 'http://34.229.82.49:8080/flaskapi/get_balance';
        $myBody['path_name'] = $path;
        // $response = Http::acceptJson()->post($url, array($myBody));
        $response = Http::acceptJson()->post($url, [
            'path_name' => $path,
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

        foreach ($desviaciones as $key_desviaciones => &$value_desviaciones) {
            foreach($value_desviaciones as $key_value_desviaciones => &$value_desviaciones_item){
                $value_desviaciones_item = number_format($value_desviaciones_item * 100, 2);
            }
        }

        $table_mediciones_fields = $this->createTableMedicionesFields($mediciones, $flujos);
        $array_mediciones = $this->createTableMediciones($mediciones, $flujos);

        // logica tabla restricciones
        $restricciones = $data->datos_entrada['restricciones'];
        $jerarquia = $data->datos_entrada['jerarquia'];
        $array_restricciones = $this->createTableRestricciones($restricciones, $jerarquia);

        // logica tabla restricciones fields
        $tabla_restricciones_fields = $this->createTableRestriccionesFields($restricciones, $jerarquia);
        // logica tabla restricciones data
        $tabla_restricciones = $this->createTableRestriccionesData($restricciones, $jerarquia, $desviaciones);

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


        $balance_nodos_fields = $this->createTableBalanceNodosFields();
        $array_balance_nodos = $this->createTableBalanceNodos($balance_nodos, $nodos_data, $nodos);



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
        'path' => $path,
        'datos_entrada_id' => $datos_entrada_id];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "ERROR";
            return $e->getResponse()->getBody()->getContents();
        }

        return response()->json(['message' => 'uploaded successfully'], 200);
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

        $size_mediciones = sizeof($request->datos_entrada["mediciones"][0]);
        //dd($size_mediciones);
        $new_mediciones = array();

        foreach($balances_table as $key => $value){
            $new_mediciones_array = array();
            foreach($value as $key2 => $value2){
                if($key2 != "Flujos"){
                    array_push($new_mediciones_array, $value2);
                    // array_push($new_mediciones_array, intval(str_replace(".","",$value2)));
                }
            }
            array_push($new_mediciones, $new_mediciones_array);
        }
        //dd($new_mediciones);

        $url = 'http://34.229.82.49:8080/flaskapi/correr_balance';
        $myBody['datos_entrada_id'] = $datos_entrada_id;
        //$response = Http::acceptJson()->post($url, array($myBody));
        $response = Http::acceptJson()->post($url, [
            'datos_entrada_id' => 39,
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

        foreach ($desviaciones as $key_desviaciones => &$value_desviaciones) {
            foreach($value_desviaciones as $key_value_desviaciones => &$value_desviaciones_item){
                $value_desviaciones_item = number_format($value_desviaciones_item * 100, 2);
            }
        }

        $table_mediciones_fields = $this->createTableMedicionesFields($mediciones, $flujos);
        $array_mediciones = $this->createTableMediciones($mediciones, $flujos);

        // logica tabla restricciones
        $restricciones = $data->datos_entrada['restricciones'];
        $jerarquia = $data->datos_entrada['jerarquia'];
        $array_restricciones = $this->createTableRestricciones($restricciones, $jerarquia);

        // logica tabla restricciones fields
        $tabla_restricciones_fields = $this->createTableRestriccionesFields($restricciones, $jerarquia);
        // logica tabla restricciones data
        $tabla_restricciones = $this->createTableRestriccionesData($restricciones, $jerarquia, $desviaciones);

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


        $balance_nodos_fields = $this->createTableBalanceNodosFields();
        $array_balance_nodos = $this->createTableBalanceNodos($balance_nodos, $nodos_data, $nodos);



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
        'path' => ""];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "ERROR";
            return $e->getResponse()->getBody()->getContents();
        }

        return response()->json(['message' => 'uploaded successfully'], 200);
    }
}
