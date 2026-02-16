<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                    :class="connected ? 'bg-emerald-600' : 'bg-gray-300 dark:bg-gray-700'" x-data x-cloak>
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">Gmail DTE</h2>
                    <p class="text-xs text-gray-400 mt-0.5 hidden sm:block">Importación automática de XML</p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0" x-data="{ connected: {{ $connected ? 'true' : 'false' }} }">
                @if($connected)
                    <span
                        class="hidden sm:flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                        Conectado
                    </span>

                    <button id="btn-run" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                               bg-indigo-600 hover:bg-indigo-700 active:scale-95
                               text-white transition shadow-sm shadow-indigo-200 dark:shadow-indigo-900">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span class="hidden sm:inline">Sincronizar ahora</span>
                        <span class="sm:hidden">Sync</span>
                    </button>

                    <form method="POST" action="{{ route('gmail.disconnect') }}" class="contents">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                                   border border-gray-200 dark:border-gray-700
                                   bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400
                                   hover:border-red-300 hover:text-red-600 transition">
                            Desconectar
                        </button>
                    </form>
                @else
                    <a href="{{ route('gmail.redirect') }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl
                          bg-indigo-600 hover:bg-indigo-700 active:scale-95
                          text-white transition shadow-sm shadow-indigo-200">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M12.545 10.239v3.821h5.445c-.712 2.315-2.647 3.972-5.445 3.972a6.033 6.033 0 110-12.064 5.963 5.963 0 014.123 1.632l2.917-2.917A10.025 10.025 0 0012.545 2C7.021 2 2.543 6.477 2.543 12s4.478 10 10.002 10c8.396 0 10.249-7.85 9.426-11.748l-9.426-.013z" />
                        </svg>
                        Conectar con Google
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <style>
        [x-cloak] {
            display: none !important;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(8px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        @keyframes ping {

            75%,
            100% {
                transform: scale(2);
                opacity: 0
            }
        }

        .au {
            animation: fadeUp .4s cubic-bezier(.22, 1, .36, 1) both
        }

        .d1 {
            animation-delay: .05s
        }

        .d2 {
            animation-delay: .1s
        }

        .d3 {
            animation-delay: .15s
        }

        .d4 {
            animation-delay: .2s
        }

        .page-bg {
            background: #f1f5f9;
            min-height: 100%
        }

        .dark .page-bg {
            background: #0d1117
        }

        /* Panel */
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
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px
        }

        .dark .panel-head {
            border-bottom-color: #1e2a3b
        }

        /* Stat card */
        .s-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px
        }

        .dark .s-card {
            background: #161c2c;
            border-color: #1e2a3b
        }

        /* Connect card */
        .connect-card {
            background: #fff;
            border: 2px dashed #c7d2fe;
            border-radius: 20px;
            padding: 48px 32px
        }

        .dark .connect-card {
            background: #161c2c;
            border-color: #312e81
        }

        /* Log line */
        .log-line {
            font-family: ui-monospace, monospace;
            font-size: 11px;
            padding: 3px 0;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            display: flex;
            align-items: baseline;
            gap: 8px
        }

        .dark .log-line {
            border-bottom-color: #1a2232;
            color: #94a3b8
        }

        .log-line:last-child {
            border: none
        }

        /* Status row */
        .mv-row {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #f8fafc;
            transition: background .12s
        }

        .dark .mv-row {
            border-bottom-color: #1a2232
        }

        .mv-row:last-child {
            border: none
        }

        .mv-row:hover {
            background: #f8fafc
        }

        .dark .mv-row:hover {
            background: #1a2436
        }

        /* Spinner */
        .spin {
            animation: spin 1s linear infinite
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700
        }

        .badge-ok {
            background: #dcfce7;
            color: #15803d
        }

        .badge-pend {
            background: #fef9c3;
            color: #854d0e
        }

        .badge-veh {
            background: #e0e7ff;
            color: #4338ca
        }

        .dark .badge-ok {
            background: rgba(22, 163, 74, .15);
            color: #4ade80
        }

        .dark .badge-pend {
            background: rgba(234, 179, 8, .15);
            color: #facc15
        }

        .dark .badge-veh {
            background: rgba(99, 102, 241, .15);
            color: #a5b4fc
        }

        /* Pulse dot */
        .pulse-dot {
            position: relative;
            display: inline-flex
        }

        .pulse-dot span {
            animation: ping 1.5s cubic-bezier(0, 0, .2, 1) infinite
        }
    </style>

    {{-- Flash --}}
    @if(session('success'))
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-5">
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl
                    bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-medium
                    dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-400">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-5">
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl
                    bg-red-50 border border-red-200 text-red-700 text-sm font-medium
                    dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
                {{ session('error') }}
            </div>
        </div>
    @endif

    <div class="page-bg">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5" x-data="gmailDashboard()">

            @if($connected)
                {{-- ══ CONECTADO ══════════════════════════════════════ --}}

                {{-- Stats --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="s-card au d1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Total importados</p>
                        <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100 tabular-nums"
                            x-text="stats.total ?? {{ $stats['total'] }}"></p>
                    </div>
                    <div class="s-card au d2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Pendientes</p>
                        <p class="text-2xl font-extrabold tabular-nums" :class="(stats.pendientes ?? {{ $stats['pendientes'] }}) > 0
                        ? 'text-amber-500' : 'text-gray-900 dark:text-gray-100'"
                            x-text="stats.pendientes ?? {{ $stats['pendientes'] }}"></p>
                    </div>
                    <div class="s-card au d3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Hoy</p>
                        <p class="text-2xl font-extrabold text-gray-900 dark:text-gray-100 tabular-nums"
                            x-text="stats.hoy ?? {{ $stats['hoy'] }}"></p>
                    </div>
                    <div class="s-card au d4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Último sync</p>
                        <p class="text-sm font-bold text-gray-700 dark:text-gray-300 leading-tight"
                            x-text="lastRun ?? '{{ $lastRun?->processed_at ?? '—' }}'"></p>
                    </div>
                </div>

                {{-- Consola + actividad --}}
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

                    {{-- Consola de salida (3 cols) --}}
                    <div class="lg:col-span-3 panel au d2">
                        <div class="panel-head">
                            <div class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100 flex-1">Consola</p>

                            {{-- Estado del proceso --}}
                            <div class="flex items-center gap-2">
                                <template x-if="running">
                                    <span
                                        class="flex items-center gap-1.5 text-xs text-indigo-600 dark:text-indigo-400 font-semibold">
                                        <svg class="w-3.5 h-3.5 spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Procesando…
                                    </span>
                                </template>
                                <template x-if="!running && lastOutput">
                                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">✔
                                        Completado</span>
                                </template>
                            </div>
                        </div>

                        {{-- Output --}}
                        <div class="bg-gray-950 dark:bg-black min-h-[220px] max-h-80 overflow-y-auto p-4 font-mono text-xs">
                            <template x-if="!lastOutput && !running">
                                <p class="text-gray-600">$ Listo para sincronizar. Presiona "Sincronizar ahora" para
                                    ejecutar.</p>
                            </template>
                            <template x-if="running">
                                <p class="text-indigo-400 animate-pulse">$ Ejecutando gmail:leer-xml…</p>
                            </template>
                            <template x-if="lastOutput">
                                <div>
                                    <template x-for="(line, i) in outputLines" :key="i">
                                        <div class="flex items-start gap-2 py-0.5">
                                            <span class="text-gray-600 shrink-0" x-text="'> '"></span>
                                            <span :class="{
                                        'text-emerald-400': line.startsWith('✔') || line.startsWith('📦'),
                                        'text-red-400':     line.startsWith('❌'),
                                        'text-amber-400':   line.startsWith('🚫') || line.startsWith('⚠'),
                                        'text-blue-400':    line.startsWith('📎') || line.startsWith('✉'),
                                        'text-indigo-300':  line.startsWith('⛽'),
                                        'text-gray-300':    true,
                                    }" x-text="line || '&nbsp;'"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Actividad reciente (2 cols) --}}
                    <div class="lg:col-span-2 panel au d3 flex flex-col">
                        <div class="panel-head">
                            <div
                                class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Actividad reciente</p>

                            {{-- Indicador de polling --}}
                            <div class="pulse-dot ml-auto shrink-0" title="Actualizando automáticamente">
                                <span class="absolute inline-flex h-2 w-2 rounded-full bg-emerald-400 opacity-75"
                                    style="animation:ping 2s ease-in-out infinite"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto" style="max-height:300px">
                            <template x-for="m in recentItems" :key="m.id">
                                <div class="mv-row">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :class="m.cantidad > 0
                                     ? 'bg-emerald-50 dark:bg-emerald-900/20'
                                     : 'bg-red-50 dark:bg-red-900/20'">
                                        <svg class="w-3.5 h-3.5"
                                            :class="m.cantidad > 0 ? 'text-emerald-600' : 'text-red-500'" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-gray-700 dark:text-gray-300 truncate"
                                            x-text="(m.cantidad > 0 ? '+' : '') + Number(m.cantidad).toLocaleString('es-CL') + ' L'">
                                        </p>
                                        <p class="text-[10px] text-gray-400 truncate font-mono" x-text="m.referencia"></p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="badge" :class="{
                                          'badge-ok': m.estado === 'aprobado',
                                          'badge-pend': m.estado === 'pendiente',
                                          'badge-veh': m.tipo === 'vehiculo',
                                      }" x-text="m.estado"></span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="recentItems.length === 0">
                                <div class="flex flex-col items-center justify-center py-10 text-gray-400">
                                    <svg class="w-8 h-8 mb-2 opacity-40" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-xs">Sin importaciones aún</p>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>

                {{-- Información de configuración --}}
                <div class="panel au d4">
                    <div class="panel-head">
                        <div class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0" />
                            </svg>
                        </div>
                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Automatización</p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            Para que la sincronización ocurra automáticamente, agrega esta línea al <strong>cron</strong>
                            del servidor:
                        </p>
                        <div
                            class="bg-gray-950 rounded-xl px-4 py-3 font-mono text-xs text-emerald-400 flex items-center justify-between gap-4">
                            <span>* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</span>
                            <button
                                onclick="navigator.clipboard.writeText(this.closest('div').querySelector('span').textContent)"
                                class="shrink-0 text-gray-500 hover:text-white transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-2">
                            El comando <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">gmail:leer-xml</code>
                            está configurado para ejecutarse cada 5 minutos en <code
                                class="bg-gray-100 dark:bg-gray-800 px-1 rounded">app/Console/Kernel.php</code>.
                        </p>
                    </div>
                </div>

            @else
                {{-- ══ NO CONECTADO ═════════════════════════════════ --}}
                <div class="connect-card text-center au d1">

                    {{-- Logo Gmail animado --}}
                    <div class="w-20 h-20 rounded-2xl bg-white dark:bg-gray-800 shadow-lg border border-gray-100 dark:border-gray-700
                        flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                                stroke="#e2e8f0" stroke-width="1.5" fill="none" />
                            <path d="M22 6l-10 7L2 6" stroke="#6366f1" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </div>

                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                        Conecta tu cuenta de Gmail
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-8 leading-relaxed">
                        Autoriza el acceso para que el sistema lea automáticamente los correos con archivos XML DTE
                        y actualice el inventario de combustible en tiempo real.
                    </p>

                    {{-- Pasos --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-lg mx-auto mb-8 text-left">
                        <div class="flex items-start gap-3">
                            <div class="w-7 h-7 rounded-full bg-indigo-600 text-white text-xs font-bold
                                flex items-center justify-center shrink-0 mt-0.5">1</div>
                            <div>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-200">Autorizar</p>
                                <p class="text-[11px] text-gray-400">Clic en "Conectar con Google"</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-7 h-7 rounded-full bg-indigo-600 text-white text-xs font-bold
                                flex items-center justify-center shrink-0 mt-0.5">2</div>
                            <div>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-200">Google autentica</p>
                                <p class="text-[11px] text-gray-400">Selecciona tu cuenta</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-7 h-7 rounded-full bg-emerald-600 text-white text-xs font-bold
                                flex items-center justify-center shrink-0 mt-0.5">✔</div>
                            <div>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-200">Conectado</p>
                                <p class="text-[11px] text-gray-400">Sync automático activado</p>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('gmail.redirect') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl
                      bg-indigo-600 hover:bg-indigo-700 active:scale-95
                      text-white font-semibold text-sm transition
                      shadow-lg shadow-indigo-200 dark:shadow-indigo-900">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M12.545 10.239v3.821h5.445c-.712 2.315-2.647 3.972-5.445 3.972a6.033 6.033 0 110-12.064 5.963 5.963 0 014.123 1.632l2.917-2.917A10.025 10.025 0 0012.545 2C7.021 2 2.543 6.477 2.543 12s4.478 10 10.002 10c8.396 0 10.249-7.85 9.426-11.748l-9.426-.013z" />
                        </svg>
                        Conectar con Google
                    </a>

                    <p class="text-[11px] text-gray-400 mt-4">
                        Solo se solicita permiso para leer y marcar correos. Nunca se enviarán correos.
                    </p>
                </div>
            @endif

        </div>
    </div>

    <script>
        function gmailDashboard() {
            return {
                connected:   {{ $connected ? 'true' : 'false' }},
                running: false,
                lastOutput: null,
                outputLines: [],
                stats: {
                    total:      {{ $stats['total'] }},
                    pendientes: {{ $stats['pendientes'] }},
                    hoy:        {{ $stats['hoy'] }},
                },
                lastRun: '{{ $lastRun?->processed_at ?? '—' }}',
                recentItems: @json($recent),
                pollInterval: null,

                init() {
                    // ── Botón "Sincronizar ahora" ──────────────────
                    const btn = document.getElementById('btn-run');
                    if (btn) {
                        btn.addEventListener('click', () => this.runSync());
                    }

                    // ── Polling automático cada 30 s ───────────────
                    if (this.connected) {
                        this.pollInterval = setInterval(() => this.pollStatus(), 30_000);
                    }
                },

                async runSync() {
                    if (this.running) return;
                    this.running = true;
                    this.lastOutput = null;
                    this.outputLines = [];

                    try {
                        const res = await fetch('{{ route('gmail.run') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            }
                        });
                        const data = await res.json();

                        this.lastOutput = data.output ?? '';
                        this.outputLines = this.lastOutput.split('\n').filter(l => l.trim());

                        // Actualizar stats después del run
                        await this.pollStatus();

                    } catch (e) {
                        this.lastOutput = 'Error al ejecutar: ' + e.message;
                        this.outputLines = [this.lastOutput];
                    } finally {
                        this.running = false;
                    }
                },

                async pollStatus() {
                    try {
                        const res = await fetch('{{ route('gmail.status') }}', {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();

                        this.connected = data.connected;
                        this.stats = data.stats;
                        this.lastRun = data.last_run ?? '—';
                        this.recentItems = data.recent;

                    } catch { /* silencioso */ }
                },

                destroy() {
                    if (this.pollInterval) clearInterval(this.pollInterval);
                }
            };
        }
    </script>

</x-app-layout>