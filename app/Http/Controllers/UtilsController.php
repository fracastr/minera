<?php

namespace App\Http\Controllers;

use App\Models\Datos_entrada;
use App\Models\Procesos;
use App\Models\Valles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class UtilsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        dd("index");
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function getValles($user_id)
    {
        $valles = Valles::select('id as value', 'nombre as text')->get()->toArray();
        return ['valles' => $valles];
    }

    public function getProcesos($valle_id)
    {
        $procesos = Procesos::select('id as value', 'nombre as text')->where('valle_id', $valle_id)->get()->toArray();
        return ['procesos' => $procesos];
    }

    public function getExcel($datos_entrada_id, $proceso_id){
        // funcion que descarga el excel asociado a un balance
        $proceso = Procesos::find($proceso_id);
        $proceso = json_decode($proceso->componentes);
        $componentes = $proceso->data;
        $url = 'http://34.229.82.49:8080/flaskapi/get_excel';
        $response = Http::acceptJson()->post($url, [
            'datos_entrada_id' => $datos_entrada_id,
            'componentes' => $componentes
        ]);

        $data = json_decode($response->getBody()->getContents());

        $filename = $data->filename;
        $file = Storage::path($filename);
        return response()->file($file);
        // return response()->download($filename);
        // return Storage::download($filename);

    }
}
