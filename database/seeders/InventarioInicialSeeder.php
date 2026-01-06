<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventarioInicialSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // =========================
            // 1) Categorías de UoM
            // =========================
            $pesoId = DB::table('categorias_unidad_medida')->insertGetId([
                'nombre' => 'Peso',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $volumenId = DB::table('categorias_unidad_medida')->insertGetId([
                'nombre' => 'Volumen',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conteoId = DB::table('categorias_unidad_medida')->insertGetId([
                'nombre' => 'Conteo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // =========================
            // 2) Unidades de medida
            // =========================
            $kgId = DB::table('unidades_medida')->insertGetId([
                'categoria_id' => $pesoId,
                'codigo' => 'KG',
                'nombre' => 'Kilogramo',
                'precision' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $gId = DB::table('unidades_medida')->insertGetId([
                'categoria_id' => $pesoId,
                'codigo' => 'G',
                'nombre' => 'Gramo',
                'precision' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lId = DB::table('unidades_medida')->insertGetId([
                'categoria_id' => $volumenId,
                'codigo' => 'L',
                'nombre' => 'Litro',
                'precision' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $mlId = DB::table('unidades_medida')->insertGetId([
                'categoria_id' => $volumenId,
                'codigo' => 'ML',
                'nombre' => 'Mililitro',
                'precision' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $unId = DB::table('unidades_medida')->insertGetId([
                'categoria_id' => $conteoId,
                'codigo' => 'UN',
                'nombre' => 'Unidad',
                'precision' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $parId = DB::table('unidades_medida')->insertGetId([
                'categoria_id' => $conteoId,
                'codigo' => 'PAR',
                'nombre' => 'Par',
                'precision' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // =========================
            // 3) Conversiones
            // qty_hacia = qty_desde * factor
            // =========================
            DB::table('conversiones_unidad_medida')->insert([
                [
                    'desde_unidad_id' => $gId,
                    'hacia_unidad_id' => $kgId,
                    'factor' => 0.001,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'desde_unidad_id' => $mlId,
                    'hacia_unidad_id' => $lId,
                    'factor' => 0.001,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    // 1 PAR = 2 UN
                    'desde_unidad_id' => $parId,
                    'hacia_unidad_id' => $unId,
                    'factor' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // =========================
            // 4) Perfiles de impuestos
            // =========================
            $perfilNormalId = DB::table('perfiles_impuestos')->insertGetId([
                'nombre' => 'Normal IVA 19%',
                'tasa_iva' => 0.1900,
                'aplica_impuesto_especifico' => false,
                'tipo_impuesto_especifico' => null,
                'tasa_impuesto_especifico' => 0,
                'incluir_iva_en_costo_inventario' => false,
                'incluir_especifico_en_costo_inventario' => true,
                'base_iva_incluye_especifico' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $perfilDieselId = DB::table('perfiles_impuestos')->insertGetId([
                'nombre' => 'Diésel (IVA + impuesto específico por litro)',
                'tasa_iva' => 0.1900,
                'aplica_impuesto_especifico' => true,
                'tipo_impuesto_especifico' => 'POR_LITRO',
                // OJO: aquí pon el monto por litro vigente en tu negocio (placeholder)
                'tasa_impuesto_especifico' => 0,
                'incluir_iva_en_costo_inventario' => false,
                'incluir_especifico_en_costo_inventario' => true,
                'base_iva_incluye_especifico' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // =========================
            // 5) Bodega principal
            // =========================
            DB::table('bodegas')->insert([
                'codigo' => 'BOD-01',
                'nombre' => 'Bodega Principal',
                'es_principal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // =========================
            // 6) Producto ejemplo: Diésel
            // =========================
            DB::table('productos')->insert([
                'sku' => 'DIESEL',
                'nombre' => 'Diésel',
                'descripcion' => 'Combustible Diésel',
                'activo' => true,

                'unidad_stock_id' => $lId,
                'unidad_compra_id' => $lId,
                'unidad_venta_id' => $lId,
                'categoria_unidad_id' => $volumenId,

                'perfil_impuesto_id' => $perfilDieselId,
                'permite_fraccion' => true,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
