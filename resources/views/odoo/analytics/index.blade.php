<x-app-layout>

    <x-slot name="header">
        <div class="w-full flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-600 flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M12 7h.01M15 7h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-xl md:text-2xl font-black text-gray-900 dark:text-gray-100 leading-tight">Módulo de Analítica</h2>
                    <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 font-semibold">{{ $totalPlans }} planes</span>
                        <span class="text-gray-300 dark:text-gray-700">•</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 font-semibold">{{ $totalAccounts }} cuentas</span>
                        @if($lastSync)
                            <span class="text-gray-300 dark:text-gray-700">•</span>
                            <span class="text-gray-400">Sync: {{ \Carbon\Carbon::parse($lastSync)->format('d/m/Y H:i') }}</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button
                    onclick="document.dispatchEvent(new CustomEvent('open-modal-cuenta'))"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-md shadow-emerald-600/10 active:scale-95"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Nueva cuenta
                </button>
                <button
                    onclick="document.dispatchEvent(new CustomEvent('open-modal-plan'))"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/10 active:scale-95"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Nuevo plan
                </button>
            </div>
        </div>
    </x-slot>

    <style>
        .page-bg { background: #f8fafc; min-height: 100% }
        .dark .page-bg { background: #090d16 }
        
        /* Premium Card/Panels */
        .panel { 
            background: #ffffff; 
            border: 1px solid #e2e8f0; 
            border-radius: 20px; 
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            transition: all 0.25s ease;
        }
        .dark .panel { 
            background: #111827; 
            border-color: #1f2937; 
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.2);
        }
        .panel-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.05), 0 4px 6px -4px rgb(0 0 0 / 0.05);
        }
        .dark .panel-hover:hover {
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.25), 0 4px 6px -4px rgb(0 0 0 / 0.25);
        }

        /* SaaS Sidebar Navigation Lists */
        .sidebar-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            border: 1px solid transparent;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: left;
            width: 100%;
        }
        .dark .sidebar-item { color: #9ca3af; }
        .sidebar-item:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        .dark .sidebar-item:hover {
            background: #1f2937;
            color: #f9fafb;
        }
        .sidebar-item.active {
            background: #ecfdf5;
            color: #059669;
            border-color: #a7f3d0/50;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.02);
        }
        .dark .sidebar-item.active {
            background: #064e3b/30;
            color: #34d399;
            border-color: #065f46/40;
        }

        /* Modern Tables (High density) */
        .dt { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .dt thead tr { background: #f8fafc; }
        .dark .dt thead tr { background: #182235; }
        .dt thead th { 
            padding: 10px 16px; 
            text-align: left; 
            font-size: 11px; 
            font-weight: 850; 
            text-transform: uppercase; 
            letter-spacing: .08em; 
            color: #64748b; 
            border-bottom: 1px solid #e2e8f0;
        }
        .dark .dt thead th { border-bottom-color: #243249; color: #9ca3af; }
        
        .dt tbody tr.acc-row { 
            border-bottom: 1px solid #f1f5f9; 
            transition: all 0.15s ease; 
            cursor: pointer; 
        }
        .dark .dt tbody tr.acc-row { border-bottom-color: #1f2937; }
        .dt tbody tr.acc-row:hover { 
            background: #f0fdf4/50; 
            transform: translateX(3px);
        }
        .dark .dt tbody tr.acc-row:hover { 
            background: #064e3b/15; 
        }
        .dt td { 
            padding: 10px 16px; 
            color: #334155; 
        }
        .dark .dt td { color: #d1d5db; }
        
        .section-title { 
            font-size: 12px; 
            font-weight: 800; 
            text-transform: uppercase; 
            letter-spacing: .08em; 
            color: #334155; 
            padding: 12px 16px 6px;
            background: #edf2f7;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #e2e8f0;
        }
        .dark .section-title {
            background: #1e293b;
            color: #f1f5f9;
            border-top-color: #334155;
            border-bottom-color: #1e293b;
        }

        /* Active indicators */
        .tab-btn {
            position: relative;
            transition: all 0.25s ease;
        }
        .tab-btn::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: #10b981;
            transition: all 0.25s ease;
            transform: translateX(-50%);
            border-radius: 9999px;
        }
        .tab-btn.active::after {
            width: 50%;
        }
        .tab-btn.active {
            color: #10b981 !important;
        }
        .dark .tab-btn.active {
            color: #34d399 !important;
        }

        /* Scrollbar styles */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #374151;
        }
    </style>

    {{-- Root Alpine con control de navegación --}}
    <div class="page-bg" x-data="analytics()">

        {{-- ── KPI SUMMARY CARDS ────────────────────────────────────── --}}
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="panel p-4 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-black text-slate-450 dark:text-slate-500 uppercase tracking-widest">Planes Analíticos</p>
                        <h4 class="text-xl font-black text-gray-800 dark:text-gray-105 mt-0.5">{{ $totalPlans }}</h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/20 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                </div>
                <div class="panel p-4 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-black text-slate-450 dark:text-slate-500 uppercase tracking-widest">Cuentas Analíticas</p>
                        <h4 class="text-xl font-black text-gray-800 dark:text-gray-105 mt-0.5">{{ $totalAccounts }}</h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M12 7h.01M15 7h.01M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                </div>
                <div class="panel p-4 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-black text-slate-450 dark:text-slate-500 uppercase tracking-widest">Cuentas Contables</p>
                        <h4 class="text-xl font-black text-gray-800 dark:text-gray-105 mt-0.5">{{ $totalChartAccounts }}</h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/20 flex items-center justify-center text-amber-600 dark:text-amber-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2v-4M14 7h6l-2 2m2-2l-2-2"/></svg>
                    </div>
                </div>
                <div class="panel p-4 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-black text-slate-450 dark:text-slate-500 uppercase tracking-widest">Último Sync</p>
                        <h4 class="text-xs font-bold text-gray-650 dark:text-gray-250 mt-1">
                            {{ $lastSync ? \Carbon\Carbon::parse($lastSync)->diffForHumans() : 'Nunca' }}
                        </h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-950/20 flex items-center justify-center text-teal-600 dark:text-teal-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.2M7 9h8v8"/></svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── TAB SELECTOR ─────────────────────────────────────────── --}}
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div class="flex border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/60 p-1 rounded-2xl shadow-sm">
                <button 
                    @click="activeTab = 'analitica'" 
                    :class="{ 'active': activeTab === 'analitica' }"
                    class="tab-btn flex-1 py-3.5 text-sm font-extrabold text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Estructura Analítica
                </button>
                <button 
                    @click="activeTab = 'contable'" 
                    :class="{ 'active': activeTab === 'contable' }"
                    class="tab-btn flex-1 py-3.5 text-sm font-extrabold text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2v-4M14 7h6l-2 2m2-2l-2-2"/></svg>
                    Plan de Cuentas Contables
                </button>
            </div>
        </div>

        {{-- ── TAB 1: ESTRUCTURA ANALÍTICA (EXPLORER WORKSPACE) ───────── --}}
        <div id="tab-analitica" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5" x-show="activeTab === 'analitica'">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
                
                {{-- COLUMNA IZQUIERDA: SIDEBAR DE PLANES --}}
                <div class="lg:col-span-1 space-y-4">
                    
                    {{-- Buscador Integrado --}}
                    <div class="panel p-3">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                x-model="search"
                                type="text"
                                placeholder="Buscar en cuentas..."
                                class="w-full pl-9 pr-3 py-2 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-xs text-gray-700 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all"
                            >
                        </div>
                    </div>

                    {{-- Lista de Planes (Workspace Pills) --}}
                    <div class="panel p-3 space-y-1">
                        <div class="px-2 py-1.5 text-[11px] font-black text-slate-450 dark:text-slate-500 uppercase tracking-widest">Ver Proyectos</div>
                        
                        {{-- Botón "Todos" --}}
                        <button 
                            @click="selectedPlan = 'all'" 
                            :class="{ 'active': selectedPlan === 'all' }"
                            class="sidebar-item"
                        >
                            <span class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-gradient-to-tr from-emerald-500 to-teal-500"></span>
                                Todos los Planes
                            </span>
                            <span class="text-[10px] bg-gray-100 dark:bg-gray-800 text-gray-500 px-2 py-0.5 rounded-full font-bold">
                                {{ $totalAccounts }}
                            </span>
                        </button>

                        @foreach($plans as $plan)
                            @php
                                $planIds = \App\Models\OdooAnalyticPlan::where('odoo_id', $plan->odoo_id)
                                    ->orWhere('parent_odoo_id', $plan->odoo_id)
                                    ->pluck('odoo_id');

                                $planAccountsCount = \App\Models\OdooAnalyticAccount::whereIn('plan_odoo_id', $planIds)->count();

                                $colorDot = match($plan->color) {
                                    4 => '#3b82f6', 5 => '#6366f1', 8 => '#10b981',
                                    9 => '#14b8a6', 10 => '#06b6d4', default => '#94a3b8',
                                };
                            @endphp
                            
                            <button 
                                @click="selectedPlan = {{ $plan->odoo_id }}" 
                                :class="{ 'active': selectedPlan === {{ $plan->odoo_id }} }"
                                class="sidebar-item"
                            >
                                <span class="flex items-center gap-2 truncate pr-1">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ $colorDot }}"></span>
                                    <span class="truncate">{{ $plan->name_es ?? $plan->name }}</span>
                                </span>
                                <span class="text-[10px] bg-gray-100 dark:bg-gray-800 text-gray-500 px-2 py-0.5 rounded-full font-bold shrink-0">
                                    {{ $planAccountsCount }}
                                </span>
                            </button>
                        @endforeach
                    </div>

                </div>

                {{-- COLUMNA DERECHA: CUENTAS DEL PLAN SELECCIONADO --}}
                <div class="lg:col-span-3 space-y-4">
                    
                    @foreach($plans as $plan)
                        @php
                            $planIds = \App\Models\OdooAnalyticPlan::where('odoo_id', $plan->odoo_id)
                                ->orWhere('parent_odoo_id', $plan->odoo_id)
                                ->pluck('odoo_id');

                            $allAccounts = \App\Models\OdooAnalyticAccount::whereIn('plan_odoo_id', $planIds)
                                ->orderBy('plan_complete_name')->orderBy('name_es')->get();

                            $subPlans = \App\Models\OdooAnalyticPlan::where('parent_odoo_id', $plan->odoo_id)
                                ->orderBy('name')->get();

                            $colorDot = match($plan->color) {
                                4 => '#3b82f6', 5 => '#6366f1', 8 => '#10b981',
                                9 => '#14b8a6', 10 => '#06b6d4', default => '#94a3b8',
                            };
                        @endphp

                        <div 
                            class="panel plan-block overflow-hidden" 
                            x-show="selectedPlan === 'all' || selectedPlan === {{ $plan->odoo_id }}" 
                            data-plan-id="{{ $plan->odoo_id }}"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                        >
                            {{-- Header informativo del Plan (Estilo Dashboard Banner con relieve y borde dinámico) --}}
                            <div class="px-6 py-5 bg-gradient-to-r from-white via-slate-50/50 to-slate-100/10 dark:from-gray-900 dark:via-gray-850/60 dark:to-gray-800/20 border-b border-slate-150 dark:border-gray-850 flex items-center justify-between shadow-sm relative overflow-hidden" style="border-left: 5px solid {{ $colorDot }};">
                                <div class="flex items-center gap-3">
                                    <div class="w-3.5 h-3.5 rounded-lg flex items-center justify-center shrink-0" style="background:{{ $colorDot }}">
                                        <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                                    </div>
                                    <h3 class="text-base font-black text-gray-950 dark:text-gray-50">
                                        {{ $plan->name_es ?? $plan->name }}
                                        <span class="text-xs font-normal text-gray-400 block sm:inline sm:ml-2">/ {{ $plan->name }}</span>
                                    </h3>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-black uppercase tracking-wider bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-650 dark:text-gray-300 shadow-sm">
                                    {{ $allAccounts->count() }} Cuentas
                                </span>
                            </div>

                            {{-- Renderizado de Cuentas --}}
                            @if($allAccounts->isEmpty())
                                <div class="p-8 text-center text-xs text-gray-400">
                                    No hay cuentas analíticas configuradas para este plan.
                                </div>
                            @else
                                @if($subPlans->count())
                                    @foreach($subPlans as $sub)
                                        @php $subAccounts = $allAccounts->where('plan_odoo_id', $sub->odoo_id); @endphp
                                        @if($subAccounts->count())
                                            <div class="section-title flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-450 dark:bg-gray-500"></span>
                                                {{ $sub->name_es ?? $sub->name }}
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="dt">
                                                    <thead>
                                                        <tr>
                                                            <th class="w-20">ID</th>
                                                            <th>Cuenta Analítica</th>
                                                            <th class="w-32 text-right">Código</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($subAccounts as $acc)
                                                            <tr class="acc-row"
                                                                data-name-es="{{ strtolower($acc->name_es ?? $acc->name) }}"
                                                                @click="openModal({{ $acc->odoo_id }}, '{{ addslashes($acc->name_es ?? $acc->name) }}', '{{ addslashes($acc->name) }}', '{{ addslashes($acc->code ?? '') }}', '{{ addslashes($acc->plan_complete_name) }}')"
                                                            >
                                                                <td class="font-mono text-xs font-bold text-gray-400 dark:text-gray-500">#{{ $acc->odoo_id }}</td>
                                                                <td class="font-bold text-gray-800 dark:text-gray-150 flex items-center gap-1.5">
                                                                    {{ $acc->name_es ?? $acc->name }}
                                                                    <span class="text-[10px] text-gray-300 dark:text-gray-700">↗</span>
                                                                </td>
                                                                <td class="text-right">
                                                                    @if($acc->code)
                                                                        <span class="font-mono text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 px-2.5 py-0.5 rounded-lg">{{ $acc->code }}</span>
                                                                    @else
                                                                        <span class="text-gray-300 dark:text-gray-700">—</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    @endforeach

                                    @php $rootAccounts = $allAccounts->where('plan_odoo_id', $plan->odoo_id); @endphp
                                    @if($rootAccounts->count())
                                        <div class="section-title flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-450 dark:bg-emerald-500"></span>
                                            General
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="dt">
                                                <thead>
                                                    <tr>
                                                        <th class="w-20">ID</th>
                                                        <th>Cuenta Analítica</th>
                                                        <th class="w-32 text-right">Código</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($rootAccounts as $acc)
                                                        <tr class="acc-row"
                                                            data-name-es="{{ strtolower($acc->name_es ?? $acc->name) }}"
                                                            @click="openModal({{ $acc->odoo_id }}, '{{ addslashes($acc->name_es ?? $acc->name) }}', '{{ addslashes($acc->name) }}', '{{ addslashes($acc->code ?? '') }}', '{{ addslashes($acc->plan_complete_name) }}')"
                                                        >
                                                            <td class="font-mono text-xs font-bold text-gray-400 dark:text-gray-500">#{{ $acc->odoo_id }}</td>
                                                            <td class="font-bold text-gray-800 dark:text-gray-155 flex items-center gap-1.5">
                                                                {{ $acc->name_es ?? $acc->name }}
                                                                <span class="text-[10px] text-gray-300 dark:text-gray-700">↗</span>
                                                            </td>
                                                            <td class="text-right">
                                                                @if($acc->code)
                                                                    <span class="font-mono text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 px-2.5 py-0.5 rounded-lg">{{ $acc->code }}</span>
                                                                @else
                                                                    <span class="text-gray-300 dark:text-gray-700">—</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                @else
                                    <div class="overflow-x-auto">
                                        <table class="dt">
                                            <thead>
                                                <tr>
                                                    <th class="w-20">ID</th>
                                                    <th>Cuenta Analítica</th>
                                                    <th class="w-32 text-right">Código</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($allAccounts as $acc)
                                                    <tr class="acc-row"
                                                        data-name-es="{{ strtolower($acc->name_es ?? $acc->name) }}"
                                                        @click="openModal({{ $acc->odoo_id }}, '{{ addslashes($acc->name_es ?? $acc->name) }}', '{{ addslashes($acc->name) }}', '{{ addslashes($acc->code ?? '') }}', '{{ addslashes($acc->plan_complete_name) }}')"
                                                    >
                                                        <td class="font-mono text-xs font-bold text-gray-400 dark:text-gray-500">#{{ $acc->odoo_id }}</td>
                                                        <td class="font-bold text-gray-800 dark:text-gray-160 flex items-center gap-1.5">
                                                            {{ $acc->name_es ?? $acc->name }}
                                                            <span class="text-[10px] text-gray-300 dark:text-gray-700">↗</span>
                                                        </td>
                                                        <td class="text-right">
                                                            @if($acc->code)
                                                                <span class="font-mono text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 px-2.5 py-0.5 rounded-lg">{{ $acc->code }}</span>
                                                            @else
                                                                <span class="text-gray-300 dark:text-gray-700">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach

                </div>

            </div>
        </div>

        {{-- ── TAB 2: PLAN DE CUENTAS CONTABLES (EXPLORER WORKSPACE) ────── --}}
        <div id="tab-contable" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5" x-show="activeTab === 'contable'" style="display: none">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
                
                {{-- COLUMNA IZQUIERDA: GRUPOS CONTABLES --}}
                <div class="lg:col-span-1 space-y-4">
                    
                    {{-- Buscador Integrado Contable --}}
                    <div class="panel p-3">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                x-model="searchContable"
                                type="text"
                                placeholder="Buscar cuentas contables..."
                                class="w-full pl-9 pr-3 py-2 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-xs text-gray-700 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all"
                            >
                        </div>
                    </div>

                    <div class="panel p-3 space-y-1">
                        <div class="px-2 py-1.5 text-[11px] font-black text-slate-450 dark:text-slate-500 uppercase tracking-widest">Grupos Contables</div>
                        
                        {{-- Botón "Todos" --}}
                        <button 
                            @click="selectedGroup = 'all'" 
                            :class="{ 'active': selectedGroup === 'all' }"
                            class="sidebar-item"
                        >
                            <span class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-500"></span>
                                Catálogo Completo
                            </span>
                            <span class="text-[10px] bg-gray-100 dark:bg-gray-800 text-gray-500 px-2 py-0.5 rounded-full font-bold">
                                {{ $totalChartAccounts }}
                            </span>
                        </button>

                        @php
                            $groupColors = [
                                'Activo'           => ['dot' => '#3b82f6', 'badge' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/20 dark:text-blue-400 border border-blue-100 dark:border-blue-900/30'],
                                'Pasivo'           => ['dot' => '#ef4444', 'badge' => 'bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-400 border border-red-100 dark:border-red-900/30'],
                                'Patrimonio'       => ['dot' => '#8b5cf6', 'badge' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/20 dark:text-purple-400 border border-purple-100 dark:border-purple-900/30'],
                                'Ingresos'         => ['dot' => '#10b981', 'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/30'],
                                'Gastos'           => ['dot' => '#f59e0b', 'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400 border border-amber-100 dark:border-amber-900/30'],
                                'Fuera de balance' => ['dot' => '#94a3b8', 'badge' => 'bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-700/60'],
                            ];
                        @endphp

                        @foreach($chartOfAccounts as $groupLabel => $accounts)
                            @php $gc = $groupColors[$groupLabel] ?? $groupColors['Fuera de balance']; @endphp
                            <button 
                                @click="selectedGroup = '{{ $groupLabel }}'" 
                                :class="{ 'active': selectedGroup === '{{ $groupLabel }}' }"
                                class="sidebar-item"
                            >
                                <span class="flex items-center gap-2 truncate pr-1">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ $gc['dot'] }}"></span>
                                    <span class="truncate">{{ $groupLabel }}</span>
                                </span>
                                <span class="text-[10px] bg-gray-100 dark:bg-gray-800 text-gray-500 px-2 py-0.5 rounded-full font-bold shrink-0">
                                    {{ $accounts->count() }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- COLUMNA DERECHA: CATÁLOGO FINANCIERO --}}
                <div class="lg:col-span-3 space-y-4">
                    @foreach($chartOfAccounts as $groupLabel => $accounts)
                        @php $gc = $groupColors[$groupLabel] ?? $groupColors['Fuera de balance']; @endphp
                        
                        <div 
                            class="panel plan-block overflow-hidden" 
                            x-show="selectedGroup === 'all' || selectedGroup === '{{ $groupLabel }}'"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                        >
                            {{-- Header del Grupo (Estilo Dashboard Banner con relieve y borde dinámico) --}}
                            <div class="px-6 py-5 bg-gradient-to-r from-white via-slate-50/50 to-slate-100/10 dark:from-gray-900 dark:to-gray-850/40 border-b border-slate-150 dark:border-gray-850 flex items-center justify-between shadow-sm relative overflow-hidden" style="border-left: 5px solid {{ $gc['dot'] }};">
                                <div class="flex items-center gap-3">
                                    <div class="w-3.5 h-3.5 rounded-full shrink-0" style="background:{{ $gc['dot'] }}"></div>
                                    <h3 class="text-base font-black text-gray-950 dark:text-gray-50">{{ $groupLabel }}</h3>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-black uppercase tracking-wider {{ $gc['badge'] }} shadow-sm">
                                    {{ $accounts->count() }} Cuentas
                                </span>
                            </div>

                            {{-- Tabla de Cuentas Contables --}}
                            <div class="overflow-x-auto">
                                <table class="dt">
                                    <thead>
                                        <tr>
                                            <th class="w-32">Código</th>
                                            <th>Nombre de Cuenta</th>
                                            <th class="w-56 hidden sm:table-cell">Tipo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($accounts as $acc)
                                            <tr class="acc-row"
                                                data-name-es="{{ strtolower($acc->name_es ?? $acc->name) }}"
                                                @click="openModal({{ $acc->odoo_id }}, '{{ addslashes($acc->name_es ?? $acc->name) }}', '{{ addslashes($acc->name) }}', '{{ addslashes($acc->code) }}', '{{ $groupLabel }}')"
                                            >
                                                <td class="font-mono text-xs font-extrabold text-gray-500 dark:text-gray-400">{{ $acc->code }}</td>
                                                <td class="font-bold text-gray-800 dark:text-gray-100 flex items-center gap-1.5">
                                                    {{ $acc->name_es ?? $acc->name }}
                                                    <span class="text-[10px] text-gray-300 dark:text-gray-700">↗</span>
                                                </td>
                                                <td class="hidden sm:table-cell text-xs font-semibold text-gray-400">{{ $acc->account_type_label }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>
        </div>

        {{-- ── FORMULARIO MODAL NUEVA CUENTA (REUSADO) ──────────────── --}}
        <div x-show="modalCuenta" x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="modalCuenta = false"></div>
            <div x-show="modalCuenta"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 w-full max-w-md z-10 overflow-hidden" @click.stop>

                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <h3 class="text-sm font-extrabold text-gray-900 dark:text-gray-100">Nueva cuenta analítica</h3>
                    </div>
                    <button @click="modalCuenta = false" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('odoo.analytics.account.store') }}" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">Nombre en Español <span class="text-red-500">*</span></label>
                        <input name="name_es" type="text" required placeholder="Ej: Mantención maquinaria"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">Nombre en Inglés <span class="text-red-500">*</span></label>
                        <input name="name" type="text" required placeholder="Ej: Machinery maintenance"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">Código <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <input name="code" type="text" placeholder="Ej: P1"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">Plan analítico <span class="text-red-500">*</span></label>
                        <select name="plan_odoo_id" required
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all">
                            <option value="">Seleccionar plan...</option>
                            @foreach($allPlans as $p)
                                <option value="{{ $p->odoo_id }}">{{ $p->name_es ?? $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-3">
                        <button type="button" @click="modalCuenta = false" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">Cancelar</button>
                        <button type="submit" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white transition-all shadow-md shadow-emerald-600/10">Crear cuenta</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── FORMULARIO MODAL NUEVO PROYECTO (REUSADO) ────────────── --}}
        <div x-show="modalPlan" x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="modalPlan = false"></div>
            <div x-show="modalPlan"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 w-full max-w-md z-10 overflow-hidden" @click.stop>

                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-950/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <h3 class="text-sm font-extrabold text-gray-900 dark:text-gray-100">Nuevo proyecto / plan</h3>
                    </div>
                    <button @click="modalPlan = false" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('odoo.analytics.plan.store') }}" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">Nombre en Español <span class="text-red-500">*</span></label>
                        <input name="name_es" type="text" required placeholder="Ej: Cosecha 2027"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">Nombre en Inglés <span class="text-red-500">*</span></label>
                        <input name="name" type="text" required placeholder="Ej: Harvest 2027"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">Plan padre <span class="text-gray-400 font-normal">(opcional)</span></label>
                        <select name="parent_odoo_id"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all">
                            <option value="">Ninguno (plan raíz)</option>
                            @foreach($allPlans as $p)
                                <option value="{{ $p->odoo_id }}">{{ $p->name_es ?? $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-3">
                        <button type="button" @click="modalPlan = false" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-all">Cancelar</button>
                        <button type="submit" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-all shadow-md shadow-indigo-600/10">Crear proyecto</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── DETAIL MODAL (REUSADO) ──────────────────────────────── --}}
        <div x-show="modal.open" x-transition.opacity.duration.150ms class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="modal.open = false"></div>
            <div x-show="modal.open"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 w-full max-w-md z-10 overflow-hidden" @click.stop>

                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs text-emerald-500 font-bold" x-text="'ID: ' + modal.id"></span>
                        <span class="text-[11px] font-extrabold px-2.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400" x-text="modal.plan"></span>
                    </div>
                    <button @click="modal.open = false" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <div class="rounded-2xl bg-gradient-to-r from-emerald-50 to-emerald-500/5 dark:from-emerald-950/20 dark:to-emerald-900/5 border border-emerald-100/50 dark:border-emerald-900/50 px-4 py-3.5">
                        <p class="text-[9px] font-extrabold uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mb-1 flex items-center gap-1.5">
                            <span class="text-xs">🇨🇱</span> Español
                        </p>
                        <p class="text-base font-extrabold text-gray-900 dark:text-gray-100" x-text="modal.es"></p>
                    </div>

                    <div class="rounded-2xl bg-gradient-to-r from-amber-50 to-amber-500/5 dark:from-amber-950/20 dark:to-amber-900/5 border border-amber-100/50 dark:border-amber-900/50 px-4 py-3.5">
                        <p class="text-[9px] font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400 mb-1 flex items-center gap-1.5">
                            <span class="text-xs">🇬🇧</span> English
                        </p>
                        <p class="text-base font-extrabold text-gray-900 dark:text-gray-100" x-text="modal.en"></p>
                    </div>

                    <template x-if="modal.code">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-700/60">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Código de Cuenta</span>
                            <span class="font-mono text-xs font-bold bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 px-3 py-1 rounded-lg" x-text="modal.code"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </div>

    <script>
    function analytics() {
        return {
            search: '',
            searchContable: '',
            modalCuenta: false,
            modalPlan: false,
            activeTab: 'analitica',
            selectedPlan: 'all',  // Filtro activo de plan
            selectedGroup: 'all', // Filtro activo de cuentas contables
            modal: { open: false, id: null, es: '', en: '', code: '', plan: '' },

            init() {
                this.$watch('search', val => this.applySearch(val, '#tab-analitica'));
                this.$watch('searchContable', val => this.applySearch(val, '#tab-contable'));

                document.addEventListener('keydown', e => {
                    if (e.key === 'Escape') {
                        this.modal.open = false;
                        this.modalCuenta = false;
                        this.modalPlan = false;
                    }
                });

                document.addEventListener('open-modal-cuenta', () => this.modalCuenta = true);
                document.addEventListener('open-modal-plan',   () => this.modalPlan   = true);
            },

            openModal(id, es, en, code, plan) {
                this.modal = { open: true, id, es, en, code, plan };
            },

            applySearch(q, containerId) {
                q = q.toLowerCase().trim();
                const container = document.querySelector(containerId);
                if (!container) return;
                
                // Filtramos las filas del active panel en base a la búsqueda
                container.querySelectorAll('.plan-block').forEach(block => {
                    const rows = block.querySelectorAll('.acc-row');
                    let anyMatch = false;
                    rows.forEach(row => {
                        const name = row.dataset.nameEs ?? '';
                        const match = !q || name.includes(q);
                        row.style.display = match ? '' : 'none';
                        if (match) anyMatch = true;
                    });
                    
                    // Si hay término de búsqueda, forzamos mostrar el bloque que contiene la coincidencia,
                    // ignorando temporalmente la selección en el sidebar para no ocultarle la coincidencia.
                    if (q) {
                        if (anyMatch) {
                            block.style.setProperty('display', '', 'important');
                        } else {
                            block.style.setProperty('display', 'none', 'important');
                        }
                    } else {
                        // Si no hay búsqueda, vuelve a regirse por Alpine (selectedPlan / selectedGroup)
                        block.style.display = '';
                    }
                });
            }
        }
    }
    </script>

</x-app-layout>
