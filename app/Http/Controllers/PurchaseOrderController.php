<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderController extends Controller
{
    private const DEFAULT_NOTES_TEMPLATE = "Estimado/a {PROVEEDOR},\n\nJunto con saludar, enviamos cotización para su revisión y confirmación.\n\nPor favor indicar plazo de entrega y condiciones.\n\nSaludos cordiales.";

    public function index()
    {
        $db = DB::connection('fuelcontrol');

        $orders = $db->table('purchase_orders')
            ->orderByDesc('id')
            ->paginate(20);

        // Nombres de todos los proveedores destinatarios por orden
        $suppliersByOrder = $db->table('purchase_order_recipients as por')
            ->leftJoin('purchase_order_supplier_emails as pose', 'pose.email', '=', 'por.email')
            ->leftJoin('purchase_order_suppliers as pos', 'pos.id', '=', 'pose.supplier_id')
            ->whereIn('por.purchase_order_id', $orders->pluck('id'))
            ->select('por.purchase_order_id', 'pos.name as supplier_name')
            ->get()
            ->groupBy('purchase_order_id')
            ->map(fn($rows) => $rows->pluck('supplier_name')->filter()->unique()->values());

        return view('purchase_orders.index', compact('orders', 'suppliersByOrder'));
    }

    public function create()
    {
        $db = DB::connection('fuelcontrol');

        $products = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->select('id', 'nombre', 'codigo', 'unidad', 'costo_promedio', 'is_active')
            ->where('is_active', 1)
            ->orderBy('nombre')
            ->get();

        $suppliers = $db->table('purchase_order_suppliers')
            ->select([
                'id',
                'name',
                'rut',
                'taxpayer_type',
                'activity_description',
                'address_line_1',
                'address_line_2',
                'comuna',
                'region',
                'postal_code',
                'country',
                'phone',
                'mobile',
                'website',
                'language',
            ])
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $emailsBySupplier = $db->table('purchase_order_supplier_emails')
            ->select('supplier_id', 'email', 'is_primary')
            ->whereIn('supplier_id', $suppliers->pluck('id'))
            ->orderByDesc('is_primary')
            ->orderBy('email')
            ->get()
            ->groupBy('supplier_id');

        $suppliers = $suppliers->map(function ($supplier) use ($emailsBySupplier) {
            $supplier->emails = ($emailsBySupplier->get($supplier->id) ?? collect())
                ->map(fn($row) => [
                    'email' => (string) $row->email,
                    'is_primary' => (int) $row->is_primary === 1,
                ])
                ->values()
                ->all();
            return $supplier;
        })->values();

        $defaultNotesTemplate = self::DEFAULT_NOTES_TEMPLATE;

        return view('purchase_orders.create', compact('products', 'suppliers', 'defaultNotesTemplate'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'currency' => ['required', 'in:CLP,USD,EUR'],
            'recipient_emails' => ['nullable', 'array'],
            'recipient_emails.*' => ['email'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ]);

        $db = DB::connection('fuelcontrol');

        $supplierId = isset($data['supplier_id']) && $data['supplier_id'] !== '' ? (int) $data['supplier_id'] : null;
        if (!$supplierId) {
            return back()->withInput()->with('warning', 'Debes seleccionar al menos un proveedor.');
        }

        $supplier = $db->table('purchase_order_suppliers')
            ->select('id', 'name')
            ->where('id', $supplierId)
            ->where('is_active', 1)
            ->first();

        if (!$supplier) {
            return back()->withInput()->with('warning', 'Proveedor no válido.');
        }

        $supplierName = (string) $supplier->name;

        // Emails de los destinatarios seleccionados en el formulario
        $emails = array_values(array_unique(array_filter(
            array_map('mb_strtolower', (array) ($data['recipient_emails'] ?? [])),
            fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL)
        )));

        // Si no se enviaron emails, usar los del proveedor principal
        if (empty($emails)) {
            $emails = $db->table('purchase_order_supplier_emails')
                ->where('supplier_id', $supplierId)
                ->pluck('email')
                ->map(fn($e) => mb_strtolower((string) $e))
                ->all();
        }

        $products = $db->table('gmail_inventory_products')
            ->select('id', 'nombre', 'unidad', 'costo_promedio')
            ->get()
            ->keyBy('id');
        $productsByName = $products->mapWithKeys(function ($product) {
            return [mb_strtolower(trim((string) $product->nombre)) => $product];
        });

        $cleanItems = [];
        $subtotal = 0.0;

        foreach ($data['items'] as $idx => $item) {
            $inventoryId = isset($item['inventory_product_id']) && $item['inventory_product_id'] !== ''
                ? (int) $item['inventory_product_id']
                : null;

            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];

            if ($quantity <= 0) {
                return back()->withInput()->with('warning', 'Cantidad inválida en una línea.');
            }

            if ($unitPrice < 0) {
                return back()->withInput()->with('warning', 'Precio inválido en una línea.');
            }

            $productName = trim((string) ($item['product_name'] ?? ''));
            $unit = trim((string) ($item['unit'] ?? ''));

            if ($inventoryId) {
                $p = $products->get($inventoryId);
                if (!$p) {
                    return back()->withInput()->with('warning', 'Producto de inventario no válido en una línea.');
                }
                $productName = $productName !== '' ? $productName : (string) $p->nombre;
                $unit = $unit !== '' ? $unit : (string) ($p->unidad ?? 'UN');
                if ($unitPrice === 0.0 && (float) ($p->costo_promedio ?? 0) > 0) {
                    $unitPrice = (float) $p->costo_promedio;
                }
            } else {
                $nameKey = mb_strtolower(trim($productName));
                $existingByName = $productsByName->get($nameKey);
                if ($existingByName) {
                    $inventoryId = (int) $existingByName->id;
                    $unit = $unit !== '' ? $unit : (string) ($existingByName->unidad ?? 'UN');
                    if ($unitPrice === 0.0 && (float) ($existingByName->costo_promedio ?? 0) > 0) {
                        $unitPrice = (float) $existingByName->costo_promedio;
                    }
                }
            }

            if ($productName === '') {
                return back()->withInput()->with('warning', 'Nombre de producto requerido en la línea ' . ($idx + 1) . '.');
            }

            if ($unit === '') {
                $unit = 'UN';
            }

            $lineTotal = round($quantity * $unitPrice, 2);
            $subtotal += $lineTotal;

            $cleanItems[] = [
                'inventory_product_id' => $inventoryId,
                'product_name' => $productName,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'is_custom' => $inventoryId ? 0 : 1,
            ];
        }

        $now = now();

        $orderId = $db->transaction(function () use ($db, $data, $supplierId, $supplierName, $cleanItems, $emails, $subtotal, $now) {
            $year = now()->format('Y');
            $lastId = (int) ($db->table('purchase_orders')->max('id') ?? 0) + 1;
            $orderNumber = 'COT-' . $year . '-' . str_pad((string) $lastId, 5, '0', STR_PAD_LEFT);

            $orderId = $db->table('purchase_orders')->insertGetId([
                'order_number' => $orderNumber,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'currency' => $data['currency'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'created_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($emails as $email) {
                $db->table('purchase_order_recipients')->insert([
                    'purchase_order_id' => $orderId,
                    'email' => $email,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($cleanItems as $line) {
                $db->table('purchase_order_items')->insert([
                    'purchase_order_id' => $orderId,
                    'inventory_product_id' => $line['inventory_product_id'],
                    'product_name' => $line['product_name'],
                    'unit' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                    'is_custom' => $line['is_custom'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Product typed manually: persist it automatically for future autocomplete.
                if ($line['is_custom'] === 1) {
                    $exists = $db->table('gmail_inventory_products')
                        ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($line['product_name'])])
                        ->exists();

                    if (!$exists) {
                        $db->table('gmail_inventory_products')->insert([
                            'nombre' => $line['product_name'],
                            'codigo' => null,
                            'unidad' => $line['unit'],
                            'stock_actual' => 0,
                            'costo_promedio' => $line['unit_price'],
                            'is_active' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            return $orderId;
        });

        // ── Auto-envío de correo al crear ─────────────────────────────────
        $sentCount  = 0;
        $emailError = null;

        if (!empty($emails)) {
            try {
                $order      = $db->table('purchase_orders')->where('id', $orderId)->first();
                $orderItems = $db->table('purchase_order_items')
                    ->where('purchase_order_id', $orderId)->orderBy('id')->get();

                $recipientList = $db->table('purchase_order_recipients as por')
                    ->leftJoin('purchase_order_supplier_emails as pose', 'pose.email', '=', 'por.email')
                    ->leftJoin('purchase_order_suppliers as pos', 'pos.id', '=', 'pose.supplier_id')
                    ->where('por.purchase_order_id', $orderId)
                    ->select('por.email', 'pos.name as supplier_name')
                    ->get()
                    ->map(fn($r) => [
                        'email'         => $r->email,
                        'supplier_name' => $r->supplier_name ?? $supplierName,
                    ])
                    ->all();

                $subject         = 'Cotización ' . $order->order_number;
                $messageTemplate = trim((string) ($data['notes'] ?? ''));

                foreach ($recipientList as $recipient) {
                    $personalizedMsg = str_replace('{PROVEEDOR}', $recipient['supplier_name'], $messageTemplate);

                    $pdf = Pdf::loadView('purchase_orders.pdf', [
                        'order'         => $order,
                        'items'         => $orderItems,
                        'supplierName'  => $recipient['supplier_name'],
                        'supplierEmail' => $recipient['email'],
                        'message'       => $personalizedMsg,
                    ])->setPaper('a4', 'portrait');

                    $pdfFilename = 'Cotizacion_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $order->order_number) . '.pdf';
                    $pdfContent  = $pdf->output();

                    $bodyHtml = '
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">
  <div style="background:#0f766e;padding:20px 24px;border-radius:8px 8px 0 0;">
    <h1 style="margin:0;color:#fff;font-size:20px;">Cotización ' . e($order->order_number) . '</h1>
    <p style="margin:4px 0 0;color:#ccfbf1;font-size:13px;">Solicitud de cotización de precios</p>
  </div>
  <div style="background:#f8fafc;padding:20px 24px;border:1px solid #e2e8f0;border-top:none;">
    <p style="margin:0 0 10px;font-size:14px;"><strong>Estimado/a:</strong> ' . e($recipient['supplier_name']) . '</p>'
    . ($personalizedMsg !== '' ? '<p style="margin:10px 0;font-size:13px;line-height:1.6;white-space:pre-line;">' . e($personalizedMsg) . '</p>' : '') . '
    <p style="margin:16px 0 0;font-size:13px;color:#64748b;">
      Adjunto encontrará el detalle completo de los productos solicitados en formato PDF.<br>
      Por favor responda indicando sus precios unitarios por ítem.
    </p>
  </div>
  <div style="padding:12px 24px;background:#f1f5f9;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;font-size:11px;color:#94a3b8;text-align:center;">
    ' . config('app.name', 'Sistema') . ' &mdash; Cotización N° ' . e($order->order_number) . '
  </div>
</div>';

                    $emailAddr = $recipient['email'];
                    Mail::send([], [], function ($msg) use ($emailAddr, $subject, $bodyHtml, $pdfContent, $pdfFilename) {
                        $msg->to($emailAddr)
                            ->subject($subject)
                            ->html($bodyHtml)
                            ->attachData($pdfContent, $pdfFilename, ['mime' => 'application/pdf']);
                    });
                }

                $sentCount = count($recipientList);
                $db->table('purchase_orders')->where('id', $orderId)->update([
                    'status'     => 'sent',
                    'sent_at'    => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $ex) {
                $emailError = $ex->getMessage();
            }
        }

        if ($emailError) {
            return redirect()->route('purchase_orders.show', $orderId)
                ->with('success', 'Cotización creada.')
                ->with('warning', 'No se pudo enviar el correo: ' . $emailError);
        }

        $successMsg = $sentCount > 0
            ? 'Cotización creada y enviada a ' . $sentCount . ' destinatario(s).'
            : 'Cotización creada.';

        return redirect()->route('purchase_orders.show', $orderId)->with('success', $successMsg);
    }

    public function confirmAsOrder(Request $request, int $id)
    {
        $validated = $request->validate([
            'chosen_supplier_id' => ['required', 'integer'],
            'apply_reply_id'     => ['nullable', 'integer'],
        ]);

        $db = DB::connection('fuelcontrol');

        $supplier = $db->table('purchase_order_suppliers')
            ->where('id', $validated['chosen_supplier_id'])
            ->where('is_active', 1)
            ->first();

        if (!$supplier) {
            return back()->with('warning', 'Proveedor no válido.');
        }

        $db->table('purchase_orders')
            ->where('id', $id)
            ->where('status', 'sent')
            ->update([
                'status'        => 'order',
                'supplier_id'   => $supplier->id,
                'supplier_name' => $supplier->name,
                'updated_at'    => now(),
            ]);

        // Aplicar precios cotizados del proveedor a los ítems de la OC
        if (!empty($validated['apply_reply_id'])) {
            $replyId     = (int) $validated['apply_reply_id'];
            $replyItems  = $db->table('purchase_order_reply_items')->where('reply_id', $replyId)->get();
            $newTotal    = 0;

            foreach ($replyItems as $ri) {
                if ($ri->unit_price_quoted !== null) {
                    $db->table('purchase_order_items')
                        ->where('id', $ri->purchase_order_item_id)
                        ->where('purchase_order_id', $id)
                        ->update([
                            'unit_price' => $ri->unit_price_quoted,
                            'line_total' => $ri->line_total_quoted,
                            'updated_at' => now(),
                        ]);
                    $newTotal += (float) $ri->line_total_quoted;
                }
            }

            if ($newTotal > 0) {
                $db->table('purchase_orders')->where('id', $id)->update([
                    'total'      => round($newTotal, 2),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── Enviar correo de confirmación de OC al proveedor ──────────────
        $emailSent = false;
        try {
            $freshOrder = $db->table('purchase_orders')->where('id', $id)->first();
            $freshItems = $db->table('purchase_order_items')
                ->where('purchase_order_id', $id)->orderBy('id')->get();

            $supplierEmail = $db->table('purchase_order_supplier_emails')
                ->where('supplier_id', $supplier->id)
                ->orderByDesc('is_primary')
                ->value('email');

            if ($supplierEmail) {
                $pdf = Pdf::loadView('purchase_orders.order_pdf', [
                    'order'         => $freshOrder,
                    'items'         => $freshItems,
                    'supplierName'  => $supplier->name,
                    'supplierEmail' => $supplierEmail,
                ])->setPaper('a4', 'portrait');

                $pdfFilename = 'OrdenCompra_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $freshOrder->order_number) . '.pdf';
                $pdfContent  = $pdf->output();
                $subject     = 'Orden de Compra ' . $freshOrder->order_number;

                $bodyHtml = '
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">
  <div style="background:#1d4ed8;padding:20px 24px;border-radius:8px 8px 0 0;">
    <h1 style="margin:0;color:#fff;font-size:20px;">Orden de Compra ' . e($freshOrder->order_number) . '</h1>
    <p style="margin:4px 0 0;color:#bfdbfe;font-size:13px;">Confirmación de pedido</p>
  </div>
  <div style="background:#f8fafc;padding:20px 24px;border:1px solid #e2e8f0;border-top:none;">
    <p style="margin:0 0 12px;font-size:14px;"><strong>Estimado/a:</strong> ' . e($supplier->name) . '</p>
    <p style="margin:0 0 12px;font-size:13px;line-height:1.6;">
      Nos complace confirmar la <strong>Orden de Compra N° ' . e($freshOrder->order_number) . '</strong>.<br>
      Adjunto encontrará el detalle completo de los productos solicitados.<br>
      Por favor proceda con el despacho y confirme la fecha estimada de entrega.
    </p>
    <p style="margin:0;font-size:12px;color:#64748b;">Total: <strong>' . e($freshOrder->currency) . ' ' . number_format((float) $freshOrder->total, 2, ',', '.') . '</strong></p>
  </div>
  <div style="padding:12px 24px;background:#f1f5f9;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;font-size:11px;color:#94a3b8;text-align:center;">
    ' . config('app.name', 'Sistema') . ' &mdash; Orden de Compra N° ' . e($freshOrder->order_number) . '
  </div>
</div>';

                Mail::send([], [], function ($message) use ($supplierEmail, $subject, $bodyHtml, $pdfContent, $pdfFilename) {
                    $message->to($supplierEmail)
                        ->subject($subject)
                        ->html($bodyHtml)
                        ->attachData($pdfContent, $pdfFilename, ['mime' => 'application/pdf']);
                });

                $emailSent = true;
            }
        } catch (\Throwable $e) {
            // OC ya confirmada, el correo es no-crítico
        }

        $msg = 'Orden de compra creada con ' . $supplier->name . '.';
        if ($emailSent) {
            $msg .= ' Se envió confirmación por correo al proveedor.';
        }

        return redirect()->route('purchase_orders.show', $id)->with('success', $msg);
    }

    public function upsertSupplier(Request $request)
    {
        $payload = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:25'],
            'taxpayer_type' => ['nullable', 'string', 'max:255'],
            'activity_description' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'comuna' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:60'],
            'mobile' => ['nullable', 'string', 'max:60'],
            'website' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:30'],
            'emails' => ['nullable', 'string'],
        ]);

        $db = DB::connection('fuelcontrol');
        $emails = $this->normalizeEmails((string) ($payload['emails'] ?? ''));
        $now = now();

        $supplierId = $db->transaction(function () use ($db, $payload, $emails, $now) {
            $supplierId = isset($payload['supplier_id']) && $payload['supplier_id'] !== ''
                ? (int) $payload['supplier_id']
                : null;

            $data = [
                'name' => trim((string) $payload['name']),
                'rut' => $this->nullableString($payload['rut'] ?? null),
                'taxpayer_type' => $this->nullableString($payload['taxpayer_type'] ?? null),
                'activity_description' => $this->nullableString($payload['activity_description'] ?? null),
                'address_line_1' => $this->nullableString($payload['address_line_1'] ?? null),
                'address_line_2' => $this->nullableString($payload['address_line_2'] ?? null),
                'comuna' => $this->nullableString($payload['comuna'] ?? null),
                'region' => $this->nullableString($payload['region'] ?? null),
                'postal_code' => $this->nullableString($payload['postal_code'] ?? null),
                'country' => $this->nullableString($payload['country'] ?? null) ?? 'Chile',
                'phone' => $this->nullableString($payload['phone'] ?? null),
                'mobile' => $this->nullableString($payload['mobile'] ?? null),
                'website' => $this->nullableString($payload['website'] ?? null),
                'language' => $this->nullableString($payload['language'] ?? null) ?? 'es_CL',
                'is_active' => 1,
                'updated_at' => $now,
            ];

            if ($supplierId) {
                $exists = $db->table('purchase_order_suppliers')->where('id', $supplierId)->exists();
                if (!$exists) {
                    abort(404, 'Proveedor no encontrado.');
                }
                $db->table('purchase_order_suppliers')->where('id', $supplierId)->update($data);
            } else {
                $data['created_at'] = $now;
                $supplierId = $db->table('purchase_order_suppliers')->insertGetId($data);
            }

            $db->table('purchase_order_supplier_emails')->where('supplier_id', $supplierId)->delete();
            foreach ($emails as $index => $email) {
                $db->table('purchase_order_supplier_emails')->insert([
                    'supplier_id' => $supplierId,
                    'email' => $email,
                    'is_primary' => $index === 0 ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $supplierId;
        });

        $supplier = $db->table('purchase_order_suppliers')
            ->select([
                'id', 'name', 'rut', 'taxpayer_type', 'activity_description',
                'address_line_1', 'address_line_2', 'comuna', 'region',
                'postal_code', 'country', 'phone', 'mobile', 'website', 'language',
            ])
            ->where('id', $supplierId)
            ->first();

        $supplierEmails = $db->table('purchase_order_supplier_emails')
            ->select('email', 'is_primary')
            ->where('supplier_id', $supplierId)
            ->orderByDesc('is_primary')
            ->orderBy('email')
            ->get()
            ->map(fn($row) => [
                'email' => (string) $row->email,
                'is_primary' => (int) $row->is_primary === 1,
            ])
            ->values()
            ->all();

        $supplier->emails = $supplierEmails;

        return response()->json([
            'ok' => true,
            'message' => 'Proveedor guardado correctamente.',
            'supplier' => $supplier,
        ]);
    }

    public function show(int $id)
    {
        $db = DB::connection('fuelcontrol');

        $order = $db->table('purchase_orders')->where('id', $id)->firstOrFail();
        $items = $db->table('purchase_order_items')->where('purchase_order_id', $id)->orderBy('id')->get();

        // Recipients enriched with their supplier name (via join on supplier emails table)
        $recipients = $db->table('purchase_order_recipients as por')
            ->leftJoin('purchase_order_supplier_emails as pose', 'pose.email', '=', 'por.email')
            ->leftJoin('purchase_order_suppliers as pos', 'pos.id', '=', 'pose.supplier_id')
            ->where('por.purchase_order_id', $id)
            ->select('por.email', 'pos.name as supplier_name', 'pose.supplier_id')
            ->orderBy('por.id')
            ->get()
            ->map(function ($r) use ($order) {
                $r->supplier_name = $r->supplier_name ?? $order->supplier_name;
                return $r;
            });

        $replies = $db->table('purchase_order_replies')
            ->where('purchase_order_id', $id)
            ->orderBy('created_at')
            ->get();

        // Cargar items cotizados por cada respuesta
        $replyIds = $replies->pluck('id')->all();
        $replyItemsAll = $replyIds
            ? $db->table('purchase_order_reply_items')
                ->whereIn('reply_id', $replyIds)
                ->orderBy('purchase_order_item_id')
                ->get()
                ->groupBy('reply_id')
            : collect();

        return view('purchase_orders.show', compact('order', 'items', 'recipients', 'replies', 'replyItemsAll'));
    }

    public function sendEmail(Request $request, int $id)
    {
        $validated = $request->validate([
            'emails' => ['nullable', 'string'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $db = DB::connection('fuelcontrol');
        $order = $db->table('purchase_orders')->where('id', $id)->firstOrFail();
        $items = $db->table('purchase_order_items')->where('purchase_order_id', $id)->orderBy('id')->get();

        // Build recipient list: [{email, supplier_name}]
        $customEmails = $this->normalizeEmails((string) ($validated['emails'] ?? ''));

        if (!empty($customEmails)) {
            // Map typed emails → supplier name (join via supplier emails table)
            $emailToSupplier = $db->table('purchase_order_supplier_emails as pose')
                ->join('purchase_order_suppliers as pos', 'pos.id', '=', 'pose.supplier_id')
                ->whereIn('pose.email', $customEmails)
                ->pluck('pos.name', 'pose.email');

            $recipientList = array_map(fn($email) => [
                'email' => $email,
                'supplier_name' => $emailToSupplier[$email] ?? $order->supplier_name,
            ], $customEmails);
        } else {
            // Use saved recipients enriched with supplier names
            $recipientList = $db->table('purchase_order_recipients as por')
                ->leftJoin('purchase_order_supplier_emails as pose', 'pose.email', '=', 'por.email')
                ->leftJoin('purchase_order_suppliers as pos', 'pos.id', '=', 'pose.supplier_id')
                ->where('por.purchase_order_id', $id)
                ->select('por.email', 'pos.name as supplier_name')
                ->orderBy('por.id')
                ->get()
                ->map(fn($r) => [
                    'email' => $r->email,
                    'supplier_name' => $r->supplier_name ?? $order->supplier_name,
                ])
                ->all();
        }

        if (empty($recipientList)) {
            return back()->with('warning', 'Debes ingresar al menos un correo destinatario.');
        }

        $subject = trim((string) ($validated['subject'] ?? ''));
        if ($subject === '') {
            $subject = 'Cotización ' . ($order->order_number ?? ('#' . $order->id));
        }

        $messageTemplate = trim((string) ($validated['message'] ?? ''));

        try {
            foreach ($recipientList as $recipient) {
                $personalizedMsg = str_replace('{PROVEEDOR}', $recipient['supplier_name'], $messageTemplate);

                // ── Generar PDF personalizado para este destinatario ──
                $pdf = Pdf::loadView('purchase_orders.pdf', [
                    'order'        => $order,
                    'items'        => $items,
                    'supplierName' => $recipient['supplier_name'],
                    'supplierEmail'=> $recipient['email'],
                    'message'      => $personalizedMsg,
                ])->setPaper('a4', 'portrait');

                $pdfFilename = 'Cotizacion_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $order->order_number) . '.pdf';
                $pdfContent  = $pdf->output();

                // ── Cuerpo del correo: limpio y profesional ──
                $bodyHtml = '
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;">
  <div style="background:#0f766e;padding:20px 24px;border-radius:8px 8px 0 0;">
    <h1 style="margin:0;color:#fff;font-size:20px;">Cotización ' . e($order->order_number) . '</h1>
    <p style="margin:4px 0 0;color:#ccfbf1;font-size:13px;">Solicitud de cotización de precios</p>
  </div>
  <div style="background:#f8fafc;padding:20px 24px;border:1px solid #e2e8f0;border-top:none;">
    <p style="margin:0 0 10px;font-size:14px;"><strong>Estimado/a:</strong> ' . e($recipient['supplier_name']) . '</p>'
    . ($personalizedMsg !== '' ? '<p style="margin:10px 0;font-size:13px;line-height:1.6;white-space:pre-line;">' . e($personalizedMsg) . '</p>' : '') . '
    <p style="margin:16px 0 0;font-size:13px;color:#64748b;">
      Adjunto encontrará el detalle completo de los productos solicitados en formato PDF.<br>
      Por favor responda indicando sus precios unitarios por ítem.
    </p>
  </div>
  <div style="padding:12px 24px;background:#f1f5f9;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;font-size:11px;color:#94a3b8;text-align:center;">
    ' . config('app.name', 'Sistema') . ' &mdash; Cotización N° ' . e($order->order_number) . '
  </div>
</div>';

                $emailAddr = $recipient['email'];
                Mail::send([], [], function ($message) use ($emailAddr, $subject, $bodyHtml, $pdfContent, $pdfFilename) {
                    $message->to($emailAddr)
                        ->subject($subject)
                        ->html($bodyHtml)
                        ->attachData($pdfContent, $pdfFilename, ['mime' => 'application/pdf']);
                });
            }
        } catch (\Throwable $e) {
            return back()->with('warning', 'No se pudo enviar el correo: ' . $e->getMessage());
        }

        $db->table('purchase_orders')->where('id', $id)->update([
            'status' => 'sent',
            'sent_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Orden enviada a ' . count($recipientList) . ' destinatario(s).');
    }

    private function normalizeEmails(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[;,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $emails = [];

        foreach ($parts as $part) {
            $email = mb_strtolower(trim($part));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    // ── Respuestas de proveedores (chat/timeline) ─────────────────────────────

    public function storeReply(Request $request, int $id)
    {
        $db = DB::connection('fuelcontrol');
        $order = $db->table('purchase_orders')->where('id', $id)->first();
        if (!$order) abort(404);

        $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'notes'         => ['nullable', 'string', 'max:8000'],
            'total_quoted'  => ['nullable', 'numeric', 'min:0'],
            'currency'      => ['nullable', 'string', 'max:10'],
            'pdf'           => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'item_prices'   => ['nullable', 'array'],
            'item_prices.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $pdfPath = null;
        $pdfOriginalName = null;
        if ($request->hasFile('pdf')) {
            $file = $request->file('pdf');
            $pdfOriginalName = $file->getClientOriginalName();
            $pdfPath = $file->store('cotizacion-pdfs', 'public');
        }

        // Calcular total desde ítems si se ingresaron precios por ítem
        $itemPrices = $request->input('item_prices', []);
        $currency   = $request->input('currency') ?: ($order->currency ?? 'CLP');
        $items      = $db->table('purchase_order_items')->where('purchase_order_id', $id)->orderBy('id')->get();

        $totalFromItems = null;
        if (!empty(array_filter($itemPrices, fn($v) => $v !== null && $v !== ''))) {
            $totalFromItems = 0;
            foreach ($items as $item) {
                $unitPrice = isset($itemPrices[$item->id]) && $itemPrices[$item->id] !== ''
                    ? (float) $itemPrices[$item->id] : 0;
                $totalFromItems += $unitPrice * (float) $item->quantity;
            }
        }

        $totalQuoted = $totalFromItems ?? ($request->input('total_quoted') ?: null);

        $replyId = $db->table('purchase_order_replies')->insertGetId([
            'purchase_order_id' => $id,
            'supplier_name'     => $request->input('supplier_name'),
            'notes'             => $request->input('notes') ?: null,
            'total_quoted'      => $totalQuoted,
            'currency'          => $currency,
            'pdf_path'          => $pdfPath,
            'pdf_original_name' => $pdfOriginalName,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Guardar precios por ítem
        foreach ($items as $item) {
            if (isset($itemPrices[$item->id]) && $itemPrices[$item->id] !== '') {
                $unitPrice = (float) $itemPrices[$item->id];
                $lineTotal = round($unitPrice * (float) $item->quantity, 2);
                $db->table('purchase_order_reply_items')->insert([
                    'reply_id'               => $replyId,
                    'purchase_order_item_id' => $item->id,
                    'product_name'           => $item->product_name,
                    'unit'                   => $item->unit,
                    'quantity'               => $item->quantity,
                    'unit_price_quoted'      => $unitPrice,
                    'line_total_quoted'      => $lineTotal,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
            }
        }

        return back()->with('success', 'Respuesta de ' . $request->input('supplier_name') . ' registrada.');
    }

    public function updateReply(Request $request, int $id, int $replyId)
    {
        $db = DB::connection('fuelcontrol');

        $request->validate([
            'item_prices'   => ['nullable', 'array'],
            'item_prices.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $itemPrices = $request->input('item_prices', []);
        $items      = $db->table('purchase_order_items')->where('purchase_order_id', $id)->orderBy('id')->get();

        // Recalcular total desde ítems
        $totalFromItems = null;
        if (!empty(array_filter($itemPrices, fn($v) => $v !== null && $v !== ''))) {
            $totalFromItems = 0;
            foreach ($items as $item) {
                $unitPrice = isset($itemPrices[$item->id]) && $itemPrices[$item->id] !== ''
                    ? (float) $itemPrices[$item->id] : 0;
                $totalFromItems += $unitPrice * (float) $item->quantity;
            }
        }

        $data = ['updated_at' => now()];

        if ($totalFromItems !== null) {
            $data['total_quoted'] = round($totalFromItems, 2);
        } elseif ($request->has('total_quoted')) {
            $val = $request->input('total_quoted');
            $data['total_quoted'] = ($val === '' || $val === null) ? null : (float) str_replace(['.', ','], ['', '.'], $val);
        }

        if ($request->has('notes')) {
            $data['notes'] = $request->input('notes') ?: null;
        }
        if ($request->has('currency')) {
            $data['currency'] = $request->input('currency');
        }

        $db->table('purchase_order_replies')
            ->where('id', $replyId)
            ->where('purchase_order_id', $id)
            ->update($data);

        // Actualizar/insertar precios por ítem
        foreach ($items as $item) {
            if (isset($itemPrices[$item->id]) && $itemPrices[$item->id] !== '') {
                $unitPrice = (float) $itemPrices[$item->id];
                $lineTotal = round($unitPrice * (float) $item->quantity, 2);
                $db->table('purchase_order_reply_items')->updateOrInsert(
                    ['reply_id' => $replyId, 'purchase_order_item_id' => $item->id],
                    [
                        'product_name'      => $item->product_name,
                        'unit'              => $item->unit,
                        'quantity'          => $item->quantity,
                        'unit_price_quoted' => $unitPrice,
                        'line_total_quoted' => $lineTotal,
                        'updated_at'        => now(),
                        'created_at'        => now(),
                    ]
                );
            } else {
                // Si se borró el precio, eliminar el item del reply
                $db->table('purchase_order_reply_items')
                    ->where('reply_id', $replyId)
                    ->where('purchase_order_item_id', $item->id)
                    ->delete();
            }
        }

        return back()->with('success', 'Respuesta actualizada.');
    }

    public function updateItem(Request $request, int $id, int $itemId)
    {
        $db = DB::connection('fuelcontrol');

        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'unit'         => ['nullable', 'string', 'max:30'],
            'quantity'     => ['required', 'numeric', 'gt:0'],
            'unit_price'   => ['required', 'numeric', 'gte:0'],
        ]);

        $item = $db->table('purchase_order_items')
            ->where('id', $itemId)
            ->where('purchase_order_id', $id)
            ->first();

        if (!$item) {
            abort(404);
        }

        $quantity  = (float) $validated['quantity'];
        $unitPrice = (float) $validated['unit_price'];
        $lineTotal = round($quantity * $unitPrice, 2);

        $db->table('purchase_order_items')
            ->where('id', $itemId)
            ->where('purchase_order_id', $id)
            ->update([
                'product_name' => trim($validated['product_name']),
                'unit'         => trim($validated['unit'] ?? '') ?: 'UN',
                'quantity'     => $quantity,
                'unit_price'   => $unitPrice,
                'line_total'   => $lineTotal,
                'updated_at'   => now(),
            ]);

        // Recalculate order total from all items
        $newTotal = $db->table('purchase_order_items')
            ->where('purchase_order_id', $id)
            ->sum('line_total');

        $db->table('purchase_orders')->where('id', $id)->update([
            'total'      => round((float) $newTotal, 2),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Ítem actualizado.');
    }

    public function deleteReply(int $id, int $replyId)
    {
        $db = DB::connection('fuelcontrol');
        $reply = $db->table('purchase_order_replies')
            ->where('id', $replyId)
            ->where('purchase_order_id', $id)
            ->first();

        if ($reply && $reply->pdf_path) {
            Storage::disk('public')->delete($reply->pdf_path);
        }

        $db->table('purchase_order_replies')
            ->where('id', $replyId)
            ->where('purchase_order_id', $id)
            ->delete();

        return back()->with('success', 'Respuesta eliminada.');
    }

    public function serveAttachment(int $replyId)
    {
        $db    = DB::connection('fuelcontrol');
        $reply = $db->table('purchase_order_replies')->where('id', $replyId)->first();

        if (!$reply || !$reply->pdf_path) {
            abort(404);
        }
        if (!Storage::disk('public')->exists($reply->pdf_path)) {
            abort(404, 'Archivo no encontrado en el servidor.');
        }

        $path = Storage::disk('public')->path($reply->pdf_path);
        $name = $reply->pdf_original_name ?? basename($reply->pdf_path);
        $mime = Storage::disk('public')->mimeType($reply->pdf_path) ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
        ]);
    }
}
