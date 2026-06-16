<style>
    @keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    .au { animation: fadeUp .5s cubic-bezier(.16, 1, .3, 1) both; }
    .d1 { animation-delay: .05s; }
    .d2 { animation-delay: .10s; }
    .d3 { animation-delay: .15s; }
    .d4 { animation-delay: .20s; }

    /* Glassmorphism premium theme */
    .t-card-glow {
        background: rgba(255, 255, 255, 0.65);
        backdrop-filter: blur(24px) saturate(180%);
        -webkit-backdrop-filter: blur(24px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 24px;
        box-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(.16, 1, .3, 1);
        overflow: hidden;
    }
    .t-card-glow:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -15px rgba(99, 102, 241, 0.08);
        border-color: rgba(99, 102, 241, 0.25);
    }
    .dark .t-card-glow {
        background: rgba(15, 23, 42, 0.45);
        border-color: rgba(255, 255, 255, 0.06);
        box-shadow: 0 20px 40px -20px rgba(0, 0, 0, 0.4);
    }
    .dark .t-card-glow:hover {
        border-color: rgba(99, 102, 241, 0.2);
        box-shadow: 0 20px 40px -15px rgba(99, 102, 241, 0.12);
    }

    /* Table styles */
    .dt { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
    .dt th {
        padding: 14px 16px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
        background: rgba(248, 250, 252, 0.6);
        border-bottom: 1px solid rgba(226, 232, 240, 0.7);
    }
    .dark .dt th { 
        color: #94a3b8;
        background: rgba(15, 23, 42, 0.7);
        border-bottom-color: rgba(255, 255, 255, 0.05);
    }
    .dt td {
        padding: 14px 16px;
        border-bottom: 1px solid rgba(241, 245, 249, 0.6);
        color: #334155;
        transition: all 0.2s;
    }
    .dark .dt td {
        border-bottom-color: rgba(255, 255, 255, 0.03);
        color: #cbd5e1;
    }
    .dt tbody tr:hover td {
        background: rgba(248, 250, 252, 0.8);
    }
    .dark .dt tbody tr:hover td {
        background: rgba(255, 255, 255, 0.015);
    }
    .dt th.r, .dt td.r { text-align: right; }
    .dt th.c, .dt td.c { text-align: center; }

    /* Diff indicator badges */
    .diff-badge {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        padding: 2px 6px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
    }
    .diff-up { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
    .diff-down { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }
</style>

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between w-full gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">Comparativa de Cosechas</h2>
                <x-breadcrumbs :items="[
                    ['label' => 'Inicio', 'url' => route('index')],
                    ['label' => 'Comparación Cosechas'],
                ]" />
            </div>
            <div>
                <a href="{{ route('index') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-xl
                          bg-gray-100/80 dark:bg-gray-800/80 hover:bg-gray-200 dark:hover:bg-gray-700
                          text-gray-700 dark:text-gray-200 border border-gray-200/40 dark:border-gray-700/40
                          transition duration-200 active:scale-95 shadow-sm">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver al Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        
        {{-- ══ SECTOR DE FILTROS ══ --}}
        <div class="au d1 bg-gradient-to-r from-indigo-50/50 to-emerald-50/20 dark:from-indigo-950/10 dark:to-emerald-950/5 p-6 rounded-3xl border border-indigo-100/30 dark:border-indigo-900/10 shadow-sm">
            <form method="GET" action="{{ route('dashboard.comparacion') }}" class="w-full flex flex-col md:flex-row md:items-center gap-6">
                <div class="flex-1 w-full grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-indigo-500/80 dark:text-indigo-400 uppercase tracking-widest flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            Temporada Principal (A)
                        </span>
                        <select name="season_a" onchange="this.form.submit()" 
                                class="w-full rounded-2xl border border-gray-200 dark:border-gray-800 
                                       bg-white dark:bg-gray-900 text-sm font-bold p-3.5 outline-none 
                                       focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-800 dark:text-gray-100 transition shadow-sm">
                            @foreach($seasons as $s)
                                <option value="{{ $s }}" {{ $seasonA === $s ? 'selected' : '' }}>Temporada {{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-xs font-bold text-emerald-500/80 dark:text-emerald-400 uppercase tracking-widest flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Temporada de Comparación (B)
                        </span>
                        <select name="season_b" onchange="this.form.submit()" 
                                class="w-full rounded-2xl border border-gray-200 dark:border-gray-800 
                                       bg-white dark:bg-gray-900 text-sm font-bold p-3.5 outline-none 
                                       focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-gray-800 dark:text-gray-100 transition shadow-sm">
                            @foreach($seasons as $s)
                                <option value="{{ $s }}" {{ $seasonB === $s ? 'selected' : '' }}>Temporada {{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>

        {{-- ══ CARD KPI COMPARATIVES ══ --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 au d2">
            
            {{-- KPI Bins --}}
            <div class="t-card-glow p-6 flex flex-col justify-between relative group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-violet-500/5 dark:bg-violet-400/5 rounded-bl-full pointer-events-none transition-all duration-300 group-hover:scale-110"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Bins (Agrak)</span>
                    <div class="p-2.5 rounded-2xl bg-violet-50 dark:bg-violet-950/20 text-violet-600 dark:text-violet-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                    </div>
                </div>
                <div class="mt-5 space-y-2.5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonA }}:</span>
                        <span class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">{{ number_format($totalBinsA, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-gray-100 dark:border-gray-800 pt-2">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonB }}:</span>
                        <span class="text-lg font-bold text-gray-500 dark:text-gray-400">{{ number_format($totalBinsB, 0, ',', '.') }}</span>
                    </div>
                </div>
                @if($totalBinsB > 0)
                    @php $binsDiff = (($totalBinsA - $totalBinsB) / $totalBinsB) * 100; @endphp
                    <div class="mt-4 flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $binsDiff >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400' }}">
                            {{ $binsDiff >= 0 ? '▲ +' : '▼ ' }}{{ number_format($binsDiff, 1) }}%
                        </span>
                        <span class="text-[10px] text-gray-400 uppercase font-black">vs Temp. {{ $seasonB }}</span>
                    </div>
                @endif
            </div>

            {{-- KPI Kilos Centros (Recepción) --}}
            <div class="t-card-glow p-6 flex flex-col justify-between relative group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 dark:bg-amber-400/5 rounded-bl-full pointer-events-none transition-all duration-300 group-hover:scale-110"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Kilos Centros</span>
                    <div class="p-2.5 rounded-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                </div>
                <div class="mt-5 space-y-2.5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonA }}:</span>
                        <span class="text-2xl font-black text-amber-600 dark:text-amber-400 tracking-tight">{{ number_format($totalKilosCentrosA, 1, ',', '.') }}</span>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-gray-100 dark:border-gray-800 pt-2">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonB }}:</span>
                        <span class="text-lg font-bold text-gray-500 dark:text-gray-400">{{ number_format($totalKilosCentrosB, 1, ',', '.') }}</span>
                    </div>
                </div>
                @if($totalKilosCentrosB > 0)
                    @php $kilosCentrosDiff = (($totalKilosCentrosA - $totalKilosCentrosB) / $totalKilosCentrosB) * 100; @endphp
                    <div class="mt-4 flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $kilosCentrosDiff >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400' }}">
                            {{ $kilosCentrosDiff >= 0 ? '▲ +' : '▼ ' }}{{ number_format($kilosCentrosDiff, 1) }}%
                        </span>
                        <span class="text-[10px] text-gray-400 uppercase font-black">vs Temp. {{ $seasonB }}</span>
                    </div>
                @endif
            </div>

            {{-- KPI Kilos Odoo (Despachados) --}}
            <div class="t-card-glow p-6 flex flex-col justify-between relative group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-blue-500/5 dark:bg-blue-400/5 rounded-bl-full pointer-events-none transition-all duration-300 group-hover:scale-110"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Kilos Odoo</span>
                    <div class="p-2.5 rounded-2xl bg-blue-50 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </div>
                </div>
                <div class="mt-5 space-y-2.5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonA }}:</span>
                        <span class="text-2xl font-black text-blue-600 dark:text-blue-400 tracking-tight">{{ number_format($totalKilosA, 1, ',', '.') }}</span>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-gray-100 dark:border-gray-800 pt-2">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonB }}:</span>
                        <span class="text-lg font-bold text-gray-500 dark:text-gray-400">{{ number_format($totalKilosB, 1, ',', '.') }}</span>
                    </div>
                </div>
                @if($totalKilosB > 0)
                    @php $kilosDiff = (($totalKilosA - $totalKilosB) / $totalKilosB) * 100; @endphp
                    <div class="mt-4 flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $kilosDiff >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400' }}">
                            {{ $kilosDiff >= 0 ? '▲ +' : '▼ ' }}{{ number_format($kilosDiff, 1) }}%
                        </span>
                        <span class="text-[10px] text-gray-400 uppercase font-black">vs Temp. {{ $seasonB }}</span>
                    </div>
                @endif
            </div>

            {{-- KPI Combustible Cosechadoras --}}
            <div class="t-card-glow p-6 flex flex-col justify-between relative group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-rose-500/5 dark:bg-rose-400/5 rounded-bl-full pointer-events-none transition-all duration-300 group-hover:scale-110"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest flex items-center gap-1">
                        Combustible Cosechadoras
                    </span>
                    <div class="p-2.5 rounded-2xl bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-5 space-y-2.5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonA }}:</span>
                        <span class="text-2xl font-black text-rose-600 dark:text-rose-400 tracking-tight">{{ number_format($totalLitrosA, 1, ',', '.') }} L</span>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-gray-100 dark:border-gray-800 pt-2">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonB }}:</span>
                        <span class="text-lg font-bold text-gray-500 dark:text-gray-400">{{ number_format($totalLitrosB, 1, ',', '.') }} L</span>
                    </div>
                </div>
                @if($totalLitrosB > 0)
                    @php $litrosDiff = (($totalLitrosA - $totalLitrosB) / $totalLitrosB) * 100; @endphp
                    <div class="mt-4 flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $litrosDiff >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400' }}">
                            {{ $litrosDiff >= 0 ? '▲ +' : '▼ ' }}{{ number_format($litrosDiff, 1) }}%
                        </span>
                        <span class="text-[10px] text-gray-400 uppercase font-black">vs Temp. {{ $seasonB }}</span>
                    </div>
                @else
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-gray-300 dark:text-gray-700 font-bold">—</span>
                        <span class="text-[10px] text-gray-400 uppercase font-black">vs Temp. {{ $seasonB }}</span>
                    </div>
                @endif
            </div>

            {{-- KPI Días Cosecha --}}
            <div class="t-card-glow p-6 flex flex-col justify-between relative group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/5 dark:bg-emerald-400/5 rounded-bl-full pointer-events-none transition-all duration-300 group-hover:scale-110"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Días de Cosecha</span>
                    <div class="p-2.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-5 space-y-2.5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonA }}:</span>
                        <span class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">{{ $activeDaysA }}</span>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-gray-100 dark:border-gray-800 pt-2">
                        <span class="text-xs font-bold text-gray-400">Temp. {{ $seasonB }}:</span>
                        <span class="text-lg font-bold text-gray-500 dark:text-gray-400">{{ $activeDaysB }}</span>
                    </div>
                </div>
                @php $daysDiff = $activeDaysA - $activeDaysB; @endphp
                <div class="mt-4 flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-50 dark:bg-gray-950/45 text-gray-600 dark:text-gray-400">
                        {{ $daysDiff >= 0 ? '+' : '' }}{{ $daysDiff }} días
                    </span>
                    <span class="text-[10px] text-gray-400 uppercase font-black">Diferencia</span>
                </div>
            </div>

        </div>

        {{-- ══ SECCIÓN GRÁFICOS: BINS ══ --}}
        <div class="space-y-4">
            <x-section-label dot="bg-violet-500">Métricas de Campo (Bins Cosechados - Agrak)</x-section-label>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 au d3">
                {{-- Bins Acumulados --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Evolución Bins Acumulados</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Avance acumulativo día de cosecha a día de cosecha</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="binsCumChart"></canvas>
                        </div>
                    </div>
                </div>
                {{-- Bins Diarios --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Producción Diaria (Bins)</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Bins cosechados día por día</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="binsDailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ SECCIÓN GRÁFICOS: KILOS CENTROS ══ --}}
        <div class="space-y-4">
            <x-section-label dot="bg-amber-500">Métricas de Recepción (Kilos en Plantas)</x-section-label>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 au d3">
                {{-- Kilos Centros Acumulados --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Evolución Kilos Acumulados</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Kilos netos recepcionados acumulados</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="kilosCentrosCumChart"></canvas>
                        </div>
                    </div>
                </div>
                {{-- Kilos Centros Diarios --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Recepción Diaria (Kilos)</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Kilos netos recepcionados día por día</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="kilosCentrosDailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ SECCIÓN GRÁFICOS: KILOS ODOO ══ --}}
        <div class="space-y-4">
            <x-section-label dot="bg-blue-500">Métricas de Despacho (Kilos en Odoo)</x-section-label>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 au d3">
                {{-- Kilos Odoo Acumulados --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Evolución Kilos Acumulados</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Kilos despachados declarados acumulados</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="kilosCumChart"></canvas>
                        </div>
                    </div>
                </div>
                {{-- Kilos Odoo Diarios --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Despacho Diario (Kilos)</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Kilos despachados declarados día por día</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="kilosDailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ SECCIÓN GRÁFICOS: COMBUSTIBLE ══ --}}
        <div class="space-y-4">
            <x-section-label dot="bg-rose-500">Métricas de Combustible (Cosechadoras - FuelControl)</x-section-label>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 au d3">
                {{-- Litros Acumulados --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Evolución Combustible Acumulado</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Litros de petróleo consumidos acumulados</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="litrosCumChart"></canvas>
                        </div>
                    </div>
                </div>
                {{-- Litros Diarios --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Consumo Diario de Combustible</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Litros de petróleo consumidos día por día</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="litrosDailyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ SECCIÓN GRÁFICOS: DISTRIBUCIÓN AGRAK ══ --}}
        <div class="space-y-4">
            <x-section-label dot="bg-indigo-500">Distribución de Cosecha (Agrak)</x-section-label>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 au d3">
                {{-- Bins por Cuartel --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Bins por Cuartel</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Distribución de bins por cada cuartel cosechado (AGRAK)</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="cuartelesChart"></canvas>
                        </div>
                    </div>
                </div>
                {{-- Bins por Cosechadora --}}
                <div x-data="{ expanded: false }" class="relative">
                    <template x-teleport="body">
                        <div x-show="expanded" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 bg-black/60 backdrop-blur-md z-[60]"
                             @click="expanded = false"
                             style="display: none;">
                        </div>
                    </template>
                    <div :class="expanded ? 'fixed inset-x-4 inset-y-8 md:inset-16 z-[70] p-8 flex flex-col bg-white/95 dark:bg-slate-900/95 border border-gray-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden' : 't-card-glow p-6 flex flex-col'"
                         class="transition-all duration-300">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100/50 dark:border-gray-800/40">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest">Cosechadora AGRAK</h3>
                                <p class="text-[11px] text-gray-400 mt-0.5">Total de bins cosechados por máquina (AGRAK)</p>
                            </div>
                            <button @click="expanded = !expanded" 
                                    class="p-2 rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/40 dark:hover:bg-slate-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition active:scale-95">
                                <svg x-show="!expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4" />
                                </svg>
                                <svg x-show="expanded" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div :class="expanded ? 'h-[calc(100vh-220px)] md:h-[calc(100vh-200px)]' : 'h-80'" class="w-full relative transition-all duration-300">
                            <canvas id="maquinasChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ TABLA DÍA A DÍA ══ --}}
        <div class="au d4">
            <x-section-label dot="bg-indigo-500">Detalle comparativo por día de cosecha</x-section-label>
            
            <div class="t-card-glow mt-3 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th class="c w-16 bg-gray-50/50 dark:bg-slate-900/60 font-bold border-r border-gray-200/50 dark:border-gray-800/30">Día</th>
                                <th class="c border-r border-gray-200/50 dark:border-gray-800/30" colspan="4">Temporada {{ $seasonA }} (A)</th>
                                <th class="c border-r border-gray-200/50 dark:border-gray-800/30" colspan="4">Temporada {{ $seasonB }} (B)</th>
                                <th class="c" colspan="3">Comparación</th>
                            </tr>
                            <tr class="border-b border-gray-150 dark:border-gray-800/60">
                                <th class="c bg-gray-50/50 dark:bg-slate-900/60 border-r border-gray-200/50 dark:border-gray-800/30 font-bold">#</th>
                                
                                {{-- Season A --}}
                                <th class="border-l border-gray-100 dark:border-gray-800/30">Fecha</th>
                                <th class="r">Bins</th>
                                <th class="r">Kilos Centros</th>
                                <th class="r border-r border-gray-200/50 dark:border-gray-800/30">Combustible</th>

                                {{-- Season B --}}
                                <th>Fecha</th>
                                <th class="r">Bins</th>
                                <th class="r">Kilos Centros</th>
                                <th class="r border-r border-gray-200/50 dark:border-gray-800/30">Combustible</th>

                                {{-- Comparison --}}
                                <th class="r">Δ Bins</th>
                                <th class="r">Δ Kilos</th>
                                <th class="r">Δ Comb.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tableRows as $row)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-white/[0.015]">
                                    <td class="c font-bold bg-gray-50/30 dark:bg-slate-900/10 text-gray-500 border-r border-gray-200/50 dark:border-gray-800/30">{{ $row['day'] }}</td>
                                    
                                    {{-- Season A --}}
                                    <td class="text-gray-600 dark:text-gray-400 font-medium">{{ $row['dateA'] }}</td>
                                    <td class="text-right font-semibold">
                                        {{ $row['binsA'] !== null ? number_format($row['binsA'], 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="text-right font-black text-amber-600 dark:text-amber-400">
                                        {{ $row['kilosCentrosA'] !== null ? number_format($row['kilosCentrosA'], 0, ',', '.') . ' kg' : '—' }}
                                    </td>
                                    <td class="text-right font-semibold text-rose-600 dark:text-rose-400 border-r border-gray-200/50 dark:border-gray-800/30">
                                        {{ $row['litrosA'] !== null ? number_format($row['litrosA'], 1, ',', '.') . ' L' : '—' }}
                                    </td>

                                    {{-- Season B --}}
                                    <td class="text-gray-500">{{ $row['dateB'] }}</td>
                                    <td class="text-right font-medium text-gray-500">
                                        {{ $row['binsB'] !== null ? number_format($row['binsB'], 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="text-right font-bold text-gray-500">
                                        {{ $row['kilosCentrosB'] !== null ? number_format($row['kilosCentrosB'], 0, ',', '.') . ' kg' : '—' }}
                                    </td>
                                    <td class="text-right font-medium text-gray-500 border-r border-gray-200/50 dark:border-gray-800/30">
                                        {{ $row['litrosB'] !== null ? number_format($row['litrosB'], 1, ',', '.') . ' L' : '—' }}
                                    </td>

                                    {{-- Diff Columns --}}
                                    <td class="text-right font-semibold">
                                        @if($row['binsA'] !== null && $row['binsB'] !== null)
                                            @php $binsDiff = $row['binsA'] - $row['binsB']; @endphp
                                            <span class="diff-badge {{ $binsDiff >= 0 ? 'diff-up' : 'diff-down' }}">
                                                {{ $binsDiff >= 0 ? '+' : '' }}{{ $binsDiff }}
                                            </span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-bold">
                                        @if($row['kilosCentrosA'] !== null && $row['kilosCentrosB'] !== null)
                                            @php $kilosDiff = $row['kilosCentrosA'] - $row['kilosCentrosB']; @endphp
                                            <span class="diff-badge {{ $kilosDiff >= 0 ? 'diff-up' : 'diff-down' }}">
                                                {{ $kilosDiff >= 0 ? '+' : '' }}{{ number_format($kilosDiff, 0, ',', '.') }} kg
                                            </span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-bold">
                                        @if($row['litrosA'] !== null && $row['litrosB'] !== null)
                                            @php $litrosDiff = $row['litrosA'] - $row['litrosB']; @endphp
                                            <span class="diff-badge {{ $litrosDiff >= 0 ? 'diff-up' : 'diff-down' }}">
                                                {{ $litrosDiff >= 0 ? '+' : '' }}{{ number_format($litrosDiff, 1, ',', '.') }} L
                                            </span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-16 text-gray-400">
                                        <div class="flex flex-col items-center gap-2">
                                            <svg class="w-8 h-8 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            <span class="text-sm font-semibold">No se encontraron datos registrados para las temporadas elegidas.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
    <script>
        const isDark = () => document.documentElement.classList.contains('dark');

        function initCharts() {
            const dark = isDark();
            const gridColor = dark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.04)';
            const labelColor = dark ? '#475569' : '#94a3b8';

            // ── Bins Cumulative Chart ───────────────────────────────────────
            const binsCtx = document.getElementById('binsCumChart');
            if (binsCtx) {
                new Chart(binsCtx, {
                    type: 'line',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($binsA_cum),
                                borderColor: '#8b5cf6',
                                backgroundColor: 'rgba(139, 92, 246, 0.05)',
                                borderWidth: 3,
                                pointRadius: 2,
                                pointHoverRadius: 6,
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($binsB_cum),
                                borderColor: '#10b981',
                                borderDash: [6, 4],
                                borderWidth: 2,
                                pointRadius: 1,
                                pointHoverRadius: 5,
                                tension: 0.3,
                                fill: false,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } }
                            },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: {
                                    label: c => `  ${c.dataset.label}: ${c.parsed.y.toLocaleString('es-CL')} bins`
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: labelColor, font: { size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: gridColor },
                                ticks: { color: labelColor, font: { size: 10 } }
                            }
                        }
                    }
                });
            }

            // ── Bins Daily Chart ──────────────────────────────────────────
            const binsDailyCtx = document.getElementById('binsDailyChart');
            if (binsDailyCtx) {
                new Chart(binsDailyCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($binsA_daily),
                                backgroundColor: '#8b5cf6dd',
                                hoverBackgroundColor: '#8b5cf6',
                                borderRadius: 4,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($binsB_daily),
                                backgroundColor: '#10b981dd',
                                hoverBackgroundColor: '#10b981',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } } },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: { label: c => `  ${c.dataset.label}: ${c.parsed.y} bins` }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { color: labelColor, font: { size: 10 } } },
                            y: { beginAtZero: true, border: { display: false }, grid: { color: gridColor }, ticks: { color: labelColor, font: { size: 10 } } }
                        }
                    }
                });
            }

            // ── Kilos Centros Cumulative Chart ──────────────────────────────────────
            const kilosCentrosCtx = document.getElementById('kilosCentrosCumChart');
            if (kilosCentrosCtx) {
                new Chart(kilosCentrosCtx, {
                    type: 'line',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($kilosCentrosA_cum),
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245, 158, 11, 0.05)',
                                borderWidth: 3,
                                pointRadius: 2,
                                pointHoverRadius: 6,
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($kilosCentrosB_cum),
                                borderColor: '#10b981',
                                borderDash: [6, 4],
                                borderWidth: 2,
                                pointRadius: 1,
                                pointHoverRadius: 5,
                                tension: 0.3,
                                fill: false,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } }
                            },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: {
                                    label: c => `  ${c.dataset.label}: ${c.parsed.y.toLocaleString('es-CL', { maximumFractionDigits: 1 })} kg`
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: labelColor, font: { size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: gridColor },
                                ticks: {
                                    color: labelColor,
                                    font: { size: 10 },
                                    callback: v => v.toLocaleString('es-CL') + ' kg'
                                }
                            }
                        }
                    }
                });
            }

            // ── Kilos Centros Daily Chart ───────────────────────────────────────
            const kilosCentrosDailyCtx = document.getElementById('kilosCentrosDailyChart');
            if (kilosCentrosDailyCtx) {
                new Chart(kilosCentrosDailyCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($kilosCentrosA_daily),
                                backgroundColor: '#f59e0bdd',
                                hoverBackgroundColor: '#f59e0b',
                                borderRadius: 4,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($kilosCentrosB_daily),
                                backgroundColor: '#10b981dd',
                                hoverBackgroundColor: '#10b981',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } } },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: { label: c => `  ${c.dataset.label}: ${c.parsed.y.toLocaleString('es-CL')} kg` }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { color: labelColor, font: { size: 10 } } },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: gridColor },
                                ticks: {
                                    color: labelColor, font: { size: 10 },
                                    callback: v => v.toLocaleString('es-CL') + ' kg'
                                }
                            }
                        }
                    }
                });
            }

            // ── Kilos Odoo Cumulative Chart ──────────────────────────────────────
            const kilosCtx = document.getElementById('kilosCumChart');
            if (kilosCtx) {
                new Chart(kilosCtx, {
                    type: 'line',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($kilosA_cum),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.05)',
                                borderWidth: 3,
                                pointRadius: 2,
                                pointHoverRadius: 6,
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($kilosB_cum),
                                borderColor: '#94a3b8',
                                borderDash: [6, 4],
                                borderWidth: 2,
                                pointRadius: 1,
                                pointHoverRadius: 5,
                                tension: 0.3,
                                fill: false,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } }
                            },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: {
                                    label: c => `  ${c.dataset.label}: ${c.parsed.y.toLocaleString('es-CL', { maximumFractionDigits: 1 })} kg`
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: labelColor, font: { size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: gridColor },
                                ticks: {
                                    color: labelColor,
                                    font: { size: 10 },
                                    callback: v => v.toLocaleString('es-CL') + ' kg'
                                }
                            }
                        }
                    }
                });
            }

            // ── Kilos Odoo Daily Chart ───────────────────────────────────────
            const kilosDailyCtx = document.getElementById('kilosDailyChart');
            if (kilosDailyCtx) {
                new Chart(kilosDailyCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($kilosA_daily),
                                backgroundColor: '#3b82f6dd',
                                hoverBackgroundColor: '#3b82f6',
                                borderRadius: 4,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($kilosB_daily),
                                backgroundColor: '#94a3b8dd',
                                hoverBackgroundColor: '#94a3b8',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } } },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: { label: c => `  ${c.dataset.label}: ${c.parsed.y.toLocaleString('es-CL')} kg` }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { color: labelColor, font: { size: 10 } } },
                            y: {
                                beginAtZero: true, border: { display: false }, grid: { color: gridColor },
                                ticks: {
                                    color: labelColor, font: { size: 10 },
                                    callback: v => v.toLocaleString('es-CL') + ' kg'
                                }
                            }
                        }
                    }
                });
            }

            // ── Litros Cumulative Chart ──────────────────────────────────────
            const litrosCtx = document.getElementById('litrosCumChart');
            if (litrosCtx) {
                new Chart(litrosCtx, {
                    type: 'line',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($litrosA_cum),
                                borderColor: '#f43f5e',
                                backgroundColor: 'rgba(244, 63, 94, 0.05)',
                                borderWidth: 3,
                                pointRadius: 2,
                                pointHoverRadius: 6,
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($litrosB_cum),
                                borderColor: '#10b981',
                                borderDash: [6, 4],
                                borderWidth: 2,
                                pointRadius: 1,
                                pointHoverRadius: 5,
                                tension: 0.3,
                                fill: false,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } }
                            },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: {
                                    label: c => `  ${c.dataset.label}: ${c.parsed.y.toLocaleString('es-CL', { maximumFractionDigits: 1 })} L`
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: labelColor, font: { size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: gridColor },
                                ticks: {
                                    color: labelColor,
                                    font: { size: 10 },
                                    callback: v => v.toLocaleString('es-CL') + ' L'
                                }
                            }
                        }
                    }
                });
            }

            // ── Litros Daily Chart ───────────────────────────────────────
            const litrosDailyCtx = document.getElementById('litrosDailyChart');
            if (litrosDailyCtx) {
                new Chart(litrosDailyCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($litrosA_daily),
                                backgroundColor: '#f43f5edd',
                                hoverBackgroundColor: '#f43f5e',
                                borderRadius: 4,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($litrosB_daily),
                                backgroundColor: '#10b981dd',
                                hoverBackgroundColor: '#10b981',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } } },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: { label: c => `  ${c.dataset.label}: ${c.parsed.y.toLocaleString('es-CL')} L` }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { color: labelColor, font: { size: 10 } } },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: gridColor },
                                ticks: {
                                    color: labelColor, font: { size: 10 },
                                    callback: v => v.toLocaleString('es-CL') + ' L'
                                }
                            }
                        }
                    }
                });
            }

            // ── Cuarteles Chart (Vertical Bar Chart) ────────────────────────
            const cuartelesCtx = document.getElementById('cuartelesChart');
            if (cuartelesCtx) {
                new Chart(cuartelesCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($cuartelLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($cuartelBinsA),
                                backgroundColor: '#8b5cf6dd',
                                hoverBackgroundColor: '#8b5cf6',
                                borderRadius: 4,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($cuartelBinsB),
                                backgroundColor: '#10b981dd',
                                hoverBackgroundColor: '#10b981',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } } },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: { label: c => `  ${c.dataset.label}: ${c.parsed.y.toLocaleString('es-CL')} bins` }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { color: labelColor, font: { size: 10 } } },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: gridColor },
                                ticks: { color: labelColor, font: { size: 10 } }
                            }
                        }
                    }
                });
            }

            // ── Maquinas Chart (Vertical Bar Chart) ─────────────────────────
            const maquinasCtx = document.getElementById('maquinasChart');
            if (maquinasCtx) {
                new Chart(maquinasCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($maquinaLabels),
                        datasets: [
                            {
                                label: 'Temporada {{ $seasonA }} (Principal)',
                                data: @json($maquinaBinsA),
                                backgroundColor: '#8b5cf6dd',
                                hoverBackgroundColor: '#8b5cf6',
                                borderRadius: 4,
                            },
                            {
                                label: 'Temporada {{ $seasonB }} (Comparación)',
                                data: @json($maquinaBinsB),
                                backgroundColor: '#10b981dd',
                                hoverBackgroundColor: '#10b981',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { color: dark ? '#cbd5e1' : '#334155', font: { weight: 'bold', size: 11 } } },
                            tooltip: {
                                backgroundColor: dark ? '#1a2436' : '#fff',
                                titleColor: dark ? '#e2e8f0' : '#1e293b',
                                bodyColor: dark ? '#94a3b8' : '#64748b',
                                borderColor: dark ? '#1e2a3b' : '#e2e8f0',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: { label: c => `  ${c.dataset.label}: ${c.parsed.y.toLocaleString('es-CL')} bins` }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { color: labelColor, font: { size: 10 } } },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: gridColor },
                                ticks: { color: labelColor, font: { size: 10 } }
                            }
                        }
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', initCharts);
    </script>
</x-app-layout>
