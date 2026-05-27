<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BodegasSeeder extends Seeder
{
    public function run(): void
    {
        $bodegas = [
            [
                'id'          => 1,
                'codigo'      => 'BOD-01',
                'nombre'      => 'Bodega Principal',
                'es_principal'=> 1,
                'created_at'  => '2025-12-21 21:47:55',
                'updated_at'  => '2025-12-21 21:47:55',
            ],
            [
                'id'          => 2,
                'codigo'      => 'TAL-01',
                'nombre'      => 'Taller Mecánico',
                'es_principal'=> 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($bodegas as $bodega) {
            DB::table('bodegas')->updateOrInsert(
                ['id' => $bodega['id']],
                $bodega
            );
        }
    }
}
