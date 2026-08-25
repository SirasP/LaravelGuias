<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

/**
 * Siembra únicamente los catálogos respaldados por los formularios reales.
 *
 * Centros de costo y lugares de entrega quedan deliberadamente vacíos: los
 * documentos revisados no los enumeran y el principio del módulo es no
 * inventar datos. El administrador los carga desde Configuración.
 */
class PurchaseRequestCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDepartments();
        $this->seedUnits();
    }

    private function seedDepartments(): void
    {
        // "Administración" es el único área exigida como dato inicial. El slug
        // canónico absorbe cualquier variante de tildes o mayúsculas.
        foreach ([['Administración', 10]] as [$name, $order]) {
            Department::query()->updateOrCreate(
                ['company_code' => 'EHE', 'slug' => Department::slugFor($name)],
                ['name' => $name, 'sort_order' => $order, 'is_active' => true],
            );
        }
    }

    private function seedUnits(): void
    {
        // Unidades tomadas literalmente de los formularios analizados. Las que
        // no admiten decimales se marcan para que la interfaz lo advierta.
        $units = [
            ['un', 'Unidades', true, 10],
            ['m', 'Metros', true, 20],
            ['cubo', 'Cubos', true, 30],
            ['kg', 'Kilos', true, 40],
            ['l', 'Litros', true, 50],
            ['paq', 'Paquetes', false, 60],
            ['caja', 'Cajas', false, 70],
            ['saco', 'Sacos', false, 80],
            ['rollo', 'Rollos', false, 90],
            ['medida', 'Cada medida', false, 100],
            ['talla', 'Cada talla', false, 110],
            ['global', 'Global / Servicio', true, 120],
        ];

        foreach ($units as [$code, $name, $allowsDecimals, $order]) {
            UnitOfMeasure::query()->updateOrCreate(
                ['company_code' => 'EHE', 'slug' => UnitOfMeasure::slugFor($name)],
                [
                    'code' => $code,
                    'name' => $name,
                    'allows_decimals' => $allowsDecimals,
                    'sort_order' => $order,
                    'is_active' => true,
                ],
            );
        }
    }
}
