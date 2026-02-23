<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 min-w-0 text-xs">
                <a href="{{ route('purchase_orders.index') }}"
                    class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium truncate">
                    Cotizaciones
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300 font-mono truncate">{{ $order->order_number }}</span>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <a href="{{ route('purchase_orders.create') }}" class="hdr-btn hdr-emerald">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span class="hidden sm:inline">Nueva cotización</span>
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        [x-cloak] { display:none !important }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(8px) }
            to   { opacity:1; transform:translateY(0) }
        }
        .au { animation:fadeUp .35s ease both }
        .d1 { animation-delay:.06s }
        .d2 { animation-delay:.12s }
        .d3 { animation-delay:.18s }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .sidebar-section { padding:16px 18px; border-bottom:1px solid #f1f5f9 }
        .dark .sidebar-section { border-bottom-color:#1e2a3b }
        .sidebar-section:last-child { border-bottom:none }

        .section-label {
            font-size:10px; font-weight:700; text-transform:uppercase;
            letter-spacing:.07em; color:#94a3b8; margin-bottom:10px;
        }

        .info-row { display:flex; justify-content:space-between; align-items:baseline; gap:8px; padding:4px 0; font-size:13px }
        .info-k { color:#64748b; font-weight:600; white-space:nowrap; flex-shrink:0 }
        .dark .info-k { color:#94a3b8 }
        .info-v { color:#1e293b; font-weight:700; text-align:right; min-width:0; word-break:break-all }
        .dark .info-v { color:#e2e8f0 }

        .chip { display:inline-flex; align-items:center; border-radius:999px; padding:3px 10px; font-size:11px; font-weight:700 }

        /* Table */
        .dt { width:100%; border-collapse:collapse; font-size:13px; min-width:680px }
        .dt thead { position:sticky; top:0; z-index:1 }
        .dt thead tr { background:#f8fafc }
        .dark .dt thead tr { background:#0f1623 }
        .dt th {
            padding:9px 14px; text-align:left; font-size:10px; font-weight:700;
            letter-spacing:.07em; text-transform:uppercase; color:#94a3b8; white-space:nowrap;
            box-shadow:inset 0 -2px 0 #e2e8f0;
        }
        .dark .dt th { box-shadow:inset 0 -2px 0 #1e2a3b }
        .dt td { padding:11px 14px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr:hover td { background:#f5fffb }
        .dark .dt tbody tr:hover td { background:rgba(16,185,129,.02) }
        .amt-col {
            background:rgba(16,185,129,.04); border-left:1px solid #ecfdf5;
            font-weight:800; color:#065f46;
        }
        .dark .amt-col { background:rgba(16,185,129,.07); border-left-color:#1e2a3b; color:#6ee7b7 }
        .dt tbody tr:hover .amt-col { background:rgba(16,185,129,.09) }
        .row-num {
            display:inline-flex; width:20px; height:20px; align-items:center; justify-content:center;
            border-radius:999px; background:#f1f5f9; color:#94a3b8; font-size:10px; font-weight:700;
        }
        .dark .row-num { background:#1e2a3b; color:#64748b }

        /* Form inputs */
        .f-label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:5px }
        .dark .f-label { color:#94a3b8 }
        .f-input {
            width:100%; border-radius:10px; border:1px solid #e2e8f0; background:#fff;
            padding:8px 11px; font-size:13px; color:#111827; outline:none;
            transition:border-color .15s, box-shadow .15s;
        }
        .f-input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
        .dark .f-input:focus { border-color:#10b981 }

        /* Header buttons */
        .hdr-btn {
            display:inline-flex; align-items:center; gap:5px;
            padding:7px 11px; border-radius:10px; font-size:12px; font-weight:700;
            border:none; cursor:pointer; transition:all .13s; text-decoration:none; white-space:nowrap;
        }
        .hdr-emerald { background:#059669; color:#fff }
        .hdr-emerald:hover { background:#047857 }
        .hdr-gray { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0 }
        .hdr-gray:hover { background:#e2e8f0 }
        .dark .hdr-gray { background:#1e2a3b; color:#94a3b8; border-color:#273244 }
        .dark .hdr-gray:hover { background:#273244 }
        .hdr-btn:active { transform:scale(.97) }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5">

            @if(session('success'))
                <div class="flex items-center gap-3 mb-4 px-4 py-3 rounded-xl text-sm font-medium
                        bg-emerald-50 border border-emerald-200 text-emerald-700
                        dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400 au d1">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('warning'))
                <div class="flex items-center gap-3 mb-4 px-4 py-3 rounded-xl text-sm font-medium
                        bg-amber-50 border border-amber-200 text-amber-700
                        dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-400 au d1">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    {{ session('warning') }}
                </div>
            @endif

            <div class="flex flex-col xl:flex-row gap-4 items-start">

                {{-- ── COLUMNA IZQUIERDA ── --}}
                <div class="w-full xl:flex-1 min-w-0 space-y-4">

                    {{-- Cabecera de la orden --}}
                    <div class="panel au d1">
                        <div class="px-5 py-4">
                            @php
                                $headerSuppliers = $recipients->pluck('supplier_name')->filter()->unique()->values();
                                $statusClass = match($order->status) {
                                    'sent'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                    'order' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                };
                                $statusLabel = match($order->status) {
                                    'sent'  => 'Cotización enviada',
                                    'order' => 'Orden de compra',
                                    default => 'Pendiente',
                                };
                            @endphp
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">{{ $order->currency }}</span>
                                        <span class="chip {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </div>
                                    <h1 class="text-2xl sm:text-4xl font-black text-gray-900 dark:text-gray-100 tracking-tight font-mono leading-none">
                                        {{ $order->order_number }}
                                    </h1>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1.5 font-medium">
                                        {{ $headerSuppliers->count() > 0 ? $headerSuppliers->join(' · ') : $order->supplier_name }}
                                    </p>
                                </div>
                                {{-- Total: siempre visible --}}
                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total</p>
                                    <p class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100 tabular-nums">
                                        {{ number_format((float) $order->total, 2, ',', '.') }}
                                    </p>
                                </div>
                            </div>
                            @if($order->notes)
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">{{ $order->notes }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tabla de productos --}}
                    <div class="panel au d2">
                        <div class="px-4 sm:px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Líneas de productos</h3>
                            <span class="text-xs text-gray-400">{{ count($items) }} ítem{{ count($items) !== 1 ? 's' : '' }}</span>
                        </div>

                        {{-- ══ MÓVIL: cards (< sm) ══════════════════════════════ --}}
                        <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                            @php $rowNum = 0; @endphp
                            @foreach($items as $i)
                                @php $rowNum++ @endphp
                                <div class="px-4 py-3 flex items-start justify-between gap-3">
                                    <div class="flex items-start gap-2.5 min-w-0">
                                        <span class="row-num shrink-0 mt-0.5">{{ $rowNum }}</span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 leading-tight">{{ $i->product_name }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">
                                                <span class="inline-flex px-1.5 py-px rounded text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-500">{{ $i->unit }}</span>
                                                &nbsp;{{ number_format((float) $i->quantity, 2, ',', '.') }}
                                                &nbsp;×&nbsp;{{ number_format((float) $i->unit_price, 2, ',', '.') }}
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-sm font-black tabular-nums text-emerald-700 dark:text-emerald-400 shrink-0">
                                        {{ number_format((float) $i->line_total, 2, ',', '.') }}
                                    </p>
                                </div>
                            @endforeach
                        </div>

                        {{-- ══ DESKTOP: tabla (≥ sm) ══════════════════════════════ --}}
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="dt">
                                <thead>
                                    <tr>
                                        <th class="w-10 text-center">#</th>
                                        <th>Producto</th>
                                        <th>UdM</th>
                                        <th class="text-right">Cantidad</th>
                                        <th class="text-right">Precio unit.</th>
                                        <th class="text-right pr-5">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $rowNum = 0; @endphp
                                    @foreach($items as $i)
                                        @php $rowNum++ @endphp
                                        <tr>
                                            <td class="text-center"><span class="row-num">{{ $rowNum }}</span></td>
                                            <td class="font-semibold text-gray-800 dark:text-gray-200">{{ $i->product_name }}</td>
                                            <td>
                                                <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">{{ $i->unit }}</span>
                                            </td>
                                            <td class="text-right tabular-nums font-semibold">{{ number_format((float) $i->quantity, 4, ',', '.') }}</td>
                                            <td class="text-right tabular-nums text-gray-600 dark:text-gray-400">{{ number_format((float) $i->unit_price, 2, ',', '.') }}</td>
                                            <td class="text-right tabular-nums amt-col pr-5 text-sm">{{ number_format((float) $i->line_total, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t-2 border-gray-100 dark:border-gray-800 px-4 sm:px-5 py-4 bg-gray-50/60 dark:bg-gray-900/20">
                            <div class="flex justify-end">
                                <div class="flex justify-between items-baseline gap-6 sm:gap-10">
                                    <span class="text-xs font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">Total {{ $order->currency }}</span>
                                    <span class="text-xl sm:text-2xl font-black tabular-nums text-emerald-600 dark:text-emerald-400">
                                        {{ number_format((float) $order->total, 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>{{-- /left --}}

                {{-- ── SIDEBAR ── --}}
                <div class="w-full xl:w-72 2xl:w-80 shrink-0 space-y-4 au d2">

                    {{-- Info de la orden --}}
                    <div class="panel">
                        <div class="sidebar-section">
                            @php
                                $allSupplierNames = $recipients->pluck('supplier_name')->filter()->unique()->values();
                            @endphp
                            <p class="section-label">{{ $allSupplierNames->count() > 1 ? 'Proveedores' : 'Proveedor' }}</p>
                            @if($allSupplierNames->count() > 0)
                                <div class="space-y-1">
                                    @foreach($allSupplierNames as $sn)
                                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ $sn }}</p>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-tight">{{ $order->supplier_name }}</p>
                            @endif
                        </div>
                        <div class="sidebar-section">
                            <p class="section-label">Detalles</p>
                            <div class="space-y-1.5">
                                <div class="info-row">
                                    <span class="info-k">Número</span>
                                    <span class="info-v text-xs font-mono">{{ $order->order_number }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-k">Moneda</span>
                                    <span class="info-v">{{ $order->currency }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-k">Estado</span>
                                    <span class="chip {{ $statusClass }}">{{ $statusLabel }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-k">Creación</span>
                                    <span class="info-v text-xs">{{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y H:i') }}</span>
                                </div>
                                @if($order->sent_at)
                                    <div class="info-row">
                                        <span class="info-k">Enviada</span>
                                        <span class="info-v text-xs text-emerald-600 dark:text-emerald-400">{{ \Carbon\Carbon::parse($order->sent_at)->format('d/m/Y H:i') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if($recipients->count())
                            <div class="sidebar-section">
                                <p class="section-label">Destinatarios</p>
                                <div class="space-y-2">
                                    @foreach($recipients as $r)
                                        <div>
                                            <p class="text-[11px] font-bold text-gray-700 dark:text-gray-300">{{ $r->supplier_name }}</p>
                                            <p class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 truncate">{{ $r->email }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Crear orden de compra --}}
                    @if($order->status === 'sent')
                    <div class="panel">
                        <div class="sidebar-section">
                            <p class="section-label">Crear orden de compra</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 leading-relaxed">
                                Selecciona el proveedor con quien decidiste comprar:
                            </p>

                            @php
                                $uniqueSuppliers = $recipients
                                    ->filter(fn($r) => $r->supplier_name)
                                    ->unique('supplier_name')
                                    ->values();
                            @endphp

                            <form method="POST" action="{{ route('purchase_orders.confirm_order', $order->id) }}" class="space-y-3">
                                @csrf

                                @if($uniqueSuppliers->count() > 0)
                                    {{-- Tarjetas de selección --}}
                                    <div class="space-y-1.5" x-data="{ chosen: {{ $uniqueSuppliers->count() === 1 ? $uniqueSuppliers->first()->supplier_id ?? 0 : 'null' }} }">
                                        @foreach($uniqueSuppliers as $rs)
                                        @php
                                            $spId = DB::connection('fuelcontrol')
                                                ->table('purchase_order_supplier_emails')
                                                ->where('email', $rs->email)
                                                ->value('supplier_id') ?? $order->supplier_id;
                                        @endphp
                                        <label class="flex items-start gap-2.5 p-2.5 rounded-xl border cursor-pointer transition
                                            hover:border-blue-300 dark:hover:border-blue-700"
                                            :class="{{ $spId }} === chosen
                                                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                                : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/30'">
                                            <input type="radio" name="chosen_supplier_id"
                                                value="{{ $spId }}"
                                                x-model="chosen"
                                                class="mt-0.5 accent-blue-600 shrink-0"
                                                {{ $uniqueSuppliers->count() === 1 ? 'checked' : '' }}>
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-gray-800 dark:text-gray-200 leading-tight">{{ $rs->supplier_name }}</p>
                                                <p class="text-[11px] text-gray-400 font-mono truncate">{{ $rs->email }}</p>
                                            </div>
                                        </label>
                                        @endforeach

                                        <input type="hidden" name="chosen_supplier_id" :value="chosen" x-show="false">

                                        <button type="submit"
                                            class="w-full flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition mt-1"
                                            :disabled="!chosen"
                                            :class="!chosen ? 'opacity-50 cursor-not-allowed' : ''"
                                            onclick="return confirm('¿Confirmas la orden de compra con el proveedor seleccionado?')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Crear orden de compra
                                        </button>
                                    </div>
                                @else
                                    {{-- Sin destinatarios guardados: usar el proveedor principal --}}
                                    <input type="hidden" name="chosen_supplier_id" value="{{ $order->supplier_id }}">
                                    <p class="text-xs font-bold text-gray-800 dark:text-gray-200 mb-2">{{ $order->supplier_name }}</p>
                                    <button type="submit"
                                        class="w-full flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition"
                                        onclick="return confirm('¿Confirmas la orden de compra con {{ addslashes($order->supplier_name) }}?')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Crear orden de compra
                                    </button>
                                @endif
                            </form>
                        </div>
                    </div>
                    @endif

                </div>{{-- /sidebar --}}

            </div>{{-- /layout --}}

        </div>
    </div>
</x-app-layout>
