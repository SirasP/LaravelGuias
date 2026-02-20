<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderController extends Controller
{
    private const DEFAULT_NOTES_TEMPLATE = "Estimado/a {PROVEEDOR},\n\nJunto con saludar, enviamos orden de compra para su revisión y confirmación.\n\nPor favor indicar plazo de entrega y condiciones.\n\nSaludos cordiales.";

    public function index()
    {
        $orders = DB::connection('fuelcontrol')
            ->table('purchase_orders')
            ->orderByDesc('id')
            ->paginate(20);

        return view('purchase_orders.index', compact('orders'));
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
            'supplier_mode' => ['required', 'in:existing,new'],
            'supplier_id' => ['nullable', 'integer'],
            'supplier_new_name' => ['nullable', 'string', 'max:255'],
            'supplier_new_rut' => ['nullable', 'string', 'max:25'],
            'supplier_new_taxpayer_type' => ['nullable', 'string', 'max:255'],
            'supplier_new_activity_description' => ['nullable', 'string', 'max:255'],
            'supplier_new_address_line_1' => ['nullable', 'string', 'max:255'],
            'supplier_new_address_line_2' => ['nullable', 'string', 'max:255'],
            'supplier_new_comuna' => ['nullable', 'string', 'max:120'],
            'supplier_new_region' => ['nullable', 'string', 'max:120'],
            'supplier_new_postal_code' => ['nullable', 'string', 'max:30'],
            'supplier_new_country' => ['nullable', 'string', 'max:120'],
            'supplier_new_phone' => ['nullable', 'string', 'max:60'],
            'supplier_new_mobile' => ['nullable', 'string', 'max:60'],
            'supplier_new_website' => ['nullable', 'string', 'max:255'],
            'supplier_new_language' => ['nullable', 'string', 'max:30'],
            'supplier_new_emails' => ['nullable', 'string'],
            'currency' => ['required', 'in:CLP,USD,EUR'],
            'recipient_emails' => ['nullable', 'array'],
            'recipient_emails.*' => ['email'],
            'extra_emails' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ]);

        $db = DB::connection('fuelcontrol');

        $supplierMode = (string) ($data['supplier_mode'] ?? 'existing');
        $supplierId = null;
        $supplierName = '';
        $supplierEmails = [];

        if ($supplierMode === 'existing') {
            $supplierId = isset($data['supplier_id']) && $data['supplier_id'] !== '' ? (int) $data['supplier_id'] : null;
            if (!$supplierId) {
                return back()->withInput()->with('warning', 'Debes seleccionar un proveedor existente.');
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
            $supplierEmails = $db->table('purchase_order_supplier_emails')
                ->where('supplier_id', $supplierId)
                ->pluck('email')
                ->all();
        } else {
            $supplierName = trim((string) ($data['supplier_new_name'] ?? ''));
            if ($supplierName === '') {
                return back()->withInput()->with('warning', 'Debes ingresar el nombre del proveedor.');
            }
        }

        $selectedRecipientEmails = array_values(array_unique(array_filter(
            array_map('mb_strtolower', (array) ($data['recipient_emails'] ?? [])),
            fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL)
        )));

        $extraEmails = $this->normalizeEmails((string) ($data['extra_emails'] ?? ''));
        $emails = array_values(array_unique(array_merge($selectedRecipientEmails, $extraEmails)));

        if ($supplierMode === 'new') {
            $newSupplierEmails = $this->normalizeEmails((string) ($data['supplier_new_emails'] ?? ''));
            if (empty($emails)) {
                $emails = $newSupplierEmails;
            }
            $supplierEmails = $newSupplierEmails;
        }

        if ($supplierMode === 'existing' && empty($emails)) {
            $emails = array_values(array_unique($supplierEmails));
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

        $orderId = $db->transaction(function () use ($db, $data, $supplierMode, $supplierId, $supplierName, $supplierEmails, $cleanItems, $emails, $subtotal, $now) {
            $year = now()->format('Y');
            $lastId = (int) ($db->table('purchase_orders')->max('id') ?? 0) + 1;
            $orderNumber = 'OC-' . $year . '-' . str_pad((string) $lastId, 5, '0', STR_PAD_LEFT);

            $resolvedSupplierId = $supplierId;
            if ($supplierMode === 'new') {
                $resolvedSupplierId = $db->table('purchase_order_suppliers')->insertGetId([
                    'name' => $supplierName,
                    'rut' => $this->nullableString($data['supplier_new_rut'] ?? null),
                    'taxpayer_type' => $this->nullableString($data['supplier_new_taxpayer_type'] ?? null),
                    'activity_description' => $this->nullableString($data['supplier_new_activity_description'] ?? null),
                    'address_line_1' => $this->nullableString($data['supplier_new_address_line_1'] ?? null),
                    'address_line_2' => $this->nullableString($data['supplier_new_address_line_2'] ?? null),
                    'comuna' => $this->nullableString($data['supplier_new_comuna'] ?? null),
                    'region' => $this->nullableString($data['supplier_new_region'] ?? null),
                    'postal_code' => $this->nullableString($data['supplier_new_postal_code'] ?? null),
                    'country' => $this->nullableString($data['supplier_new_country'] ?? null) ?? 'Chile',
                    'phone' => $this->nullableString($data['supplier_new_phone'] ?? null),
                    'mobile' => $this->nullableString($data['supplier_new_mobile'] ?? null),
                    'website' => $this->nullableString($data['supplier_new_website'] ?? null),
                    'language' => $this->nullableString($data['supplier_new_language'] ?? null) ?? 'es_CL',
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach (array_values(array_unique($supplierEmails)) as $index => $email) {
                    $db->table('purchase_order_supplier_emails')->insert([
                        'supplier_id' => $resolvedSupplierId,
                        'email' => $email,
                        'is_primary' => $index === 0 ? 1 : 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $orderId = $db->table('purchase_orders')->insertGetId([
                'order_number' => $orderNumber,
                'supplier_id' => $resolvedSupplierId,
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

        return redirect()->route('purchase_orders.show', $orderId)->with('success', 'Orden de compra creada.');
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
        $recipients = $db->table('purchase_order_recipients')->where('purchase_order_id', $id)->orderBy('id')->pluck('email');

        return view('purchase_orders.show', compact('order', 'items', 'recipients'));
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

        $emails = $this->normalizeEmails((string) ($validated['emails'] ?? ''));
        if (empty($emails)) {
            $emails = $db->table('purchase_order_recipients')->where('purchase_order_id', $id)->pluck('email')->all();
        }

        if (empty($emails)) {
            return back()->with('warning', 'Debes ingresar al menos un correo destinatario.');
        }

        $subject = trim((string) ($validated['subject'] ?? ''));
        if ($subject === '') {
            $subject = 'Orden de compra ' . ($order->order_number ?? ('#' . $order->id));
        }

        $messageText = trim((string) ($validated['message'] ?? ''));

        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>'
                . '<td style="padding:6px;border:1px solid #ddd;">' . e($item->product_name) . '</td>'
                . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . number_format((float) $item->quantity, 4, ',', '.') . '</td>'
                . '<td style="padding:6px;border:1px solid #ddd;">' . e($item->unit) . '</td>'
                . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . number_format((float) $item->unit_price, 2, ',', '.') . '</td>'
                . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . number_format((float) $item->line_total, 2, ',', '.') . '</td>'
                . '</tr>';
        }

        $html = '<h2 style="margin:0 0 8px;">Orden de compra ' . e($order->order_number) . '</h2>'
            . '<p style="margin:0 0 6px;"><strong>Proveedor:</strong> ' . e($order->supplier_name) . '</p>'
            . '<p style="margin:0 0 6px;"><strong>Moneda:</strong> ' . e($order->currency) . '</p>'
            . ($messageText !== '' ? ('<p style="margin:8px 0 12px;">' . nl2br(e($messageText)) . '</p>') : '')
            . '<table style="border-collapse:collapse;width:100%;font-size:13px;">'
            . '<thead><tr>'
            . '<th style="padding:6px;border:1px solid #ddd;text-align:left;">Producto</th>'
            . '<th style="padding:6px;border:1px solid #ddd;text-align:right;">Cantidad</th>'
            . '<th style="padding:6px;border:1px solid #ddd;text-align:left;">UdM</th>'
            . '<th style="padding:6px;border:1px solid #ddd;text-align:right;">Precio</th>'
            . '<th style="padding:6px;border:1px solid #ddd;text-align:right;">Importe</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<p style="margin-top:12px;"><strong>Total:</strong> ' . e($order->currency) . ' ' . number_format((float) $order->total, 2, ',', '.') . '</p>';

        try {
            Mail::send([], [], function ($message) use ($emails, $subject, $html) {
                $message->to($emails)->subject($subject)->html($html);
            });
        } catch (\Throwable $e) {
            return back()->with('warning', 'No se pudo enviar el correo: ' . $e->getMessage());
        }

        $db->table('purchase_orders')->where('id', $id)->update([
            'status' => 'sent',
            'sent_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Orden enviada por correo.');
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
}
