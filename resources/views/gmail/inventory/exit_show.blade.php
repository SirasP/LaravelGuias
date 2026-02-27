<x-app-layout>
    @php
        $isVenta    = ($movement->tipo_salida ?? '') === 'Venta';
        $isEpp      = ($movement->tipo_salida ?? '') === 'EPP';
        $tipoLabel  = $movement->tipo_salida ?? 'Salida';
        $costoTotal = (float) $movement->costo_total;
        $pvTotal    = (float) ($movement->precio_venta ?? 0);
        $margen     = ($isVenta && $costoTotal > 0 && $pvTotal > 0)
                        ? round((($pvTotal - $costoTotal) / $costoTotal) * 100, 1)
                        : null;
        $usuario    = $movement->usuario_id
                        ? (\App\Models\User::find($movement->usuario_id)?->name ?? 'Usuario #'.$movement->usuario_id)
                        : null;

        $fromGroup        = request()->query('from') === 'group';
        $groupDestinatario = trim((string) request()->query('destinatario', ''));
        $groupTipo        = trim((string) request()->query('tipo', ''));
        $hasGroupBack     = $fromGroup && $groupDestinatario !== '';
        $backUrl          = $hasGroupBack
            ? route('gmail.inventory.exits.group', array_filter(['destinatario' => $groupDestinatario, 'tipo' => $groupTipo ?: null]))
            : route('gmail.inventory.exits', $isVenta ? ['tipo' => 'Venta'] : []);
        $backLabel        = $hasGroupBack ? 'Resumen de salidas' : 'Salidas de inventario';

        $accentColor = $isVenta ? '#10b981' : ($isEpp ? '#3b82f6' : '#94a3b8');
        $sellUrl     = route('gmail.inventory.exits.sell', $movement->id);
    @endphp

    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0 text-xs">
                <a href="{{ $backUrl }}"
                    class="flex items-center gap-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition font-medium shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <span class="hidden sm:inline">{{ $backLabel }}</span>
                </a>
                <svg class="w-3 h-3 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-gray-700 dark:text-gray-300 truncate">
                    {{ $movement->destinatario ?? 'Movimiento #'.$movement->id }}
                </span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if(auth()->user()?->isAdmin())
                @php
                    $editParams = array_filter([
                        'from'         => request()->query('from'),
                        'destinatario' => request()->query('destinatario'),
                        'tipo'         => request()->query('tipo'),
                    ]);
                @endphp
                <a href="{{ route('gmail.inventory.exits.edit', array_merge(['id' => $movement->id], $editParams)) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-50 hover:bg-amber-100 dark:bg-amber-900/20 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modificar
                </a>
                @endif
                <a href="{{ route('gmail.inventory.exits.pdf', $movement->id) }}" target="_blank"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/20 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    PDF
                </a>
                <span class="text-xs text-gray-400">Mov. #{{ $movement->id }}</span>
            </div>
        </div>
    </x-slot>

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:20px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }
        .stat-label { font-size:10px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#94a3b8; margin-bottom:3px }
        .stat-value { font-size:14px; font-weight:700; color:#0f172a }
        .dark .stat-value { color:#f1f5f9 }
        .chip-venta  { background:#d1fae5; color:#065f46 }
        .chip-epp    { background:#dbeafe; color:#1e40af }
        .chip-salida { background:#f1f5f9; color:#475569 }
        .dark .chip-venta  { background:#064e3b; color:#6ee7b7 }
        .dark .chip-epp    { background:#1e3a5f; color:#93c5fd }
        .dark .chip-salida { background:#1e2a3b; color:#94a3b8 }
        .sell-input {
            border-radius:10px; border:1px solid #e2e8f0; background:#fff;
            padding:8px 12px; font-size:13px; color:#111827; outline:none; width:100%;
        }
        .sell-input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.12) }
        .dark .sell-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
    </style>

    <div class="page-bg">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

            {{-- Hero panel --}}
            <div class="panel">
                {{-- Colored top accent bar --}}
                <div class="h-1.5 {{ $isVenta ? 'bg-emerald-500' : ($isEpp ? 'bg-blue-500' : 'bg-slate-400') }}"></div>

                <div class="px-5 pt-5 pb-4">
                    {{-- Top row: destinatario + date --}}
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wide chip-{{ strtolower($tipoLabel) === 'venta' ? 'venta' : (strtolower($tipoLabel) === 'epp' ? 'epp' : 'salida') }}">
                                    {{ $isVenta ? '💰' : ($isEpp ? '🦺' : '📦') }} {{ $tipoLabel }}
                                </span>
                                <span class="text-xs text-gray-400 font-medium">#{{ $movement->id }}</span>
                            </div>
                            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-gray-100 leading-tight break-words">
                                {{ $movement->destinatario ?? '—' }}
                            </h1>
                            @if ($movement->notas)
                                <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $movement->notas }}</p>
                            @endif
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100 tabular-nums">
                                {{ \Carbon\Carbon::parse($movement->ocurrio_el)->format('d/m/Y') }}
                            </p>
                            <p class="text-sm text-gray-400 mt-0.5">
                                {{ $movement->created_at ? \Carbon\Carbon::parse($movement->created_at)->format('H:i') : '' }}
                            </p>
                            @if ($usuario)
                                <p class="text-xs text-gray-400 mt-0.5 truncate max-w-[140px]">{{ $usuario }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Stats row --}}
                    <div class="grid grid-cols-2 {{ $isVenta ? 'sm:grid-cols-4' : 'sm:grid-cols-3' }} gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <div class="bg-gray-50 dark:bg-gray-800/40 rounded-2xl px-4 py-3">
                            <p class="stat-label">Productos</p>
                            <p class="stat-value">{{ $lines->count() }} {{ $lines->count() === 1 ? 'línea' : 'líneas' }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800/40 rounded-2xl px-4 py-3">
                            <p class="stat-label">Unidades</p>
                            <p class="stat-value">{{ number_format((float)$lines->sum('cantidad'), 2, ',', '.') }}</p>
                        </div>
                        @if(auth()->user()->canSeeValues())
                        <div class="bg-rose-50 dark:bg-rose-900/20 rounded-2xl px-4 py-3">
                            <p class="stat-label" style="color:#f43f5e">Costo total</p>
                            <p class="text-sm font-bold text-rose-600 dark:text-rose-400 text-lg">$ {{ number_format($costoTotal, 0, ',', '.') }}</p>
                        </div>
                        @if ($isVenta)
                            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-2xl px-4 py-3">
                                <p class="stat-label" style="color:#059669">Precio venta</p>
                                @if ($pvTotal > 0)
                                    <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400 text-lg">$ {{ number_format($pvTotal, 0, ',', '.') }}</p>
                                @else
                                    <p class="text-sm text-gray-400">Sin registrar</p>
                                @endif
                            </div>
                        @endif
                        @endif
                    </div>

                    {{-- Margen badge --}}
                    @if ($margen !== null && auth()->user()->canSeeValues())
                        <div class="mt-3 rounded-2xl {{ $margen >= 0 ? 'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-900/20 border border-rose-100 dark:border-rose-800' }} px-4 py-3 flex items-center justify-between">
                            <p class="text-xs font-bold {{ $margen >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }} uppercase tracking-wide">Margen de ganancia</p>
                            <p class="text-2xl font-black {{ $margen >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ $margen }}%</p>
                        </div>
                    @endif

                    {{-- Sell price form (Venta without price) --}}
                    @if ($isVenta && $pvTotal == 0)
                        <div class="mt-3" x-data="{
                            show: false, pv: '', saving: false, err: '', saved: null,
                            fmt(n) { return '$ ' + parseFloat(n).toLocaleString('es-CL',{minimumFractionDigits:0,maximumFractionDigits:0}) },
                            async save() {
                                this.err = '';
                                const v = parseFloat(this.pv.replace(/\./g,'').replace(',','.'));
                                if (!v || v <= 0) { this.err = 'Ingresa un precio válido.'; return; }
                                this.saving = true;
                                try {
                                    const r = await fetch('{{ $sellUrl }}', {
                                        method:'POST',
                                        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},
                                        body:JSON.stringify({precio_venta:v})
                                    });
                                    const d = await r.json();
                                    if (d.ok) { this.saved = d; this.show = false; }
                                    else { this.err = d.error ?? 'Error.'; }
                                } catch(e) { this.err = 'Error de conexión.'; }
                                finally { this.saving = false; }
                            }
                        }">
                            <template x-if="saved">
                                <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800 px-4 py-3 flex items-center justify-between">
                                    <p class="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wide">Precio venta registrado</p>
                                    <p class="text-xl font-black text-emerald-600 dark:text-emerald-400" x-text="fmt(saved.precio_venta)"></p>
                                </div>
                            </template>
                            <template x-if="!saved && !show">
                                <button @click="show = true"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-2xl border-2 border-dashed border-emerald-300 dark:border-emerald-700 text-emerald-600 dark:text-emerald-400 text-sm font-semibold hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Registrar precio de venta
                                </button>
                            </template>
                            <template x-if="!saved && show">
                                <div class="rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 space-y-3">
                                    <p class="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wide">Registrar precio de venta</p>
                                    <div class="flex gap-2">
                                        <input type="text" inputmode="numeric" x-model="pv"
                                            class="sell-input" placeholder="$ 0"
                                            @keydown.enter.prevent="save()"
                                            @keydown.escape="show = false">
                                        <button @click="save()" :disabled="saving"
                                            class="shrink-0 px-4 py-2 text-sm font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition disabled:opacity-50">
                                            <span x-show="!saving">Guardar</span>
                                            <span x-show="saving">...</span>
                                        </button>
                                        <button @click="show = false"
                                            class="shrink-0 px-2 py-2 text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">✕</button>
                                    </div>
                                    <p x-show="err" x-text="err" class="text-xs text-rose-600 dark:text-rose-400"></p>
                                </div>
                            </template>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Lines panel --}}
            <div class="panel">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">Detalle de productos</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Consumo FIFO por lote</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                        {{ $lines->count() }} {{ $lines->count() === 1 ? 'línea' : 'líneas' }}
                    </span>
                </div>

                @if ($lines->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm text-gray-400">Sin líneas registradas.</p>
                    </div>
                @else
                    {{-- Desktop table --}}
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800/50">
                                    <th class="text-left px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Producto</th>
                                    <th class="text-left px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Código</th>
                                    <th class="text-right px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Cantidad</th>
                                    @if(auth()->user()->canSeeValues())
                                    <th class="text-right px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">C. Unit.</th>
                                    <th class="text-right px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Total</th>
                                    @endif
                                    <th class="text-right px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Lote</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-800/50">
                                @foreach($lines as $line)
                                    <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/20 transition">
                                        <td class="px-5 py-3.5">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $line->producto }}</p>
                                            <p class="text-xs text-gray-400 mt-0.5">{{ $line->unidad ?? '' }}</p>
                                        </td>
                                        <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">{{ $line->codigo ?? '—' }}</td>
                                        <td class="px-4 py-3.5 text-right font-semibold text-gray-800 dark:text-gray-200 tabular-nums">
                                            {{ number_format((float)$line->cantidad, 2, ',', '.') }}
                                        </td>
                                        @if(auth()->user()->canSeeValues())
                                        <td class="px-4 py-3.5 text-right text-gray-500 dark:text-gray-400 tabular-nums text-xs">
                                            $ {{ number_format((float)$line->costo_unitario, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3.5 text-right font-bold text-gray-900 dark:text-gray-100 tabular-nums">
                                            $ {{ number_format((float)$line->costo_total, 0, ',', '.') }}
                                        </td>
                                        @endif
                                        <td class="px-5 py-3.5 text-right text-xs text-gray-400 tabular-nums">
                                            {{ $line->lote_fecha ? \Carbon\Carbon::parse($line->lote_fecha)->format('d/m/Y') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            @if(auth()->user()->canSeeValues())
                            <tfoot>
                                <tr class="bg-gray-50 dark:bg-gray-800/50 border-t-2 border-gray-100 dark:border-gray-800">
                                    <td colspan="4" class="px-5 py-3 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total del movimiento</td>
                                    <td class="px-4 py-3 text-right text-base font-black text-rose-600 dark:text-rose-400 tabular-nums">
                                        $ {{ number_format($costoTotal, 0, ',', '.') }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>

                    {{-- Mobile: stacked cards --}}
                    <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($lines as $line)
                            <div class="px-4 py-3.5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-gray-900 dark:text-gray-100 leading-tight">{{ $line->producto }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $line->codigo ?? '' }} · {{ $line->unidad ?? '' }}</p>
                                    </div>
                                    @if(auth()->user()->canSeeValues())
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 tabular-nums shrink-0">
                                        $ {{ number_format((float)$line->costo_total, 0, ',', '.') }}
                                    </p>
                                    @endif
                                </div>
                                <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold">{{ number_format((float)$line->cantidad, 2, ',', '.') }} {{ $line->unidad ?? '' }}</span>
                                    @if(auth()->user()->canSeeValues())
                                    <span>$ {{ number_format((float)$line->costo_unitario, 2, ',', '.') }} / u</span>
                                    @endif
                                    @if ($line->lote_fecha)
                                        <span class="text-gray-400">Lote {{ \Carbon\Carbon::parse($line->lote_fecha)->format('d/m/Y') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if(auth()->user()->canSeeValues())
                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/40 flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total</span>
                            <span class="text-base font-black text-rose-600 dark:text-rose-400 tabular-nums">$ {{ number_format($costoTotal, 0, ',', '.') }}</span>
                        </div>
                        @endif
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
