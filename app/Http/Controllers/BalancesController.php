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

    public function import(Request $request)
    {
        try {
            // $path = $request->file('file')->store('public');
            // $path = '/home/ubuntu/minera/storage/app/'. $path;
            $path = '/home/ubuntu/minera/storage/app/public/iYTQWSjieGJPHoKE6rVrPmsaeIDjZGach602nWVe.xlsx';


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
        $array_mediciones = array();
        $medicion_bd =  new stdClass();

        foreach($mediciones as $key_mediciones => $value_mediciones){
            $object_mediciones = array();
            if(sizeof($value_mediciones) == 4){
                $object_mediciones['Flujos'] = $flujos[$key_mediciones];
                $object_mediciones['TMS medido'] = number_format($value_mediciones[0], 0, ',', '.');
                $object_mediciones['TMS balance'] = number_format($value_mediciones[1], 0, ',', '.');
                $object_mediciones['Fet [%] Medido'] = number_format($value_mediciones[2] * 100, 2);
                $object_mediciones['Fet [%] Balance'] = number_format($value_mediciones[3] * 100, 2);
            }
            else if(sizeof($value_mediciones) == 6){
                $object_mediciones['Flujos'] = $flujos[$key_mediciones];
                $object_mediciones['TMS medido'] = number_format($value_mediciones[0], 0, ',', '.');
                $object_mediciones['TMS balance'] = number_format($value_mediciones[1], 0, ',', '.');
                $object_mediciones['Fet [%] Medido'] = number_format($value_mediciones[2] * 100, 2);
                $object_mediciones['Fet [%] Balance'] = number_format($value_mediciones[3] * 100, 2);
                $object_mediciones['FeMag [%] Medido'] = number_format($value_mediciones[4] * 100, 2);
                $object_mediciones['FeMag [%] Balance'] = number_format($value_mediciones[5] * 100, 2);
            }
            array_push($array_mediciones, (object)$object_mediciones);
        }

        // logica tabla restricciones

        $restricciones = $data->datos_entrada['restricciones'];
        $jerarquia = $data->datos_entrada['jerarquia'];
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

        // logica tabla resultado restricciones
        // $resultado_restricciones = $data->datos_entrada['restricciones'];
        // $jerarquia = $data->datos_entrada['jerarquia'];
        // $array_resultado_restricciones = array();

        // foreach ($resultado_restricciones as $key_resultado_restricciones => $value_resultado_restricciones) {
        //     $object_resultado_restricciones = array();
        //     if(sizeof($value_resultado_restricciones) == 4){
        //         $object_resultado_restricciones['TMS inf[%]'] = 0;
        //         $object_resultado_restricciones['TMS sup[%]'] = 0;
        //         $object_resultado_restricciones['Fet [%] inf'] = 0;
        //         $object_resultado_restricciones['Fet [%] sup'] = 0;
        //     }
        //     else if(sizeof($value_resultado_restricciones) == 6){
        //         $object_resultado_restricciones['TMS inf[%]'] = 0;
        //         $object_resultado_restricciones['TMS sup[%]'] = 0;
        //         $object_resultado_restricciones['Fet [%] inf'] = 0;
        //         $object_resultado_restricciones['Fet [%] sup'] = 0;
        //         $object_resultado_restricciones['FeMag [%] Inf'] = 0;
        //         $object_resultado_restricciones['FeMag [%] sup'] = 0;
        //     }
        //     array_push($array_resultado_restricciones, (object)$object_resultado_restricciones);
        // }




        $tabla_restricciones = array();
        $tabla_restricciones_fields = array();
        if(sizeof($restricciones[0]) == 4){
            $tabla_restricciones_fields[0] = (object) ['key' => 'TMS inf[%]'];
            $tabla_restricciones_fields[1] = (object) ['key' => 'TMS inf[%] ', 'variant' => 'success'];
            $tabla_restricciones_fields[2] = (object) ['key' => 'TMS sup[%]'];
            $tabla_restricciones_fields[3] = (object) ['key' => 'TMS sup[%] ', 'variant' => 'success'];
            $tabla_restricciones_fields[4] = (object) ['key' => 'Fet [%] inf'];
            $tabla_restricciones_fields[5] = (object) ['key' => 'Fet [%] inf ', 'variant' => 'success'];
            $tabla_restricciones_fields[6] = (object) ['key' => 'Fet [%] sup'];
            $tabla_restricciones_fields[7] = (object) ['key' => 'Fet [%] sup ', 'variant' => 'success'];
            $tabla_restricciones_fields[8] = (object) ['key' => 'Jerarquia'];
        }
        else if(sizeof($restricciones[0]) == 6){
            $tabla_restricciones_fields[0] = (object) ['key' => 'TMS inf[%]'];
            $tabla_restricciones_fields[1] = (object) ['key' => 'TMS inf[%] ', 'variant' => 'success'];
            $tabla_restricciones_fields[2] = (object) ['key' => 'TMS sup[%]'];
            $tabla_restricciones_fields[3] = (object) ['key' => 'TMS sup[%] ', 'variant' => 'success'];
            $tabla_restricciones_fields[4] = (object) ['key' => 'Fet [%] inf'];
            $tabla_restricciones_fields[5] = (object) ['key' => 'Fet [%] inf ', 'variant' => 'success'];
            $tabla_restricciones_fields[6] = (object) ['key' => 'Fet [%] sup'];
            $tabla_restricciones_fields[7] = (object) ['key' => 'Fet [%] sup ', 'variant' => 'success'];
            $tabla_restricciones_fields[8] = (object) ['key' => 'FeMag [%] Inf'];
            $tabla_restricciones_fields[9] = (object) ['key' => 'FeMag [%] Inf ', 'variant' => 'success'];
            $tabla_restricciones_fields[10] = (object) ['key' => 'FeMag [%] sup'];
            $tabla_restricciones_fields[11] = (object) ['key' => 'FeMag [%] sup ', 'variant' => 'success'];
            $tabla_restricciones_fields[12] = (object) ['key' => 'Jerarquia'];
        }

        foreach ($desviaciones as $key_desviaciones => &$value_desviaciones) {
            foreach($value_desviaciones as $key_value_desviaciones => &$value_desviaciones_item){
                $value_desviaciones_item = number_format($value_desviaciones_item * 100, 2);
            }
        }

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

        $nodos_data = $data->nodos_data;
        foreach ($nodos_data as $key_nodos_data => &$value_nodos_data) {
            foreach($value_nodos_data as $key_value_nodos_data => &$value_nodos_data_item){
                $value_nodos_data_item = number_format($value_nodos_data_item, 2, ',', '.');
            }
        }

        // logica tabla balance nodos
        $balance_nodos = $data->datos_entrada['matriz'];
        $jerarquia = $data->datos_entrada['jerarquia'];
        $array_balance_nodos = array();

        foreach ($balance_nodos as $key_balance_nodos => $value_balance_nodos) {
            $object_balance_nodos = array();
            $object_balance_nodos['TMS'] = $nodos_data[$key_balance_nodos][0];
            $object_balance_nodos['Finos FeT'] = $nodos_data[$key_balance_nodos][1];
            array_push($array_balance_nodos, (object)$object_balance_nodos);
        }

        $data_entrada = $balance_nodos = $data->datos_entrada;
        $datos_entrada_model = new Datos_entrada();
        $datos_entrada_model->datos_entrada = json_encode($data_entrada);
        $datos_entrada_model->save();

        return [
        'data' => $data,
        'balances_table' => $array_mediciones,
        'nodos_data' => $nodos_data,
        'restricciones_table' => $tabla_restricciones,
        'balance_nodos' => $array_balance_nodos,
        'restricciones_fields' => $tabla_restricciones_fields,
        'path' => $path];
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


        $url = 'http://34.229.82.49:8080/flaskapi/correr_balance';
        // $myBody['datos_entrada'] = $datos_entrada;
        // $response = Http::acceptJson()->post($url, array($myBody));
        $response = Http::acceptJson()->get($url);
        //dd($response);
        //dd(json_decode($response->getBody()->getContents()));
        $data = json_decode($response->getBody()->getContents());
        dd($response);
        foreach ($data as $key => &$value) {
            // $value = json_decode( preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $value), true );
            $value = json_decode($value, true);
        }

        // logica para tabla mediciones
        $mediciones = $data->datos_entrada['mediciones'];
        $flujos = $data->datos_entrada['flujos'];
        $desviaciones = $data->desviaciones;
        $array_mediciones = array();
        $medicion_bd =  new stdClass();

        foreach($mediciones as $key_mediciones => $value_mediciones){
            $object_mediciones = array();
            if(sizeof($value_mediciones) == 4){
                $object_mediciones['Flujos'] = $flujos[$key_mediciones];
                $object_mediciones['TMS medido'] = number_format($value_mediciones[0], 0, ',', '.');
                $object_mediciones['TMS balance'] = number_format($value_mediciones[1], 0, ',', '.');
                $object_mediciones['Fet [%] Medido'] = number_format($value_mediciones[2] * 100, 2);
                $object_mediciones['Fet [%] Balance'] = number_format($value_mediciones[3] * 100, 2);
            }
            else if(sizeof($value_mediciones) == 6){
                $object_mediciones['Flujos'] = $flujos[$key_mediciones];
                $object_mediciones['TMS medido'] = number_format($value_mediciones[0], 0, ',', '.');
                $object_mediciones['TMS balance'] = number_format($value_mediciones[1], 0, ',', '.');
                $object_mediciones['Fet [%] Medido'] = number_format($value_mediciones[2] * 100, 2);
                $object_mediciones['Fet [%] Balance'] = number_format($value_mediciones[3] * 100, 2);
                $object_mediciones['FeMag [%] Medido'] = number_format($value_mediciones[4] * 100, 2);
                $object_mediciones['FeMag [%] Balance'] = number_format($value_mediciones[5] * 100, 2);
            }
            array_push($array_mediciones, (object)$object_mediciones);
        }

        // logica tabla restricciones

        $restricciones = $data->datos_entrada['restricciones'];
        $jerarquia = $data->datos_entrada['jerarquia'];
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

        // logica tabla resultado restricciones
        // $resultado_restricciones = $data->datos_entrada['restricciones'];
        // $jerarquia = $data->datos_entrada['jerarquia'];
        // $array_resultado_restricciones = array();

        // foreach ($resultado_restricciones as $key_resultado_restricciones => $value_resultado_restricciones) {
        //     $object_resultado_restricciones = array();
        //     if(sizeof($value_resultado_restricciones) == 4){
        //         $object_resultado_restricciones['TMS inf[%]'] = 0;
        //         $object_resultado_restricciones['TMS sup[%]'] = 0;
        //         $object_resultado_restricciones['Fet [%] inf'] = 0;
        //         $object_resultado_restricciones['Fet [%] sup'] = 0;
        //     }
        //     else if(sizeof($value_resultado_restricciones) == 6){
        //         $object_resultado_restricciones['TMS inf[%]'] = 0;
        //         $object_resultado_restricciones['TMS sup[%]'] = 0;
        //         $object_resultado_restricciones['Fet [%] inf'] = 0;
        //         $object_resultado_restricciones['Fet [%] sup'] = 0;
        //         $object_resultado_restricciones['FeMag [%] Inf'] = 0;
        //         $object_resultado_restricciones['FeMag [%] sup'] = 0;
        //     }
        //     array_push($array_resultado_restricciones, (object)$object_resultado_restricciones);
        // }




        $tabla_restricciones = array();
        $tabla_restricciones_fields = array();
        if(sizeof($restricciones[0]) == 4){
            $tabla_restricciones_fields[0] = (object) ['key' => 'TMS inf[%]'];
            $tabla_restricciones_fields[1] = (object) ['key' => 'TMS inf[%] ', 'variant' => 'success'];
            $tabla_restricciones_fields[2] = (object) ['key' => 'TMS sup[%]'];
            $tabla_restricciones_fields[3] = (object) ['key' => 'TMS sup[%] ', 'variant' => 'success'];
            $tabla_restricciones_fields[4] = (object) ['key' => 'Fet [%] inf'];
            $tabla_restricciones_fields[5] = (object) ['key' => 'Fet [%] inf ', 'variant' => 'success'];
            $tabla_restricciones_fields[6] = (object) ['key' => 'Fet [%] sup'];
            $tabla_restricciones_fields[7] = (object) ['key' => 'Fet [%] sup ', 'variant' => 'success'];
            $tabla_restricciones_fields[8] = (object) ['key' => 'Jerarquia'];
        }
        else if(sizeof($restricciones[0]) == 6){
            $tabla_restricciones_fields[0] = (object) ['key' => 'TMS inf[%]'];
            $tabla_restricciones_fields[1] = (object) ['key' => 'TMS inf[%] ', 'variant' => 'success'];
            $tabla_restricciones_fields[2] = (object) ['key' => 'TMS sup[%]'];
            $tabla_restricciones_fields[3] = (object) ['key' => 'TMS sup[%] ', 'variant' => 'success'];
            $tabla_restricciones_fields[4] = (object) ['key' => 'Fet [%] inf'];
            $tabla_restricciones_fields[5] = (object) ['key' => 'Fet [%] inf ', 'variant' => 'success'];
            $tabla_restricciones_fields[6] = (object) ['key' => 'Fet [%] sup'];
            $tabla_restricciones_fields[7] = (object) ['key' => 'Fet [%] sup ', 'variant' => 'success'];
            $tabla_restricciones_fields[8] = (object) ['key' => 'FeMag [%] Inf'];
            $tabla_restricciones_fields[9] = (object) ['key' => 'FeMag [%] Inf ', 'variant' => 'success'];
            $tabla_restricciones_fields[10] = (object) ['key' => 'FeMag [%] sup'];
            $tabla_restricciones_fields[11] = (object) ['key' => 'FeMag [%] sup ', 'variant' => 'success'];
            $tabla_restricciones_fields[12] = (object) ['key' => 'Jerarquia'];
        }

        foreach ($desviaciones as $key_desviaciones => &$value_desviaciones) {
            foreach($value_desviaciones as $key_value_desviaciones => &$value_desviaciones_item){
                $value_desviaciones_item = number_format($value_desviaciones_item * 100, 2);
            }
        }

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

        $nodos_data = $data->nodos_data;
        foreach ($nodos_data as $key_nodos_data => &$value_nodos_data) {
            foreach($value_nodos_data as $key_value_nodos_data => &$value_nodos_data_item){
                $value_nodos_data_item = number_format($value_nodos_data_item, 2, ',', '.');
            }
        }

        // logica tabla balance nodos
        $balance_nodos = $data->datos_entrada['matriz'];
        $jerarquia = $data->datos_entrada['jerarquia'];
        $array_balance_nodos = array();

        foreach ($balance_nodos as $key_balance_nodos => $value_balance_nodos) {
            $object_balance_nodos = array();
            $object_balance_nodos['TMS'] = $nodos_data[$key_balance_nodos][0];
            $object_balance_nodos['Finos FeT'] = $nodos_data[$key_balance_nodos][1];
            array_push($array_balance_nodos, (object)$object_balance_nodos);
        }



        return [
        'data' => $data,
        'balances_table' => $array_mediciones,
        'nodos_data' => $nodos_data,
        'restricciones_table' => $tabla_restricciones,
        'balance_nodos' => $array_balance_nodos,
        'restricciones_fields' => $tabla_restricciones_fields,
        'path' => ""];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "ERROR";
            return $e->getResponse()->getBody()->getContents();
        }
    }
}
