<x-app-layout>
    <x-slot name="header">
        <div class="w-full grid grid-cols-1 md:grid-cols-[auto,1fr,auto] items-center gap-3">
            <div class="flex items-center gap-3 md:justify-self-start">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Facturas proveedor</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Tablero (admin)</p>
                </div>
            </div>

            <form method="GET" class="w-full md:max-w-xl md:justify-self-center">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}" class="f-input" placeholder="Buscar por folio, proveedor, referencia...">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition">Buscar</button>
                </div>
            </form>

            <div class="hidden md:block md:justify-self-end w-8 h-8"></div>
        </div>
    </x-slot>

    <style>
        .page-bg { background:#f1f5f9; min-height:100% }
        .dark .page-bg { background:#0d1117 }
        .panel { background:#fff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden }
        .dark .panel { background:#161c2c; border-color:#1e2a3b }
        .dt { width:100%; border-collapse:collapse; font-size:13px }
        .dt thead tr { background:#f8fafc; border-bottom:1px solid #f1f5f9 }
        .dark .dt thead tr { background:#111827; border-bottom-color:#1e2a3b }
        .dt th { padding:10px 12px; text-align:left; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; white-space:nowrap }
        .dt td { padding:12px; border-bottom:1px solid #f8fafc; color:#334155; vertical-align:middle }
        .dark .dt td { border-bottom-color:#1a2232; color:#cbd5e1 }
        .dt tbody tr:last-child td { border-bottom:none }
        .dt tbody tr:hover td { background:#f8fafc }
        .dark .dt tbody tr:hover td { background:#1a2436 }
        .f-input { width:100%; border-radius:12px; border:1px solid #e2e8f0; background:#fff; padding:9px 12px; font-size:13px; color:#111827; outline:none }
        .f-input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.12) }
        .dark .f-input { border-color:#1e2a3b; background:#0d1117; color:#f1f5f9 }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <div class="panel">
                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Proveedor</th>
                                <th>Fecha factura</th>
                                <th>Fecha contable</th>
                                <th>Vencimiento</th>
                                <th>Referencia</th>
                                <th>Imp. no incluidos</th>
                                <th>Total facturacion</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $tipoMap = [
                                    33 => ['sigla' => 'FAC', 'nombre' => 'Factura electronica'],
                                    34 => ['sigla' => 'FEX', 'nombre' => 'Factura exenta'],
                                    56 => ['sigla' => 'ND',  'nombre' => 'Nota de debito'],
                                    61 => ['sigla' => 'NC',  'nombre' => 'Nota de credito'],
                                ];
                            @endphp
                            @forelse($documents as $d)
                                @php
                                    $tipo = $tipoMap[(int) ($d->tipo_dte ?? 0)] ?? ['sigla' => 'DTE', 'nombre' => 'Documento tributario'];
                                    $vencDate = $d->fecha_vencimiento ? \Carbon\Carbon::parse($d->fecha_vencimiento)->startOfDay() : null;
                                    $hoy = now()->startOfDay();
                                    $diasVencido = $vencDate ? $vencDate->diffInDays($hoy, false) : null;

                                    if ($diasVencido !== null && $diasVencido > 0) {
                                        $vencHuman = $diasVencido === 1 ? 'ayer' : "hace {$diasVencido} dias";
                                        $estado = 'Sin pagar';
                                    } else {
                                        $vencHuman = '—';
                                        $estado = 'Pagado';
                                    }
                                @endphp
                                <tr class="cursor-pointer" tabindex="0"
                                    onclick="window.location='{{ route('gmail.dtes.show', $d->id) }}'"
                                    onkeydown="if(event.key==='Enter'){window.location='{{ route('gmail.dtes.show', $d->id) }}'}">
                                    <td>
                                        <div class="font-semibold">{{ $tipo['sigla'] }} {{ $d->folio ?? '—' }}</div>
                                        <div class="text-[11px] text-gray-400">{{ $tipo['nombre'] }}</div>
                                    </td>
                                    <td>
                                        <div class="font-semibold">{{ $d->proveedor_nombre ?? '—' }}</div>
                                        <div class="text-[11px] text-gray-400">{{ $d->proveedor_rut ?? '—' }}</div>
                                    </td>
                                    <td>{{ $d->fecha_factura ?? '—' }}</td>
                                    <td>{{ $d->fecha_contable ?? '—' }}</td>
                                    <td><span class="text-rose-600 dark:text-rose-400 font-semibold">{{ $vencHuman }}</span></td>
                                    <td class="max-w-[220px] truncate" title="{{ $d->referencia }}">{{ $d->referencia ?? '—' }}</td>
                                    <td>{{ number_format((float) $d->monto_neto, 0, ',', '.') }}</td>
                                    <td class="font-bold">{{ number_format((float) $d->monto_total, 0, ',', '.') }}</td>
                                    <td>
                                        <span class="inline-flex px-3 py-1 text-[11px] font-semibold rounded-full {{ $estado === 'Pagado' ? 'bg-green-600 text-white' : 'bg-rose-600 text-white' }}">
                                            {{ $estado }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-10 text-gray-400">No hay DTE no combustible.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $documents->links() }}</div>
        </div>
    </div>
</x-app-layout>
