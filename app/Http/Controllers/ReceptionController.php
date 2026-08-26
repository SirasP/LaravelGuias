<?php

namespace App\Http\Controllers;

use App\Services\ReceptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceptionController extends Controller
{
    public function __construct(private ReceptionService $receptions) {}

    private function db()
    {
        return DB::connection('fuelcontrol');
    }

    /**
     * Formulario de recepción para una OC: lista ítems con pendiente = pedido - ya recibido.
     */
    public function create(int $orderId)
    {
        $order = $this->db()->table('purchase_orders')->where('id', $orderId)->firstOrFail();
        $items = $this->db()->table('purchase_order_items')
            ->where('purchase_order_id', $orderId)
            ->orderBy('id')
            ->get();

        $recibidoPorItem = $this->recibidoPorItem($orderId);

        $items = $items->map(function ($it) use ($recibidoPorItem) {
            $recibido = (float) ($recibidoPorItem[$it->id] ?? 0);
            $it->ya_recibido = $recibido;
            $it->pendiente = max((float) $it->quantity - $recibido, 0);

            return $it;
        });

        $bodegas = $this->bodegas();

        return view('recepciones.create', compact('order', 'items', 'bodegas'));
    }

    public function store(Request $request, int $orderId)
    {
        $order = $this->db()->table('purchase_orders')->where('id', $orderId)->firstOrFail();

        $data = $request->validate([
            'bodega_id' => ['nullable', 'integer'],
            'fecha_recepcion' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.item_id' => ['nullable', 'integer'],
            'lineas.*.product_name' => ['required', 'string'],
            'lineas.*.unidad' => ['nullable', 'string'],
            'lineas.*.cantidad_pedida' => ['nullable', 'numeric'],
            'lineas.*.cantidad_recibida' => ['required', 'numeric', 'min:0'],
            'lineas.*.costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.inventory_product_id' => ['nullable', 'integer'],
        ]);

        // Descartar líneas con cantidad 0
        $lineas = collect($data['lineas'])->filter(fn ($l) => (float) $l['cantidad_recibida'] > 0)->values();
        if ($lineas->isEmpty()) {
            return back()->withInput()->with('error', 'Debes recibir al menos una unidad.');
        }

        $recepcionId = $this->db()->transaction(function () use ($order, $data, $lineas) {
            $recepcionId = $this->db()->table('recepciones')->insertGetId([
                'purchase_order_id' => $order->id,
                'proveedor_rut' => null,
                'proveedor_nombre' => $order->supplier_name,
                'bodega_id' => $data['bodega_id'] ?? null,
                'estado' => 'BORRADOR',
                'fecha_recepcion' => $data['fecha_recepcion'] ?? now(),
                'usuario_id' => auth()->id(),
                'notas' => $data['notas'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lineas as $l) {
                $this->db()->table('recepcion_lineas')->insert([
                    'recepcion_id' => $recepcionId,
                    'purchase_order_item_id' => $l['item_id'] ?? null,
                    'inventory_product_id' => $l['inventory_product_id'] ?? null,
                    'product_name' => $l['product_name'],
                    'unidad' => $l['unidad'] ?? 'UN',
                    'cantidad_pedida' => $l['cantidad_pedida'] ?? null,
                    'cantidad_recibida' => $l['cantidad_recibida'],
                    'costo_unitario' => $l['costo_unitario'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $recepcionId;
        });

        return redirect()
            ->route('purchase_orders.receptions.show', ['id' => $recepcionId])
            ->with('success', 'Recepción creada en borrador. Revisa y confírmala para ingresar el stock.');
    }

    public function confirm(int $id)
    {
        try {
            $this->receptions->confirmReception($id, auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo confirmar: '.$e->getMessage());
        }

        return back()->with('success', 'Recepción confirmada. Stock ingresado.');
    }

    public function show(int $id)
    {
        $recepcion = $this->db()->table('recepciones')->where('id', $id)->firstOrFail();
        $lineas = $this->db()->table('recepcion_lineas')->where('recepcion_id', $id)->orderBy('id')->get();

        $order = $recepcion->purchase_order_id
            ? $this->db()->table('purchase_orders')->where('id', $recepcion->purchase_order_id)->first()
            : null;

        $reconcile = $this->receptions->reconcile($id);

        // Facturas candidatas para vincular (sin recepción aún), más recientes primero.
        // Se cargan para el selector con búsqueda en vivo (cliente). NO se filtra por
        // proveedor en servidor porque el supplier_name de la OC rara vez coincide con
        // el proveedor_nombre del XML/SII.
        $facturasDisponibles = $this->db()->table('gmail_dte_documents')
            ->whereNull('recepcion_id')
            ->orderByDesc('fecha_factura')
            ->orderByDesc('id')
            ->limit(2000)
            ->get([
                'id', 'folio', 'tipo_dte', 'proveedor_nombre', 'proveedor_rut',
                'fecha_factura', 'fecha_vencimiento', 'monto_neto', 'monto_iva',
                'monto_total', 'payment_status',
            ])
            ->map(function ($f) {
                $clp = fn ($n) => '$'.number_format((float) $n, 0, ',', '.');

                return [
                    'id' => $f->id,
                    'folio' => $f->folio,
                    'tipo' => $this->tipoDteLabel($f->tipo_dte),
                    'proveedor' => $f->proveedor_nombre ?? 'Sin proveedor',
                    'rut' => $f->proveedor_rut,
                    'fecha' => $f->fecha_factura,
                    'vence' => $f->fecha_vencimiento,
                    'neto_fmt' => $clp($f->monto_neto),
                    'iva_fmt' => $clp($f->monto_iva),
                    'monto' => (float) $f->monto_total,
                    'monto_fmt' => $clp($f->monto_total),
                    'pagado' => ($f->payment_status ?? 'sin_pagar') === 'pagado',
                    'search' => mb_strtolower(trim(
                        ($f->folio ?? '').' '.($f->proveedor_nombre ?? '').' '.($f->proveedor_rut ?? '')
                    )),
                ];
            })
            ->values();

        $facturaVinculada = $recepcion->gmail_document_id
            ? $this->db()->table('gmail_dte_documents')->where('id', $recepcion->gmail_document_id)->first()
            : null;

        return view('recepciones.show', compact(
            'recepcion', 'lineas', 'order', 'reconcile', 'facturasDisponibles', 'facturaVinculada'
        ));
    }

    /**
     * Vincula una factura DTE a la recepción (conciliación 3 vías). NO mueve stock.
     */
    public function matchInvoice(Request $request, int $id)
    {
        $recepcion = $this->db()->table('recepciones')->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'gmail_document_id' => ['required', 'integer'],
        ]);

        $factura = $this->db()->table('gmail_dte_documents')->where('id', $data['gmail_document_id'])->first();
        if (! $factura) {
            return back()->with('error', 'Factura no encontrada.');
        }

        $this->db()->transaction(function () use ($recepcion, $factura) {
            $this->db()->table('recepciones')->where('id', $recepcion->id)->update([
                'gmail_document_id' => $factura->id,
                'updated_at' => now(),
            ]);
            $this->db()->table('gmail_dte_documents')->where('id', $factura->id)->update([
                'recepcion_id' => $recepcion->id,
                'purchase_order_id' => $recepcion->purchase_order_id,
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Factura vinculada a la recepción.');
    }

    public function unmatchInvoice(int $id)
    {
        $recepcion = $this->db()->table('recepciones')->where('id', $id)->firstOrFail();

        $this->db()->transaction(function () use ($recepcion) {
            if ($recepcion->gmail_document_id) {
                $this->db()->table('gmail_dte_documents')->where('id', $recepcion->gmail_document_id)->update([
                    'recepcion_id' => null,
                    'purchase_order_id' => null,
                    'updated_at' => now(),
                ]);
            }
            $this->db()->table('recepciones')->where('id', $recepcion->id)->update([
                'gmail_document_id' => null,
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Factura desvinculada.');
    }

    /** Etiqueta corta del tipo de DTE (códigos SII Chile). */
    private function tipoDteLabel($tipo): string
    {
        return match ((int) $tipo) {
            33 => 'Factura',
            34 => 'Factura exenta',
            39 => 'Boleta',
            41 => 'Boleta exenta',
            46 => 'Factura compra',
            52 => 'Guía despacho',
            56 => 'Nota débito',
            61 => 'Nota crédito',
            default => $tipo ? ('DTE '.$tipo) : 'DTE',
        };
    }

    /** Suma recibida (confirmada) por purchase_order_item_id. */
    private function recibidoPorItem(int $orderId)
    {
        return $this->db()->table('recepcion_lineas as rl')
            ->join('recepciones as r', 'r.id', '=', 'rl.recepcion_id')
            ->where('r.purchase_order_id', $orderId)
            ->where('r.estado', 'CONFIRMADA')
            ->whereNotNull('rl.purchase_order_item_id')
            ->groupBy('rl.purchase_order_item_id')
            ->select('rl.purchase_order_item_id', DB::raw('SUM(rl.cantidad_recibida) as recibido'))
            ->pluck('recibido', 'rl.purchase_order_item_id');
    }

    /** Bodegas desde la base default (guias), donde vive la tabla `bodegas`. */
    private function bodegas()
    {
        try {
            return DB::table('bodegas')->orderBy('nombre')->get(['id', 'nombre']);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
