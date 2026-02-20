<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderController extends Controller
{
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
        $products = DB::connection('fuelcontrol')
            ->table('gmail_inventory_products')
            ->select('id', 'nombre', 'codigo', 'unidad', 'costo_promedio', 'is_active')
            ->where('is_active', 1)
            ->orderBy('nombre')
            ->get();

        return view('purchase_orders.create', compact('products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'in:CLP,USD,EUR'],
            'emails' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.save_as_inventory' => ['nullable', 'boolean'],
        ]);

        $emails = $this->normalizeEmails((string) ($data['emails'] ?? ''));

        $db = DB::connection('fuelcontrol');
        $products = $db->table('gmail_inventory_products')
            ->select('id', 'nombre', 'unidad', 'costo_promedio')
            ->get()
            ->keyBy('id');

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
                'save_as_inventory' => (bool) ($item['save_as_inventory'] ?? false),
            ];
        }

        $now = now();

        $orderId = $db->transaction(function () use ($db, $data, $cleanItems, $emails, $subtotal, $now) {
            $year = now()->format('Y');
            $lastId = (int) ($db->table('purchase_orders')->max('id') ?? 0) + 1;
            $orderNumber = 'OC-' . $year . '-' . str_pad((string) $lastId, 5, '0', STR_PAD_LEFT);

            $orderId = $db->table('purchase_orders')->insertGetId([
                'order_number' => $orderNumber,
                'supplier_name' => $data['supplier_name'],
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

                if ($line['is_custom'] === 1 && $line['save_as_inventory'] === true) {
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
}
