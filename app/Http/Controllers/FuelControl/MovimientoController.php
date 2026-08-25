<?php

namespace App\Http\Controllers\FuelControl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovimientoController extends Controller
{
    /**
     * Listado general
     */
    public function index(Request $request)
    {
        $query = DB::connection('fuelcontrol')
            ->table('movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->leftJoin('vehiculos as v', 'v.id', '=', 'm.vehiculo_id')
            ->select(
                'm.*',
                'p.nombre as producto_nombre',
                'v.patente as vehiculo_patente',
                'v.descripcion as vehiculo_descripcion'
            );

        // 🔎 FILTRO POR ESTADO
        if ($request->filled('estado')) {
            $query->where('m.estado', $request->estado);
        }

        // 🔎 FILTRO POR TIPO
        if ($request->filled('tipo')) {
            $query->where('m.tipo', $request->tipo);
        }

        // 🔎 FILTRO POR PRODUCTO
        if ($request->filled('producto_id')) {
            $query->where('m.producto_id', $request->producto_id);
        }

        // 🔎 FILTRO POR FECHA
        if ($request->filled('fecha')) {

            switch ($request->fecha) {
                case 'hoy':
                    $query->whereDate('m.fecha_movimiento', today());
                    break;

                case 'semana':
                    $query->whereBetween('m.fecha_movimiento', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ]);
                    break;

                case 'mes':
                    $query->whereMonth('m.fecha_movimiento', now()->month)
                        ->whereYear('m.fecha_movimiento', now()->year);
                    break;

                case 'trimestre':
                    $query->whereBetween('m.fecha_movimiento', [
                        now()->startOfQuarter(),
                        now()->endOfQuarter()
                    ]);
                    break;
            }
        }

        $movimientos = $query
            ->orderByDesc('m.fecha_movimiento')
            ->paginate(20)
            ->withQueryString();

        // 🔥 ESTA PARTE TE FALTABA
        $productos = DB::connection('fuelcontrol')
            ->table('productos')
            ->orderBy('nombre')
            ->get();

        return view('fuelcontrol.movimientos.index', compact('movimientos', 'productos'));
    }
    
    /**
     * Vista de detalle completa (Odómetros, Historial, Gráficos)
     */
    public function detalle($id)
    {
        $db = DB::connection('fuelcontrol');

        $movimiento = $db->table('movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->leftJoin('vehiculos as v', 'v.id', '=', 'm.vehiculo_id')
            ->select(
                'm.*',
                'p.nombre as producto_nombre',
                'v.patente',
                'v.descripcion as vehiculo_descripcion',
                'v.tipo as vehiculo_tipo'
            )
            ->where('m.id', $id)
            ->first();

        if (!$movimiento || !$movimiento->vehiculo_id) {
            return redirect()->route('fuelcontrol.movimientos')->with('error', 'Vehículo no encontrado');
        }

        // Clasificación: ¿Es maquinaria? (usa Horas en lugar de Km)
        $esMaquinaria = preg_match('/tractor|excavadora|telescopico|pala|fumigador|moto\s?bomba|retro|generador|mini/i', $movimiento->vehiculo_descripcion);
        $unidad = $esMaquinaria ? 'L/h' : 'km/L';
        $etiquetaRendimiento = $esMaquinaria ? 'Consumo Horario' : 'Rendimiento';

        // Historial completo del vehículo
        $historialRaw = $db->table('movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->where('m.vehiculo_id', $movimiento->vehiculo_id)
            ->where(function ($q) {
                $q->whereNull('m.estado')->orWhere('m.estado', 'aprobado');
            })
            ->select('m.*', 'p.nombre as producto_nombre')
            ->orderBy('m.fecha_movimiento')
            ->orderBy('m.id')
            ->get();

        $historial = collect();
        $prevOdo = null;
        $prevFecha = null;

        foreach ($historialRaw as $h) {
            // 🔥 Para rendimiento USAR SOLO el odómetro del vehículo (no el de la bomba)
            $odo = (float) ($h->odometro ?? 0); 
            $dif = null;
            $rendimiento = null;
            $frecuencia = null;

            if ($prevFecha) {
                $frecuencia = \Carbon\Carbon::parse($h->fecha_movimiento)->diffForHumans($prevFecha, true);
            }

            if ($odo > 0 && !is_null($prevOdo) && $odo > $prevOdo) {
                $dif = $odo - $prevOdo;
                $litros = abs((float) $h->cantidad);
                if ($litros > 0) {
                    if ($esMaquinaria) {
                        // Consumo de maquinaria: Litros consumidos por cada hora de uso
                        $rendimiento = $litros / $dif; // L/h
                    } else {
                        // Rendimiento de vehículo: Kilómetros recorridos por cada litro
                        $rendimiento = $dif / $litros; // km/L
                    }
                }
            }

            $historial->push([
                'id' => $h->id,
                'fecha' => $h->fecha_movimiento,
                'producto' => $h->producto_nombre,
                'cantidad' => $h->cantidad,
                'odometro' => $h->odometro,
                'odometro_bomba' => $h->odometro_bomba,
                'odo_usado' => $odo,
                'dif' => $dif,
                'rendimiento' => $rendimiento,
                'frecuencia' => $frecuencia
            ]);

            if ($odo > 0) {
                $prevOdo = $odo;
            }
            $prevFecha = $h->fecha_movimiento;
        }

        // Datos para el gráfico (últimos 15 movimientos con rendimiento)
        $chartData = $historial->whereNotNull('rendimiento')->take(-15);
        $labels = $chartData->map(fn($item) => \Carbon\Carbon::parse($item['fecha'])->format('d/m'))->values();
        $dataRendimiento = $chartData->pluck('rendimiento')->values();
        $dataLitros = $chartData->map(fn($item) => abs($item['cantidad']))->values();

        // El historial para la tabla (reverso para ver lo más reciente primero)
        $historialTable = $historial->reverse();

        // Promedio de frecuencia (días entre cargas)
        $avgDays = null;
        if ($historial->count() > 1) {
            $primera = \Carbon\Carbon::parse($historial->first()['fecha']);
            $ultima = \Carbon\Carbon::parse($historial->last()['fecha']);
            $diasTotales = $primera->diffInDays($ultima);
            $avgDays = $diasTotales / ($historial->count() - 1);
        }

        return view('fuelcontrol.movimientos.detalle', compact(
            'movimiento',
            'historialTable',
            'labels',
            'dataRendimiento',
            'dataLitros',
            'unidad',
            'esMaquinaria',
            'avgDays',
            'etiquetaRendimiento'
        ));
    }


    /**
     * Mostrar detalle XML
     */
    public function show($id)
    {
        $conexion = DB::connection('fuelcontrol');

        $movimiento = $conexion->table('movimientos')
            ->where('id', $id)
            ->first();

        if (!$movimiento) {
            abort(404);
        }

        if (empty($movimiento->xml_path)) {
            return response()->json([
                'error' => true,
                'message' => 'Este movimiento no tiene XML'
            ], 404);
        }

        $ruta = storage_path('app/' . $movimiento->xml_path);

        if (!file_exists($ruta)) {
            return response()->json([
                'error' => true,
                'message' => 'Archivo XML no encontrado'
            ], 404);
        }

        $xml = file_get_contents($ruta);

        return view('fuelcontrol.movimientos.xml', compact('movimiento', 'xml'));
    }

    /**
     * Aprobar XML
     */
    public function aprobar($id)
    {
        $conexion = DB::connection('fuelcontrol');

        $movimiento = $conexion->table('movimientos')
            ->where('id', $id)
            ->first();

        if (!$movimiento) {
            abort(404);
        }

        if ($movimiento->estado !== 'pendiente') {
            return response()->json([
                'error' => true,
                'message' => 'Ya fue procesado'
            ], 400);
        }

        $conexion->transaction(function () use ($conexion, $movimiento) {

            $producto = $conexion->table('productos')
                ->where('id', $movimiento->producto_id)
                ->first();

            if (!$producto) {
                throw new \Exception('Producto no encontrado');
            }

            $conexion->table('productos')
                ->where('id', $producto->id)
                ->update([
                    'cantidad' => $producto->cantidad + $movimiento->cantidad
                ]);

            $conexion->table('movimientos')
                ->where('id', $movimiento->id)
                ->update([
                    'estado' => 'aprobado'
                ]);
        });

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Rechazar XML
     */
    public function rechazar($id)
    {
        $conexion = DB::connection('fuelcontrol');

        $movimiento = $conexion->table('movimientos')
            ->where('id', $id)
            ->first();

        if (!$movimiento) {
            abort(404);
        }

        if ($movimiento->estado !== 'pendiente') {
            return response()->json([
                'error' => true,
                'message' => 'Ya fue procesado'
            ], 400);
        }

        $conexion->table('movimientos')
            ->where('id', $movimiento->id)
            ->update([
                'estado' => 'rechazado'
            ]);

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Crear movimiento manual
     */
    public function store(Request $request)
    {
        $request->validate([
            'producto_id' => 'required',
            'cantidad' => 'required|numeric',
            'tipo' => 'required|in:ingreso,salida',
        ]);

        $conexion = DB::connection('fuelcontrol');

        $conexion->transaction(function () use ($conexion, $request) {

            $producto = $conexion->table('productos')
                ->where('id', $request->producto_id)
                ->first();

            if (!$producto) {
                throw new \Exception('Producto no encontrado');
            }

            $nuevaCantidad = $request->tipo === 'ingreso'
                ? $producto->cantidad + $request->cantidad
                : $producto->cantidad - $request->cantidad;

            $conexion->table('movimientos')->insert([
                'producto_id' => $request->producto_id,
                'cantidad' => $request->cantidad,
                'tipo' => $request->tipo,
                'estado' => 'aprobado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conexion->table('productos')
                ->where('id', $producto->id)
                ->update([
                    'cantidad' => $nuevaCantidad
                ]);
        });

        return back()->with('success', 'Movimiento registrado correctamente');
    }
    /**
     * Listado de ingresos de combustible
     */
    public function ingreso(Request $request)
    {
        $query = DB::connection('fuelcontrol')
            ->table('movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->leftJoin('vehiculos as v', 'v.id', '=', 'm.vehiculo_id')
            ->select(
                'm.*',
                'p.nombre as producto_nombre',
                'v.patente',
                'v.descripcion as vehiculo_descripcion'
            )
            ->where('m.tipo', 'entrada');

        $ingresos = $query
            ->orderByDesc('m.fecha_movimiento')
            ->paginate(20)
            ->withQueryString();

        // KPIs de Totales (Basados en Entradas)
        $total_gasolina = DB::connection('fuelcontrol')->table('movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->where('m.tipo', 'entrada')
            ->where('p.nombre', 'LIKE', '%gasolina%')
            ->sum('m.cantidad');

        $total_diesel = DB::connection('fuelcontrol')->table('movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->where('m.tipo', 'entrada')
            ->where('p.nombre', 'LIKE', '%diesel%')
            ->sum('m.cantidad');

        return view('fuelcontrol.ingreso.index', compact('ingresos', 'total_gasolina', 'total_diesel'));
    }

    /**
     * Registrar ingreso a stock desde una factura DTE de combustible pendiente
     */
    public function ingresarDesdeDte(Request $request, $id)
    {
        $db = DB::connection('fuelcontrol');
        
        // 1. Obtener la factura
        $dte = $db->table('gmail_dte_documents')
            ->where('id', $id)
            ->first();
            
        if (!$dte) {
            return response()->json(['error' => 'Factura no encontrada'], 404);
        }
        
        // Evitar doble proceso
        $alreadyMatched = $db->table('movimientos')
            ->where('xml_path', $dte->xml_filename)
            ->exists();
            
        if ($alreadyMatched) {
            return response()->json(['error' => 'Esta factura ya fue ingresada a stock'], 400);
        }
        
        // 2. Parsea el XML
        $ruta = 'xml/' . $dte->xml_filename;
        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($ruta)) {
            return response()->json(['error' => 'Archivo XML físico no encontrado en el servidor'], 404);
        }
        
        $xmlContent = \Illuminate\Support\Facades\Storage::disk('local')->get($ruta);
        
        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            if (!$xml) {
                return response()->json(['error' => 'El archivo XML contiene un formato inválido'], 400);
            }
            $xml->registerXPathNamespace('sii', 'http://www.sii.cl/SiiDte');

            // Recarga de tarjeta prepago / cupón: la factura viene en litros,
            // pero es saldo cargado a una tarjeta y nunca entró al estanque.
            $termPago = strtoupper((string) ($xml->xpath('//sii:IdDoc/sii:TermPagoGlosa')[0] ?? ''));
            $sucursalEmisor = strtoupper((string) ($xml->xpath('//sii:Emisor/sii:Sucursal')[0] ?? ''));
            if (str_contains($termPago, 'PREPAGO') || str_contains($sucursalEmisor, 'CUPON')) {
                return response()->json([
                    'error' => 'Esta factura es una recarga de tarjeta prepago: el combustible no entró al estanque.'
                ], 400);
            }

            $detalles = $xml->xpath('//sii:Detalle') ?? [];
            $fuelDetails = [];
            
            foreach ($detalles as $detalle) {
                $nombre = strtoupper((string) $detalle->NmbItem);
                $cantidad = (float) $detalle->QtyItem;
                
                // Excluir herramientas, repuestos, aceites y bidones que no son combustible real
                $exclusiones = ['FILTRO', 'JUEGO', 'JGO', 'COMPRESIMETRO', 'ACEITE', 'ADITIVO', 'BOMBA', 'MANGUERA', 'BIDON', 'LIMPIADOR', 'INYECTOR', 'TAPA', 'SERVICIO', 'FLETE', 'HERRAMIENTA', 'EQUIPO'];
                $esExcluido = false;
                foreach ($exclusiones as $ex) {
                    if (str_contains($nombre, $ex)) {
                        $esExcluido = true;
                        break;
                    }
                }
                
                // El combustible siempre se factura en litros; un repuesto con
                // "DIESEL" en el nombre viene en UNID y no debe entrar al estanque.
                $unidad = strtoupper(trim((string) $detalle->UnmdItem));
                $esLitros = $unidad === 'L'
                    || str_starts_with($unidad, 'LT')
                    || str_starts_with($unidad, 'LITR');

                $productoNombre = null;
                if (!$esExcluido && $esLitros) {
                    $productoNombre = match (true) {
                        str_contains($nombre, 'DIESEL')   => 'Diesel',
                        str_contains($nombre, 'GASOLINA') => 'Gasolina',
                        default                            => null,
                    };
                }

                if ($productoNombre && $cantidad > 0) {
                    $fuelDetails[] = [
                        'producto' => $productoNombre,
                        'cantidad' => $cantidad
                    ];
                }
            }
            
            if (empty($fuelDetails)) {
                return response()->json(['error' => 'No se encontraron líneas de Diesel o Gasolina en esta factura'], 400);
            }
            
            // Iniciar transacción
            $db->beginTransaction();
            
            $movimientoIds = [];
            
            foreach ($fuelDetails as $fuel) {
                $productoNombre = $fuel['producto'];
                $cantidad = $fuel['cantidad'];
                
                $producto = $db->table('productos')->where('nombre', $productoNombre)->first();
                if (!$producto) {
                    throw new \Exception("El producto '{$productoNombre}' no está registrado en FuelControl.");
                }
                
                // Actualizar stock del producto
                $db->table('productos')
                    ->where('id', $producto->id)
                    ->increment('cantidad', $cantidad);
                    
                // Generar hash único
                $hash = hash('sha256', implode('|', [
                    $dte->gmail_message_id ?? 'reconciliation', $dte->xml_filename, $productoNombre, $cantidad
                ]));
                
                // Insertar movimiento
                $movimientoId = $db->table('movimientos')->insertGetId([
                    'producto_id'      => $producto->id,
                    'vehiculo_id'      => null,
                    'cantidad'         => $cantidad,
                    'tipo'             => 'entrada',
                    'origen'           => 'xml_estanque',
                    'referencia'       => $dte->xml_filename,
                    'requiere_revision'=> 0,
                    'estado'           => 'aprobado',
                    'xml_path'         => $dte->xml_filename,
                    'usuario'          => 'manual_reconcile',
                    'fecha_movimiento' => $dte->fecha_factura,
                    'hash_unico'       => $hash,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                
                $movimientoIds[] = $movimientoId;
            }
            
            // Actualizar estado del DTE
            $db->table('gmail_dte_documents')
                ->where('id', $id)
                ->update([
                    'inventory_status' => 'combustible',
                    'updated_at' => now()
                ]);
                
            $db->commit();
            
            return response()->json([
                'ok' => true,
                'message' => 'Ingreso registrado correctamente en stock',
                'movements' => $movimientoIds
            ]);
            
        } catch (\Throwable $e) {
            $db->rollBack();
            return response()->json([
                'error' => 'Error al procesar el ingreso: ' . $e->getMessage()
            ], 500);
        }
    }
}
