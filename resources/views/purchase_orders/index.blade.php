<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-emerald-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Cotizaciones</h2>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $orders->total() }} cotizaciones</p>
                </div>
            </div>
            <a href="{{ route('purchase_orders.create') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nueva cotización
            </a>
        </div>
    </x-slot>

    <style>
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(8px) }
            to   { opacity:1; transform:translateY(0) }
        }
        .au { animation:fadeUp .35s ease both }
        .d1 { animation-delay:.06s }
        .d2 { animation-delay:.12s }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead { position:sticky; top:0; z-index:1 }
        .dt thead tr { background:#f8fafc }
        .dark .dt thead tr { background:#0f1623 }
        .dt th {
            padding:9px 14px; text-align:left; font-size:10px; font-weight:700;
            letter-spacing:.07em; text-transform:uppercase; color:#94a3b8; white-space:nowrap;
            box-shadow:inset 0 -2px 0 #e2e8f0;
        }
        .dark .dt th { box-shadow:inset 0 -2px 0 #1e2a3b }
        .dt td { padding:12px 14px; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr { cursor:pointer }
        .dt tbody tr:hover td { background:#f5fffb }
        .dark .dt tbody tr:hover td { background:rgba(16,185,129,.025) }
    </style>

    <div class="page-bg" x-data="{ filter: 'all' }">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            @if(session('success'))
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium
                        bg-emerald-50 border border-emerald-200 text-emerald-700
                        dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400 au d1">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            <div class="panel au d1">
                {{-- Toolbar --}}
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button @click="filter = 'all'"
                            class="px-3 py-1.5 text-xs font-semibold rounded-xl transition"
                            :class="filter === 'all'
                                ? 'bg-emerald-600 text-white'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'">
                            Todas
                        </button>
                        <button @click="filter = 'sent'"
                            class="px-3 py-1.5 text-xs font-semibold rounded-xl transition"
                            :class="filter === 'sent'
                                ? 'bg-emerald-600 text-white'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'">
                            Enviadas
                        </button>
                        <button @click="filter = 'order'"
                            class="px-3 py-1.5 text-xs font-semibold rounded-xl transition"
                            :class="filter === 'order'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'">
                            Órdenes
                        </button>
                    </div>
                    <span class="text-xs text-gray-400">{{ $orders->total() }} en total</span>
                </div>

                @if($orders->count() > 0)

                    {{-- ══ MÓVIL: lista de tarjetas (< sm) ════════════════════════ --}}
                    <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($orders as $o)
                            @php
                                $names = $suppliersByOrder->get($o->id, collect());
                                $badgeClass = match($o->status) {
                                    'sent'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                    'order' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                };
                                $badgeLabel = match($o->status) {
                                    'sent'  => 'Enviada',
                                    'order' => 'Orden de compra',
                                    default => 'Pendiente',
                                };
                            @endphp
                            <a href="{{ route('purchase_orders.show', $o->id) }}"
                               x-show="filter === 'all' || filter === '{{ $o->status }}'"
                               class="flex items-start justify-between gap-3 px-4 py-3.5
                                      hover:bg-gray-50 dark:hover:bg-white/[.02] transition active:bg-gray-100">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-black font-mono text-sm text-gray-900 dark:text-gray-100 tracking-tight">{{ $o->order_number }}</span>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                    </div>
                                    @if($names->count() > 0)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            {{ $names->join(' · ') }}
                                        </p>
                                    @elseif($o->supplier_name)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $o->supplier_name }}</p>
                                    @endif
                                    <p class="text-[11px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($o->created_at)->format('d/m/Y') }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-0.5">{{ $o->currency }}</p>
                                    <p class="text-sm font-black tabular-nums text-gray-900 dark:text-gray-100">
                                        {{ number_format((float) $o->total, 0, ',', '.') }}
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    {{-- ══ DESKTOP: tabla (≥ sm) ══════════════════════════════════ --}}
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="dt">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Proveedor</th>
                                    <th>Moneda</th>
                                    <th class="text-right">Total</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $o)
                                    @php
                                        $names = $suppliersByOrder->get($o->id, collect());
                                        $badgeClass = match($o->status) {
                                            'sent'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                            'order' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                            default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                        };
                                        $badgeLabel = match($o->status) {
                                            'sent'  => 'Cotización enviada',
                                            'order' => 'Orden de compra',
                                            default => 'Pendiente',
                                        };
                                    @endphp
                                    <tr x-show="filter === 'all' || filter === '{{ $o->status }}'"
                                        onclick="window.location='{{ route('purchase_orders.show', $o->id) }}'">
                                        <td>
                                            <span class="font-black text-gray-900 dark:text-gray-100 font-mono tracking-tight">{{ $o->order_number }}</span>
                                        </td>
                                        <td>
                                            @if($names->count() > 0)
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($names as $sn)
                                                        <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">{{ $sn }}</span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $o->supplier_name }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">{{ $o->currency }}</span>
                                        </td>
                                        <td class="text-right">
                                            <span class="font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format((float) $o->total, 2, ',', '.') }}</span>
                                        </td>
                                        <td>
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                        </td>
                                        <td class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($o->created_at)->format('d/m/Y H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                @else
                    <div class="flex flex-col items-center justify-center py-16 gap-3 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Aún no hay cotizaciones</p>
                        <p class="text-xs text-gray-400">Las cotizaciones aparecerán aquí una vez creadas.</p>
                        <a href="{{ route('purchase_orders.create') }}"
                            class="mt-1 inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Crear primera cotización
                        </a>
                    </div>
                @endif
            </div>

            <div class="au d2">{{ $orders->links() }}</div>

        </div>
    </div>
</x-app-layout>
