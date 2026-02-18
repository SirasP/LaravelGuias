<x-app-layout>

    {{-- ═══════════════════════════════════════════════════
    HEADER
    ═══════════════════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 3v18m4-10v10m4-6v6M7 13v8M3 9v12" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Dashboard</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Control de combustible</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="hidden sm:flex items-center gap-1.5 text-xs text-gray-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                    En línea · {{ now()->format('d M Y, H:i') }}
                </span>
            </div>
        </div>
    </x-slot>

    {{-- ─── Notificaciones SweetAlert ─── --}}
    @if($notificaciones->count())
        @php
            $notificacionesData = $notificaciones->map(fn($n) => [
                'id' => $n->id,
                'tipo' => $n->tipo,
                'movimiento_id' => $n->movimiento_id,
                'titulo' => $n->titulo,
                'mensaje' => $n->mensaje,
                'estado' => $n->estado ?? null,
                'url_leer' => route('fuelcontrol.notificaciones.leer', $n->id),
                'url_xml' => in_array($n->tipo, ['xml_revision', 'xml_entrada']) && $n->movimiento_id
                    ? route('fuelcontrol.xml.show', $n->movimiento_id) : null,
                'url_aprobar' => in_array($n->tipo, ['xml_revision', 'xml_entrada']) && $n->movimiento_id
                    ? route('fuelcontrol.xml.aprobar', $n->movimiento_id) : null,
                'url_rechazar' => in_array($n->tipo, ['xml_revision', 'xml_entrada']) && $n->movimiento_id
                    ? route('fuelcontrol.xml.rechazar', $n->movimiento_id) : null,
            ])->values();
        @endphp
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const notificaciones = @json($notificacionesData);
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const post = (url) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } });

                (async () => {
                    for (const n of notificaciones) {
                        const r = await Swal.fire({
                            toast: true, position: 'top-end',
                            icon: n.url_xml ? 'info' : 'success',
                            title: n.titulo, text: n.mensaje,
                            showConfirmButton: !n.url_xml,
                            confirmButtonText: '✔ Marcar leída', confirmButtonColor: '#16a34a',
                            showDenyButton: !!n.url_xml, denyButtonText: '📄 Ver XML',
                            showCloseButton: true, timer: null,
                        });
                        if (r.isDenied && n.url_xml) {
                            const esPendiente = n.estado === 'pendiente';
                            const mr = await Swal.fire({
                                title: esPendiente ? 'Detalle XML' : `Documento ${n.estado}`,
                                width: '75%', showCloseButton: true,
                                showConfirmButton: esPendiente, confirmButtonText: '✔ Aprobar', confirmButtonColor: '#16a34a',
                                showDenyButton: esPendiente, denyButtonText: '✖ Rechazar', denyButtonColor: '#dc2626',
                                showCancelButton: true, cancelButtonText: 'Cerrar',
                                html: '<div class="py-8 text-center text-gray-400">Cargando…</div>',
                                didOpen: async () => {
                                    try { Swal.getHtmlContainer().innerHTML = await (await fetch(n.url_xml)).text(); }
                                    catch { Swal.getHtmlContainer().innerHTML = '<p class="text-red-500 text-center p-4">Error al cargar</p>'; }
                                }
                            });
                            if (esPendiente && mr.isConfirmed && n.url_aprobar) {
                                try {
                                    const res = await post(n.url_aprobar); const data = await res.json();
                                    if (!res.ok) throw new Error(data.message);
                                    if (n.url_leer) await post(n.url_leer);
                                    await Swal.fire({ icon: 'success', title: 'Aprobado', text: 'Stock ingresado correctamente', timer: 2000, showConfirmButton: false });
                                } catch (e) { await Swal.fire({ icon: 'error', title: 'Error', text: e.message }); }
                                continue;
                            }
                            if (esPendiente && mr.isDenied && n.url_rechazar) {
                                try {
                                    const res = await post(n.url_rechazar); const data = await res.json();
                                    if (!res.ok) throw new Error(data.message);
                                    if (n.url_leer) await post(n.url_leer);
                                    await Swal.fire({ icon: 'info', title: 'Rechazado', timer: 2000, showConfirmButton: false });
                                } catch (e) { await Swal.fire({ icon: 'error', title: 'Error', text: e.message }); }
                                continue;
                            }
                            if (mr.dismiss === Swal.DismissReason.cancel && !esPendiente && n.url_leer) await post(n.url_leer);
                            continue;
                        }
                        if (r.isConfirmed && n.url_leer) await post(n.url_leer);
                    }
                })();
            });
        </script>
    @endif

    <style>
        [x-cloak] {
            display: none !important;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0
            }

            to {
                opacity: 1
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(.95)
            }

            to {
                opacity: 1;
                transform: scale(1)
            }
        }

        @keyframes slideBar {
            from {
                width: 0
            }

            to {
                width: var(--w)
            }
        }

        .au {
            animation: fadeUp .45s cubic-bezier(.22, 1, .36, 1) both
        }

        .au2 {
            animation: scaleIn .35s cubic-bezier(.22, 1, .36, 1) both
        }

        .d1 {
            animation-delay: .05s
        }

        .d2 {
            animation-delay: .11s
        }

        .d3 {
            animation-delay: .17s
        }

        .d4 {
            animation-delay: .23s
        }

        .d5 {
            animation-delay: .29s
        }

        .d6 {
            animation-delay: .35s
        }

        /* ── Page ── */
        .page-bg {
            background: #f1f5f9;
            min-height: 100%
        }

        .dark .page-bg {
            background: #0d1117
        }

        /* ── Panel ── */
        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            overflow: hidden
        }

        .dark .panel {
            background: #161c2c;
            border-color: #1e2a3b
        }

        .panel-head {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px
        }

        .dark .panel-head {
            border-bottom-color: #1e2a3b
        }

        /* ── Stat card ── */
        .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .dark .stat-card {
            background: #161c2c;
            border-color: #1e2a3b
        }

        /* ── Mobile card ── */
        .m-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 16px
        }

        .dark .m-card {
            background: #161c2c;
            border-color: #1e2a3b
        }

        /* ── Table ── */
        .dt {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px
        }

        .dt thead tr {
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9
        }

        .dark .dt thead tr {
            background: #111827;
            border-bottom-color: #1e2a3b
        }

        .dt th {
            padding: 10px 18px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #94a3b8;
            white-space: nowrap
        }

        .dt th.r {
            text-align: right
        }

        .dt th.c {
            text-align: center
        }

        .dt td {
            padding: 13px 18px;
            border-bottom: 1px solid #f8fafc;
            color: #334155;
            vertical-align: middle
        }

        .dark .dt td {
            border-bottom-color: #1a2232;
            color: #cbd5e1
        }

        .dt tbody tr:last-child td {
            border-bottom: none
        }

        .dt tbody tr {
            transition: background .12s
        }

        .dt tbody tr:hover td {
            background: #f8fafc
        }

        .dark .dt tbody tr:hover td {
            background: #1a2436
        }

        /* ── Progress bar ── */
        .prog-track {
            height: 6px;
            background: #f1f5f9;
            border-radius: 99px;
            overflow: hidden;
            min-width: 120px
        }

        .dark .prog-track {
            background: #1e2a3b
        }

        .prog-fill {
            height: 100%;
            border-radius: 99px;
            animation: slideBar .8s cubic-bezier(.22, 1, .36, 1) both
        }

        @keyframes slideBar {
            from {
                width: 0
            }
        }

        /* ── Status badges ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700
        }

        .badge-ok {
            background: #dcfce7;
            color: #15803d
        }

        .badge-warn {
            background: #fef9c3;
            color: #854d0e
        }

        .badge-crit {
            background: #fee2e2;
            color: #dc2626
        }

        .dark .badge-ok {
            background: rgba(22, 163, 74, .15);
            color: #4ade80
        }

        .dark .badge-warn {
            background: rgba(234, 179, 8, .15);
            color: #facc15
        }

        .dark .badge-crit {
            background: rgba(220, 38, 38, .15);
            color: #f87171
        }

        /* ── Movement row ── */
        .mv-row {
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid #f8fafc;
            transition: background .12s;
            cursor: default
        }

        .dark .mv-row {
            border-bottom-color: #1a2232
        }

        .mv-row:last-child {
            border-bottom: none
        }

        .mv-row:hover {
            background: #f8fafc
        }

        .dark .mv-row:hover {
            background: #1a2436
        }

        .mv-row.clickable {
            cursor: pointer
        }

        .mv-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .mv-pos {
            background: #dcfce7
        }

        .mv-pos svg {
            color: #16a34a
        }

        .mv-neg {
            background: #fee2e2
        }

        .mv-neg svg {
            color: #dc2626
        }

        .dark .mv-pos {
            background: rgba(22, 163, 74, .15)
        }

        .dark .mv-pos svg {
            color: #4ade80
        }

        .dark .mv-neg {
            background: rgba(220, 38, 38, .15)
        }

        .dark .mv-neg svg {
            color: #f87171
        }

        /* ── Section toggle button ── */
        .sec-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0;
            background: none;
            border: none;
            cursor: pointer
        }

        .sec-toggle:focus {
            outline: none
        }

        .chevron-icon {
            width: 16px;
            height: 16px;
            color: #94a3b8;
            transition: transform .25s;
            flex-shrink: 0
        }

        .chevron-icon.rotated {
            transform: rotate(180deg)
        }

        /* ── Chart card ── */
        .chart-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 20px
        }

        .dark .chart-card {
            background: #161c2c;
            border-color: #1e2a3b
        }

        .chart-title {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .dark .chart-title {
            color: #cbd5e1
        }

        /* ── Divider label ── */
        .section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 0
        }

        /* ── Fuel icon dot ── */
        .fuel-dot {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center
        }

        /* ── Tipo pill ── */
        .tipo-ingreso {
            background: #dcfce7;
            color: #15803d
        }

        .tipo-egreso {
            background: #fee2e2;
            color: #dc2626
        }

        .dark .tipo-ingreso {
            background: rgba(22, 163, 74, .15);
            color: #4ade80
        }

        .dark .tipo-egreso {
            background: rgba(220, 38, 38, .15);
            color: #f87171
        }

        .tipo-pill {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: capitalize
        }
    </style>

    <div class="page-bg">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-7 space-y-6">

            {{-- ══════════════════════════════════════════
            STAT CARDS
            ══════════════════════════════════════════ --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

                {{-- Productos --}}
                <div class="stat-card au d1">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Productos</p>
                        <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ $resumen['total_productos'] }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                </div>

                {{-- Vehículos --}}
                <div class="stat-card au d2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Vehículos</p>
                        <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ $resumen['total_vehiculos'] }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 17h8m-4-4v4M3 11h18M3 11l2-5h14l2 5M3 11v6a1 1 0 001 1h1m12 0h1a1 1 0 001-1v-6" />
                        </svg>
                    </div>
                </div>

                {{-- Movimientos hoy --}}
                <div class="stat-card au d3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Hoy</p>
                        <p class="text-xl font-black text-gray-900 dark:text-gray-100 tabular-nums">{{ $resumen['movimientos_hoy'] }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

                {{-- Notificaciones pendientes --}}
                <div class="stat-card au d4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Notificaciones</p>
                        <p class="text-xl font-black text-rose-600 dark:text-rose-400 tabular-nums">{{ $notificaciones->count() }}</p>
                    </div>
                    <div class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-900/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════════
            GRÁFICOS
            ══════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- Gasolina --}}
                <div class="chart-card au d3">
                    <div class="flex items-center justify-between mb-5">
                        <div class="chart-title">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 inline-block"></span>
                            Gasolina — últimos 30 días
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Litros</span>
                    </div>
                    <div class="relative h-48">
                        <canvas id="gasolinaChart"></canvas>
                    </div>
                </div>

                {{-- Diesel --}}
                <div class="chart-card au d4">
                    <div class="flex items-center justify-between mb-5">
                        <div class="chart-title">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                            Diésel — últimos 30 días
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Litros</span>
                    </div>
                    <div class="relative h-48">
                        <canvas id="dieselChart"></canvas>
                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════════
            ROW: INVENTARIO + MOVIMIENTOS
            ══════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">

                {{-- ── INVENTARIO (3 cols) ───────────────── --}}
                <div class="xl:col-span-3 panel au d4" x-data="{ open: true }">
                    <div class="panel-head">
                        <button class="sec-toggle" @click="open = !open">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-gray-100">Inventario
                                    actual</span>
                                <span
                                    class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                                    {{ count($productos) }} productos
                                </span>
                            </div>
                            <svg class="chevron-icon" :class="{ rotated: !open }" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <div x-show="open" x-collapse>
                        {{-- Desktop table --}}
                        <div class="hidden lg:block overflow-x-auto">
                            <table class="dt">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="r">Stock (L)</th>
                                        <th>Nivel</th>
                                        <th class="c">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($productos as $p)
                                        @php
                                            $caps = ['diesel' => 10000, 'gasolina' => 100];
                                            $nombre = strtolower($p->nombre);
                                            $cap = $caps[$nombre] ?? 100;
                                            $pct = min(100, max(0, round(($p->cantidad / $cap) * 100)));
                                            [$barColor, $badgeClass, $estado] = match (true) {
                                                $pct < 20 => ['bg-rose-500', 'badge-crit', 'Crítico'],
                                                $pct < 50 => ['bg-amber-400', 'badge-warn', 'Bajo'],
                                                default => ['bg-emerald-500', 'badge-ok', 'Normal'],
                                            };
                                            $iconBg = match (true) {
                                                $pct < 20 => 'bg-rose-50 dark:bg-rose-900/20',
                                                $pct < 50 => 'bg-amber-50 dark:bg-amber-900/20',
                                                default => 'bg-emerald-50 dark:bg-emerald-900/20',
                                            };
                                            $iconColor = match (true) {
                                                $pct < 20 => 'text-rose-500',
                                                $pct < 50 => 'text-amber-500',
                                                default => 'text-emerald-500',
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <div class="fuel-dot {{ $iconBg }}">
                                                        <svg class="w-4 h-4 {{ $iconColor }}" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                        </svg>
                                                    </div>
                                                    <span
                                                        class="font-semibold text-gray-800 dark:text-gray-100">{{ ucfirst($p->nombre) }}</span>
                                                </div>
                                            </td>
                                            <td class="text-right font-bold tabular-nums text-gray-800 dark:text-gray-100">
                                                {{ number_format($p->cantidad, 2) }}
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-2">
                                                    <div class="prog-track flex-1">
                                                        <div class="{{ $barColor }} prog-fill" style="width:{{ $pct }}%">
                                                        </div>
                                                    </div>
                                                    <span
                                                        class="text-[11px] font-bold text-gray-400 w-9 text-right tabular-nums">
                                                        {{ $pct }}%
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge {{ $badgeClass }}">
                                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 8 8">
                                                        <circle cx="4" cy="4" r="3" />
                                                    </svg>
                                                    {{ $estado }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="py-12 text-center text-sm text-gray-400">
                                                No hay productos registrados.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile cards --}}
                        <div class="lg:hidden p-3 space-y-2">
                            @forelse($productos as $p)
                                @php
                                    $caps = ['diesel' => 10000, 'gasolina' => 100];
                                    $nombre = strtolower($p->nombre);
                                    $cap = $caps[$nombre] ?? 100;
                                    $pct = min(100, max(0, round(($p->cantidad / $cap) * 100)));
                                    [$barColor, $badgeClass, $estado] = match (true) {
                                        $pct < 20 => ['bg-rose-500', 'badge-crit', 'Crítico'],
                                        $pct < 50 => ['bg-amber-400', 'badge-warn', 'Bajo'],
                                        default => ['bg-emerald-500', 'badge-ok', 'Normal'],
                                    };
                                    $iconBg = match (true) {
                                        $pct < 20 => 'bg-rose-50 dark:bg-rose-900/20',
                                        $pct < 50 => 'bg-amber-50 dark:bg-amber-900/20',
                                        default => 'bg-emerald-50 dark:bg-emerald-900/20',
                                    };
                                    $iconColor = match (true) {
                                        $pct < 20 => 'text-rose-500',
                                        $pct < 50 => 'text-amber-500',
                                        default => 'text-emerald-500',
                                    };
                                @endphp
                                <div class="m-card">
                                    <div class="flex items-center justify-between gap-3 mb-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="fuel-dot {{ $iconBg }} shrink-0">
                                                <svg class="w-4 h-4 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ ucfirst($p->nombre) }}</p>
                                                <span class="badge {{ $badgeClass }} mt-0.5">
                                                    <svg class="w-2 h-2" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                                                    {{ $estado }}
                                                </span>
                                            </div>
                                        </div>
                                        <p class="text-base font-black tabular-nums text-gray-900 dark:text-gray-100 shrink-0">
                                            {{ number_format($p->cantidad, 2) }}
                                            <span class="text-[10px] font-bold text-gray-400">L</span>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="prog-track flex-1">
                                            <div class="{{ $barColor }} prog-fill" style="width:{{ $pct }}%"></div>
                                        </div>
                                        <span class="text-[11px] font-bold text-gray-400 tabular-nums">{{ $pct }}%</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-sm text-gray-400 py-8">
                                    No hay productos registrados.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- ── MOVIMIENTOS (2 cols) ──────────────── --}}
                <div class="xl:col-span-2 panel au d5 flex flex-col" x-data="{ open: true }">
                    <div class="panel-head">
                        <button class="sec-toggle" @click="open = !open">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-gray-100">Últimos
                                    movimientos</span>
                            </div>
                            <svg class="chevron-icon" :class="{ rotated: !open }" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <div x-show="open" x-collapse class="flex-1 overflow-y-auto" style="max-height:420px">
                        @forelse($movimientos as $m)
                            @php
                                $isPos = strtolower($m->tipo) === 'entrada' || strtolower($m->tipo) === 'ingreso';
                                $tipoClass = $isPos ? 'tipo-ingreso' : 'tipo-egreso';
                            @endphp
                            <div class="mv-row {{ !empty($m->xml_path) ? 'clickable' : '' }}" @if(!empty($m->xml_path))
                            onclick="abrirMovimiento({{ $m->id }})" @endif>
                                <div class="mv-icon {{ $isPos ? 'mv-pos' : 'mv-neg' }}">
                                    @if($isPos)
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-semibold text-gray-800 dark:text-gray-100 truncate">
                                        {{ ucfirst($m->producto_nombre ?? 'Producto #' . $m->producto_id) }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="tipo-pill {{ $tipoClass }}">{{ ucfirst($m->tipo) }}</span>
                                        <span class="text-[11px] text-gray-400">
                                            {{ \Carbon\Carbon::parse($m->fecha_movimiento)->format('d/m/Y') }}
                                        </span>
                                    </div>
                                </div>
                                <p
                                    class="text-sm font-bold tabular-nums shrink-0
                                       {{ $isPos ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ $isPos ? '+' : '-' }}{{ number_format(abs($m->cantidad), 2) }} L
                                </p>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center py-16 gap-2">
                                <div
                                    class="w-10 h-10 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-sm text-gray-400">Sin movimientos</p>
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>{{-- /row --}}

        </div>
    </div>

    {{-- ══ Chart.js ══ --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)';
            const tickColor = isDark ? '#475569' : '#94a3b8';
            const tooltipBg = isDark ? '#1e293b' : '#fff';
            const tooltipFg = isDark ? '#f1f5f9' : '#1e293b';

            const sharedOpts = (color) => ({
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: tooltipBg,
                        titleColor: tooltipFg,
                        bodyColor: tickColor,
                        borderColor: isDark ? '#1e2a3b' : '#e2e8f0',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 10,
                        callbacks: {
                            label: (ctx) => ` ${ctx.parsed.y.toLocaleString('es-CL')} L`,
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor, drawTicks: false },
                        ticks: { color: tickColor, font: { size: 10 }, maxRotation: 0 },
                        border: { display: false },
                    },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: tickColor, font: { size: 10 },
                            callback: (v) => v.toLocaleString('es-CL') + ' L'
                        },
                        border: { display: false },
                    }
                }
            });

            const gasCtx = document.getElementById('gasolinaChart');
            const dieCtx = document.getElementById('dieselChart');

            if (gasCtx) new Chart(gasCtx, {
                type: 'bar',
                data: {
                    labels: @json($labelsGasolina),
                    datasets: [{
                        label: 'Gasolina (L)',
                        data: @json($dataGasolina),
                        backgroundColor: 'rgba(99,102,241,.75)',
                        hoverBackgroundColor: 'rgba(99,102,241,1)',
                        borderRadius: 7,
                        borderSkipped: false,
                    }]
                },
                options: sharedOpts('indigo')
            });

            if (dieCtx) new Chart(dieCtx, {
                type: 'bar',
                data: {
                    labels: @json($labelsDiesel),
                    datasets: [{
                        label: 'Diésel (L)',
                        data: @json($dataDiesel),
                        backgroundColor: 'rgba(16,185,129,.75)',
                        hoverBackgroundColor: 'rgba(16,185,129,1)',
                        borderRadius: 7,
                        borderSkipped: false,
                    }]
                },
                options: sharedOpts('emerald')
            });

        });
    </script>

    <script>
        window.abrirMovimiento = async (id) => {
            try {
                const html = await (await fetch(`/fuelcontrol/xml/${id}`)).text();
                await Swal.fire({ width: '75%', showCloseButton: true, showConfirmButton: false, html });
            } catch {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el XML.' });
            }
        };
    </script>
    <script>
        window.switchTab = function (tab) {

            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            const content = document.getElementById('content-' + tab);
            if (content) content.classList.remove('hidden');

            const activeTab = document.getElementById('tab-' + tab);
            if (activeTab) {
                activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
                activeTab.classList.remove('border-transparent', 'text-gray-500');
            }
        };
    </script>
</x-app-layout>