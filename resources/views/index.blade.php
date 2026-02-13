{{-- WebSocket + Auth (scope global) --}}
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
        /* ── Base ── */
        .dash-page {
            background: #f1f5f9;
            min-height: 100vh;
        }

        .dark .dash-page {
            background: #0d1117;
        }

        /* ── Hero banner ── */
        .hero-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1a2744 55%, #0d2137 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse 60% 80% at 10% 60%, rgba(16, 185, 129, .10) 0%, transparent 60%),
                radial-gradient(ellipse 50% 60% at 85% 20%, rgba(99, 102, 241, .08) 0%, transparent 55%),
                radial-gradient(ellipse 40% 50% at 55% 90%, rgba(245, 158, 11, .06) 0%, transparent 50%);
        }

        .hero-dots {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, rgba(255, 255, 255, .035) 1px, transparent 1px);
            background-size: 28px 28px;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        /* ── KPI hero cards ── */
        .kpi-card {
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 14px;
            padding: 18px 22px;
            transition: background .2s, transform .2s;
            cursor: default;
        }

        .kpi-card:hover {
            background: rgba(255, 255, 255, .09);
            transform: translateY(-2px);
        }

        .kpi-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .35);
            margin-bottom: 8px;
        }

        .kpi-value {
            font-size: clamp(1.4rem, 2.5vw, 2rem);
            font-weight: 900;
            letter-spacing: -.04em;
            line-height: 1;
            color: #fff;
            font-variant-numeric: tabular-nums;
        }

        .kpi-badge {
            margin-top: 10px;
            font-size: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
        }

        /* ── Dash cards ── */
        .d-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            transition: box-shadow .2s;
        }

        .dark .d-card {
            background: #161c2c;
            border-color: #1e2a3b;
        }

        .d-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, .07);
        }

        .dark .d-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, .45);
        }

        /* ── Section label ── */
        .s-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .10em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 12px;
        }

        .s-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ── Table ── */
        .dt {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
        }

        .dt thead tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .dark .dt thead tr {
            border-bottom-color: #1e2a3b;
        }

        .dt th {
            padding: 10px 18px;
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
            padding: 12px 18px;
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

        /* ── Animations ── */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .au {
            animation: slideUp .45s cubic-bezier(.22, 1, .36, 1) both;
        }

        .d1 {
            animation-delay: .04s;
        }

        .d2 {
            animation-delay: .08s;
        }

        .d3 {
            animation-delay: .12s;
        }

        .d4 {
            animation-delay: .16s;
        }

        .d5 {
            animation-delay: .20s;
        }

        .d6 {
            animation-delay: .24s;
        }

        .d7 {
            animation-delay: .28s;
        }

        .d8 {
            animation-delay: .32s;
        }
    </style>

    @php
        $kpi = (float) $kpi5Dias;
        $kpiFormatted = number_format($kpi, $kpi == floor($kpi) ? 1 : 2, ',', '.');
        $kpiC = (float) $kpiCentros;
        $kpiCFormatted = number_format($kpiC, $kpiC == floor($kpiC) ? 1 : 2, ',', '.');
    @endphp

    <div class="dash-page">

        {{-- ══ HERO ══ --}}
        <div class="hero-banner px-4 sm:px-6 lg:px-8 pt-8 pb-12">
            <div class="hero-dots"></div>
            <div class="hero-content max-w-7xl mx-auto">

                <div class="flex items-center gap-3 mb-7">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-white/25">Operaciones
                        agrícolas</span>
                    <span class="h-px flex-1 bg-white/08 bg-white/[.08]"></span>
                    <span class="text-[10px] text-white/20 font-mono">{{ now()->format('d M Y · H:i') }}</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="kpi-card au d1">
                        <div class="kpi-label">Kilos ODOO</div>
                        <div class="kpi-value counter" data-target="{{ $kpi }}" data-dec="1" data-suffix="kg">
                            {{ $kpiFormatted }} kg</div>
                        <div class="kpi-badge bg-emerald-500/20 text-emerald-400">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9" />
                            </svg>
                            ODOO
                        </div>
                    </div>
                    <div class="kpi-card au d2">
                        <div class="kpi-label">Bandejas ODOO</div>
                        <div class="kpi-value counter" data-target="{{ $kpiBandejas ?? 0 }}" data-dec="0">
                            {{ number_format($kpiBandejas ?? 0, 0, ',', '.') }}</div>
                        <div class="kpi-badge bg-blue-500/20 text-blue-400">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2" />
                            </svg>
                            Bandejas
                        </div>
                    </div>
                    <div class="kpi-card au d3">
                        <div class="kpi-label">Kilos Centros</div>
                        <div class="kpi-value counter" data-target="{{ $kpiC }}" data-dec="1" data-suffix="kg">
                            {{ $kpiCFormatted }} kg</div>
                        <div class="kpi-badge bg-amber-500/20 text-amber-400">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16" />
                            </svg>
                            Centros
                        </div>
                    </div>
                    <div class="kpi-card au d4">
                        <div class="kpi-label">Kilos AGRAK <span style="opacity:.4">(est.)</span></div>
                        <div id="kilosAgrakHero" class="kpi-value">—</div>
                        <div class="kpi-badge bg-emerald-500/20 text-emerald-400">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10" />
                            </svg>
                            AGRAK
                        </div>
                    </div>
                    <div class="kpi-card au d5">
                        <div class="kpi-label">Bandejas AGRAK</div>
                        <div class="kpi-value counter" data-target="{{ $kpiBandejasAgrak ?? 0 }}" data-dec="0">
                            {{ number_format($kpiBandejasAgrak ?? 0, 0, ',', '.') }}</div>
                        <div class="kpi-badge bg-indigo-500/20 text-indigo-400">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4" />
                            </svg>
                            AGRAK
                        </div>
                    </div>
                    <div class="kpi-card au d6">
                        <div class="kpi-label">Bins AGRAK</div>
                        <div class="kpi-value counter" data-target="{{ $kpiBinsAgrak ?? 0 }}" data-dec="0">
                            {{ number_format($kpiBinsAgrak ?? 0, 0, ',', '.') }}</div>
                        <div class="kpi-badge bg-violet-500/20 text-violet-400">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8" />
                            </svg>
                            AGRAK
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ BODY ══ --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

            {{-- Peso promedio --}}
            <div class="d-card au d1">
                <div
                    class="px-6 py-4 border-b border-gray-100 dark:border-gray-800/80 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Ajuste · Peso promedio AGRAK</p>
                        <p class="text-xs text-gray-400 mt-0.5">Multiplica bandejas × kg/bandeja para estimar kilos
                            totales</p>
                    </div>
                </div>
                <div class="px-6 py-4 flex items-center gap-5 flex-wrap">
                    <div class="flex items-center gap-3">
                        <input id="kgPromedio" type="number" step="0.1" min="0" value="{{ $kgPromedioAgrak }}" class="w-24 text-right px-3 py-2 text-sm font-bold rounded-xl
                                  border border-gray-200 dark:border-gray-700
                                  bg-gray-50 dark:bg-gray-900
                                  text-gray-900 dark:text-gray-100
                                  focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
                        <span class="text-sm text-gray-500">kg / bandeja</span>
                    </div>
                    <div class="h-5 w-px bg-gray-200 dark:bg-gray-700 hidden sm:block"></div>
                    <p class="text-sm text-gray-500">
                        Activo: <span id="kgPromedioLabel" class="font-bold text-gray-800 dark:text-gray-100">—</span>
                    </p>
                    <div class="h-5 w-px bg-gray-200 dark:bg-gray-700 hidden sm:block"></div>
                    <p class="text-sm text-gray-500">
                        Estimado: <span id="kilosAgrak"
                            class="font-bold text-emerald-600 dark:text-emerald-400">—</span>
                    </p>
                    <button onclick="applyKgPromedio()"
                        class="ml-auto px-5 py-2.5 text-sm font-bold rounded-xl
                               bg-indigo-600 hover:bg-indigo-700 active:scale-95
                               text-white transition-all duration-150 shadow-sm shadow-indigo-200 dark:shadow-indigo-900">
                        Aplicar
                    </button>
                </div>
            </div>

            {{-- Tabla empresas --}}
            <div class="au d2">
                <div class="s-tag">
                    <span class="s-dot bg-indigo-500"></span>
                    Empresas &middot; Kilos informados por centros
                </div>

                {{-- Desktop --}}
                <div class="hidden lg:block d-card">
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
                                    <td class="r text-right font-semibold">{{ $row->total_guias }}</td>
                                    <td class="text-right">
                                        @if($row->guias_sin_match > 0)
                                            <span
                                                class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ $row->guias_sin_match }}</span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right text-gray-600 dark:text-gray-400 font-medium">
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
                        <div class="d-card p-4">
                            <div class="flex items-start justify-between mb-3">
                                <a href="{{ route('centros.detalle', ['contacto' => $row->contacto]) }}"
                                    class="font-bold text-sm text-indigo-600 dark:text-indigo-400 hover:underline">{{ $row->contacto }}</a>
                                <span
                                    class="text-sm font-black text-emerald-600 dark:text-emerald-400 ml-2">{{ number_format($row->total_kilos, 1, ',', '.') }}
                                    kg</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="bg-gray-50 dark:bg-gray-900/60 rounded-xl p-2.5">
                                    <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-1">Guías</p>
                                    <p class="text-sm font-black text-gray-800 dark:text-gray-200">{{ $row->total_guias }}
                                    </p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-900/60 rounded-xl p-2.5">
                                    <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 mb-1">Sin resp.
                                    </p>
                                    <p
                                        class="text-sm font-black {{ $row->guias_sin_match > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-300 dark:text-gray-700' }}">
                                        {{ $row->guias_sin_match > 0 ? $row->guias_sin_match : '—' }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-900/60 rounded-xl p-2.5">
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

            {{-- Gráficos --}}
            <div class="au d3">
                <div class="s-tag">
                    <span class="s-dot bg-blue-500"></span>
                    Evolución temporal
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    @php
                        $graficos = [
                            ['id' => 'kilosChart', 'title' => 'Kilos ODOO', 'sub' => 'Diario', 'dot' => 'bg-blue-500'],
                            ['id' => 'centrosChart', 'title' => 'Kilos Centros', 'sub' => 'Diario', 'dot' => 'bg-indigo-500'],
                            ['id' => 'contactosChart', 'title' => 'Kilos por empresa', 'sub' => 'Centros', 'dot' => 'bg-emerald-500'],
                            ['id' => 'bandejasAgrakChart', 'title' => 'Bandejas AGRAK', 'sub' => 'Diario', 'dot' => 'bg-violet-500'],
                            ['id' => 'binsAgrakChart', 'title' => 'Bins AGRAK', 'sub' => 'Diario', 'dot' => 'bg-purple-500'],
                            ['id' => 'binsPorCuartelChart', 'title' => 'Bins por Cuartel', 'sub' => 'AGRAK', 'dot' => 'bg-orange-500'],
                        ];
                    @endphp

                    @foreach($graficos as $g)
                        <div class="d-card au d{{ $loop->index + 4 }}">
                            <div class="px-5 pt-4 pb-0 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $g['dot'] }} flex-shrink-0"></span>
                                    <span
                                        class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ $g['title'] }}</span>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400
                                         bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">{{ $g['sub'] }}</span>
                            </div>
                            <div class="p-5">
                                <div class="relative h-44"><canvas id="{{ $g['id'] }}"></canvas></div>
                            </div>
                        </div>
                    @endforeach

                    <div class="md:col-span-2 d-card au d8">
                        <div class="px-5 pt-4 pb-0 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-sky-500 flex-shrink-0"></span>
                                <span class="text-sm font-bold text-gray-800 dark:text-gray-100">Cosechadora
                                    AGRAK</span>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400
                                     bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">Total / máquina</span>
                        </div>
                        <div class="p-5">
                            <div class="relative h-64"><canvas id="maquinasAgrakChart"></canvas></div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
    <script>
        // ── Helpers ───────────────────────────────────────────────────────────────
        const isDark = () => document.documentElement.classList.contains('dark');

        function fmtCL(v, dec = 1) {
            const n = Number(v);
            return isNaN(n) ? '—' : n.toLocaleString('es-CL', { minimumFractionDigits: dec, maximumFractionDigits: 2 });
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
                        x: { grid: { display: false }, border: { display: false }, ticks: { color: dark ? '#475569' : '#94a3b8', font: { size: 10 }, maxRotation: 45 } },
                        y: {
                            beginAtZero: true, border: { display: false },
                            grid: { color: dark ? 'rgba(255,255,255,.04)' : 'rgba(0,0,0,.04)' },
                            ticks: {
                                color: dark ? '#475569' : '#94a3b8', font: { size: 10 },
                                callback: v => fmtCL(v) + (unit ? ' ' + unit : '')
                            }
                        }
                    }
                }
            });
        }

        // ── Counter up ────────────────────────────────────────────────────────────
        function counterUp(el, target, duration = 950, dec = 0) {
            const t0 = performance.now();
            const suffix = (el.dataset.suffix ? ' ' + el.dataset.suffix : '');
            function tick(now) {
                const p = Math.min((now - t0) / duration, 1);
                const e = 1 - Math.pow(1 - p, 3);
                el.textContent = fmtCL(target * e, dec) + suffix;
                if (p < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }

        // ── Kg promedio ───────────────────────────────────────────────────────────
        const BANDEJAS = {{ (int) ($kpiBandejasAgrak ?? 0) }};

        function recalcularKgAgrak() {
            const kg = parseFloat(document.getElementById('kgPromedio')?.value) || 0;
            const txt = fmtCL(BANDEJAS * kg) + ' kg';
            const c = document.getElementById('kilosAgrak');
            const h = document.getElementById('kilosAgrakHero');
            if (c) c.textContent = txt;
            if (h) h.textContent = txt;
        }

        function actualizarLabelKg() {
            const i = document.getElementById('kgPromedio');
            const l = document.getElementById('kgPromedioLabel');
            if (i && l) l.textContent = String(parseFloat(i.value) || 0).replace('.', ',') + ' kg / bandeja';
        }

        function applyKgPromedio() {
            const kg = parseFloat(document.getElementById('kgPromedio')?.value) || 0;
            fetch(KG_PROMEDIO_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ kg_promedio: kg })
            })
                .then(r => { if (!r.ok) throw 0; return r.json(); })
                .then(() => { recalcularKgAgrak(); actualizarLabelKg(); })
                .catch(() => alert('No se pudo guardar el promedio'));
        }

        // ── Init ──────────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {

            // Counters
            document.querySelectorAll('.counter[data-target]').forEach(el => {
                const target = parseFloat(el.dataset.target || 0);
                const dec = parseInt(el.dataset.dec || 0);
                counterUp(el, target, 1000, dec);
            });

            // Live input
            const kgInput = document.getElementById('kgPromedio');
            if (kgInput) kgInput.addEventListener('input', () => { recalcularKgAgrak(); actualizarLabelKg(); });

            actualizarLabelKg();
            recalcularKgAgrak();

            // Charts
            makeChart('kilosChart', @json($chartLabels ?? []), @json($chartData ?? []), { color: '#3b82f6', unit: 'kg' });
            makeChart('centrosChart', @json($chartLabels ?? []), @json($centrosData ?? []), { color: '#6366f1', unit: 'kg' });
            makeChart('contactosChart', @json($contactosLabels ?? []), @json($contactosKilos ?? []), { color: '#10b981', unit: 'kg' });
            makeChart('bandejasAgrakChart', @json($bandejasAgrakLabels ?? []), @json($bandejasAgrakData ?? []), { color: '#8b5cf6', unit: 'bandejas' });
            makeChart('binsAgrakChart', @json($binsAgrakLabels ?? []), @json($binsAgrakData ?? []), { color: '#a855f7', unit: 'bins' });
            makeChart('binsPorCuartelChart', @json($binsPorCuartelLabels ?? []), @json($binsPorCuartelData ?? []), { color: '#f97316', unit: 'bins' });
            makeChart('maquinasAgrakChart', @json($maquinasLabels ?? []), @json($maquinasTotales ?? []), { color: '#0ea5e9', unit: 'bins' });
        });
    </script>

</x-app-layout>