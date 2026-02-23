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
                                <div x-data="{ editRow: false }">
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
                                        <div class="flex items-center gap-2 shrink-0">
                                            <p class="text-sm font-black tabular-nums text-emerald-700 dark:text-emerald-400">
                                                {{ number_format((float) $i->line_total, 2, ',', '.') }}
                                            </p>
                                            <button type="button" @click="editRow = !editRow"
                                                class="w-6 h-6 flex items-center justify-center rounded-md text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition"
                                                title="Editar ítem">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    {{-- Edit form (mobile) --}}
                                    <div x-show="editRow" x-cloak class="px-4 pb-3 bg-blue-50/60 dark:bg-blue-900/10">
                                        <form method="POST" action="{{ route('purchase_orders.update_item', [$order->id, $i->id]) }}" class="space-y-2">
                                            @csrf @method('PATCH')
                                            <div>
                                                <label class="f-label">Producto</label>
                                                <input type="text" name="product_name" value="{{ $i->product_name }}" class="f-input py-1.5 text-xs" required>
                                            </div>
                                            <div class="flex gap-2">
                                                <div class="w-20">
                                                    <label class="f-label">UdM</label>
                                                    <input type="text" name="unit" value="{{ $i->unit }}" class="f-input py-1.5 text-xs text-center" maxlength="10">
                                                </div>
                                                <div class="flex-1">
                                                    <label class="f-label">Cantidad</label>
                                                    <input type="number" name="quantity" value="{{ $i->quantity }}" step="0.0001" min="0.0001" class="f-input py-1.5 text-xs text-right" required>
                                                </div>
                                                <div class="flex-1">
                                                    <label class="f-label">Precio unit.</label>
                                                    <input type="number" name="unit_price" value="{{ $i->unit_price }}" step="1" min="0" class="f-input py-1.5 text-xs text-right" required>
                                                </div>
                                            </div>
                                            <div class="flex gap-2 pt-1">
                                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                    Guardar
                                                </button>
                                                <button type="button" @click="editRow = false" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
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
                                @php $rowNum = 0; @endphp
                                @foreach($items as $i)
                                    @php $rowNum++ @endphp
                                    <tbody x-data="{ editRow: false }">
                                        {{-- Display row --}}
                                        <tr>
                                            <td class="text-center"><span class="row-num">{{ $rowNum }}</span></td>
                                            <td class="font-semibold text-gray-800 dark:text-gray-200">{{ $i->product_name }}</td>
                                            <td>
                                                <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">{{ $i->unit }}</span>
                                            </td>
                                            <td class="text-right tabular-nums font-semibold">{{ number_format((float) $i->quantity, 4, ',', '.') }}</td>
                                            <td class="text-right tabular-nums text-gray-600 dark:text-gray-400">{{ number_format((float) $i->unit_price, 2, ',', '.') }}</td>
                                            <td class="text-right tabular-nums amt-col pr-5 text-sm">
                                                <div class="flex items-center justify-end gap-2">
                                                    <span>{{ number_format((float) $i->line_total, 2, ',', '.') }}</span>
                                                    <button type="button" @click="editRow = true" x-show="!editRow"
                                                        class="w-5 h-5 flex items-center justify-center rounded-md text-gray-400 hover:text-blue-500 hover:bg-white dark:hover:bg-blue-900/20 transition"
                                                        title="Editar ítem">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        {{-- Edit row --}}
                                        <tr x-show="editRow" x-cloak class="bg-blue-50/70 dark:bg-blue-900/10">
                                            <td colspan="6" class="px-3 py-3">
                                                <form method="POST"
                                                      action="{{ route('purchase_orders.update_item', [$order->id, $i->id]) }}"
                                                      class="flex flex-wrap gap-2 items-end">
                                                    @csrf @method('PATCH')
                                                    <div class="flex-1 min-w-40">
                                                        <label class="f-label">Producto</label>
                                                        <input type="text" name="product_name" value="{{ $i->product_name }}"
                                                               class="f-input py-1.5 text-xs" required>
                                                    </div>
                                                    <div class="w-20">
                                                        <label class="f-label">UdM</label>
                                                        <input type="text" name="unit" value="{{ $i->unit }}"
                                                               class="f-input py-1.5 text-xs text-center" maxlength="10">
                                                    </div>
                                                    <div class="w-32">
                                                        <label class="f-label">Cantidad</label>
                                                        <input type="number" name="quantity" value="{{ $i->quantity }}"
                                                               step="0.0001" min="0.0001"
                                                               class="f-input py-1.5 text-xs text-right" required>
                                                    </div>
                                                    <div class="w-36">
                                                        <label class="f-label">Precio unit.</label>
                                                        <input type="number" name="unit_price" value="{{ $i->unit_price }}"
                                                               step="1" min="0"
                                                               class="f-input py-1.5 text-xs text-right" required>
                                                    </div>
                                                    <div class="flex gap-1.5 pb-0.5">
                                                        <button type="submit"
                                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                            Guardar
                                                        </button>
                                                        <button type="button" @click="editRow = false"
                                                            class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 dark:border-gray-700 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                                            Cancelar
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    </tbody>
                                @endforeach
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

                    {{-- ── Timeline de respuestas ── --}}
                    <div class="panel au d3" x-data="{ addOpen: {{ $errors->any() ? 'true' : 'false' }} }">

                        {{-- Header --}}
                        <div class="px-4 sm:px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Respuestas de proveedores</h3>
                                @if($replies->count() > 0)
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-black
                                                 bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                        {{ $replies->count() }}
                                    </span>
                                @endif
                            </div>
                            <button type="button" @click="addOpen = !addOpen"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl transition"
                                :class="addOpen
                                    ? 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200'
                                    : 'bg-blue-600 hover:bg-blue-700 text-white'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                     x-show="!addOpen">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span x-text="addOpen ? '✕ Cerrar' : 'Registrar respuesta'"></span>
                            </button>
                        </div>

                        {{-- Timeline --}}
                        <div class="px-4 sm:px-5 py-4">
                            <div class="relative">

                                {{-- Línea vertical de conexión --}}
                                @if($replies->count() > 0)
                                    <div class="absolute left-[15px] top-8 bottom-8 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                                @endif

                                {{-- Evento: cotización enviada --}}
                                <div class="flex gap-3 mb-4">
                                    <div class="shrink-0 w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/40
                                                flex items-center justify-center z-10">
                                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0 pt-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Cotización enviada</span>
                                            @if($order->sent_at)
                                                <span class="text-[11px] text-gray-400">{{ \Carbon\Carbon::parse($order->sent_at)->format('d/m/Y H:i') }}</span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                            Para:
                                            @php $destNames = $recipients->pluck('supplier_name')->filter()->unique(); @endphp
                                            {{ $destNames->count() > 0 ? $destNames->join(', ') : $order->supplier_name }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Respuestas --}}
                                @forelse($replies as $reply)
                                    @php
                                        $isEmail = ($reply->source ?? 'manual') === 'email';
                                        $ext     = $reply->pdf_path ? strtolower(pathinfo($reply->pdf_path, PATHINFO_EXTENSION)) : null;
                                        $isImage = in_array($ext, ['png','jpg','jpeg','webp','gif']);
                                        $isPdf   = $ext === 'pdf';
                                        $fileUrl = $reply->pdf_path ? route('purchase_orders.attachment', $reply->id) : null;
                                        $notesLen = mb_strlen($reply->notes ?? '');
                                    @endphp
                                    <div class="flex gap-3 mb-4" x-data="{ expanded: false, editing: false }">
                                        {{-- Avatar --}}
                                        <div class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center z-10
                                                    {{ $isEmail ? 'bg-sky-100 dark:bg-sky-900/40' : 'bg-blue-100 dark:bg-blue-900/40' }}">
                                            @if($isEmail)
                                                <svg class="w-4 h-4 text-sky-500 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            @endif
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="rounded-2xl rounded-tl-sm p-3 shadow-sm border
                                                        {{ $isEmail
                                                            ? 'bg-sky-50 border-sky-200 dark:bg-sky-900/10 dark:border-sky-800'
                                                            : 'bg-blue-50 border-blue-200 dark:bg-blue-900/10 dark:border-blue-800' }}">

                                                {{-- Cabecera --}}
                                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-1.5 flex-wrap">
                                                            <span class="text-xs font-black {{ $isEmail ? 'text-sky-800 dark:text-sky-300' : 'text-blue-800 dark:text-blue-300' }}">
                                                                {{ $reply->supplier_name }}
                                                            </span>
                                                            @if($isEmail)
                                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full
                                                                             bg-sky-100 text-sky-600 border border-sky-200
                                                                             dark:bg-sky-900/30 dark:text-sky-400 dark:border-sky-700
                                                                             text-[9px] font-bold tracking-wide">
                                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                                    </svg>
                                                                    Correo automático
                                                                </span>
                                                            @endif
                                                            <span class="text-[10px] text-gray-400">
                                                                {{ \Carbon\Carbon::parse($reply->created_at)->format('d/m/Y H:i') }}
                                                            </span>
                                                        </div>
                                                        {{-- Remitente (solo en correos automáticos) --}}
                                                        @if($isEmail && $reply->sender_email)
                                                            <p class="text-[10px] text-sky-600/70 dark:text-sky-400/70 mt-0.5 truncate">
                                                                {{ $reply->sender_email }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-center gap-1.5 shrink-0">
                                                        @if($reply->total_quoted)
                                                            <span x-show="!editing"
                                                                  class="text-sm font-black tabular-nums text-emerald-700 dark:text-emerald-400">
                                                                {{ $reply->currency }} {{ number_format((float)$reply->total_quoted, 0, ',', '.') }}
                                                            </span>
                                                        @endif
                                                        {{-- Botón editar --}}
                                                        <button type="button" @click="editing = true" x-show="!editing"
                                                            class="w-5 h-5 flex items-center justify-center rounded-md
                                                                   text-gray-400 hover:text-blue-500 hover:bg-blue-50
                                                                   dark:hover:bg-blue-900/20 transition"
                                                            title="Editar precio / notas">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </button>
                                                        {{-- Botón eliminar --}}
                                                        <form method="POST"
                                                            action="{{ route('purchase_orders.delete_reply', [$order->id, $reply->id]) }}"
                                                            @submit.prevent="openConfirm({
                                                                title: '¿Eliminar respuesta?',
                                                                message: 'Esta acción no se puede deshacer.',
                                                                confirmLabel: 'Eliminar',
                                                                type: 'danger',
                                                                callback: () => $el.submit()
                                                            })"
                                                            class="inline" x-show="!editing">
                                                            @csrf @method('DELETE')
                                                            <button type="submit"
                                                                class="w-5 h-5 flex items-center justify-center rounded-md
                                                                       text-gray-400 hover:text-rose-500 hover:bg-rose-50
                                                                       dark:hover:bg-rose-900/20 transition text-xs">×</button>
                                                        </form>
                                                    </div>
                                                </div>

                                                {{-- Formulario edición inline --}}
                                                <div x-show="editing" x-cloak
                                                     class="mt-2 pt-2 border-t border-gray-200/60 dark:border-gray-700/60">
                                                    @php $rItems = $replyItemsAll->get($reply->id, collect()); @endphp
                                                    <form method="POST"
                                                          action="{{ route('purchase_orders.update_reply', [$order->id, $reply->id]) }}">
                                                        @csrf @method('PATCH')
                                                        <div class="space-y-2">

                                                            {{-- PDF viewer inline (solo cuando hay adjunto PDF) --}}
                                                            @if($fileUrl && $isPdf)
                                                            <div>
                                                                <p class="f-label flex items-center gap-1">
                                                                    <svg class="w-3 h-3 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    Cotización del proveedor
                                                                </p>
                                                                <iframe src="{{ $fileUrl }}"
                                                                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white"
                                                                        style="height:460px;"
                                                                        title="{{ $reply->pdf_original_name ?? 'PDF cotización' }}">
                                                                    <p class="text-xs text-gray-500 p-2">
                                                                        Tu navegador no puede mostrar el PDF.
                                                                        <a href="{{ $fileUrl }}" target="_blank" class="text-blue-600 underline">Abrir en nueva pestaña</a>
                                                                    </p>
                                                                </iframe>
                                                            </div>
                                                            @elseif($fileUrl && $isImage)
                                                            <div>
                                                                <p class="f-label">Cotización del proveedor</p>
                                                                <img src="{{ $fileUrl }}" alt="{{ $reply->pdf_original_name }}"
                                                                     class="w-full max-h-72 object-contain rounded-xl border border-gray-200 dark:border-gray-700 bg-white">
                                                            </div>
                                                            @endif

                                                            <div class="flex gap-2">
                                                                <div class="w-20 shrink-0">
                                                                    <label class="f-label">Moneda</label>
                                                                    <input type="text" name="currency"
                                                                           value="{{ $reply->currency }}"
                                                                           class="f-input text-center text-xs" maxlength="5">
                                                                </div>
                                                            </div>
                                                            {{-- Precios por ítem --}}
                                                            <div>
                                                                <label class="f-label">Precios por producto</label>
                                                                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                                                                    <table class="w-full text-xs">
                                                                        <thead class="bg-gray-50 dark:bg-gray-800/60">
                                                                            <tr>
                                                                                <th class="text-left px-2 py-1.5 font-semibold text-gray-400">Producto</th>
                                                                                <th class="text-right px-2 py-1.5 font-semibold text-gray-400 w-20">Cant.</th>
                                                                                <th class="text-right px-2 py-1.5 font-semibold text-gray-400 w-28">Precio unit.</th>
                                                                                <th class="text-right px-2 py-1.5 font-semibold text-gray-400 w-24">Subtotal</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                                            @foreach($items as $item)
                                                                            @php $ri = $rItems->firstWhere('purchase_order_item_id', $item->id); @endphp
                                                                            <tr>
                                                                                <td class="px-2 py-1.5 text-gray-700 dark:text-gray-300 font-medium">{{ $item->product_name }}</td>
                                                                                <td class="px-2 py-1.5 text-right text-gray-400 tabular-nums">{{ number_format((float)$item->quantity, 2, ',', '.') }}</td>
                                                                                <td class="px-2 py-1.5 text-right">
                                                                                    <input type="number" name="item_prices[{{ $item->id }}]"
                                                                                           step="1" min="0"
                                                                                           class="f-input text-right tabular-nums py-1 px-2 text-xs"
                                                                                           placeholder="0"
                                                                                           oninput="calcReplyTotal('{{ $reply->id }}')"
                                                                                           data-qty="{{ (float)$item->quantity }}"
                                                                                           value="{{ $ri ? $ri->unit_price_quoted : '' }}">
                                                                                </td>
                                                                                <td class="px-2 py-1.5 text-right tabular-nums text-emerald-700 dark:text-emerald-400 font-bold"
                                                                                    id="sub-{{ $reply->id }}-{{ $item->id }}">
                                                                                    {{ $ri ? number_format((float)$ri->line_total_quoted, 0, ',', '.') : '—' }}
                                                                                </td>
                                                                            </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                        <tfoot>
                                                                            <tr class="bg-emerald-50 dark:bg-emerald-900/10">
                                                                                <td colspan="3" class="px-2 py-1.5 font-black text-gray-700 dark:text-gray-200 uppercase tracking-wide text-[10px]">Total</td>
                                                                                <td class="px-2 py-1.5 text-right tabular-nums font-black text-emerald-700 dark:text-emerald-400 text-sm"
                                                                                    id="total-{{ $reply->id }}">
                                                                                    {{ $reply->total_quoted ? number_format((float)$reply->total_quoted, 0, ',', '.') : '—' }}
                                                                                </td>
                                                                            </tr>
                                                                        </tfoot>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="f-label">Notas</label>
                                                                <textarea name="notes" rows="3" class="f-input" style="resize:vertical">{{ $reply->notes }}</textarea>
                                                            </div>
                                                            <div class="flex gap-2">
                                                                <button type="submit"
                                                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold rounded-lg
                                                                           bg-blue-600 hover:bg-blue-700 text-white transition">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                                    </svg>
                                                                    Guardar
                                                                </button>
                                                                <button type="button" @click="editing = false"
                                                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200
                                                                           dark:border-gray-700 text-gray-500 hover:bg-gray-100
                                                                           dark:hover:bg-gray-800 transition">
                                                                    Cancelar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>

                                                {{-- Cuerpo del correo (colapsable si es largo) --}}
                                                @if($reply->notes)
                                                    @if($notesLen > 300)
                                                        <div class="relative">
                                                            <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line"
                                                               :class="expanded ? '' : 'line-clamp-4'">{{ $reply->notes }}</p>
                                                            <button type="button"
                                                                @click="expanded = !expanded"
                                                                class="mt-1 text-[10px] font-semibold {{ $isEmail ? 'text-sky-600 hover:text-sky-800' : 'text-blue-600 hover:text-blue-800' }} transition">
                                                                <span x-text="expanded ? '▲ Ver menos' : '▼ Ver más ({{ $notesLen }} caracteres)'"></span>
                                                            </button>
                                                        </div>
                                                    @else
                                                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ $reply->notes }}</p>
                                                    @endif
                                                @endif

                                                {{-- Adjunto --}}
                                                @if($fileUrl)
                                                    <div class="mt-2.5">
                                                        @if($isImage)
                                                            {{-- Miniatura de imagen --}}
                                                            <a href="{{ $fileUrl }}" target="_blank" class="block">
                                                                <img src="{{ $fileUrl }}"
                                                                     alt="{{ $reply->pdf_original_name }}"
                                                                     class="max-h-48 rounded-lg border border-gray-200 dark:border-gray-700 object-contain bg-white shadow-sm hover:opacity-90 transition">
                                                            </a>
                                                            <p class="text-[10px] text-gray-400 mt-1">{{ $reply->pdf_original_name }}</p>
                                                        @else
                                                            {{-- PDF u otro archivo --}}
                                                            <div class="flex items-center gap-2">
                                                                <a href="{{ $fileUrl }}" target="_blank"
                                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg
                                                                           bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                                                                           text-[11px] font-semibold text-gray-700 dark:text-gray-300
                                                                           hover:border-sky-400 hover:text-sky-600 transition shadow-sm">
                                                                    <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    {{ $reply->pdf_original_name ?? 'Ver PDF' }}
                                                                </a>
                                                                <a href="{{ $fileUrl }}" download="{{ $reply->pdf_original_name }}"
                                                                    class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg
                                                                           bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                                                                           text-[11px] text-gray-500 dark:text-gray-400
                                                                           hover:border-emerald-400 hover:text-emerald-600 transition shadow-sm"
                                                                    title="Descargar">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                                    </svg>
                                                                </a>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="ml-11 text-xs text-gray-400 italic py-2">
                                        Aún no hay respuestas registradas. Cuando un proveedor te envíe su cotización, regístrala aquí.
                                    </div>
                                @endforelse

                            </div>
                        </div>

                        {{-- Tabla comparativa (≥ 2 respuestas con precio) --}}
                        @php $repliesWithPrice = $replies->whereNotNull('total_quoted')->sortBy('total_quoted'); @endphp
                        @if($repliesWithPrice->count() >= 2)
                            @php $minPrice = $repliesWithPrice->min('total_quoted'); @endphp
                            <div class="mx-4 sm:mx-5 mb-4 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div class="px-3 py-2 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Comparación de precios</p>
                                </div>
                                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($repliesWithPrice as $r)
                                        @php $isMin = (float)$r->total_quoted === (float)$minPrice; @endphp
                                        <div class="px-3 py-2.5 flex items-center justify-between gap-3
                                                    {{ $isMin ? 'bg-emerald-50 dark:bg-emerald-900/10' : '' }}">
                                            <div class="flex items-center gap-2 min-w-0">
                                                @if($isMin)
                                                    <span class="shrink-0 text-emerald-500" title="Más económico">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                    </span>
                                                @endif
                                                <span class="text-xs font-bold {{ $isMin ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-300' }} truncate">
                                                    {{ $r->supplier_name }}
                                                </span>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <p class="text-sm font-black tabular-nums {{ $isMin ? 'text-emerald-700 dark:text-emerald-400' : 'text-gray-700 dark:text-gray-300' }}">
                                                    {{ $r->currency }} {{ number_format((float)$r->total_quoted, 0, ',', '.') }}
                                                </p>
                                                @if(!$isMin)
                                                    <p class="text-[10px] text-rose-500 font-semibold">
                                                        +{{ number_format((float)$r->total_quoted - (float)$minPrice, 0, ',', '.') }} más
                                                    </p>
                                                @else
                                                    <p class="text-[10px] text-emerald-600 font-semibold">Más económico</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Formulario: Registrar respuesta --}}
                        <div x-show="addOpen" x-cloak
                             class="border-t border-gray-100 dark:border-gray-800 px-4 sm:px-5 py-4"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0">

                            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Nueva respuesta</p>

                            @if($errors->any())
                                <div class="mb-3 text-xs text-rose-600 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl px-3 py-2">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <form method="POST"
                                  action="{{ route('purchase_orders.store_reply', $order->id) }}"
                                  enctype="multipart/form-data"
                                  class="space-y-3">
                                @csrf

                                {{-- Proveedor --}}
                                <div>
                                    <label class="f-label">Proveedor *</label>
                                    @php
                                        $replySupplierNames = $recipients->pluck('supplier_name')->filter()->unique()->values();
                                    @endphp
                                    @if($replySupplierNames->count() > 0)
                                        <select name="supplier_name" class="f-input" required>
                                            <option value="">— Selecciona proveedor —</option>
                                            @foreach($replySupplierNames as $sn)
                                                <option value="{{ $sn }}" {{ old('supplier_name') === $sn ? 'selected' : '' }}>{{ $sn }}</option>
                                            @endforeach
                                            <option value="__otro__">Otro (escribir manualmente)</option>
                                        </select>
                                    @else
                                        <input type="text" name="supplier_name" class="f-input"
                                               placeholder="Nombre del proveedor" value="{{ old('supplier_name') }}" required>
                                    @endif
                                </div>

                                {{-- Precios por ítem --}}
                                <div>
                                    <label class="f-label">Precios cotizados por producto</label>
                                    <p class="text-[10px] text-gray-400 mb-1.5">Ingresa el precio unitario que te ofreció el proveedor para cada ítem. El total se calcula automáticamente.</p>
                                    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                                        <table class="w-full text-xs">
                                            <thead class="bg-gray-50 dark:bg-gray-800/60">
                                                <tr>
                                                    <th class="text-left px-3 py-2 font-semibold text-gray-500 dark:text-gray-400">Producto</th>
                                                    <th class="text-right px-3 py-2 font-semibold text-gray-500 dark:text-gray-400 w-20">Cant.</th>
                                                    <th class="text-right px-3 py-2 font-semibold text-gray-500 dark:text-gray-400 w-32">Precio unit.</th>
                                                    <th class="text-right px-3 py-2 font-semibold text-gray-500 dark:text-gray-400 w-28">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800" id="reply-items-body-new">
                                                @foreach($items as $item)
                                                <tr>
                                                    <td class="px-3 py-2 text-gray-800 dark:text-gray-200 font-medium">{{ $item->product_name }}</td>
                                                    <td class="px-3 py-2 text-right text-gray-500 tabular-nums">{{ number_format((float)$item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                                                    <td class="px-3 py-2 text-right">
                                                        <input type="number" name="item_prices[{{ $item->id }}]"
                                                               step="1" min="0"
                                                               class="f-input text-right tabular-nums py-1 px-2 text-xs"
                                                               placeholder="0"
                                                               oninput="calcReplyTotal('new')"
                                                               data-qty="{{ (float)$item->quantity }}"
                                                               value="{{ old('item_prices.'.$item->id) }}">
                                                    </td>
                                                    <td class="px-3 py-2 text-right tabular-nums text-emerald-700 dark:text-emerald-400 font-bold" id="sub-new-{{ $item->id }}">—</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr class="bg-emerald-50 dark:bg-emerald-900/10">
                                                    <td colspan="3" class="px-3 py-2 text-xs font-black text-gray-700 dark:text-gray-200 uppercase tracking-wide">Total {{ $order->currency }}</td>
                                                    <td class="px-3 py-2 text-right tabular-nums font-black text-emerald-700 dark:text-emerald-400 text-sm" id="total-new">—</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                {{-- Total manual (oculto si se usan ítems) --}}
                                <div id="total-manual-wrap-new">
                                    <label class="f-label">Total cotizado manual ({{ $order->currency }})</label>
                                    <input type="number" name="total_quoted" step="1" min="0"
                                           class="f-input" placeholder="Se calcula automático desde los ítems"
                                           value="{{ old('total_quoted') }}" id="total-quoted-input-new">
                                    <p class="text-[10px] text-gray-400 mt-0.5">Solo si no ingresas precios por ítem</p>
                                </div>

                                {{-- Notas --}}
                                <div>
                                    <label class="f-label">Mensaje / notas</label>
                                    <textarea name="notes" rows="3" class="f-input" style="resize:vertical"
                                              placeholder="Copia el texto que te envió el proveedor, o escribe un resumen...">{{ old('notes') }}</textarea>
                                </div>

                                {{-- PDF --}}
                                <div>
                                    <label class="f-label">Adjunto (PDF / imagen)</label>
                                    <label class="flex items-center gap-3 px-3 py-2.5 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700
                                                  hover:border-blue-400 dark:hover:border-blue-600 cursor-pointer transition group">
                                        <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 group-hover:text-blue-600 transition">
                                                Subir cotización del proveedor
                                            </p>
                                            <p class="text-[10px] text-gray-400">PDF, JPG o PNG — máx. 20 MB</p>
                                        </div>
                                        <input type="file" name="pdf" accept=".pdf,.jpg,.jpeg,.png" class="sr-only"
                                               onchange="this.closest('label').querySelector('p').textContent = this.files[0]?.name ?? 'Subir cotización del proveedor'">
                                    </label>
                                </div>

                                <div class="flex gap-2 pt-1">
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-xl
                                               bg-blue-600 hover:bg-blue-700 text-white transition shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Guardar respuesta
                                    </button>
                                    <button type="button" @click="addOpen = false"
                                        class="px-4 py-2 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700
                                               text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                        Cancelar
                                    </button>
                                </div>
                            </form>
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

                            @php
                                $repliesWithItems = $replies->filter(fn($r) =>
                                    $replyItemsAll->has($r->id) && $replyItemsAll->get($r->id)->isNotEmpty()
                                );
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

                                        {{-- Selector: aplicar precios de la respuesta --}}
                                        @if($repliesWithItems->count() > 0)
                                        <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">
                                                Aplicar precios cotizados a la OC
                                            </p>
                                            <select name="apply_reply_id"
                                                class="f-input text-xs py-1.5">
                                                <option value="">— No aplicar precios —</option>
                                                @foreach($repliesWithItems as $rr)
                                                <option value="{{ $rr->id }}">
                                                    {{ $rr->supplier_name }}
                                                    @if($rr->total_quoted)
                                                        — {{ $rr->currency }} {{ number_format((float)$rr->total_quoted, 0, ',', '.') }}
                                                    @endif
                                                </option>
                                                @endforeach
                                            </select>
                                            <p class="text-[10px] text-blue-600 dark:text-blue-400 mt-1">
                                                Los precios unitarios de la OC se actualizarán con los del proveedor elegido.
                                            </p>
                                        </div>
                                        @endif

                                        <button type="button"
                                            class="w-full flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition mt-1"
                                            :disabled="!chosen"
                                            :class="!chosen ? 'opacity-50 cursor-not-allowed' : ''"
                                            @click="if(!chosen) return; openConfirm({
                                                title: '¿Confirmar orden de compra?',
                                                message: 'Se enviará la OC al proveedor seleccionado y se notificará por correo.',
                                                confirmLabel: 'Crear OC',
                                                type: 'confirm',
                                                callback: () => $el.closest('form').submit()
                                            })">
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
                                    <button type="button"
                                        class="w-full flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition"
                                        @click="openConfirm({
                                            title: '¿Confirmar orden de compra?',
                                            message: 'Se enviará la OC a {{ addslashes($order->supplier_name) }} por correo.',
                                            confirmLabel: 'Crear OC',
                                            type: 'confirm',
                                            callback: () => $el.closest('form').submit()
                                        })">
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
{{-- ── MODAL DE CONFIRMACIÓN (Alpine.js) ── --}}
<div x-data="{
        show: false,
        title: '',
        message: '',
        confirmLabel: 'Confirmar',
        type: 'danger',
        _cb: null,
    }"
    x-on:confirm-dialog.window="
        title        = $event.detail.title        ?? '¿Estás seguro?';
        message      = $event.detail.message      ?? '';
        confirmLabel = $event.detail.confirmLabel ?? 'Confirmar';
        type         = $event.detail.type         ?? 'danger';
        _cb          = $event.detail.callback     ?? null;
        show         = true;
    "
    x-show="show" x-cloak
    @keydown.escape.window="show = false"
    class="fixed inset-0 z-[200] flex items-end sm:items-center justify-center p-4"
    style="background:rgba(15,23,42,.55);"
    @click.self="show = false">

    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-800 w-full max-w-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4">

        <div class="p-5">
            <div class="flex items-start gap-3">
                {{-- Icono según tipo --}}
                <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
                     :class="{
                         'bg-rose-100 dark:bg-rose-900/30': type === 'danger',
                         'bg-blue-100 dark:bg-blue-900/30': type === 'confirm',
                         'bg-amber-100 dark:bg-amber-900/30': type === 'warning',
                     }">
                    <template x-if="type === 'danger'">
                        <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </template>
                    <template x-if="type === 'confirm'">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </template>
                    <template x-if="type === 'warning'">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </template>
                </div>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-snug" x-text="title"></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed" x-text="message" x-show="message"></p>
                </div>
            </div>
        </div>

        <div class="px-5 pb-5 flex gap-2 justify-end">
            <button type="button" @click="show = false"
                class="px-4 py-2 text-xs font-semibold rounded-xl border border-gray-200 dark:border-gray-700
                       text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                Cancelar
            </button>
            <button type="button"
                @click="show = false; if (_cb) _cb()"
                class="px-4 py-2 text-xs font-bold rounded-xl text-white transition"
                :class="{
                    'bg-rose-600 hover:bg-rose-700': type === 'danger',
                    'bg-blue-600 hover:bg-blue-700': type === 'confirm',
                    'bg-amber-600 hover:bg-amber-700': type === 'warning',
                }"
                x-text="confirmLabel">
            </button>
        </div>
    </div>
</div>

<script>
function openConfirm(options) {
    window.dispatchEvent(new CustomEvent('confirm-dialog', { detail: options }));
}

function calcReplyTotal(replyKey) {
    // replyKey = 'new' para el form de nueva respuesta, o el reply.id para edición
    const prefix = replyKey === 'new' ? 'new' : replyKey;
    const inputs = document.querySelectorAll(
        `input[oninput="calcReplyTotal('${replyKey}')"]`
    );
    let total = 0;
    inputs.forEach(function(input) {
        const qty = parseFloat(input.getAttribute('data-qty') || 0);
        const price = parseFloat(input.value) || 0;
        const sub   = qty * price;
        total += sub;
        // Extraer item id del name: item_prices[{id}]
        const match = input.name.match(/\[(\d+)\]/);
        if (match) {
            const itemId = match[1];
            const subCell = document.getElementById('sub-' + prefix + '-' + itemId);
            if (subCell) {
                subCell.textContent = sub > 0 ? Math.round(sub).toLocaleString('es-CL') : '—';
            }
        }
    });
    const totalCell = document.getElementById('total-' + prefix);
    if (totalCell) {
        totalCell.textContent = total > 0 ? Math.round(total).toLocaleString('es-CL') : '—';
    }
    // También llenar el input total_quoted oculto si existe
    const totalInput = document.getElementById('total-quoted-input-new');
    if (totalInput && replyKey === 'new') {
        totalInput.value = total > 0 ? Math.round(total) : '';
    }
}
// Inicializar totales al cargar (por si hay old() values)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id^="total-"]').forEach(function(el) {
        const replyKey = el.id.replace('total-', '');
        if (replyKey) calcReplyTotal(replyKey);
    });
});
</script>
</x-app-layout>
