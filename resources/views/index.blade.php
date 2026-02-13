{{-- WebSocket + Auth --}}
<script>
    const KG_PROMEDIO_URL = "{{ route('agrak.kg-promedio') }}";
    window.AUTH_USER = { id: {{ auth()->id() }}, name: "{{ auth()->user()->name }}", role: "{{ auth()->user()->role }}" };
    const ws = new WebSocket("ws://109.72.119.62/ws");
    ws.onopen = () => ws.send(JSON.stringify({ type: 'register', userId: window.AUTH_USER.id, name: window.AUTH_USER.name }));
    ws.onmessage = e => {
        let d; try { d = JSON.parse(e.data); } catch { return; }
        if (window.AUTH_USER.id !== 1) return;
        if (d.type === 'user_connected') Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: `${d.name} se conectó`, showConfirmButton: false, showCloseButton: true, timer: null });
        if (d.type === 'xml_entrada') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: d.titulo, text: d.mensaje, showConfirmButton: false, timer: null });
    };
</script>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">Dashboard</h2>
                <p class="text-sm text-gray-400 mt-0.5">Resumen últimos 120 días</p>
            </div>
            <span class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full
                     bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400
                     border border-emerald-100 dark:border-emerald-800/50">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                En vivo
            </span>
        </div>
    </x-slot>

    {{-- Notificaciones admin --}}
    @if(auth()->id() === 1 && $notificaciones->count())
        @php
            $notificacionesData = $notificaciones->map(fn($n) => [
                'id' => $n->id,
                'tipo' => $n->tipo ?? null,
                'movimiento_id' => $n->movimiento_id ?? null,
                'titulo' => $n->titulo,
                'mensaje' => $n->mensaje,
                'url_leer' => route('fuelcontrol.notificaciones.leer', $n->id),
                'url_xml' => isset($n->tipo) && in_array($n->tipo, ['xml_revision', 'xml_entrada']) && $n->movimiento_id
                    ? route('fuelcontrol.xml.show', $n->movimiento_id) : null,
            ])->values();
        @endphp
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                const notifs = @json($notificacionesData);
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                for (const n of notifs) {
                    const r = await Swal.fire({
                        toast: true, position: 'top-end', icon: n.url_xml ? 'info' : 'success',
                        title: n.titulo, text: n.mensaje, showConfirmButton: !n.url_xml, confirmButtonText: '✔ Leída',
                        confirmButtonColor: '#16a34a', showDenyButton: !!n.url_xml, denyButtonText: '📄 Ver XML',
                        showCloseButton: true, timer: null
                    });
                    if (r.isDenied && n.url_xml) {
                        const mr = await Swal.fire({
                            title: n.titulo, width: '75%', showCloseButton: true,
                            showConfirmButton: true, confirmButtonText: '✔ Leída', confirmButtonColor: '#16a34a',
                            html: '<div class="py-6 text-center text-gray-400">Cargando...</div>',
                            didOpen: async () => {
                                const c = Swal.getHtmlContainer();
                                try { c.innerHTML = await (await fetch(n.url_xml)).text(); }
                                catch { c.innerHTML = '<p class="text-red-500 text-center">Error al cargar</p>'; }
                            }
                        });
                        if (mr.isConfirmed && n.url_leer) await fetch(n.url_leer, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } });
                        continue;
                    }
                    if (r.isConfirmed && n.url_leer) await fetch(n.url_leer, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } });
                }
            });
        </script>
    @endif

    <style>
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .au {
            animation: slideUp .4s cubic-bezier(.22, 1, .36, 1) both;
        }

        .d1 {
            animation-delay: .04s
        }

        .d2 {
            animation-delay: .08s
        }

        .d3 {
            animation-delay: .12s
        }

        .d4 {
            animation-delay: .16s
        }

        .d5 {
            animation-delay: .20s
        }

        .d6 {
            animation-delay: .24s
        }

        .d7 {
            animation-delay: .28s
        }

        .d8 {
            animation-delay: .32s
        }

        .d9 {
            animation-delay: .36s
        }

        .section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .10em;
            text-transform: uppercase;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .dark .section-label::after {
            background: #1e2a3b;
        }

        /* KPI cards */
        .kpi-group {
            display: grid;
            gap: 10px;
        }

        .kpi-card {
            background: #fff;
            border-radius: 14px;
            padding: 16px 18px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: box-shadow .2s, transform .15s;
        }

        .dark .kpi-card {
            background: #161c2c;
            border-color: #1e2a3b;
        }

        .kpi-card:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, .07);
            transform: translateY(-1px);
        }

        .dark .kpi-card:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, .4);
        }

        .kpi-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 1.55rem;
            font-weight: 900;
            letter-spacing: -.04em;
            line-height: 1.1;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }

        .dark .kpi-value {
            color: #f1f5f9;
        }

        .kpi-icon {
            padding: 10px;
            border-radius: 12px;
            flex-shrink: 0;
        }

        /* Chart card */
        .chart-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .dark .chart-card {
            background: #161c2c;
            border-color: #1e2a3b;
        }

        .chart-header {
            padding: 14px 18px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chart-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .dark .chart-title {
            color: #e2e8f0;
        }

        .chart-pill {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #94a3b8;
            background: #f1f5f9;
            border-radius: 999px;
            padding: 2px 9px;
        }

        .dark .chart-pill {
            background: #1e2a3b;
        }

        /* Table */
        .dt {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dt thead tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .dark .dt thead tr {
            border-bottom-color: #1e2a3b;
        }

        .dt th {
            padding: 10px 16px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
            white-space: nowrap;
        }

        .dt th.r {
            text-align: right;
        }

        .dt td {
            padding: 11px 16px;
            border-bottom: 1px solid #f8fafc;
            color: #334155;
        }

        .dark .dt td {
            border-bottom-color: #1a2232;
            color: #cbd5e1;
        }

        .dt tbody tr:last-child td {
            border-bottom: none;
        }

        .dt tbody tr {
            transition: background .1s;
        }

        .dt tbody tr:hover td {
            background: #f8fafc;
        }

        .dark .dt tbody tr:hover td {
            background: #1a2436;
        }

        /* Kg adjustment card */
        .kg-card {
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dark .kg-card {
            background: #161c2c;
            border-color: #1e2a3b;
        }
    </style>

    @php
        $kpi = (float) $kpi5Dias;
        $kpiFormatted = number_format($kpi, $kpi == floor($kpi) ? 1 : 2, ',', '.');
        $kpiC = (float) $kpiCentros;
        $kpiCFormatted = number_format($kpiC, $kpiC == floor($kpiC) ? 1 : 2, ',', '.');
        // Pasamos el valor exacto de bandejas a JS sin casteo int
        $bandejasAgrakExact = (float) ($kpiBandejasAgrak ?? 0);
        $kgProm = (float) ($kgPromedioAgrak ?? 2.5);
    @endphp

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-7 space-y-8">

        {{-- ══ SECCIÓN ODOO ══ --}}
        <div class="au d1">
            <div class="section-label">
                <span class="inline-flex items-center gap-2.5">
                    <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                    ODOO · últimos 120 días
                </span>
            </div>
            <div class="kpi-group grid-cols-1 sm:grid-cols-2">
                <div class="kpi-card">
                    <div>
                        <div class="kpi-label">Kilos</div>
                        <div class="kpi-value">{{ $kpiFormatted }} <span
                                class="text-base font-semibold text-gray-400">kg</span></div>
                    </div>
                    <div class="kpi-icon bg-blue-50 dark:bg-blue-900/20">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </div>
                </div>
                <div class="kpi-card">
                    <div>
                        <div class="kpi-label">Bandejas</div>
                        <div class="kpi-value">{{ number_format($kpiBandejas ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="kpi-icon bg-sky-50 dark:bg-sky-900/20">
                        <svg class="w-6 h-6 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ SECCIÓN CENTROS ══ --}}
        <div class="au d2">
            <div class="section-label">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-amber-500 flex-shrink-0"></span>
                    Centros · últimos 120 días
                </span>
            </div>
            <div class="kpi-card">
                <div>
                    <div class="kpi-label">Kilos recepcionados</div>
                    <div class="kpi-value">{{ $kpiCFormatted }} <span
                            class="text-base font-semibold text-gray-400">kg</span></div>
                </div>
                <div class="kpi-icon bg-amber-50 dark:bg-amber-900/20">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- ══ SECCIÓN AGRAK ══ --}}
        <div class="au d3">
            <div class="section-label">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0"></span>
                    AGRAK · últimos 120 días
                </span>
            </div>

            <div class="space-y-3">
                <div class="kpi-group grid-cols-1 sm:grid-cols-3">
                    {{-- Kilos estimados --}}
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-label">Kilos estimados</div>
                            <div id="kilosAgrak" class="kpi-value">—</div>
                        </div>
                        <div class="kpi-icon bg-emerald-50 dark:bg-emerald-900/20">
                            <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                    {{-- Bandejas --}}
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-label">Bandejas</div>
                            <div class="kpi-value">{{ number_format($kpiBandejasAgrak ?? 0, 0, ',', '.') }}</div>
                        </div>
                        <div class="kpi-icon bg-indigo-50 dark:bg-indigo-900/20">
                            <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                    </div>
                    {{-- Bins --}}
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-label">Bins</div>
                            <div class="kpi-value">{{ number_format($kpiBinsAgrak ?? 0, 0, ',', '.') }}</div>
                        </div>
                        <div class="kpi-icon bg-violet-50 dark:bg-violet-900/20">
                            <svg class="w-6 h-6 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Ajuste peso promedio --}}
                <div class="kg-card au d4">
                    <div class="flex items-center gap-2 shrink-0">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="text-xs font-semibold text-gray-500">Peso promedio</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <input id="kgPromedio" type="number" step="0.1" min="0" value="{{ $kgProm }}" class="w-20 text-right px-2.5 py-1.5 text-sm font-bold rounded-lg
                                  border border-gray-200 dark:border-gray-600
                                  bg-white dark:bg-gray-800
                                  text-gray-900 dark:text-gray-100
                                  focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
                        <span class="text-sm text-gray-400">kg / bandeja</span>
                    </div>
                    <div class="h-5 w-px bg-gray-200 dark:bg-gray-700 hidden sm:block"></div>
                    <div class="text-sm text-gray-500 min-w-0">
                        Estimado:
                        <span id="kilosAgrakInline" class="font-bold text-emerald-600 dark:text-emerald-400">—</span>
                    </div>
                    <button onclick="applyKgPromedio()" class="ml-auto px-4 py-2 text-xs font-bold rounded-lg
                               bg-indigo-600 hover:bg-indigo-700 active:scale-95
                               text-white transition-all shadow-sm">
                        Guardar
                    </button>
                </div>
            </div>
        </div>

        {{-- ══ TABLA EMPRESAS ══ --}}
        <div class="au d5">
            <div class="section-label">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                    Empresas · kilos informados por centros
                </span>
            </div>

            {{-- Desktop --}}
            <div
                class="hidden lg:block bg-white dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-700/60 overflow-hidden">
                <table class="dt">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th class="r">Guías</th>
                            <th class="r">Sin respuesta</th>
                            <th class="r">Bandejas ODOO</th>
                            <th class="r">Kilos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kilosPorContacto as $row)
                            <tr>
                                <td class="font-semibold">
                                    <a href="{{ route('centros.detalle', ['contacto' => $row->contacto]) }}"
                                        class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row->contacto }}</a>
                                </td>
                                <td class="text-right font-semibold">{{ $row->total_guias }}</td>
                                <td class="text-right">
                                    @if($row->guias_sin_match > 0)
                                        <span
                                            class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400">{{ $row->guias_sin_match }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-700">—</span>
                                    @endif
                                </td>
                                <td class="text-right text-gray-500 dark:text-gray-400">
                                    {{ number_format($bandejasPorContacto[$row->contacto]->total_bandejas ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="text-right font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ number_format($row->total_kilos, 1, ',', '.') }} kg</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="lg:hidden space-y-2">
                @foreach($kilosPorContacto as $row)
                    <div
                        class="bg-white dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-700/60 p-4">
                        <div class="flex items-start justify-between mb-3">
                            <a href="{{ route('centros.detalle', ['contacto' => $row->contacto]) }}"
                                class="font-bold text-sm text-indigo-600 dark:text-indigo-400 hover:underline leading-snug">{{ $row->contacto }}</a>
                            <span
                                class="text-sm font-black text-emerald-600 dark:text-emerald-400 ml-2 shrink-0">{{ number_format($row->total_kilos, 1, ',', '.') }}
                                kg</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-2">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-1">Guías</p>
                                <p class="text-sm font-black text-gray-800 dark:text-gray-200">{{ $row->total_guias }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-2">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-1">Sin resp.</p>
                                <p
                                    class="text-sm font-black {{ $row->guias_sin_match > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-300 dark:text-gray-700' }}">
                                    {{ $row->guias_sin_match > 0 ? $row->guias_sin_match : '—' }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-2">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-1">Bandejas</p>
                                <p class="text-sm font-black text-gray-800 dark:text-gray-200">
                                    {{ number_format($bandejasPorContacto[$row->contacto]->total_bandejas ?? 0, 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ══ GRÁFICOS — 1 columna ══ --}}
        <div class="au d6">
            <div class="section-label">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-slate-400 flex-shrink-0"></span>
                    Evolución temporal
                </span>
            </div>

            <div class="space-y-4">
                @php
                    $graficos = [
                        ['id' => 'kilosChart', 'title' => 'Kilos ODOO', 'sub' => 'Diario', 'dot' => 'bg-blue-500'],
                        ['id' => 'centrosChart', 'title' => 'Kilos Centros', 'sub' => 'Diario', 'dot' => 'bg-amber-500'],
                        ['id' => 'contactosChart', 'title' => 'Kilos por empresa', 'sub' => 'Centros', 'dot' => 'bg-indigo-500'],
                        ['id' => 'bandejasAgrakChart', 'title' => 'Bandejas AGRAK', 'sub' => 'Diario', 'dot' => 'bg-emerald-500'],
                        ['id' => 'binsAgrakChart', 'title' => 'Bins AGRAK', 'sub' => 'Diario', 'dot' => 'bg-violet-500'],
                        ['id' => 'binsPorCuartelChart', 'title' => 'Bins por Cuartel', 'sub' => 'AGRAK', 'dot' => 'bg-orange-500'],
                        ['id' => 'maquinasAgrakChart', 'title' => 'Cosechadora AGRAK', 'sub' => 'Total / máquina', 'dot' => 'bg-sky-500'],
                    ];
                @endphp

                @foreach($graficos as $g)
                    <div class="chart-card au d{{ min($loop->iteration + 6, 9) }}">
                        <div class="chart-header">
                            <div class="chart-title">
                                <span class="w-2 h-2 rounded-full {{ $g['dot'] }} flex-shrink-0"></span>
                                {{ $g['title'] }}
                            </div>
                            <span class="chart-pill">{{ $g['sub'] }}</span>
                        </div>
                        <div class="p-5">
                            <div class="relative h-44"><canvas id="{{ $g['id'] }}"></canvas></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>{{-- /max-w --}}

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
    <script>
        // ── DATOS DESDE PHP ──────────────────────────────────────────────────────────
        // Valor exacto de bandejas AGRAK (float, no int para no perder decimales)
        const BANDEJAS_AGRAK = {{ $bandejasAgrakExact }};

        // ── Helpers ──────────────────────────────────────────────────────────────────
        const isDark = () => document.documentElement.classList.contains('dark');

        function fmtCL(v, dec = 1) {
            const n = Number(v);
            if (isNaN(n)) return '—';
            return n.toLocaleString('es-CL', { minimumFractionDigits: dec, maximumFractionDigits: 2 });
        }

        function makeChart(id, labels, rawData, { color = '#3b82f6', unit = '' } = {}) {
            const el = document.getElementById(id);
            if (!el || !labels?.length || !rawData?.length) return;
            const data = rawData.map(Number);
            const dark = isDark();
            new Chart(el, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: color + (dark ? 'bb' : 'cc'),
                        hoverBackgroundColor: color,
                        borderRadius: 5,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: dark ? '#1a2436' : '#fff',
                            titleColor: dark ? '#e2e8f0' : '#1e293b',
                            bodyColor: dark ? '#94a3b8' : '#64748b',
                            borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                            borderWidth: 1, padding: 12, cornerRadius: 10,
                            callbacks: { label: c => '  ' + fmtCL(c.parsed.y) + (unit ? ' ' + unit : '') }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false }, border: { display: false },
                            ticks: { color: dark ? '#475569' : '#94a3b8', font: { size: 10 }, maxRotation: 45 }
                        },
                        y: {
                            beginAtZero: true, border: { display: false },
                            grid: { color: dark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.04)' },
                            ticks: {
                                color: dark ? '#475569' : '#94a3b8', font: { size: 10 },
                                callback: v => fmtCL(v) + (unit ? ' ' + unit : '')
                            }
                        }
                    }
                }
            });
        }

        // ── Kg promedio — lógica de ajuste ───────────────────────────────────────────
        function calcKilosAgrak() {
            const input = document.getElementById('kgPromedio');
            if (!input) return 0;
            const kg = parseFloat(input.value);
            return isNaN(kg) ? 0 : BANDEJAS_AGRAK * kg;
        }

        function actualizarDisplayKg() {
            const total = calcKilosAgrak();
            const txt = fmtCL(total) + ' kg';
            // Actualiza TODOS los elementos que muestran el estimado
            const card = document.getElementById('kilosAgrak');
            const inline = document.getElementById('kilosAgrakInline');
            if (card) card.textContent = txt;
            if (inline) inline.textContent = txt;
        }

        function applyKgPromedio() {
            const input = document.getElementById('kgPromedio');
            const kg = parseFloat(input?.value);
            if (isNaN(kg)) return;

            fetch(KG_PROMEDIO_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ kg_promedio: kg })
            })
                .then(r => { if (!r.ok) throw new Error('Error ' + r.status); return r.json(); })
                .then(() => {
                    actualizarDisplayKg();
                    // Feedback visual breve
                    const btn = document.querySelector('button[onclick="applyKgPromedio()"]');
                    if (btn) {
                        const orig = btn.textContent;
                        btn.textContent = '✓ Guardado';
                        btn.classList.replace('bg-indigo-600', 'bg-emerald-600');
                        btn.classList.replace('hover:bg-indigo-700', 'hover:bg-emerald-700');
                        setTimeout(() => {
                            btn.textContent = orig;
                            btn.classList.replace('bg-emerald-600', 'bg-indigo-600');
                            btn.classList.replace('hover:bg-emerald-700', 'hover:bg-indigo-700');
                        }, 1800);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('No se pudo guardar el promedio.');
                });
        }

        // ── Init ──────────────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {

            // Reactive input — actualiza display en tiempo real
            const kgInput = document.getElementById('kgPromedio');
            if (kgInput) {
                kgInput.addEventListener('input', actualizarDisplayKg);
                // Calcular valor inicial inmediatamente
                actualizarDisplayKg();
            }

            // Gráficos
            makeChart('kilosChart', @json($chartLabels ?? []), @json($chartData ?? []), { color: '#3b82f6', unit: 'kg' });
            makeChart('centrosChart', @json($chartLabels ?? []), @json($centrosData ?? []), { color: '#f59e0b', unit: 'kg' });
            makeChart('contactosChart', @json($contactosLabels ?? []), @json($contactosKilos ?? []), { color: '#6366f1', unit: 'kg' });
            makeChart('bandejasAgrakChart', @json($bandejasAgrakLabels ?? []), @json($bandejasAgrakData ?? []), { color: '#10b981', unit: 'bandejas' });
            makeChart('binsAgrakChart', @json($binsAgrakLabels ?? []), @json($binsAgrakData ?? []), { color: '#8b5cf6', unit: 'bins' });
            makeChart('binsPorCuartelChart', @json($binsPorCuartelLabels ?? []), @json($binsPorCuartelData ?? []), { color: '#f97316', unit: 'bins' });
            makeChart('maquinasAgrakChart', @json($maquinasLabels ?? []), @json($maquinasTotales ?? []), { color: '#0ea5e9', unit: 'bins' });
        });
    </script>

</x-app-layout>