<?php

namespace App\Imports;

use App\Models\Balances;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BalancesImport implements ToCollection, WithHeadingRow
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        //
    }

    public function model(array $row)
    {
        return new Balances([
            'flujos'  => $row['flujos'],
            'tag' => $row['tag'],
            'tms' => $row['tms'],
            'humedad' => $row['humedad'],
            'leyfe' => $row['ley fe'],
            'descripcion' => $row['descripcion'],
        ]);
    }
}
