<?php

namespace App\Http\Controllers;

use App\Models\Datos_entrada;
use App\Models\Procesos;
use App\Models\Valles;
use Exception;
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
     * @param  \Illuminate\Http\Request  $request
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

    public function getExcel($datos_entrada_id, $proceso_id, Request $request){
        try {
            // Obtener datos_entrada con relaciones
            $datos_entrada = Datos_entrada::with(['balance'])->find($datos_entrada_id);

            if (!$datos_entrada) {
                throw new Exception('Datos entrada no encontrado');
            }

            // Obtener valle y proceso
            $valle = $datos_entrada->valle_id ? Valles::find($datos_entrada->valle_id) : null;
            $proceso = Procesos::find($proceso_id);

            if (!$proceso) {
                throw new Exception('Proceso no encontrado');
            }

            // Obtener user_id del balance o del request autenticado
            $user_id = null;
            if ($datos_entrada->balance && $datos_entrada->balance->user_id) {
                $user_id = $datos_entrada->balance->user_id;
            } elseif ($request->user()) {
                $user_id = $request->user()->id;
            }

            // Generar timestamp
            $timestamp = now()->format('YmdHis');

            // Construir nombre del archivo: valle_proceso_userid_timestamp.xlsx
            $valle_nombre = $valle ? str_replace(' ', '_', strtolower($valle->nombre)) : 'valle';
            $proceso_nombre = str_replace(' ', '_', strtolower($proceso->nombre));
            $google_drive_filename = $valle_nombre . '_' . $proceso_nombre . '_' . ($user_id ?? '0') . '_' . $timestamp . '.xlsx';

            $filename = $datos_entrada_id . '.xlsx';
            // funcion que descarga el excel asociado a un balance
            $proceso_data = Procesos::find($proceso_id);
            $proceso_data = json_decode($proceso_data->componentes);
            $componentes = $proceso_data->data;
            $url = env('FLASK_API_URL') . '/get_excel';
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
            $public = public_path('Export');
            $storage = storage_path('app/public');
            $data = json_encode($data_response->matriz);
            $data_extra = json_encode($data_response->data_extra);
            $command = $public . '/excelnode.js';
            $filename = '';
            $nodepath = env('NODEPATH');
            $process = new Process([$nodepath, $command, $data, $data_extra, $datos_entrada_id, $public, $public . '/' . $arr_files[$proceso_id], $storage]);
            $process->run();

            // executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            else{
                $contents = Storage::get('public/'. $datos_entrada_id.'.xlsx');
                // Subir a Google Drive con el nuevo nombre
                $move = Storage::disk('google')->put($google_drive_filename, $contents);


                $client = new Google_Client();
                $client->setClientId(env('GOOGLE_DRIVE_CLIENT_ID'));
                $client->setClientSecret(env('GOOGLE_DRIVE_CLIENT_SECRET'));
                $client->refreshToken(env('GOOGLE_DRIVE_REFRESH_TOKEN'));
                $service = new \Google_Service_Drive($client);

                // Buscar el archivo con el nuevo nombre
                $qry = "name='".$google_drive_filename."'";

                $files = $service->files->listFiles([
                    'q' => $qry,
                    'fields' => 'files(webViewLink)'
                ]);

                $return = $files[0]->webViewLink;
                return $return;
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }


    }
}
