<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    public function index()
    {
        $db = DB::connection('fuelcontrol');

        $productos = $db->table('productos')
            ->orderBy('nombre')
            ->get();

        // IDs de productos Diesel y Gasolina
        $dieselId = $db->table('productos')->where('nombre', 'like', '%diesel%')->value('id');
        $gasolinaId = $db->table('productos')->where('nombre', 'like', '%gasolina%')->value('id');

        // Diesel: Total y Promedio
        $totalDiesel = 0;
        $avgDiesel = 0;
        if ($dieselId) {
            $totalDiesel = $db->table('movimientos')
                ->where('producto_id', $dieselId)
                ->where('tipo', 'salida')
                ->where(function ($q) {
                    $q->where('estado', 'aprobado')->orWhereNull('estado');
                })
                ->sum(DB::raw('ABS(cantidad)'));

            $avgDiesel = $db->table('movimientos')
                ->where('producto_id', $dieselId)
                ->where('tipo', 'salida')
                ->where(function ($q) {
                    $q->where('estado', 'aprobado')->orWhereNull('estado');
                })
                ->avg(DB::raw('ABS(cantidad)')) ?? 0;
        }

        // Gasolina: Total y Promedio
        $totalGasolina = 0;
        $avgGasolina = 0;
        if ($gasolinaId) {
            $totalGasolina = $db->table('movimientos')
                ->where('producto_id', $gasolinaId)
                ->where('tipo', 'salida')
                ->where(function ($q) {
                    $q->where('estado', 'aprobado')->orWhereNull('estado');
                })
                ->sum(DB::raw('ABS(cantidad)'));

            $avgGasolina = $db->table('movimientos')
                ->where('producto_id', $gasolinaId)
                ->where('tipo', 'salida')
                ->where(function ($q) {
                    $q->where('estado', 'aprobado')->orWhereNull('estado');
                })
                ->avg(DB::raw('ABS(cantidad)')) ?? 0;
        }

        return view('fuelcontrol.productos.index', compact(
            'productos', 
            'totalDiesel', 'avgDiesel', 
            'totalGasolina', 'avgGasolina'
        ));
    }

    public function create()
    {
        return view('fuelcontrol.productos.create');
    }

    public function store(Request $request)
    {
        $nombre = trim(mb_strtolower($request->nombre));

        $request->validate([
            'nombre' => 'required|string|max:100',
            'cantidad' => 'required|numeric|min:0',
        ]);

        DB::connection('fuelcontrol')->transaction(function () use ($nombre, $request) {

            $producto = DB::connection('fuelcontrol')
                ->table('productos')
                ->where('nombre', $nombre)
                ->first();

            if ($producto) {
                // 🔁 Sumar stock existente
                DB::connection('fuelcontrol')
                    ->table('productos')
                    ->where('id', $producto->id)
                    ->update([
                        'cantidad' => $producto->cantidad + $request->cantidad,
                        'usuario' => auth()->user()->name ?? 'sistema',
                        'fecha_registro' => now(),
                    ]);

                session()->flash('success', 'Stock actualizado correctamente');
                return;
            }

            // 🆕 Crear producto
            DB::connection('fuelcontrol')
                ->table('productos')
                ->insert([
                    'nombre' => $nombre,
                    'cantidad' => $request->cantidad,
                    'usuario' => auth()->user()->name ?? 'sistema',
                    'fecha_registro' => now(),
                ]);

            session()->flash('success', 'Producto creado correctamente');
        });

        return redirect()->route('fuelcontrol.productos');
    }
    public function edit($id)
    {
        return redirect()
            ->route('fuelcontrol.productos')
            ->with('warning', 'La edicion se realiza desde el modal en la lista de productos.');
    }
    public function destroy($id)
    {
        $producto = DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->first();

        abort_if(!$producto, 404);

        DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('fuelcontrol.productos')
            ->with('success', 'Producto eliminado correctamente');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'cantidad' => 'required|numeric|min:0',
        ]);

        DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->update([
                'nombre' => $request->nombre,
                'cantidad' => $request->cantidad,
                'usuario' => auth()->user()->name ?? 'sistema',
            ]);

        return redirect()
            ->route('fuelcontrol.productos')
            ->with('success', 'Producto actualizado correctamente');
    }


    public function importarXml($id)
    {
        $producto = DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->first();

        abort_if(!$producto, 404);

        // buscar XML pendientes
        $xmls = DB::table('xml_facturas')
            ->where('estado', 'pendiente')
            ->orderBy('id')
            ->get();

        foreach ($xmls as $xml) {

            $data = simplexml_load_file(storage_path('app/' . $xml->archivo));

            $descripcion = strtolower((string) $data->Detalle->NmbItem);
            $litros = (float) $data->Detalle->QtyItem;

            // validar producto
            if (
                str_contains($producto->nombre, 'diesel') &&
                !str_contains($descripcion, 'diesel')
            ) {
                continue;
            }

            if (
                str_contains($producto->nombre, 'gasolina') &&
                !str_contains($descripcion, 'gasolina')
            ) {
                continue;
            }

            // ✅ SUMAR STOCK
            DB::connection('fuelcontrol')
                ->table('productos')
                ->where('id', $producto->id)
                ->update([
                    'cantidad' => $producto->cantidad + $litros,
                    'usuario' => auth()->user()->name ?? 'sistema',
                ]);

            // marcar XML como procesado
            DB::table('xml_facturas')
                ->where('id', $xml->id)
                ->update([
                    'estado' => 'procesado',
                    'producto_detectado' => $producto->nombre,
                    'litros' => $litros,
                ]);

            return redirect()
                ->route('fuelcontrol.productos')
                ->with('success', "Se importaron {$litros} L desde XML");
        }

        return redirect()
            ->route('fuelcontrol.productos')
            ->with('warning', 'No hay XML pendientes para este producto');
    }

    /**
     * Auditoría de odómetros de bomba (especialmente Diesel)
     */
    public function auditoria($id)
    {
        $data = $this->getAuditData($id);
        $producto = $data['producto'];
        $auditData = $data['auditData']->reverse();
        $isDiesel = $data['isDiesel'];

        return view('fuelcontrol.productos.auditoria', compact('producto', 'auditData', 'isDiesel'));
    }

    /**
     * Centralizar lógica de obtención de datos para auditoría
     */
    private function getAuditData($id)
    {
        $producto = DB::connection('fuelcontrol')
            ->table('productos')
            ->where('id', $id)
            ->first();

        abort_if(!$producto, 404, 'Producto no encontrado');

        $isDiesel = str_contains(strtolower($producto->nombre), 'diesel');

        $movimientos = DB::connection('fuelcontrol')
            ->table('movimientos')
            ->leftJoin('vehiculos', 'movimientos.vehiculo_id', '=', 'vehiculos.id')
            ->select('movimientos.*', 'vehiculos.descripcion as vehiculo_nombre', 'vehiculos.patente as vehiculo_patente')
            ->where('movimientos.producto_id', $id)
            ->where(function ($q) {
                $q->whereNull('movimientos.estado')->orWhere('movimientos.estado', 'aprobado');
            })
            ->orderBy('movimientos.fecha_movimiento')
            ->orderBy('movimientos.id')
            ->get();

        $auditData = collect();
        $prevOdo = null;

        foreach ($movimientos as $m) {
            $odo = (float) $m->odometro_bomba;
            $cantidad = (float) $m->cantidad;
            $descuadrado = false;
            $diferencia = 0;

            if ($odo > 0 && !is_null($prevOdo)) {
                // Consideramos una salida tanto si la cantidad es negativa como si el tipo es 'salida'
                if ($isDiesel && ($m->tipo === 'salida' || $cantidad < 0)) {
                    $esperado = (float) $prevOdo + abs($cantidad);
                    if (abs($odo - $esperado) > 0.1) {
                        $descuadrado = true;
                        $diferencia = $odo - $esperado;
                    }
                }
            }

            // Construcción del nombre de la máquina más robusta
            $maquinaNombre = 'N/A';
            if ($m->vehiculo_id) {
                $parts = [];
                if ($m->vehiculo_nombre) $parts[] = $m->vehiculo_nombre;
                if ($m->vehiculo_patente) $parts[] = $m->vehiculo_patente;
                $maquinaNombre = !empty($parts) ? implode(' - ', $parts) : "Vehículo #{$m->vehiculo_id}";
            }

            $auditData->push([
                'id' => $m->id,
                'fecha' => $m->fecha_movimiento,
                'cantidad' => $cantidad,
                'odometro' => $odo,
                'prev_odo' => $prevOdo,
                'descuadrado' => $descuadrado,
                'diferencia' => $diferencia,
                'tipo' => $m->tipo,
                'usuario' => $m->usuario ?? 'N/A',
                'maquina' => $maquinaNombre
            ]);

            if ($odo > 0) {
                $prevOdo = $odo;
            }
        }

        return [
            'producto' => $producto,
            'auditData' => $auditData,
            'isDiesel' => $isDiesel
        ];
    }

    /**
     * Exportar auditoría a Excel
     */
    public function exportAuditoria($id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->getAuditData($id);
        $producto = $data['producto'];
        $auditData = $data['auditData']->reverse();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Auditoria de Flujo');

        // Encabezados
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'FuelControl - Auditoría de Flujo: ' . strtoupper($producto->nombre));
        $sheet->setCellValue('A2', 'Generado: ' . now()->format('d-m-Y H:i'));

        $headers = ['Fecha / Hora', 'Máquina', 'Operación', 'Cantidad (L)', 'Secuencia Bomba', 'Anterior', 'Estado', 'Diferencia (L)'];
        $sheet->fromArray($headers, null, 'A4');

        $row = 5;
        foreach ($auditData as $item) {
            $sheet->setCellValue("A{$row}", \Carbon\Carbon::parse($item['fecha'])->format('d-m-Y H:i'));
            $sheet->setCellValue("B{$row}", $item['maquina']);
            $sheet->setCellValue("C{$row}", strtoupper($item['tipo']));
            $sheet->setCellValue("D{$row}", abs($item['cantidad']));
            $sheet->setCellValue("E{$row}", $item['odometro']);
            $sheet->setCellValue("F{$row}", $item['prev_odo']);
            $sheet->setCellValue("G{$row}", $item['descuadrado'] ? 'DESCUADRADO' : ($item['odometro'] > 0 ? 'OK' : 'N/A'));
            $sheet->setCellValue("H{$row}", $item['diferencia']);

            // Estilo si hay error
            if ($item['descuadrado']) {
                $sheet->getStyle("A{$row}:H{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FECACA'); // rose-200
            }

            $row++;
        }

        // Estilos generales
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A4:H4')->getFont()->setBold(true);
        $sheet->getStyle('A4:H4')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E293B'); // dark
        $sheet->getStyle('A4:H4')->getFont()->getColor()->setRGB('FFFFFF');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'auditoria_' . str_replace(' ', '_', strtolower($producto->nombre)) . '_' . now()->format('Ymd_His') . '.xlsx';

        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
