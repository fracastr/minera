<?php

namespace App\Http\Controllers;

use App\Models\Datos_entrada;
use App\Models\Procesos;
use App\Models\Valles;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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

        //$files = Storage::disk('google')->allFiles();
        $filename = $datos_entrada_id . '.xlsx';

        // $contents = Storage::path('out.xlsx');
        // dd($contents);
        // $path = $request->file('file')->storeAs(
            //     null,
            //     $file->getClientOriginalName(),
            //     'google'
            // );
        //$move = Storage::move(Storage::path('out.xlsx'), Storage::disk('google'));

        // dd($move);
        // funcion que descarga el excel asociado a un balance
        $proceso = Procesos::find($proceso_id);
        $proceso = json_decode($proceso->componentes);
        $componentes = $proceso->data;
        $url = 'http://34.229.82.49:8080/flaskapi/get_excel';
        $response = Http::acceptJson()->post($url, [
            'datos_entrada_id' => $datos_entrada_id,
            'componentes' => $componentes
        ]);
        /*
        Lista de procesos por id
        #   valle, proceso, valle_id, proceso_id
            Copiapo, Puerto, 1, 1
            Copiapo, CNN, 1, 2
            Copiapo, Planta Magnetita, 1, 3
            Huasco, Los Colorados, 2, 4
            Huasco, Pellet, 2, 5
            Elqui, Elqui, 3, 6
            Elqui, Pleito, 3, 7
        */
        $arr_files = array();
        $arr_files[1] = 'Exportar_Copiapo_Puerto.xlsx';
        $arr_files[2] = 'Exportar_Copiapo_CNN.xlsx';
        $arr_files[3] = 'Exportar_Copiapo_PM.xlsx';
        $arr_files[4] = 'Exportar_Huasco_Colorados.xlsx';
        $arr_files[5] = 'Exportar_Huasco_Pellet.xlsx';
        $arr_files[6] = 'Exportar_Elqui_Elqui.xlsx';
        $arr_files[7] = 'Exportar_Elqui_Pleito.xlsx';

        $data_response = json_decode($response->getBody()->getContents());
        //dd($data->matriz);
        $public = public_path('Export');
        $storage = storage_path('app/public');
        //$data = implode(',',$data->matriz);
        $data = json_encode($data_response->matriz);
        $data_extra = json_encode($data_response->data_extra);
        $command = $public . '/excelnode.js';
        $filename = '';
        // $process = new Process(['/usr/local/bin/node', $command, $data, $datos_entrada_id, $public]);
        $process = new Process(['/usr/bin/node', $command, $data, $data_extra, $datos_entrada_id, $public, $public . '/' . $arr_files[$proceso_id], $storage]);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        else{
            // dd($process->getOutput());
            //$contents = Storage::disk('local')->get('out.xlsx');
            $contents = Storage::get('public/'. $datos_entrada_id.'.xlsx');
            $move = Storage::disk('google')->put($datos_entrada_id.'.xlsx', $contents);


            $client = new Google_Client();
            $client->setClientId(env('GOOGLE_DRIVE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_DRIVE_CLIENT_SECRET'));
            $client->refreshToken(env('GOOGLE_DRIVE_REFRESH_TOKEN'));
            $service = new \Google_Service_Drive($client);

            $qry = "name='".$datos_entrada_id.".xlsx'";

            $files = $service->files->listFiles([
                'q' => $qry,
                'fields' => 'files(webViewLink)'
            ]);
            // dd($files[0]->webViewLink);

            $return = $files[0]->webViewLink;
            return $return;
            // $contents = Storage::allFiles();
            // dd($contents);
        }

        // echo $process->getOutput();
        // $filename = $data->filename;
        // $filename = explode("/", $filename);
        // $filename = end($filename);
        // $file = Storage::path("public/".$filename);
        // return response()->file($file);
        // return response()->download($filename);
        // return Storage::download($filename);

    }
}
