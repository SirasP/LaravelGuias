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
    </style>

    <div class="page-bg" x-data="exitsPage('{{ route('gmail.inventory.api.exit.detail', 0) }}')">
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
                <div class="kpi-card">
                    <p class="text-xs text-gray-400 mb-1">Más retirado este mes</p>
                    @if ($topProducto)
                        <p class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">{{ $topProducto->nombre }}</p>
                        <p class="text-xs text-gray-400">{{ number_format((float) $topProducto->total_qty, 2, ',', '.') }} unidades</p>
                    @else
                        <p class="text-sm text-gray-400">Sin datos</p>
                    @endif
                </div>
            </div>

            @if ($movements->count() > 0)
                <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="w-8 px-4 py-3"></th>
                                <th class="text-left text-xs font-semibold text-gray-400 px-3 py-3">Fecha</th>
                                <th class="text-left text-xs font-semibold text-gray-400 px-3 py-3">Destinatario</th>
                                <th class="text-right text-xs font-semibold text-gray-400 px-3 py-3">Productos</th>
                                <th class="text-right text-xs font-semibold text-gray-400 px-3 py-3">Costo total</th>
                                <th class="text-left text-xs font-semibold text-gray-400 px-3 py-3 hidden md:table-cell">Registrado por</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($movements as $m)
                                {{-- Main row --}}
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors cursor-pointer"
                                    @click="toggleDetail({{ $m->id }})">
                                    <td class="px-4 py-3 text-center">
                                        <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200 mx-auto"
                                            :class="openId === {{ $m->id }} ? 'rotate-90' : ''"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ \Carbon\Carbon::parse($m->ocurrio_el)->format('d/m/Y') }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            {{ \Carbon\Carbon::parse($m->created_at)->format('H:i') }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100 truncate max-w-[200px]">
                                            {{ $m->destinatario ?? '—' }}
                                        </p>
                                        @if ($m->notas)
                                            <p class="text-xs text-gray-400 truncate max-w-[200px]">{{ $m->notas }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                                            {{ $lineCounts[$m->id] ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                        $ {{ number_format((float) $m->costo_total, 2, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-3 hidden md:table-cell">
                                        <p class="text-xs text-gray-400">
                                            @if ($m->usuario_id)
                                                {{ optional(\App\Models\User::find($m->usuario_id))->name ?? 'Usuario #' . $m->usuario_id }}
                                            @else
                                                —
                                            @endif
                                        </p>
                                    </td>
                                </tr>

                                {{-- Expandable detail row --}}
                                <tr x-show="openId === {{ $m->id }}"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0"
                                    x-transition:enter-end="opacity-100"
                                    style="display:none">
                                    <td colspan="6" class="px-6 pb-4 pt-0 bg-gray-50/60 dark:bg-gray-800/30">

                                        {{-- Loading state --}}
                                        <div x-show="detailLoading" class="py-4 text-center text-xs text-gray-400 animate-pulse">
                                            Cargando detalle...
                                        </div>

                                        {{-- Detail table --}}
                                        <div x-show="!detailLoading && detailLines.length > 0">
                                            <table class="w-full text-xs mt-1">
                                                <thead>
                                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                                        <th class="text-left font-semibold text-gray-400 py-1.5 pr-3">Producto</th>
                                                        <th class="text-left font-semibold text-gray-400 py-1.5 pr-3 hidden sm:table-cell">Código</th>
                                                        <th class="text-right font-semibold text-gray-400 py-1.5 pr-3">Lote ingresado</th>
                                                        <th class="text-right font-semibold text-gray-400 py-1.5 pr-3">Cantidad</th>
                                                        <th class="text-right font-semibold text-gray-400 py-1.5 pr-3">Costo unit.</th>
                                                        <th class="text-right font-semibold text-gray-400 py-1.5">Costo total</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                    <template x-for="(line, li) in detailLines" :key="li">
                                                        <tr>
                                                            <td class="py-1.5 pr-3">
                                                                <p class="font-semibold text-gray-800 dark:text-gray-200" x-text="line.producto"></p>
                                                                <p class="text-gray-400" x-text="line.unidad"></p>
                                                            </td>
                                                            <td class="py-1.5 pr-3 text-gray-500 hidden sm:table-cell" x-text="line.codigo ?? '—'"></td>
                                                            <td class="py-1.5 pr-3 text-right text-amber-600 dark:text-amber-400 font-medium"
                                                                x-text="line.lote_fecha ? new Date(line.lote_fecha).toLocaleDateString('es-CL') : '—'">
                                                            </td>
                                                            <td class="py-1.5 pr-3 text-right font-semibold text-gray-800 dark:text-gray-200"
                                                                x-text="parseFloat(line.cantidad).toLocaleString('es-CL', {minimumFractionDigits:4,maximumFractionDigits:4})">
                                                            </td>
                                                            <td class="py-1.5 pr-3 text-right text-gray-600 dark:text-gray-400"
                                                                x-text="'$ ' + parseFloat(line.costo_unitario).toLocaleString('es-CL', {minimumFractionDigits:2,maximumFractionDigits:2})">
                                                            </td>
                                                            <td class="py-1.5 text-right font-semibold text-gray-800 dark:text-gray-200"
                                                                x-text="'$ ' + parseFloat(line.costo_total).toLocaleString('es-CL', {minimumFractionDigits:2,maximumFractionDigits:2})">
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div x-show="!detailLoading && detailLines.length === 0" class="py-3 text-xs text-gray-400 text-center">
                                            Sin líneas de detalle.
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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

    <script>
        function exitsPage(detailBaseUrl) {
            return {
                openId: null,
                detailLines: [],
                detailLoading: false,

                async toggleDetail(id) {
                    if (this.openId === id) {
                        this.openId = null;
                        this.detailLines = [];
                        return;
                    }
                    this.openId = id;
                    this.detailLines = [];
                    this.detailLoading = true;
                    try {
                        const url = detailBaseUrl.replace('/0/', '/' + id + '/');
                        const res = await fetch(url);
                        this.detailLines = await res.json();
                    } catch (e) {
                        this.detailLines = [];
                    } finally {
                        this.detailLoading = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
