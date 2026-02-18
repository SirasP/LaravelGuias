<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">
                        {{ $dte->tipo_nombre ?? $dte->tipo_dte }} #{{ $dte->folio }}
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Inventario · DTE</p>
                </div>
            </div>
            <a href="{{ route('inventario.dtes.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-xl
                       text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800
                       border border-gray-200 dark:border-gray-700
                       hover:border-indigo-300 hover:text-indigo-600 dark:hover:text-indigo-400
                       transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver
            </a>
        </div>
    </x-slot>

    <style>
        @keyframes fadeUp { from { opacity:0; transform:translateY(8px) } to { opacity:1; transform:translateY(0) } }
        .au { animation: fadeUp .4s cubic-bezier(.22,1,.36,1) both }
        .d1 { animation-delay:.04s } .d2 { animation-delay:.08s } .d3 { animation-delay:.12s } .d4 { animation-delay:.16s }

        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }

        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:18px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }

        .panel-head { padding:15px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:12px }
        .dark .panel-head { border-bottom-color:#1e2a3b }

        .stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px 18px; display:flex; align-items:center; justify-content:space-between }
        .dark .stat-card { background:#161c2c; border-color:#1e2a3b }

        .info-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9 }
        .dark .info-row { border-bottom-color:#1e2a3b }
        .info-row:last-child { border-bottom:none }

        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
        .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
        .dt th { padding:10px 16px; text-align:left; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; white-space:nowrap }
        .dt td { padding:12px 16px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr:hover td { background:#f8fafc }
        .dark .dt tbody tr:hover td { background:#1a2436 }

        .btn { display:inline-flex; align-items:center; justify-content:center; border-radius:12px; padding:8px 14px; font-size:12px; font-weight:700; transition:all .15s ease }
        .btn-primary { background:#16a34a; color:#fff }
        .btn-primary:hover { background:#15803d }
        .btn-secondary { background:#2563eb; color:#fff }
        .btn-secondary:hover { background:#1d4ed8 }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            @if(session('ok'))
                <div class="panel au d1">
                    <div class="px-5 py-3 text-sm text-green-700 bg-green-50 dark:bg-green-900/20 dark:text-green-300">
                        {{ session('ok') }}
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="panel au d1">
                    <div class="px-5 py-3 text-sm text-red-700 bg-red-50 dark:bg-red-900/20 dark:text-red-300">
                        {{ $errors->first() }}
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="stat-card au d1">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Tipo DTE</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 truncate">{{ $dte->tipo_nombre ?? $dte->tipo_dte ?? '—' }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                    </div>
                </div>

                <div class="stat-card au d2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Folio</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ $dte->folio ?? '—' }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>

                <div class="stat-card au d3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Emisor</p>
                        <p class="text-base font-black text-gray-900 dark:text-gray-100 truncate">{{ $dte->rz_emisor ?? '—' }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5 truncate">{{ $dte->rut_emisor ?? '—' }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>

                <div class="stat-card au d4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Total</p>
                        <p class="text-base font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                            ${{ number_format((float) ($dte->mnt_total ?? 0), 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="panel au d2">
                    <div class="panel-head">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Resumen DTE</h3>
                            <p class="text-[10px] text-gray-400 mt-0.5">Datos comerciales</p>
                        </div>
                    </div>
                    <div class="px-5 py-3">
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Receptor</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $dte->rz_receptor ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">RUT receptor</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $dte->rut_receptor ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">Neto</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">
                                ${{ number_format((float) ($dte->mnt_neto ?? 0), 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="text-xs text-gray-500">IVA</span>
                            <span class="text-xs font-semibold text-gray-900 dark:text-gray-100 tabular-nums">
                                ${{ number_format((float) ($dte->iva ?? 0), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="panel au d3 lg:col-span-2">
                    <div class="panel-head">
                        <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 11h10M7 15h6m-8 6h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Detalle e inventario</h3>
                            <p class="text-[10px] text-gray-400 mt-0.5">Selección de líneas a ingresar</p>
                        </div>
                    </div>

                    <form id="frmDetInv" method="POST">
                        @csrf

                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-center gap-2">
                            <button
                                type="submit"
                                formaction="{{ route('inventario.dtes.inventario.ingresar', $dte->id) }}"
                                class="btn btn-primary">
                                Ingresar a inventario
                            </button>
                            <button
                                type="submit"
                                formaction="{{ route('inventario.dtes.detalles.updateSelection', $dte->id) }}"
                                class="btn btn-secondary">
                                Guardar selección
                            </button>
                        </div>

                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-center gap-3">
                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                <input id="checkAll" type="checkbox" class="rounded border-gray-300">
                                Seleccionar todo
                            </label>

                            <div class="flex items-center gap-2 flex-1 min-w-[220px]">
                                <span class="text-xs text-gray-500">Bodega:</span>
                                <select id="bodega_select" name="bodega_id"
                                    class="flex-1 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 text-sm">
                                    @foreach($bodegas as $b)
                                        <option value="{{ $b->id }}"
                                            {{ (string) old('bodega_id', request('bodega_id')) === (string) $b->id ? 'selected' : '' }}>
                                            {{ $b->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <p class="text-[11px] text-gray-400">
                                La bodega se aplica también al ingreso.
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="dt">
                                <thead>
                                    <tr>
                                        <th>Inventario</th>
                                        <th>#</th>
                                        <th>Item</th>
                                        <th>Cant</th>
                                        <th>A ingresar</th>
                                        <th>UM</th>
                                        <th>Precio</th>
                                        <th>Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($detalles as $l)
                                        @php
                                            $qty = (float) ($l->qty ?? 0);
                                            $precio = (float) ($l->prc_item ?? 0);
                                            $monto = (float) ($l->monto_item ?? 0);

                                            $ingresada = (float) ($l->qty_ingresada ?? 0);
                                            $pendiente = max(0, $qty - $ingresada);
                                            $completo = $pendiente <= 0;

                                            $checked = isset($l->seleccionado_inventario) ? (bool) $l->seleccionado_inventario : false;

                                            $qtyIng = (isset($l->qty_a_ingresar) && $l->qty_a_ingresar !== null)
                                                ? (float) $l->qty_a_ingresar
                                                : $pendiente;

                                            $qtyIng = min($qtyIng, $pendiente);

                                            $qtyIngValue = $pendiente > 0
                                                ? rtrim(rtrim(number_format($qtyIng, 3, '.', ''), '0'), '.')
                                                : '0';
                                        @endphp

                                        <tr class="{{ $completo ? 'bg-gray-100 dark:bg-gray-900 text-gray-400' : '' }}">
                                            <td>
                                                <input type="checkbox"
                                                    name="lines[{{ $l->id }}][selected]"
                                                    value="1"
                                                    class="rounded border-gray-300 rowCheck {{ $completo ? 'opacity-50 pointer-events-none' : '' }}"
                                                    data-line="{{ $l->id }}"
                                                    data-completo="{{ $completo ? '1' : '0' }}"
                                                    {{ $checked ? 'checked' : '' }}>
                                            </td>
                                            <td class="tabular-nums">{{ $l->nro_lin_det }}</td>
                                            <td class="font-medium text-gray-900 dark:text-gray-100">{{ $l->nmb_item }}</td>
                                            <td>
                                                <span class="tabular-nums">{{ number_format($qty, 3, ',', '.') }}</span>
                                                @if($ingresada > 0)
                                                    <div class="text-[11px] text-gray-500 mt-0.5">
                                                        Ingresado: {{ number_format($ingresada, 3, ',', '.') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="number"
                                                    step="0.001"
                                                    min="0"
                                                    max="{{ $pendiente }}"
                                                    name="lines[{{ $l->id }}][qty]"
                                                    value="{{ $qtyIngValue }}"
                                                    data-line="{{ $l->id }}"
                                                    data-completo="{{ $completo ? '1' : '0' }}"
                                                    {{ $completo ? 'readonly' : '' }}
                                                    class="w-28 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-2 py-1 text-sm qtyInput {{ $completo ? 'opacity-60' : '' }}">

                                                <div class="text-[11px] mt-1 {{ $completo ? 'text-gray-400 italic' : 'text-gray-500' }}">
                                                    Máx: {{ number_format($pendiente, 3, ',', '.') }}
                                                    @if($completo)
                                                        <div class="text-[11px] text-green-600 font-semibold mt-1">
                                                            Completamente ingresado
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $l->unmd_item }}</td>
                                            <td class="tabular-nums">{{ number_format($precio, 4, ',', '.') }}</td>
                                            <td class="tabular-nums">{{ number_format($monto, 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="py-12 text-center text-sm text-gray-400">
                                                Sin líneas (normal en algunas guías).
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>

            @if($dte->xml)
                <div class="panel au d4">
                    <div class="panel-head">
                        <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">XML</h3>
                        </div>
                    </div>
                    <div class="px-5 py-4">
                        <details>
                            <summary class="cursor-pointer text-xs font-semibold text-gray-600 dark:text-gray-300">Ver XML</summary>
                            <pre class="mt-3 text-xs overflow-x-auto text-gray-700 dark:text-gray-200">{{ $dte->xml }}</pre>
                        </details>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const checkAll = document.getElementById('checkAll');
            const rowChecks = () => document.querySelectorAll('.rowCheck');

            const toggleQty = (lineId) => {
                const input = document.querySelector(`.qtyInput[data-line="${lineId}"]`);
                const cb = document.querySelector(`.rowCheck[data-line="${lineId}"]`);
                if (!input) return;

                const completo = (input.getAttribute('data-completo') === '1') || (cb?.getAttribute('data-completo') === '1');

                if (completo) {
                    input.readOnly = true;
                    return;
                }

                input.readOnly = false;
            };

            checkAll?.addEventListener('change', () => {
                rowChecks().forEach(cb => {
                    const completo = cb.getAttribute('data-completo') === '1';
                    if (completo) return;

                    cb.checked = checkAll.checked;
                    cb.dispatchEvent(new Event('change'));
                });
            });

            document.querySelectorAll('.rowCheck').forEach(cb => {
                const lineId = cb.getAttribute('data-line');
                const apply = () => toggleQty(lineId);
                cb.addEventListener('change', apply);
                apply();
            });
        });
    </script>
</x-app-layout>
