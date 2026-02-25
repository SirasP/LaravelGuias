{{-- Ventas exit card — variables: $m, $cardLines, $costoTotal, $precioVenta, $sellUrl --}}
<div class="exit-card"
     x-data="{
        showSell: false,
        pvInput: '',
        pvSaved: {{ $precioVenta !== null ? (float) $precioVenta : 'null' }},
        costo: {{ $costoTotal }},
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

    {{-- Header --}}
    <div class="px-4 pt-4 pb-3 flex items-start justify-between gap-2 border-b border-gray-100 dark:border-gray-800">
        <div class="min-w-0">
            <p class="text-[11px] text-gray-400 mb-0.5">#{{ $m->id }}</p>
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
                @php $userName = \App\Models\User::find($m->usuario_id)?->name ?? 'Usuario #'.$m->usuario_id; @endphp
                <p class="text-[11px] text-gray-400 mt-0.5 truncate max-w-[120px]">{{ $userName }}</p>
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

    {{-- Footer --}}
    <div class="px-4 pb-4 pt-2 border-t border-gray-100 dark:border-gray-800 space-y-2">
        <div class="flex items-center justify-between flex-wrap gap-x-3 gap-y-1">
            <div>
                <p class="text-[10px] text-gray-400 uppercase tracking-wide">Costo</p>
                <p class="text-sm font-bold text-rose-600 dark:text-rose-400">
                    $ {{ number_format($costoTotal, 0, ',', '.') }}
                </p>
            </div>
            <template x-if="pvSaved !== null">
                <div class="text-right">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide">Venta</p>
                    <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400" x-text="fmt(pvSaved)"></p>
                </div>
            </template>
            <template x-if="pvSaved !== null && margen !== null">
                <div class="text-right">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wide">Margen</p>
                    <p class="text-sm font-bold"
                       :class="parseFloat(margen) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500'"
                       x-text="margen + '%'"></p>
                </div>
            </template>
        </div>

        <template x-if="!showSell">
            <button @click="showSell = true; pvInput = ''"
                class="w-full flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-dashed transition"
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
                        class="shrink-0 px-2 py-2 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">✕</button>
                </div>
                <p x-show="err" x-text="err" class="text-xs text-rose-600 dark:text-rose-400"></p>
            </div>
        </template>
    </div>
</div>
