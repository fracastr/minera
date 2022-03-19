<?php

namespace App\Http\Controllers;

use App\Imports\BalancesImport;
use App\Models\Balances;
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


        $url = 'http://54.89.227.139:8080/flaskapi/get_balance';
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

        $mediciones = $data->datos_entrada['mediciones'];
        $array_mediciones = array();
        $medicion_bd =  new stdClass();

        foreach($mediciones as $key_mediciones => $value_mediciones){
            $object_mediciones = array();
            if(sizeof($value_mediciones) == 4){
                $object_mediciones['TMS medido'] = $value_mediciones[0];
                $object_mediciones['TMS balance'] = $value_mediciones[1];
                $object_mediciones['Fet [%] Medido'] = $value_mediciones[2];
                $object_mediciones['Fet [%] Balance'] = $value_mediciones[3];
            }
            else if(sizeof($value_mediciones) == 6){
                $object_mediciones['TMS medido'] = $value_mediciones[0];
                $object_mediciones['TMS balance'] = $value_mediciones[1];
                $object_mediciones['Fet [%] Medido'] = $value_mediciones[2];
                $object_mediciones['Fet [%] Balance'] = $value_mediciones[3];
                $object_mediciones['FeMag [%] Medido'] = $value_mediciones[4];
                $object_mediciones['FeMag [%] Balance'] = $value_mediciones[5];
            }
            array_push($array_mediciones, (object)$object_mediciones);
        }
        
        return ['data' => $data, 'mediciones_table' => $array_mediciones, 'path' => $path];
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return "ERROR";
            return $e->getResponse()->getBody()->getContents();
        }

;

        return response()->json(['message' => 'uploaded successfully'], 200);
    }
}
