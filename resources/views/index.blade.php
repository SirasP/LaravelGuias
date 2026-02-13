{{-- ═══════════════════════════════════════════════════════════════════════════
     WebSocket + Auth init  (antes del layout para que esté disponible global)
 ═══════════════════════════════════════════════════════════════════════════ --}}
<script>
    const KG_PROMEDIO_URL = "{{ route('agrak.kg-promedio') }}";

    window.AUTH_USER = {
        id:   {{ auth()->id() }},
        name: "{{ auth()->user()->name }}",
        role: "{{ auth()->user()->role }}"
    };

    const ws = new WebSocket("ws://109.72.119.62/ws");

    ws.onopen = () => ws.send(JSON.stringify({
        type:   'register',
        userId: window.AUTH_USER.id,
        name:   window.AUTH_USER.name
    }));

    ws.onmessage = e => {
        let data;
        try { data = JSON.parse(e.data); } catch { return; }

        if (window.AUTH_USER.id !== 1) return;

        if (data.type === 'user_connected') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'info',
                title: `${data.name} se conectó`, showConfirmButton: false,
                showCloseButton: true, timer: null });
        }
        if (data.type === 'xml_entrada') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                title: data.titulo, text: data.mensaje,
                showConfirmButton: false, timer: null });
        }
    };
</script>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">
                    Dashboard
                </h2>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5">
                    Resumen últimos 120 días
                </p>
            </div>
            <span class="hidden sm:inline-flex items-center gap-1.5 text-xs font-medium
                         px-2.5 py-1 rounded-full
                         bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400
                         border border-green-100 dark:border-green-800">
                <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
                En vivo
            </span>
        </div>
    </x-slot>

    {{-- ── Notificaciones (solo admin) ──────────────────────────────────────── --}}
    @if(auth()->id() === 1 && $notificaciones->count())
        @php
            $notificacionesData = $notificaciones->map(fn($n) => [
                'id'           => $n->id,
                'tipo'         => $n->tipo ?? null,
                'movimiento_id'=> $n->movimiento_id ?? null,
                'titulo'       => $n->titulo,
                'mensaje'      => $n->mensaje,
                'url_leer'     => route('fuelcontrol.notificaciones.leer', $n->id),
                'url_xml'      => isset($n->tipo) &&
                                  in_array($n->tipo, ['xml_revision','xml_entrada']) &&
                                  $n->movimiento_id
                                      ? route('fuelcontrol.xml.show', $n->movimiento_id)
                                      : null,
            ])->values();
        @endphp
        <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const notifs  = @json($notificacionesData);
            const csrf    = document.querySelector('meta[name="csrf-token"]')?.content;

            for (const notif of notifs) {
                const result = await Swal.fire({
                    toast: true, position: 'top-end',
                    icon: notif.url_xml ? 'info' : 'success',
                    title: notif.titulo, text: notif.mensaje,
                    showConfirmButton: !notif.url_xml,
                    confirmButtonText: '✔ Marcar como leída',
                    confirmButtonColor: '#16a34a',
                    showDenyButton: !!notif.url_xml,
                    denyButtonText: '📄 Ver XML',
                    showCloseButton: true, timer: null
                });

                if (result.isDenied && notif.url_xml) {
                    const mr = await Swal.fire({
                        title: notif.titulo, width: '75%',
                        showCloseButton: true, showConfirmButton: true,
                        confirmButtonText: '✔ Marcar como leída',
                        confirmButtonColor: '#16a34a',
                        html: '<div class="py-6 text-center">Cargando documento...</div>',
                        didOpen: async () => {
                            const c = Swal.getHtmlContainer();
                            try {
                                c.innerHTML = await (await fetch(notif.url_xml)).text();
                            } catch {
                                c.innerHTML = '<p class="text-red-500 text-center">Error al cargar</p>';
                            }
                        }
                    });
                    if (mr.isConfirmed && notif.url_leer)
                        await fetch(notif.url_leer, { method:'POST', headers:{'X-CSRF-TOKEN':csrf} });
                    continue;
                }
                if (result.isConfirmed && notif.url_leer)
                    await fetch(notif.url_leer, { method:'POST', headers:{'X-CSRF-TOKEN':csrf} });
            }
        });
        </script>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════
         LAYOUT PRINCIPAL
     ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        @php
            $kpi          = (float) $kpi5Dias;
            $kpiFormatted = $kpi == floor($kpi)
                ? number_format($kpi, 1, ',', '.')
                : number_format($kpi, 2, ',', '.');
            $kpiC          = (float) $kpiCentros;
            $kpiCFormatted = $kpiC == floor($kpiC)
                ? number_format($kpiC, 1, ',', '.')
                : number_format($kpiC, 2, ',', '.');
        @endphp

        {{-- ── Sección ODOO ──────────────────────────────────────────────────── --}}
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
                ODOO — últimos 120 días
            </p>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 font-medium">Kilos</p>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 mt-1 tracking-tight">
                                {{ $kpiFormatted }}
                                <span class="text-base font-semibold text-gray-400">kg</span>
                            </p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-green-50 dark:bg-green-900/20">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 font-medium">Bandejas</p>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 mt-1 tracking-tight">
                                {{ number_format($kpiBandejas ?? 0, 0, ',', '.') }}
                            </p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-900/20">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Sección AGRAK ─────────────────────────────────────────────────── --}}
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
                AGRAK — últimos 120 días
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">

                {{-- Kilos AGRAK (calculado) --}}
                <div class="col-span-2 sm:col-span-1 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-start justify-between">
                        <div class="min-w-0">
                            <p class="text-xs text-gray-400 dark:text-gray-500 font-medium">Kilos estimados</p>
                            <p id="kilosAgrak" class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 mt-1 tracking-tight">
                                —
                            </p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 shrink-0">
                            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                    </div>
                    {{-- Ajuste de peso promedio --}}
                    <div class="mt-4 pt-3 border-t border-gray-50 dark:border-gray-700 flex items-center justify-between relative">
                        <p class="text-[11px] text-gray-400">
                            Prom: <span id="kgPromedioLabel" class="font-semibold text-gray-600 dark:text-gray-300">—</span>
                        </p>
                        <button id="kgToggle" onclick="toggleKgPopover(event)"
                                class="inline-flex items-center gap-1 text-[11px] font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Ajustar
                        </button>
                        {{-- Popover --}}
                        <div id="kgPopover"
                             class="absolute right-0 top-9 w-60 z-40 hidden
                                    bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700
                                    rounded-2xl shadow-xl p-4">
                            <p class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1">Peso promedio por bandeja</p>
                            <p class="text-[11px] text-gray-400 mb-3">Usado para estimar kilos AGRAK</p>
                            <div class="flex items-center gap-2 mb-3">
                                <input id="kgPromedio" type="number" step="0.1" min="0"
                                       value="{{ $kgPromedioAgrak }}"
                                       class="w-20 text-right px-2 py-1.5 border border-gray-200 dark:border-gray-600
                                              rounded-lg text-sm bg-white dark:bg-gray-800 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none">
                                <span class="text-sm text-gray-500">kg / bandeja</span>
                            </div>
                            <button onclick="applyKgPromedio()"
                                    class="w-full py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors">
                                Aplicar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 font-medium">Bandejas</p>
                            <p id="bandejasAgrak" class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 mt-1 tracking-tight">
                                {{ number_format($kpiBandejasAgrak ?? 0, 0, ',', '.') }}
                            </p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-indigo-50 dark:bg-indigo-900/20">
                            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 font-medium">Bins</p>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 mt-1 tracking-tight">
                                {{ number_format($kpiBinsAgrak ?? 0, 0, ',', '.') }}
                            </p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-violet-50 dark:bg-violet-900/20">
                            <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── KPI Centros ───────────────────────────────────────────────────── --}}
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
                Centros — últimos 120 días
            </p>
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 font-medium">Kilos recepcionados</p>
                        <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 mt-1 tracking-tight">
                            {{ $kpiCFormatted }}
                            <span class="text-base font-semibold text-gray-400">kg</span>
                        </p>
                    </div>
                    <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-900/20">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Tabla de empresas ──────────────────────────────────────────────── --}}
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
                Empresas — kilos informados por centros
            </p>

            {{-- Desktop --}}
            <div class="hidden lg:block bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                            <th class="text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 px-5 py-3">Empresa</th>
                            <th class="text-right text-[11px] font-semibold uppercase tracking-wider text-gray-400 px-5 py-3">Guías</th>
                            <th class="text-right text-[11px] font-semibold uppercase tracking-wider text-gray-400 px-5 py-3">Sin respuesta</th>
                            <th class="text-right text-[11px] font-semibold uppercase tracking-wider text-gray-400 px-5 py-3">Bandejas ODOO</th>
                            <th class="text-right text-[11px] font-semibold uppercase tracking-wider text-gray-400 px-5 py-3">Kilos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        @foreach($kilosPorContacto as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-5 py-3.5 font-medium">
                                    <a href="{{ route('centros.detalle', ['contacto' => $row->contacto]) }}"
                                       class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ $row->contacto }}
                                    </a>
                                </td>
                                <td class="px-5 py-3.5 text-right font-medium text-gray-700 dark:text-gray-300">
                                    {{ $row->total_guias }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    @if($row->guias_sin_match > 0)
                                        <span class="inline-flex items-center justify-center min-w-[1.5rem] px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400">
                                            {{ $row->guias_sin_match }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right font-medium text-gray-700 dark:text-gray-300">
                                    {{ number_format($bandejasPorContacto[$row->contacto]->total_bandejas ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-green-600 dark:text-green-400">
                                    {{ number_format($row->total_kilos, 1, ',', '.') }} kg
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="lg:hidden space-y-2">
                @foreach($kilosPorContacto as $row)
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <a href="{{ route('centros.detalle', ['contacto' => $row->contacto]) }}"
                               class="font-semibold text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $row->contacto }}
                            </a>
                            <span class="text-sm font-bold text-green-600 dark:text-green-400">
                                {{ number_format($row->total_kilos, 1, ',', '.') }} kg
                            </span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2">
                                <p class="text-[10px] text-gray-400 font-medium">Guías</p>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $row->total_guias }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2">
                                <p class="text-[10px] text-gray-400 font-medium">Sin resp.</p>
                                <p class="text-sm font-bold {{ $row->guias_sin_match > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-300 dark:text-gray-600' }}">
                                    {{ $row->guias_sin_match > 0 ? $row->guias_sin_match : '—' }}
                                </p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2">
                                <p class="text-[10px] text-gray-400 font-medium">Bandejas</p>
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">
                                    {{ number_format($bandejasPorContacto[$row->contacto]->total_bandejas ?? 0, 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── Gráficos ───────────────────────────────────────────────────────── --}}
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
                Gráficos
            </p>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Kilos ODOO diarios</p>
                    <div class="relative h-48"><canvas id="kilosChart"></canvas></div>
                    @if(empty($chartLabels))
                        <p class="text-xs text-gray-400 mt-3 text-center">Sin datos</p>
                    @endif
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Kilos Centros diarios</p>
                    <div class="relative h-48"><canvas id="centrosChart"></canvas></div>
                    @if(empty($centrosLabels))
                        <p class="text-xs text-gray-400 mt-3 text-center">Sin datos</p>
                    @endif
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Kilos por empresa (Centros)</p>
                    <div class="relative h-48"><canvas id="contactosChart"></canvas></div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Bandejas AGRAK diarias</p>
                    <div class="relative h-48"><canvas id="bandejasAgrakChart"></canvas></div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Bins AGRAK diarios</p>
                    <div class="relative h-48"><canvas id="binsAgrakChart"></canvas></div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Bins por Cuartel — AGRAK</p>
                    <div class="relative h-48"><canvas id="binsPorCuartelChart"></canvas></div>
                </div>

                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Cosechadora AGRAK — total por máquina</p>
                    <div class="relative h-72"><canvas id="maquinasAgrakChart"></canvas></div>
                </div>

            </div>
        </div>

    </div>{{-- /max-w-7xl --}}

    {{-- ═══════════════════════════════════════════════════════════════════════
         SCRIPTS — UN SOLO BLOQUE CONSOLIDADO
     ═══════════════════════════════════════════════════════════════════════ --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>

    <script>
    // ── Helpers ──────────────────────────────────────────────────────────────
    function fmtCL(value, decimals = 1) {
        const n = Number(value);
        if (isNaN(n)) return '—';
        return n.toLocaleString('es-CL', {
            minimumFractionDigits:  decimals,
            maximumFractionDigits:  2
        });
    }

    function isDark() {
        return document.documentElement.classList.contains('dark');
    }

    function chartColors() {
        return {
            grid:   isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)',
            tick:   isDark() ? '#6b7280' : '#9ca3af',
        };
    }

    function makeChart(id, labels, data, { color = '#3b82f6', label = '', unit = '' } = {}) {
        const el = document.getElementById(id);
        if (!el || !labels?.length || !data?.length) return;
        const { grid, tick } = chartColors();
        new Chart(el, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label,
                    data: data.map(Number),
                    backgroundColor: color,
                    borderRadius: 5,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark() ? '#1f2937' : '#fff',
                        titleColor:      isDark() ? '#f3f4f6' : '#111827',
                        bodyColor:       isDark() ? '#9ca3af' : '#6b7280',
                        borderColor:     isDark() ? '#374151' : '#e5e7eb',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: ctx => ' ' + fmtCL(ctx.parsed.y) + (unit ? ' ' + unit : '')
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: tick, font: { size: 10 }, maxRotation: 45 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: grid },
                        ticks: {
                            color: tick,
                            font: { size: 10 },
                            callback: v => fmtCL(v) + (unit ? ' ' + unit : '')
                        }
                    }
                }
            }
        });
    }

    // ── Kg promedio ───────────────────────────────────────────────────────────
    const BANDEJAS_AGRAK = {{ (int)($kpiBandejasAgrak ?? 0) }};

    function recalcularKgAgrak() {
        const kg = parseFloat(document.getElementById('kgPromedio')?.value) || 0;
        const el = document.getElementById('kilosAgrak');
        if (el) el.textContent = fmtCL(BANDEJAS_AGRAK * kg) + ' kg';
    }

    function actualizarLabelKg() {
        const input = document.getElementById('kgPromedio');
        const label = document.getElementById('kgPromedioLabel');
        if (!input || !label) return;
        label.textContent = String(parseFloat(input.value) || 0).replace('.', ',') + ' kg / bandeja';
    }

    function toggleKgPopover(e) {
        e.stopPropagation();
        document.getElementById('kgPopover')?.classList.toggle('hidden');
    }

    function applyKgPromedio() {
        const kg = parseFloat(document.getElementById('kgPromedio')?.value) || 0;
        fetch(KG_PROMEDIO_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ kg_promedio: kg })
        })
        .then(r => { if (!r.ok) throw new Error(); return r.json(); })
        .then(() => {
            recalcularKgAgrak();
            actualizarLabelKg();
            document.getElementById('kgPopover')?.classList.add('hidden');
        })
        .catch(() => alert('No se pudo guardar el promedio'));
    }

    document.addEventListener('click', e => {
        const p = document.getElementById('kgPopover');
        const t = document.getElementById('kgToggle');
        if (p && t && !p.contains(e.target) && !t.contains(e.target))
            p.classList.add('hidden');
    });

    // ── Init ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {

        actualizarLabelKg();
        recalcularKgAgrak();

        // Kilos ODOO diarios
        makeChart('kilosChart',
            @json($chartLabels ?? []),
            @json($chartData   ?? []),
            { color: '#3b82f6', unit: 'kg' }
        );

        // Kilos Centros diarios
        makeChart('centrosChart',
            @json($chartLabels  ?? []),
            @json($centrosData  ?? []),
            { color: '#6366f1', unit: 'kg' }
        );

        // Kilos por empresa
        makeChart('contactosChart',
            @json($contactosLabels ?? []),
            @json($contactosKilos  ?? []),
            { color: '#10b981', unit: 'kg' }
        );

        // Bandejas AGRAK
        makeChart('bandejasAgrakChart',
            @json($bandejasAgrakLabels ?? []),
            @json($bandejasAgrakData   ?? []),
            { color: '#6366f1', unit: 'bandejas' }
        );

        // Bins AGRAK
        makeChart('binsAgrakChart',
            @json($binsAgrakLabels ?? []),
            @json($binsAgrakData   ?? []),
            { color: '#8b5cf6', unit: 'bins' }
        );

        // Cosechadoras
        makeChart('maquinasAgrakChart',
            @json($maquinasLabels  ?? []),
            @json($maquinasTotales ?? []),
            { color: '#0ea5e9', unit: 'bins' }
        );

        // Bins por cuartel
        makeChart('binsPorCuartelChart',
            @json($binsPorCuartelLabels ?? []),
            @json($binsPorCuartelData   ?? []),
            { color: '#f97316', unit: 'bins' }
        );
    });
    </script>

</x-app-layout>