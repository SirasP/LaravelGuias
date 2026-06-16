<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-1.5 min-w-0 text-xs">
            <a href="{{ route('purchase_orders.index') }}"
               class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium">Cotizaciones</a>
            <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
            @if($order)
                <a href="{{ route('purchase_orders.show', $order->id) }}"
                   class="text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium font-mono">{{ $order->order_number }}</a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            @endif
            <span class="font-bold text-gray-700 dark:text-gray-300">Recepción #{{ $recepcion->id }}</span>
        </div>
    </x-slot>

    @php
        $estadoClass = match($recepcion->estado) {
            'CONFIRMADA' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
            'ANULADA'    => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
            default      => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        };
        $fmt = fn($n) => $n === null ? '—' : rtrim(rtrim(number_format((float)$n, 4, '.', ''), '0'), '.');
    @endphp

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        @if(session('success'))
            <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">{{ session('error') }}</div>
        @endif

        {{-- Cabecera --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-bold {{ $estadoClass }}">{{ $recepcion->estado }}</span>
                        @if($recepcion->fecha_recepcion)
                            <span class="text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($recepcion->fecha_recepcion)->format('d/m/Y') }}</span>
                        @endif
                    </div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100">Recepción #{{ $recepcion->id }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $recepcion->proveedor_nombre ?? '—' }}</p>
                    @if($recepcion->notas)<p class="text-xs text-gray-400 mt-1">{{ $recepcion->notas }}</p>@endif
                </div>
                @if($recepcion->estado === 'BORRADOR')
                    <form method="POST" action="{{ route('purchase_orders.receptions.confirm', $recepcion->id) }}"
                          onsubmit="return confirm('Confirmar recepción e ingresar el stock? Esta acción mueve inventario.');">
                        @csrf
                        <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm whitespace-nowrap">
                            Confirmar e ingresar stock
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Dos columnas: líneas (izq) · conciliación + factura (der) --}}
        <div class="flex flex-col xl:flex-row gap-4 items-start">

        {{-- IZQUIERDA: Líneas --}}
        <div class="w-full xl:flex-1 min-w-0 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Líneas recibidas</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-left font-bold">Producto</th>
                            <th class="px-4 py-2 text-right font-bold">Pedido</th>
                            <th class="px-4 py-2 text-right font-bold">Recibido</th>
                            <th class="px-4 py-2 text-right font-bold">Dif.</th>
                            <th class="px-4 py-2 text-right font-bold">Costo unit.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($reconcile['lineas'] as $l)
                            <tr>
                                <td class="px-4 py-2">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $l['product_name'] }}</span>
                                    <span class="text-xs text-gray-400">{{ $l['unidad'] }}</span>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ $fmt($l['cantidad_pedida']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold">{{ $fmt($l['cantidad_recibida']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums {{ ($l['diferencia'] ?? 0) < 0 ? 'text-red-600' : (($l['diferencia'] ?? 0) > 0 ? 'text-blue-600' : 'text-gray-400') }}">{{ $l['diferencia'] === null ? '—' : $fmt($l['diferencia']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ $fmt($l['costo_unitario']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- DERECHA: Conciliación + factura --}}
        <div class="w-full xl:flex-1 min-w-0 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm px-5 py-4">
            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">Conciliación (pedido · recibido · facturado)</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 px-3 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Pedido</p>
                    <p class="text-lg font-black tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($reconcile['totales']['pedido'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 px-3 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-500">Recibido</p>
                    <p class="text-lg font-black tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($reconcile['totales']['recibido'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 px-3 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-blue-500">Facturado (neto)</p>
                    <p class="text-lg font-black tabular-nums text-blue-700 dark:text-blue-300">{{ number_format($reconcile['totales']['facturado'], 0, ',', '.') }}</p>
                </div>
            </div>

            {{-- Vínculo de factura --}}
            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                @if($facturaVinculada)
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Factura vinculada:</span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">N° {{ $facturaVinculada->folio ?? $facturaVinculada->id }}</span>
                            <span class="text-gray-400">· {{ number_format((float)$facturaVinculada->monto_total, 0, ',', '.') }}</span>
                        </div>
                        <form method="POST" action="{{ route('purchase_orders.receptions.unmatch_invoice', $recepcion->id) }}"
                              onsubmit="return confirm('Desvincular la factura?');">
                            @csrf @method('DELETE')
                            <button class="text-xs font-semibold text-red-600 hover:text-red-700">Desvincular</button>
                        </form>
                    </div>
                @else
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2">Vincular factura (DTE)</label>

                    <form method="POST" action="{{ route('purchase_orders.receptions.match_invoice', $recepcion->id) }}"
                          x-data="{
                              search: '',
                              active: null,
                              facturas: @js($facturasDisponibles),
                              get results() {
                                  const q = this.search.toLowerCase().trim();
                                  let list = this.facturas;
                                  if (q) {
                                      const terms = q.split(/\s+/);
                                      list = list.filter(f => terms.every(t => f.search.includes(t)));
                                  }
                                  return list.slice(0, 100);
                              },
                          }">
                        @csrf
                        <input type="hidden" name="gmail_document_id" :value="active?.id || ''">

                        @if($facturasDisponibles->isEmpty())
                            <p class="text-xs text-amber-600 dark:text-amber-400">No hay facturas sin vincular.</p>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

                                {{-- ── IZQUIERDA: buscador + lista ── --}}
                                <div class="flex flex-col border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700 min-w-0">
                                    <div class="p-2 border-b border-gray-100 dark:border-gray-800">
                                        <div class="relative">
                                            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                                            <input type="text" x-model="search" placeholder="Buscar proveedor, folio o RUT…"
                                                   class="w-full pl-9 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                                        </div>
                                    </div>
                                    <div class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                                        <template x-for="f in results" :key="f.id">
                                            <button type="button" @click="active = f"
                                                    class="w-full text-left px-3 py-2 transition flex items-center gap-2"
                                                    :class="active?.id === f.id ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40'">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="f.pagado ? 'bg-emerald-500' : 'bg-red-500'" :title="f.pagado ? 'Pagada' : 'Sin pagar'"></span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-300 shrink-0" x-text="f.tipo"></span>
                                                        <span class="text-[11px] font-mono text-gray-400 shrink-0" x-text="'N° ' + (f.folio || f.id)"></span>
                                                    </div>
                                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate leading-tight" x-text="f.proveedor"></p>
                                                </div>
                                                <span class="text-xs font-bold text-gray-600 dark:text-gray-300 tabular-nums shrink-0" x-text="f.monto_fmt"></span>
                                            </button>
                                        </template>
                                        <div x-show="results.length === 0" class="px-3 py-6 text-xs text-gray-400 text-center">Sin coincidencias.</div>
                                    </div>
                                    <div class="px-3 py-1.5 text-[11px] text-gray-400 border-t border-gray-100 dark:border-gray-800">
                                        <span x-text="results.length"></span> de <span x-text="facturas.length"></span> facturas
                                    </div>
                                </div>

                                {{-- ── DERECHA: detalle ── --}}
                                <div class="p-4 min-w-0">
                                    {{-- Placeholder --}}
                                    <div x-show="!active" class="h-full flex flex-col items-center justify-center text-center py-8 text-gray-400">
                                        <svg class="w-8 h-8 mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <p class="text-sm font-medium">Selecciona una factura</p>
                                        <p class="text-xs">de la lista para ver su detalle</p>
                                    </div>

                                    {{-- Detalle de la activa --}}
                                    <template x-if="active">
                                        <div>
                                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                                <span class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300" x-text="active.tipo"></span>
                                                <span class="text-sm font-mono font-bold text-gray-700 dark:text-gray-300" x-text="'N° ' + (active.folio || active.id)"></span>
                                                <span class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-bold" :class="active.pagado ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'" x-text="active.pagado ? 'Pagada' : 'Sin pagar'"></span>
                                            </div>
                                            <p class="text-base font-bold text-gray-900 dark:text-gray-100 leading-tight" x-text="active.proveedor"></p>
                                            <p class="text-xs text-gray-400 font-mono mt-0.5" x-show="active.rut" x-text="active.rut"></p>

                                            <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
                                                <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 px-2.5 py-1.5">
                                                    <span class="block text-[10px] text-gray-400 uppercase font-bold">Emitida</span>
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="active.fecha || '—'"></span>
                                                </div>
                                                <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 px-2.5 py-1.5">
                                                    <span class="block text-[10px] text-gray-400 uppercase font-bold">Vence</span>
                                                    <span class="font-semibold" :class="active.vence ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400'" x-text="active.vence || '—'"></span>
                                                </div>
                                            </div>

                                            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 space-y-1 text-sm">
                                                <div class="flex justify-between"><span class="text-gray-400">Neto</span><span class="font-semibold tabular-nums text-gray-700 dark:text-gray-300" x-text="active.neto_fmt"></span></div>
                                                <div class="flex justify-between"><span class="text-gray-400">IVA</span><span class="font-semibold tabular-nums text-gray-700 dark:text-gray-300" x-text="active.iva_fmt"></span></div>
                                                <div class="flex justify-between pt-1 border-t border-gray-100 dark:border-gray-800"><span class="font-bold text-gray-700 dark:text-gray-300">Total</span><span class="font-black tabular-nums text-gray-900 dark:text-gray-100 text-base" x-text="active.monto_fmt"></span></div>
                                            </div>

                                            <button type="submit"
                                                    class="mt-4 w-full px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm">
                                                Vincular esta factura
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        @endif
                    </form>
                    <p class="text-xs text-gray-400 mt-2">Vincular la factura no mueve stock; solo concilia. El stock ya entró al confirmar la recepción.</p>
                @endif
            </div>
        </div>
        </div>{{-- /flex dos columnas --}}
    </div>
</x-app-layout>
