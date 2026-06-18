<x-app-layout>

    {{-- ═══════════════════════════════════════════════════
    HEADER — Título limpio y acciones principales
    ═══════════════════════════════════════════════════ --}}
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-4">
            <div class="flex items-center gap-2.5 min-w-0">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-none">DTE / Facturas</h2>
                    <p class="text-xs text-gray-400 mt-0.5">PDFs importados</p>
                </div>
            </div>

            {{-- Acciones principales --}}
            <div class="flex items-center gap-2 shrink-0">
                {{-- Botón Importar PDFs --}}
                <a href="{{ route('pdf.import.form') }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl
                      bg-indigo-600 hover:bg-indigo-700 active:scale-95
                      text-white transition shadow-sm shadow-indigo-200 dark:shadow-indigo-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <span>Importar PDFs</span>
                </a>

                {{-- Botón Exportar Excel --}}
                <a href="{{ route('pdf.export.xlsx', request()->query()) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl
                      bg-emerald-600 hover:bg-emerald-700 active:scale-95
                      text-white transition shadow-sm shadow-emerald-200 dark:shadow-emerald-900">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span class="hidden sm:inline">Exportar Excel</span>
                </a>
            </div>
        </div>
    </x-slot>

    @php
        /* ── Datos ── */
        $rowsJson = $imports->getCollection()->map(fn($i) => [
            'id' => $i->id,
            'guia' => $i->guia_no,
            'name' => $i->original_name,
            'template' => $i->template ?? '—',
            'productor' => $i->productor ?? '—',
            'doc_fecha' => $i->doc_fecha
                ? \Carbon\Carbon::parse($i->doc_fecha)->format('d-m-Y')
                : null,
            'created_at' => $i->created_at->format('d-m-Y H:i'),
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

        $isPdfDate = ($orderBy ?? 'doc_fecha') === 'doc_fecha';
        $nextDir = ($dir ?? 'desc') === 'desc' ? 'asc' : 'desc';

        /* ── 8 templates del controller ── */
        $allTemplates = [
            'QC' => 'QC',
            'MP' => 'MP',
            'SANCO' => 'SANCO',
            'RFP' => 'RFP',
            'VT' => 'VT',
            'LIQ_COMPUAGRO' => 'Liq.',
            'GUIA_RECEPCION_RESUMEN' => 'G. Res.',
            'XML_SII_46' => 'XML',
        ];
    @endphp

    <style>
        [x-cloak] { display: none !important; }
        .tpl { display:inline-flex; align-items:center; padding:3px 10px; border-radius:999px; font-size:10px; font-weight:700; letter-spacing:.04em; white-space:nowrap; border: 1px solid transparent; }
        .tpl-QC { background:#dcfce7; color:#15803d; border-color:#bbf7d0 } .dark .tpl-QC { background:rgba(16,185,129,.15); color:#34d399; border-color:rgba(16,185,129,.2) }
        .tpl-MP { background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe } .dark .tpl-MP { background:rgba(59,130,246,.15); color:#93c5fd; border-color:rgba(59,130,246,.2) }
        .tpl-SANCO { background:#f3e8ff; color:#7c3aed; border-color:#e9d5ff } .dark .tpl-SANCO { background:rgba(139,92,246,.15); color:#c4b5fd; border-color:rgba(139,92,246,.2) }
        .tpl-RFP { background:#e0e7ff; color:#4338ca; border-color:#c7d2fe } .dark .tpl-RFP { background:rgba(99,102,241,.15); color:#a5b4fc; border-color:rgba(99,102,241,.2) }
        .tpl-VT { background:#fef3c7; color:#b45309; border-color:#fde68a } .dark .tpl-VT { background:rgba(245,158,11,.15); color:#fcd34d; border-color:rgba(245,158,11,.2) }
        .tpl-LIQ_COMPUAGRO { background:#ffe4e6; color:#be123c; border-color:#fecdd3 } .dark .tpl-LIQ_COMPUAGRO { background:rgba(244,63,94,.15); color:#fda4af; border-color:rgba(244,63,94,.2) }
        .tpl-GUIA_RECEPCION_RESUMEN { background:#e0f2fe; color:#0369a1; border-color:#bae6fd } .dark .tpl-GUIA_RECEPCION_RESUMEN { background:rgba(14,165,233,.15); color:#7dd3fc; border-color:rgba(14,165,233,.2) }
        .tpl-XML_SII_46 { background:#f1f5f9; color:#475569; border-color:#e2e8f0 } .dark .tpl-XML_SII_46 { background:rgba(255,255,255,.06); color:#94a3b8; border-color:rgba(255,255,255,.05) }
        .tpl-unknown { background:#f8fafc; color:#94a3b8; border-color:#e2e8f0 }
    </style>

    <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6" x-data="pdfIndex({{ $rowsJson }})">

        {{-- Stats Grid (KPI Cards) --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 au d1">
            <x-kpi-card 
                label="Total Documentos" 
                value="{{ number_format($totalImports, 0, ',', '.') }}"
                iconBg="bg-indigo-50 dark:bg-indigo-900/20"
                :trend="0"
            >
                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </x-kpi-card>

            <x-kpi-card 
                label="XML SII (Tipo 46)" 
                value="{{ number_format($xmlImports, 0, ',', '.') }}"
                iconBg="bg-sky-50 dark:bg-sky-900/20"
                :pct="$totalImports > 0 ? ($xmlImports / $totalImports * 100) : 0"
            >
                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </x-kpi-card>

            <x-kpi-card 
                label="SANCO" 
                value="{{ number_format($sancoImports, 0, ',', '.') }}"
                iconBg="bg-violet-50 dark:bg-violet-900/20"
                :pct="$totalImports > 0 ? ($sancoImports / $totalImports * 100) : 0"
            >
                <svg class="w-4 h-4 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </x-kpi-card>

            <x-kpi-card 
                label="Control Calidad (QC)" 
                value="{{ number_format($qcImports, 0, ',', '.') }}"
                iconBg="bg-emerald-50 dark:bg-emerald-900/20"
                :pct="$totalImports > 0 ? ($qcImports / $totalImports * 100) : 0"
            >
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </x-kpi-card>
        </div>

        {{-- Panel de Control / Filtros (Buscador + Cosecha + Modelos) --}}
        <div class="t-card p-4 sm:p-5 space-y-4 au d1">
            <form method="GET" action="{{ route('pdf.index') }}" class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                @if($model)
                    <input type="hidden" name="model" value="{{ $model }}">
                @endif
                
                {{-- Buscador + Cosecha --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-1 max-w-3xl">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                        </svg>
                        <input name="q" x-model="q" type="text" autocomplete="off" placeholder="Buscar por guía, ID, productor, archivo…" 
                            class="w-full pl-9 pr-8 py-2.5 text-sm rounded-xl
                                  border border-gray-200 dark:border-gray-700
                                  bg-white dark:bg-gray-900
                                  text-gray-900 dark:text-gray-100
                                  focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                                  outline-none transition placeholder-gray-400 shadow-sm">
                        <button type="button" x-show="q" @click="q = ''; $el.form.submit()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex gap-2">
                        <select name="season" class="flt-select flex-1 sm:flex-initial" onchange="this.form.submit()">
                            <option value="" {{ ($season ?? '') === '' ? 'selected' : '' }}>Todas las cosechas</option>
                            @foreach($availableSeasons as $s)
                                <option value="{{ $s }}" {{ ($season ?? '') == $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>

                        <button type="submit" class="inline-flex items-center justify-center px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition active:scale-95 shadow-sm">
                            Buscar
                        </button>

                        @if($q || ($season ?? '') !== '' || ($model ?? '') !== '')
                            <a href="{{ route('pdf.index') }}" class="flt-btn flt-clear flex items-center justify-center whitespace-nowrap">
                                Limpiar
                            </a>
                        @endif
                    </div>
                </div>
            </form>

            <div class="h-px bg-gray-100 dark:bg-gray-800/80 my-1"></div>

            {{-- Filtros de template (Chips segmentados con diseño premium) --}}
            <div class="space-y-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 block">
                    Filtrar por tipo de documento:
                </span>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <a href="{{ route('pdf.index', array_merge(request()->except('model', 'page'))) }}"
                        class="text-xs font-bold px-3.5 py-1.5 rounded-full transition border
                      {{ ($model ?? '') === ''
        ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm shadow-indigo-100 dark:shadow-none'
        : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100 dark:bg-gray-800/40 dark:text-gray-300 dark:border-gray-700 hover:dark:bg-gray-800' }}">
                        Todos
                    </a>
                    @foreach($allTemplates as $tplKey => $tplLabel)
                        @php
                            $active = ($model ?? '') === $tplKey;
                            $activeClass = match($tplKey) {
                                'QC' => 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-100 dark:shadow-none',
                                'MP' => 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-100 dark:shadow-none',
                                'SANCO' => 'bg-violet-600 text-white border-violet-600 shadow-sm shadow-violet-100 dark:shadow-none',
                                'RFP' => 'bg-indigo-600 text-white border-indigo-600 shadow-sm shadow-indigo-100 dark:shadow-none',
                                'VT' => 'bg-amber-600 text-white border-amber-600 shadow-sm shadow-amber-100 dark:shadow-none',
                                'LIQ_COMPUAGRO' => 'bg-rose-600 text-white border-rose-600 shadow-sm shadow-rose-100 dark:shadow-none',
                                'GUIA_RECEPCION_RESUMEN' => 'bg-sky-600 text-white border-sky-600 shadow-sm shadow-sky-100 dark:shadow-none',
                                'XML_SII_46' => 'bg-slate-700 text-white border-slate-700 shadow-sm dark:shadow-none',
                                default => 'bg-indigo-600 text-white border-indigo-600 shadow-sm dark:shadow-none',
                            };
                            $inactiveClass = 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100 dark:bg-gray-800/40 dark:text-gray-300 dark:border-gray-700 hover:dark:bg-gray-800';
                        @endphp
                        <a href="{{ route('pdf.index', array_merge(request()->except('model', 'page'), ['model' => $tplKey])) }}"
                            class="text-xs font-bold px-3.5 py-1.5 rounded-full border transition
                                          {{ $active ? $activeClass : $inactiveClass }}">
                            {{ $tplLabel }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Flash ok --}}
        @if(session('ok'))
            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800
                        px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300 au d1">
                {{ session('ok') }}
            </div>
        @endif

        {{-- Contador + limpiar --}}
        <div class="flex items-center justify-between au d1">
            <p class="text-xs text-gray-400">
                <span>Mostrando</span>
                <span x-text="filtered.length" class="font-bold text-gray-600 dark:text-gray-300"></span>
                <span x-text="filtered.length === 1 ? 'documento' : 'documentos'"></span>
                <template x-if="q">
                    <span> · "<strong x-text="q" class="text-gray-600 dark:text-gray-300"></strong>"</span>
                </template>
            </p>
            <template x-if="q">
                <button @click="q = ''" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                    Limpiar ×
                </button>
            </template>
        </div>

        {{-- ── TABLA DESKTOP ──────────────────────────── --}}
        <div class="hidden lg:block t-card au d2">
            <div class="overflow-x-auto">
                <table class="dt">
                    <thead>
                        <tr>
                            <th class="w-14">ID</th>
                            <th class="w-24">Guía</th>
                            <th class="w-1/4">Productor / Emisor</th>
                            <th>Archivo</th>
                            <th class="w-24">Modelo</th>
                            <th class="w-32">
                                <a href="{{ request()->fullUrlWithQuery(['order_by' => 'doc_fecha', 'dir' => $isPdfDate ? $nextDir : 'desc']) }}"
                                    class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 transition cursor-pointer">
                                    Fecha PDF
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        @if($isPdfDate && ($dir ?? 'desc') === 'asc')
                                            <path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" />
                                        @else
                                            <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                        @endif
                                    </svg>
                                </a>
                            </th>
                            <th class="w-36">Importado</th>
                            <th class="w-40 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="r in filtered" :key="r.id">
                            <tr>
                                <td class="text-gray-400 text-xs font-mono" x-text="r.id"></td>
                                <td>
                                    <span class="font-bold font-mono text-indigo-600 dark:text-indigo-400"
                                        x-text="r.guia ?? '—'"></span>
                                </td>
                                <td>
                                    <span class="font-semibold text-gray-800 dark:text-gray-200 text-xs"
                                        x-text="r.productor ?? '—'"></span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        {{-- Icono de tipo de archivo dinámico --}}
                                        <template x-if="r.template === 'XML_SII_46'">
                                            {{-- XML Icon (purple) --}}
                                            <svg class="w-5 h-5 text-violet-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                            </svg>
                                        </template>
                                        <template x-if="['RFP', 'VT'].includes(r.template)">
                                            {{-- Excel Icon (green) --}}
                                            <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H3a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </template>
                                        <template x-if="!['XML_SII_46', 'RFP', 'VT'].includes(r.template)">
                                            {{-- PDF Icon (red) --}}
                                            <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                        </template>
                                        <span class="block max-w-[200px] xl:max-w-xs truncate font-medium text-gray-800 dark:text-gray-200"
                                            :title="r.name" x-text="r.name"></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="tpl" :class="tplClass(r.template)" x-text="r.template"></span>
                                </td>
                                <td class="text-gray-500 dark:text-gray-400 text-xs">
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                                        </svg>
                                        <span x-text="r.doc_fecha ?? '—'"></span>
                                    </div>
                                </td>
                                <td class="text-gray-400 text-xs">
                                    <div class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-gray-400/80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span x-text="r.created_at"></span>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="inline-flex items-center gap-1">
                                        {{-- Detalle --}}
                                        <a :href="`{{ route('pdf.import.ver', '__ID__') }}`.replace('__ID__', r.id)"
                                            class="btn-sm btn-indigo transition-all duration-150 py-1.5 px-2.5 rounded-lg flex items-center gap-1 shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <span>Ver</span>
                                        </a>

                                        {{-- PDF --}}
                                        <a :href="`{{ route('pdf.import.archivo', '__ID__') }}`.replace('__ID__', r.id)"
                                            target="_blank" class="btn-sm btn-gray transition-all duration-150 py-1.5 px-2 rounded-lg flex items-center gap-1 hover:text-red-600 dark:hover:text-red-400">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                            <span>PDF</span>
                                        </a>

                                        {{-- JSON --}}
                                        <a :href="`{{ route('pdf.import.json', '__ID__') }}`.replace('__ID__', r.id)"
                                            target="_blank" class="btn-sm btn-gray transition-all duration-150 py-1.5 px-2 rounded-lg flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                                            </svg>
                                            <span>JSON</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filtered.length === 0">
                            <td colspan="8" class="px-4 py-14 text-center text-sm text-gray-400">
                                No hay documentos que coincidan con la búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Paginación desktop --}}
        <div class="hidden lg:block au d3" data-turbo="false">{{ $imports->links() }}</div>

        {{-- ── CARDS MÓVIL ─────────────────────────────── --}}
        <div class="lg:hidden space-y-3.5 au d2">
            <template x-for="r in filtered" :key="r.id">
                <div class="m-card">
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold font-mono text-indigo-600 dark:text-indigo-400 text-sm"
                                    x-text="r.guia ?? '—'"></span>
                                <span class="text-[10px] text-gray-400 font-mono" x-text="`#${r.id}`"></span>
                            </div>
                            <p class="font-semibold text-gray-800 dark:text-gray-200 text-xs mt-1 truncate" x-text="r.productor ?? '—'"></p>
                        </div>
                        <span class="tpl shrink-0" :class="tplClass(r.template)" x-text="r.template"></span>
                    </div>

                    <div class="flex items-center gap-2 mb-3 bg-gray-50/50 dark:bg-gray-800/30 p-2 rounded-xl border border-gray-100 dark:border-gray-800/60">
                        {{-- Icono según tipo --}}
                        <template x-if="r.template === 'XML_SII_46'">
                            <svg class="w-4 h-4 text-violet-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                            </svg>
                        </template>
                        <template x-if="['RFP', 'VT'].includes(r.template)">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H3a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </template>
                        <template x-if="!['XML_SII_46', 'RFP', 'VT'].includes(r.template)">
                            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </template>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate" :title="r.name" x-text="r.name"></p>
                    </div>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs mb-3.5">
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 mb-0.5">Fecha PDF</p>
                            <p class="font-semibold text-gray-700 dark:text-gray-300" x-text="r.doc_fecha ?? '—'">
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 mb-0.5">Importado</p>
                            <p class="text-gray-600 dark:text-gray-400 font-medium" x-text="r.created_at"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 border-t border-gray-100 dark:border-gray-800 pt-3">
                        {{-- Detalle --}}
                        <a :href="`{{ route('pdf.import.ver', '__ID__') }}`.replace('__ID__', r.id)"
                            class="btn-sm btn-indigo flex-1 justify-center py-1.5 rounded-xl text-center">
                            Detalle
                        </a>

                        {{-- PDF --}}
                        <a :href="`{{ route('pdf.import.archivo', '__ID__') }}`.replace('__ID__', r.id)"
                            target="_blank" class="btn-sm btn-gray flex-1 justify-center py-1.5 rounded-xl text-center hover:text-red-500 dark:hover:text-red-400">
                            PDF
                        </a>

                        {{-- JSON --}}
                        <a :href="`{{ route('pdf.import.json', '__ID__') }}`.replace('__ID__', r.id)"
                            target="_blank" class="btn-sm btn-gray flex-1 justify-center py-1.5 rounded-xl text-center hover:text-indigo-500 dark:hover:text-indigo-400">
                            JSON
                        </a>
                    </div>
                </div>
            </template>
            <div x-show="filtered.length === 0" class="m-card text-center text-sm text-gray-400 py-12">
                No hay documentos que coincidan.
            </div>
        </div>

        {{-- Paginación móvil --}}
        <div class="lg:hidden au d3" data-turbo="false">{{ $imports->links() }}</div>

        {{-- ── REPORTE IMPORTACIÓN ─────────────────────── --}}
        @if(session('import_report'))
            <div class="t-card au d4">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <p class="text-sm font-bold text-gray-800 dark:text-gray-100">Detalle de importación reciente</p>
                    <span class="text-xs text-gray-400">{{ count(session('import_report')) }} registros</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="dt">
                        <thead>
                            <tr>
                                <th>Archivo</th>
                                <th>Estado</th>
                                <th>Modelo</th>
                                <th>Guía</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(session('import_report') as $rep)
                                @php
                                    $st = $rep['status'] ?? '';
                                    $stCls = match ($st) {
                                        'imported' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                                        'duplicate' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                        default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                                    };
                                    $repTpl = $rep['template'] ?? '';
                                    $tplCss = match ($repTpl) {
                                        'QC' => 'tpl tpl-QC',
                                        'MP' => 'tpl tpl-MP',
                                        'SANCO' => 'tpl tpl-SANCO',
                                        'RFP' => 'tpl tpl-RFP',
                                        'VT' => 'tpl tpl-VT',
                                        'LIQ_COMPUAGRO' => 'tpl tpl-LIQ_COMPUAGRO',
                                        'GUIA_RECEPCION_RESUMEN' => 'tpl tpl-GUIA_RECEPCION_RESUMEN',
                                        'XML_SII_46' => 'tpl tpl-XML_SII_46',
                                        default => 'tpl tpl-unknown',
                                    };
                                @endphp
                                <tr>
                                    <td class="text-xs text-gray-500 max-w-xs truncate">{{ $rep['file'] ?? '—' }}</td>
                                    <td>
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold {{ $stCls }}">
                                            {{ $st }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($repTpl)
                                            <span class="{{ $tplCss }}">{{ $repTpl }}</span>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">—</span>
                                        @endif
                                    </td>
                                    <td class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ $rep['guia'] ?? '—' }}
                                    </td>
                                    <td class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $rep['reason'] ?? '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('pdf', { q: '{{ request('q') }}' });
        });

        /* ── index body ── */
        function pdfIndex(rows) {
            return {
                rows,
                get q() { return Alpine.store('pdf').q; },
                set q(val) { Alpine.store('pdf').q = val; },

                get filtered() {
                    const q = (Alpine.store('pdf').q || '').trim().toLowerCase();
                    if (!q) return this.rows;
                    return this.rows.filter(r =>
                        String(r.id).includes(q) ||
                        String(r.guia ?? '').toLowerCase().includes(q) ||
                        (r.name || '').toLowerCase().includes(q) ||
                        (r.productor || '').toLowerCase().includes(q) ||
                        (r.doc_fecha || '').toLowerCase().includes(q) ||
                        (r.created_at || '').toLowerCase().includes(q) ||
                        (r.template || '').toLowerCase().includes(q)
                    );
                },

                /* Clase CSS por template — sincronizada con las 8 del controller */
                tplClass(tpl) {
                    return {
                        'tpl-QC': tpl === 'QC',
                        'tpl-MP': tpl === 'MP',
                        'tpl-SANCO': tpl === 'SANCO',
                        'tpl-RFP': tpl === 'RFP',
                        'tpl-VT': tpl === 'VT',
                        'tpl-LIQ_COMPUAGRO': tpl === 'LIQ_COMPUAGRO',
                        'tpl-GUIA_RECEPCION_RESUMEN': tpl === 'GUIA_RECEPCION_RESUMEN',
                        'tpl-XML_SII_46': tpl === 'XML_SII_46',
                        'tpl-unknown': !['QC', 'MP', 'SANCO', 'RFP', 'VT',
                            'LIQ_COMPUAGRO', 'GUIA_RECEPCION_RESUMEN',
                            'XML_SII_46'].includes(tpl),
                    };
                },
            };
        }
    </script>

</x-app-layout>