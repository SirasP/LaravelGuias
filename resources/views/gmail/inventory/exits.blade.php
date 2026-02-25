<x-app-layout>
    <x-slot name="header">
        <div class="w-full grid grid-cols-1 lg:grid-cols-[auto,1fr,auto] items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Salidas de inventario</h2>
                    <p class="text-xs text-gray-400 mt-0.5 truncate">Historial FIFO</p>
                </div>
            </div>

            <form method="GET" id="filter-form" class="hidden lg:block w-full">
                <div class="flex gap-2 items-center">
                    <input type="text" name="q" value="{{ $q }}" class="f-input flex-1"
                        placeholder="Buscar destinatario...">
                    <input type="date" name="desde" value="{{ $desde }}" class="f-input w-36" title="Desde">
                    <input type="date" name="hasta" value="{{ $hasta }}" class="f-input w-36" title="Hasta">
                    <button type="submit"
                        class="shrink-0 px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">Filtrar</button>
                    @if($q || $desde || $hasta)
                        <a href="{{ route('gmail.inventory.exits') }}"
                            class="shrink-0 px-3 py-2 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition">
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>

            <div class="hidden lg:flex items-center justify-end gap-2">
                <a href="{{ route('gmail.inventory.exits.export', array_filter(['q' => $q, 'desde' => $desde, 'hasta' => $hasta])) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                           bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700
                           text-gray-700 dark:text-gray-300 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    CSV
                </a>
                <a href="{{ route('gmail.inventory.exit.create') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                    </svg>
                    Nueva Salida
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .f-input {
            border-radius:12px; border:1px solid #e2e8f0; background:#fff;
            padding:9px 12px; font-size:13px; color:#111827; outline:none;
        }
        .f-input:focus { border-color:#f43f5e; box-shadow:0 0 0 3px rgba(244,63,94,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
        .kpi-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:16px;
            padding:16px 20px; flex:1;
        }
        .dark .kpi-card { background:#161c2c; border-color:#1e2a3b }
        .exit-card {
            background:#fff; border:1px solid #e2e8f0; border-radius:16px;
            overflow:hidden;
        }
        .dark .exit-card { background:#161c2c; border-color:#1e2a3b }
        .badge-venta  { background:#d1fae5; color:#065f46 }
        .badge-epp    { background:#dbeafe; color:#1e40af }
        .badge-salida { background:#f1f5f9; color:#475569 }
        .dark .badge-venta  { background:#064e3b; color:#6ee7b7 }
        .dark .badge-epp    { background:#1e3a5f; color:#93c5fd }
        .dark .badge-salida { background:#1e2a3b; color:#94a3b8 }
        .sell-input {
            border-radius:10px; border:1px solid #e2e8f0; background:#fff;
            padding:8px 12px; font-size:13px; color:#111827; outline:none; width:100%;
        }
        .sell-input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.12) }
        .dark .sell-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            {{-- Mobile filter --}}
            <form method="GET" class="lg:hidden space-y-2">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input flex-1"
                        placeholder="Buscar destinatario...">
                    <button type="submit"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">Buscar</button>
                </div>
                <div class="flex gap-2">
                    <input type="date" name="desde" value="{{ $desde }}" class="f-input flex-1">
                    <input type="date" name="hasta" value="{{ $hasta }}" class="f-input flex-1">
                </div>
            </form>

            @if (session('success'))
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">{{ session('success') }}</p>
                </div>
            @endif

            {{-- KPI Cards --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="kpi-card">
                    <p class="text-xs text-gray-400 mb-1">Salidas este mes</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $kpiMes->total_salidas ?? 0 }}
                    </p>
                </div>
                <div class="kpi-card">
                    <p class="text-xs text-gray-400 mb-1">Costo total este mes</p>
                    <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">
                        $ {{ number_format((float) ($kpiMes->costo_total ?? 0), 0, ',', '.') }}
                    </p>
                </div>
                @if ((float) ($kpiMes->precio_venta_total ?? 0) > 0)
                <div class="kpi-card">
                    <p class="text-xs text-gray-400 mb-1">Ventas registradas este mes</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        $ {{ number_format((float) ($kpiMes->precio_venta_total ?? 0), 0, ',', '.') }}
                    </p>
                </div>
                @else
                <div class="kpi-card">
                    <p class="text-xs text-gray-400 mb-1">Más retirado este mes</p>
                    @if ($topProducto)
                        <p class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">{{ $topProducto->nombre }}</p>
                        <p class="text-xs text-gray-400">{{ number_format((float) $topProducto->total_qty, 2, ',', '.') }} unidades</p>
                    @else
                        <p class="text-sm text-gray-400">Sin datos</p>
                    @endif
                </div>
                @endif
            </div>

            {{-- Cards grid --}}
            @if ($movements->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($movements as $m)
                        @php
                            $cardLines   = $lines->get($m->id, collect());
                            $tipoSalida  = $m->tipo_salida ?? 'Salida';
                            $precioVenta = $m->precio_venta;
                            $costoTotal  = (float) $m->costo_total;
                            $margen      = ($precioVenta !== null && $costoTotal > 0)
                                ? round((((float) $precioVenta - $costoTotal) / $costoTotal) * 100, 1)
                                : null;
                            $sellUrl     = route('gmail.inventory.exits.sell', $m->id);
                        @endphp

                        <div class="exit-card"
                             x-data="{
                                showSell: false,
                                pvInput: '',
                                pvSaved: {{ $precioVenta !== null ? $precioVenta : 'null' }},
                                costo: {{ $costoTotal }},
                                tipoSalida: '{{ $tipoSalida }}',
                                saving: false,
                                err: '',
                                get margen() {
                                    const pv = parseFloat(this.pvSaved);
                                    if (!pv || !this.costo) return null;
                                    return (((pv - this.costo) / this.costo) * 100).toFixed(1);
                                },
                                fmt(n) {
                                    return '$ ' + parseFloat(n).toLocaleString('es-CL', {minimumFractionDigits:0, maximumFractionDigits:0});
                                },
                                async submitSell() {
                                    this.err = '';
                                    const pv = parseFloat(this.pvInput.replace(/\./g,'').replace(',','.'));
                                    if (!pv || pv <= 0) { this.err = 'Ingresa un precio válido.'; return; }
                                    this.saving = true;
                                    try {
                                        const res = await fetch('{{ $sellUrl }}', {
                                            method: 'POST',
                                            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                            body: JSON.stringify({ precio_venta: pv })
                                        });
                                        const data = await res.json();
                                        if (data.ok) {
                                            this.pvSaved = data.precio_venta;
                                            this.tipoSalida = 'Venta';
                                            this.showSell = false;
                                        } else {
                                            this.err = data.error ?? 'Error al guardar.';
                                        }
                                    } catch(e) {
                                        this.err = 'Error de conexión.';
                                    } finally {
                                        this.saving = false;
                                    }
                                }
                             }">

                            {{-- Card header --}}
                            <div class="px-4 pt-4 pb-3 flex items-start justify-between gap-2 border-b border-gray-100 dark:border-gray-800">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap mb-1">
                                        {{-- Tipo badge --}}
                                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full uppercase tracking-wide"
                                              :class="{
                                                'badge-venta':  tipoSalida === 'Venta',
                                                'badge-epp':    tipoSalida === 'EPP',
                                                'badge-salida': tipoSalida === 'Salida' || !tipoSalida
                                              }"
                                              x-text="tipoSalida || 'Salida'">
                                        </span>
                                        <span class="text-[11px] text-gray-400">#{{ $m->id }}</span>
                                    </div>
                                    <p class="font-bold text-sm text-gray-900 dark:text-gray-100 truncate">
                                        {{ $m->destinatario ?? '—' }}
                                    </p>
                                    @if ($m->notas)
                                        <p class="text-xs text-gray-400 truncate mt-0.5">{{ $m->notas }}</p>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                        {{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }}
                                    </p>
                                    <p class="text-[11px] text-gray-400">
                                        {{ \Carbon\Carbon::parse($m->created_at)->format('H:i') }}
                                    </p>
                                    @if ($m->usuario_id)
                                        <p class="text-[11px] text-gray-400 mt-0.5 truncate max-w-[120px]">
                                            {{ optional(\App\Models\User::find($m->usuario_id))->name ?? 'Usuario #'.$m->usuario_id }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Product lines --}}
                            <div class="px-4 py-2">
                                @if ($cardLines->isEmpty())
                                    <p class="text-xs text-gray-400 text-center py-2">Sin líneas de detalle</p>
                                @else
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                                <th class="text-left text-gray-400 font-semibold py-1.5 pr-2">Producto</th>
                                                <th class="text-right text-gray-400 font-semibold py-1.5 pr-2">Cant.</th>
                                                <th class="text-right text-gray-400 font-semibold py-1.5 pr-2">C. Unit.</th>
                                                <th class="text-right text-gray-400 font-semibold py-1.5">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                                            @foreach ($cardLines as $line)
                                                <tr>
                                                    <td class="py-1.5 pr-2">
                                                        <p class="font-semibold text-gray-800 dark:text-gray-200 leading-tight">{{ $line->producto }}</p>
                                                        <p class="text-gray-400">{{ $line->unidad }}</p>
                                                    </td>
                                                    <td class="py-1.5 pr-2 text-right font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                                        {{ number_format((float) $line->cantidad, 2, ',', '.') }}
                                                    </td>
                                                    <td class="py-1.5 pr-2 text-right text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                        $ {{ number_format((float) $line->costo_unitario, 2, ',', '.') }}
                                                    </td>
                                                    <td class="py-1.5 text-right font-semibold text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                                        $ {{ number_format((float) $line->costo_total, 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>

                            {{-- Card footer --}}
                            <div class="px-4 pb-4 pt-2 border-t border-gray-100 dark:border-gray-800 space-y-2">
                                {{-- Costs row --}}
                                <div class="flex items-center justify-between flex-wrap gap-x-3 gap-y-1">
                                    <div>
                                        <p class="text-[10px] text-gray-400 uppercase tracking-wide">Costo</p>
                                        <p class="text-sm font-bold text-rose-600 dark:text-rose-400">
                                            $ {{ number_format($costoTotal, 0, ',', '.') }}
                                        </p>
                                    </div>

                                    {{-- Precio venta (reactive) --}}
                                    <template x-if="pvSaved !== null">
                                        <div class="text-right">
                                            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Venta</p>
                                            <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400"
                                               x-text="fmt(pvSaved)"></p>
                                        </div>
                                    </template>

                                    {{-- Margen --}}
                                    <template x-if="pvSaved !== null && margen !== null">
                                        <div class="text-right">
                                            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Margen</p>
                                            <p class="text-sm font-bold"
                                               :class="parseFloat(margen) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500'"
                                               x-text="margen + '%'"></p>
                                        </div>
                                    </template>
                                </div>

                                {{-- Sell button --}}
                                <template x-if="!showSell">
                                    <button @click="showSell = true; pvInput = ''"
                                        class="w-full flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                                               border border-dashed transition"
                                        :class="pvSaved !== null
                                            ? 'border-emerald-300 dark:border-emerald-700 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20'
                                            : 'border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/40'">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span x-text="pvSaved !== null ? 'Editar precio de venta' : 'Registrar precio de venta'"></span>
                                    </button>
                                </template>

                                {{-- Inline sell form --}}
                                <template x-if="showSell">
                                    <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-3 space-y-2">
                                        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">Precio de venta total</p>
                                        <div class="flex gap-2">
                                            <input type="text" inputmode="numeric" x-model="pvInput"
                                                class="sell-input" placeholder="$ 0"
                                                @keydown.enter.prevent="submitSell()"
                                                @keydown.escape="showSell = false">
                                            <button @click="submitSell()" :disabled="saving"
                                                class="shrink-0 px-3 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition disabled:opacity-50">
                                                <span x-show="!saving">Guardar</span>
                                                <span x-show="saving">...</span>
                                            </button>
                                            <button @click="showSell = false"
                                                class="shrink-0 px-2 py-2 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                                                ✕
                                            </button>
                                        </div>
                                        <p x-show="err" x-text="err" class="text-xs text-rose-600 dark:text-rose-400"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div>{{ $movements->links() }}</div>
            @else
                <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center">
                    <svg class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No hay salidas registradas</p>
                    <p class="text-xs text-gray-400 mt-1 mb-4">
                        @if($q || $desde || $hasta)
                            No se encontraron resultados para el filtro aplicado.
                        @else
                            Las salidas aparecerán aquí al registrar entregas de stock.
                        @endif
                    </p>
                    <a href="{{ route('gmail.inventory.exit.create') }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white transition">
                        Registrar primera salida
                    </a>
                </div>
            @endif

            {{-- FAB mobile --}}
            <a href="{{ route('gmail.inventory.exit.create') }}"
                class="fixed right-5 bottom-5 z-50 lg:hidden w-14 h-14 rounded-full inline-flex items-center justify-center
                       bg-rose-600 hover:bg-rose-700 text-white shadow-xl transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14m-7-7h14" />
                </svg>
            </a>
        </div>
    </div>
</x-app-layout>
